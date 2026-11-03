<?php

namespace Tests\Feature;

use App\Http\Requests\LoginRequest;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function medicine(array $attributes = []): Medicine
    {
        return Medicine::create([
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Biogesic 500mg',
            'unit' => 'tablet',
            'price' => 112.00,
            'reorder_level' => 5,
            ...$attributes,
        ]);
    }

    private function batch(Medicine $medicine, int $quantity, string $expiry): Batch
    {
        return Batch::create([
            'medicine_id' => $medicine->id,
            'batch_no' => 'B'.fake()->unique()->numberBetween(100, 999),
            'quantity_received' => $quantity,
            'quantity' => $quantity,
            'expiry_date' => $expiry,
            'received_at' => today(),
        ]);
    }

    public function test_login_returns_a_token_and_rejects_inactive_users(): void
    {
        $user = User::factory()->create(['email' => 'a@b.test', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'a@b.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'role']]);

        $user->update(['is_active' => false]);

        $this->postJson('/api/login', ['email' => 'a@b.test', 'password' => 'secret123'])
            ->assertStatus(422);
    }

    public function test_login_validates_input_and_locks_out_repeated_failures(): void
    {
        User::factory()->create(['email' => 'a@b.test', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        // Five wrong passwords lock that email out, even for the right password.
        foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
            $this->postJson('/api/login', ['email' => 'a@b.test', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'a@b.test', 'password' => 'secret123'])
            ->assertStatus(429)
            ->assertJsonPath('errors.email.0', fn ($message) => str_starts_with($message, 'Too many login attempts'));
    }

    public function test_logging_out_ends_the_accounts_sessions_on_every_device(): void
    {
        $user = User::factory()->create(['email' => 'a@b.test', 'password' => 'secret123']);
        $other = User::factory()->create();
        $mine = $user->createToken('device-a')->plainTextToken;
        $theirs = $user->createToken('device-b')->plainTextToken;
        $unrelated = $other->createToken('device-c')->plainTextToken;
        $get = function (string $token) {
            $this->app['auth']->forgetGuards();

            return $this->withToken($token)->getJson('/api/user');
        };

        $get($theirs)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($mine)->postJson('/api/logout')->assertOk();

        $get($mine)->assertUnauthorized();
        $get($theirs)->assertUnauthorized();
        $get($unrelated)->assertOk();
    }

    public function test_sessions_expire_after_twelve_hours(): void
    {
        $token = User::factory()->create()->createToken('device')->plainTextToken;
        $get = function () use ($token) {
            $this->app['auth']->forgetGuards();

            return $this->withToken($token)->getJson('/api/user');
        };

        $this->travel(719)->minutes();
        $get()->assertOk();

        $this->travel(2)->minutes();
        $get()->assertUnauthorized();
    }

    public function test_pages_load_scripts_versioned_by_their_contents(): void
    {
        $version = substr(md5_file(public_path('js/app.js')), 0, 12);

        $this->get('/dashboard')->assertOk()->assertSee("/js/app.js?v={$version}", false);
    }

    public function test_checkout_deducts_from_the_earliest_expiring_batch_first(): void
    {
        Sanctum::actingAs($this->user('cashier'));
        $medicine = $this->medicine();
        $later = $this->batch($medicine, 10, today()->addYear()->toDateString());
        $sooner = $this->batch($medicine, 3, today()->addMonth()->toDateString());
        $expired = $this->batch($medicine, 50, today()->subDay()->toDateString());

        $this->postJson('/api/sales', [
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 5]],
            'payment_method' => 'cash',
            'amount_paid' => 1000,
        ])->assertCreated()->assertJsonPath('total', '560.00')->assertJsonPath('change', '440.00');

        $this->assertSame(0, $sooner->fresh()->quantity);
        $this->assertSame(8, $later->fresh()->quantity);
        $this->assertSame(50, $expired->fresh()->quantity, 'Expired stock must never be sold.');
    }

    public function test_checkout_rejects_more_than_the_sellable_stock(): void
    {
        Sanctum::actingAs($this->user('cashier'));
        $medicine = $this->medicine();
        $this->batch($medicine, 2, today()->addYear()->toDateString());

        $this->postJson('/api/sales', [
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 3]],
            'payment_method' => 'gcash',
            'reference_no' => 'ABC123',
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(0, Sale::count());
    }

    public function test_senior_discount_is_vat_exempt_and_twenty_percent_off(): void
    {
        Sanctum::actingAs($this->user('cashier'));
        $medicine = $this->medicine(['price' => 112.00]);
        $this->batch($medicine, 10, today()->addYear()->toDateString());

        // 112 VAT-inclusive -> 100 net of VAT -> less 20% = 80
        $this->postJson('/api/sales', [
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_paid' => 100,
            'discount_type' => 'senior',
            'discount_id_no' => 'SC-1',
            'customer_name' => 'Lola Nena',
        ])->assertCreated()
            ->assertJsonPath('subtotal', '112.00')
            ->assertJsonPath('discount', '20.00')
            ->assertJsonPath('vat', '0.00')
            ->assertJsonPath('total', '80.00');
    }

    public function test_prescription_medicines_need_a_reference(): void
    {
        Sanctum::actingAs($this->user('cashier'));
        $medicine = $this->medicine(['requires_prescription' => true]);
        $this->batch($medicine, 10, today()->addYear()->toDateString());

        $sale = fn (array $extra = []) => $this->postJson('/api/sales', [
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
            'payment_method' => 'card',
            'reference_no' => 'APPR-1',
            ...$extra,
        ]);

        $sale()->assertStatus(422)->assertJsonValidationErrors('prescription_ref');
        $sale(['prescription_ref' => 'RX-1'])->assertCreated();
    }

    public function test_voiding_a_sale_returns_stock_to_its_batches(): void
    {
        $pharmacist = $this->user('pharmacist');
        Sanctum::actingAs($pharmacist);
        $medicine = $this->medicine();
        $batch = $this->batch($medicine, 10, today()->addYear()->toDateString());

        $sale = $this->postJson('/api/sales', [
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 4]],
            'payment_method' => 'cash',
            'amount_paid' => 1000,
        ])->json();

        $this->assertSame(6, $batch->fresh()->quantity);

        $this->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'Wrong item'])
            ->assertOk()->assertJsonPath('status', 'voided');
        $this->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'Again'])->assertStatus(422);

        $this->assertSame(10, $batch->fresh()->quantity);
    }

    public function test_roles_limit_what_each_user_can_do(): void
    {
        Sanctum::actingAs($this->user('cashier'));
        $this->getJson('/api/dashboard')->assertOk();
        $this->getJson('/api/medicines')->assertOk();
        $this->postJson('/api/medicines', [])->assertForbidden();
        $this->getJson('/api/reports')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();

        Sanctum::actingAs($this->user('pharmacist'));
        $this->getJson('/api/reports')->assertOk();
        $this->getJson('/api/batches')->assertOk();
        $this->getJson('/api/users')->assertForbidden();
        $this->putJson('/api/settings', [])->assertForbidden();

        Sanctum::actingAs($this->user('admin'));
        $this->getJson('/api/users')->assertOk();
        $this->postJson('/api/roles', ['label' => 'Clerk', 'permissions' => []])->assertForbidden();
    }

    public function test_super_admin_can_do_everything_including_managing_roles(): void
    {
        Sanctum::actingAs($this->user('superadmin'));

        $this->getJson('/api/user')->assertOk()->assertJsonPath('user.role_label', 'Super Admin')
            ->assertJsonFragment(['roles.manage'])
            ->assertJsonCount(count(Role::MENUS), 'user.menus');
        foreach (['/api/reports', '/api/batches', '/api/users', '/api/suppliers/compare', '/api/roles'] as $url) {
            $this->getJson($url)->assertOk();
        }

        // The super admin role itself can't be edited or deleted, and reserved permissions can't be granted.
        $super = Role::where('name', 'superadmin')->first();
        $this->putJson("/api/roles/{$super->id}", ['label' => 'Boss', 'permissions' => []])->assertStatus(422);
        $this->deleteJson("/api/roles/{$super->id}")->assertStatus(422);
        $this->postJson('/api/roles', ['label' => 'Sneaky', 'permissions' => ['roles.manage']])
            ->assertStatus(422)->assertJsonValidationErrors('permissions.0');
    }

    public function test_custom_roles_grant_exactly_their_permissions(): void
    {
        Sanctum::actingAs($this->user('superadmin'));
        $this->postJson('/api/roles', ['label' => 'Inventory Clerk', 'permissions' => ['stock.manage'], 'menus' => []])
            ->assertStatus(422)->assertJsonValidationErrors('menus');
        $this->postJson('/api/roles', ['label' => 'Inventory Clerk', 'permissions' => ['stock.manage'], 'menus' => ['stock', 'nope']])
            ->assertStatus(422)->assertJsonValidationErrors('menus.1');
        $this->postJson('/api/roles', ['label' => 'Inventory Clerk', 'permissions' => ['stock.manage'], 'menus' => ['stock', 'settings']])
            ->assertCreated()->assertJsonPath('name', 'inventory_clerk');

        $clerk = $this->user('inventory_clerk');
        Sanctum::actingAs($clerk);
        $this->getJson('/api/user')->assertJsonPath('user.menus', ['stock', 'settings']);
        $this->getJson('/api/batches')->assertOk();
        $this->postJson('/api/medicines', [])->assertForbidden();
        $this->postJson('/api/sales', [])->assertForbidden();

        // Permission changes take effect immediately, without signing in again.
        Sanctum::actingAs($this->user('superadmin'));
        $role = Role::where('name', 'inventory_clerk')->first();
        $this->putJson("/api/roles/{$role->id}", ['label' => 'Inventory Clerk', 'permissions' => ['reports.view'], 'menus' => ['reports']])->assertOk();
        $this->deleteJson("/api/roles/{$role->id}")->assertStatus(422); // still has a user

        Sanctum::actingAs($clerk->fresh());
        $this->getJson('/api/batches')->assertForbidden();
        $this->getJson('/api/reports')->assertOk();
    }

    public function test_only_a_super_admin_can_create_or_change_super_admins(): void
    {
        $owner = $this->user('superadmin');
        $admin = $this->user('admin');
        $payload = ['name' => 'New', 'email' => 'new@x.test', 'password' => 'password1', 'is_active' => true];

        Sanctum::actingAs($admin);
        $this->postJson('/api/users', [...$payload, 'role' => 'superadmin'])->assertStatus(422)->assertJsonValidationErrors('role');
        $this->putJson("/api/users/{$owner->id}", ['name' => 'X', 'email' => $owner->email, 'role' => 'cashier'])->assertForbidden();
        $this->deleteJson("/api/users/{$owner->id}")->assertForbidden();
        $this->putJson("/api/users/{$admin->id}", ['name' => 'Me', 'email' => $admin->email, 'role' => 'cashier'])->assertStatus(422);

        Sanctum::actingAs($owner);
        $this->postJson('/api/users', [...$payload, 'role' => 'superadmin'])->assertCreated();
        $this->putJson("/api/users/{$owner->id}", ['name' => 'Me', 'email' => $owner->email, 'role' => 'admin'])->assertStatus(422);
    }

    public function test_cashiers_only_see_their_own_sales(): void
    {
        $cashier = $this->user('cashier');
        $other = $this->user('cashier');
        $medicine = $this->medicine();
        $this->batch($medicine, 10, today()->addYear()->toDateString());
        $payload = ['items' => [['medicine_id' => $medicine->id, 'quantity' => 1]], 'payment_method' => 'cash', 'amount_paid' => 500];

        Sanctum::actingAs($other);
        $otherSale = $this->postJson('/api/sales', $payload)->json('id');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/sales', $payload);

        $this->getJson('/api/sales')->assertOk()->assertJsonPath('total', 1);
        $this->getJson("/api/sales/{$otherSale}")->assertForbidden();
    }

    public function test_stock_adjustments_are_logged(): void
    {
        $pharmacist = $this->user('pharmacist');
        Sanctum::actingAs($pharmacist);
        $batch = $this->batch($this->medicine(), 10, today()->addYear()->toDateString());

        $this->putJson("/api/batches/{$batch->id}", ['quantity' => 7, 'reason' => 'Damaged'])->assertOk();

        $this->getJson("/api/batches/{$batch->id}/adjustments")
            ->assertOk()
            ->assertJsonPath('0.quantity_before', 10)
            ->assertJsonPath('0.quantity_after', 7)
            ->assertJsonPath('0.user.name', $pharmacist->name);
    }

    public function test_receiving_stock_computes_the_actual_cost_per_piece(): void
    {
        Sanctum::actingAs($this->user('pharmacist'));
        $medicine = $this->medicine(['price' => 8, 'cost' => 1]);
        $receive = fn (array $purchase) => $this->postJson('/api/batches', [
            'medicine_id' => $medicine->id,
            'batch_no' => 'B1',
            'expiry_date' => today()->addYear()->toDateString(),
            ...$purchase,
        ]);

        // 10 + 2 on a box of 100: pays for 10 boxes, receives 12.
        $receive(['packs_bought' => 10, 'pack_size' => 100, 'list_price' => 500, 'deal_buy' => 10, 'deal_free' => 2])
            ->assertCreated()
            ->assertJsonPath('quantity', 1200)
            ->assertJsonPath('quantity_received', 1200)
            ->assertJsonPath('free_packs', 2)
            ->assertJsonPath('total_cost', '5000.00')
            ->assertJsonPath('cost', '4.1667')
            ->assertJsonPath('effective_discount', '16.67');

        // The cost follows the delivery; the selling price stays as set.
        $this->assertSame('4.17', $medicine->fresh()->cost);
        $this->assertSame('8.00', $medicine->fresh()->price);

        // 5% off a box of 24.
        $receive(['packs_bought' => 5, 'pack_size' => 24, 'list_price' => 240, 'discount_percent' => 5])
            ->assertCreated()->assertJsonPath('quantity', 120)->assertJsonPath('total_cost', '1140.00')->assertJsonPath('cost', '9.5000');

        // Only whole deals earn free packs.
        $receive(['packs_bought' => 15, 'pack_size' => 1, 'list_price' => 10, 'deal_buy' => 10, 'deal_free' => 5])
            ->assertCreated()->assertJsonPath('free_packs', 5)->assertJsonPath('quantity', 20);

        // The amount actually paid overrides the computed total.
        $receive(['packs_bought' => 10, 'pack_size' => 100, 'list_price' => 500, 'discount_percent' => 50, 'total_cost' => 4500])
            ->assertCreated()->assertJsonPath('cost', '4.5000')->assertJsonPath('effective_discount', '10.00');

        $receive(['pack_size' => 1])->assertStatus(422)->assertJsonValidationErrors('packs_bought');
        $receive(['packs_bought' => 1, 'pack_size' => 1, 'deal_buy' => 10])->assertStatus(422)->assertJsonValidationErrors('deal_free');
    }

    public function test_supplier_comparison_ranks_suppliers_by_latest_cost(): void
    {
        Sanctum::actingAs($this->user('pharmacist'));
        $medicine = $this->medicine(['price' => 10]);
        $cheap = Supplier::create(['name' => 'Cheap Co.']);
        $pricey = Supplier::create(['name' => 'Pricey Inc.']);
        $deliver = fn (Supplier $s, float $cost, string $received) => Batch::create([
            'medicine_id' => $medicine->id, 'supplier_id' => $s->id, 'batch_no' => 'B'.fake()->unique()->numberBetween(100, 999),
            'quantity_received' => 100, 'quantity' => 0, 'cost' => $cost,
            'expiry_date' => today()->addYear(), 'received_at' => $received,
        ]);

        $deliver($cheap, 8.00, today()->subMonths(3)->toDateString());
        $deliver($cheap, 6.00, today()->subMonth()->toDateString()); // latest price counts
        $deliver($pricey, 7.00, today()->subMonth()->toDateString());

        $this->getJson("/api/suppliers/compare?medicine_id={$medicine->id}")
            ->assertOk()
            ->assertJsonPath('best_supplier_id', $cheap->id)
            ->assertJsonPath('suppliers.0.last_cost', 6)
            ->assertJsonPath('suppliers.0.avg_cost', 7)
            ->assertJsonPath('suppliers.1.supplier', 'Pricey Inc.');

        $this->getJson('/api/suppliers/compare')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.saving_per_unit', 1);

        Sanctum::actingAs($this->user('cashier'));
        $this->getJson('/api/suppliers/compare')->assertForbidden();
    }

    public function test_medicine_images_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->user('pharmacist'));
        $medicine = $this->medicine();

        $first = $this->post("/api/medicines/{$medicine->id}/image", ['image' => UploadedFile::fake()->image('a.png', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()->json('image_url');
        $firstPath = $medicine->fresh()->image_path;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertSame('/storage/'.$firstPath, $first);

        // Replacing cleans up the old file.
        $this->post("/api/medicines/{$medicine->id}/image", ['image' => UploadedFile::fake()->image('b.jpg')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($firstPath);

        $this->post("/api/medicines/{$medicine->id}/image", ['image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('image');
        $this->post("/api/medicines/{$medicine->id}/image", ['image' => UploadedFile::fake()->image('huge.png')->size(11000)], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->deleteJson("/api/medicines/{$medicine->id}/image")->assertOk()->assertJsonPath('image_url', null);
        $this->assertSame([], Storage::disk('public')->allFiles('medicines'));

        Sanctum::actingAs($this->user('cashier'));
        $this->post("/api/medicines/{$medicine->id}/image", ['image' => UploadedFile::fake()->image('c.png')], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_images_go_to_vercel_blob_when_a_token_is_configured(): void
    {
        $this->app->instance(ImageStore::class, new ImageStore('blob-token'));
        $blobUrl = 'https://store.public.blob.vercel-storage.com/medicines/a.webp';
        Http::fake([
            'vercel.com/api/blob/delete' => Http::response(null),
            'vercel.com/api/blob/*' => Http::response(['url' => $blobUrl]),
        ]);
        Sanctum::actingAs($this->user('pharmacist'));
        $medicine = $this->medicine();

        $this->post("/api/medicines/{$medicine->id}/image", ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('image_url', $blobUrl);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->hasHeader('Authorization', 'Bearer blob-token')
            && $request->hasHeader('x-content-type', 'image/webp'));

        $this->deleteJson("/api/medicines/{$medicine->id}/image")->assertOk()->assertJsonPath('image_url', null);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/delete') && $request['urls'] === [$blobUrl]);
    }

    public function test_medicines_can_be_filtered_by_type_and_sorted(): void
    {
        Sanctum::actingAs($this->user('pharmacist'));
        $beta = Category::create(['name' => 'Beta']);
        $alpha = Category::create(['name' => 'Alpha']);
        $cheap = $this->medicine(['name' => 'Cheap', 'price' => 5, 'product_type' => 'oral', 'category_id' => $beta->id]);
        $dear = $this->medicine(['name' => 'Dear', 'price' => 50, 'product_type' => 'injectable', 'category_id' => $alpha->id]);
        $untyped = $this->medicine(['name' => 'Untyped', 'price' => 20]);
        $this->batch($cheap, 3, today()->addYear()->toDateString());
        $this->batch($dear, 9, today()->addYear()->toDateString());

        $names = fn (string $query) => $this->getJson("/api/medicines?{$query}")->assertOk()->json('data.*.name');

        $this->assertSame(['Dear'], $names('product_type=injectable'));
        $this->assertSame(['Cheap', 'Untyped', 'Dear'], $names('sort=price'));
        $this->assertSame(['Dear', 'Untyped', 'Cheap'], $names('sort=price&dir=desc'));
        $this->assertSame(['Dear', 'Cheap', 'Untyped'], $names('sort=stock&dir=desc'));
        $this->assertSame(['Dear', 'Cheap', 'Untyped'], $names('sort=category'));
        $this->assertSame(['Dear', 'Cheap', 'Untyped'], $names('sort=product_type&dir=asc'), 'Blank types go last.');
        $this->assertSame(['Cheap', 'Dear', 'Untyped'], $names('sort=product_type&dir=desc'), 'Blank types go last.');
        $this->assertSame(['Cheap', 'Dear', 'Untyped'], $names('sort=password'), 'Unknown columns fall back to name.');

        $this->putJson("/api/medicines/{$untyped->id}", ['sku' => $untyped->sku, 'name' => 'Untyped', 'unit' => 'tablet', 'price' => 20, 'reorder_level' => 1, 'product_type' => 'magic'])
            ->assertStatus(422)->assertJsonValidationErrors('product_type');
        $this->putJson("/api/medicines/{$untyped->id}", ['sku' => $untyped->sku, 'name' => 'Untyped', 'unit' => 'tablet', 'price' => 20, 'reorder_level' => 1, 'product_type' => 'topical'])
            ->assertOk()->assertJsonPath('product_type', 'topical');
    }

    public function test_uploaded_images_are_resized_and_converted_to_webp(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->user('admin'));
        $medicine = $this->medicine();

        $this->post("/api/medicines/{$medicine->id}/image", ['image' => UploadedFile::fake()->image('photo.jpg', 2400, 1600)], ['Accept' => 'application/json'])
            ->assertOk();

        $path = $medicine->fresh()->image_path;
        $this->assertStringEndsWith('.webp', $path);
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([800, 533, IMAGETYPE_WEBP], [$width, $height, $type]);

        // Small images are not enlarged.
        $this->post("/api/medicines/{$medicine->id}/image", ['image' => UploadedFile::fake()->image('small.png', 200, 150)], ['Accept' => 'application/json']);
        $this->assertSame([200, 150], array_slice(getimagesizefromstring(Storage::disk('public')->get($medicine->fresh()->image_path)), 0, 2));
    }

    public function test_dashboard_and_reports_render_with_data(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $medicine = $this->medicine();
        $this->batch($medicine, 10, today()->addYear()->toDateString());
        $this->postJson('/api/sales', ['items' => [['medicine_id' => $medicine->id, 'quantity' => 2]], 'payment_method' => 'cash', 'amount_paid' => 500]);

        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('kpis.txns_today', 1);
        $this->getJson('/api/reports')->assertOk()->assertJsonPath('summary.transactions', 1)->assertJsonPath('summary.items_sold', 2);
    }

    public function test_reports_show_monthly_sales_and_inventory_value(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $medicine = $this->medicine(['price' => 10]);
        $make = fn (int $quantity, string $expiry) => Batch::create([
            'medicine_id' => $medicine->id, 'batch_no' => 'B'.fake()->unique()->numberBetween(100, 999),
            'quantity_received' => $quantity, 'quantity' => $quantity, 'cost' => 4.5,
            'expiry_date' => $expiry, 'received_at' => today()->subMonth(),
        ]);
        $make(100, today()->addYear()->toDateString());
        $make(10, today()->subDay()->toDateString());
        $make(0, today()->addYear()->toDateString());

        $this->postJson('/api/sales', ['items' => [['medicine_id' => $medicine->id, 'quantity' => 20]], 'payment_method' => 'cash', 'amount_paid' => 200])->assertCreated();

        $this->getJson('/api/reports')->assertOk()
            ->assertJsonPath('monthly.0.month', today()->format('Y-m'))
            ->assertJsonPath('monthly.0.transactions', 1)
            ->assertJsonPath('monthly.0.total', 200)
            ->assertJsonPath('inventory.sellable.units', 80)
            ->assertJsonPath('inventory.sellable.at_cost', 360)
            ->assertJsonPath('inventory.sellable.at_srp', 800)
            ->assertJsonPath('inventory.expired.units', 10)
            ->assertJsonPath('inventory.expired.at_cost', 45);
    }

    public function test_pages_render(): void
    {
        foreach (['/', '/dashboard', '/pos', '/transactions', '/medicines', '/stock', '/suppliers', '/reports', '/users', '/roles', '/settings'] as $page) {
            $this->get($page)->assertOk();
        }
    }
}
