<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->timestamps();
        });

        // The app can't work without these, so they ship with the schema rather than a seeder.
        $roles = [
            ['superadmin', 'Super Admin', 'Unrestricted access, including roles and permissions', []],
            ['admin', 'Admin', 'Runs the store: staff accounts, settings, inventory and reports', [
                'pos.sell', 'sales.view_all', 'sales.void', 'medicines.manage', 'stock.manage',
                'suppliers.manage', 'reports.view', 'users.manage', 'settings.manage',
            ]],
            ['pharmacist', 'Pharmacist', 'Sells, manages medicines, stock and suppliers, voids sales', [
                'pos.sell', 'sales.view_all', 'sales.void', 'medicines.manage', 'stock.manage',
                'suppliers.manage', 'reports.view',
            ]],
            ['cashier', 'Cashier', 'Rings up sales and sees their own transactions', ['pos.sell']],
        ];

        DB::table('roles')->insert(array_map(fn ($r) => [
            'name' => $r[0],
            'label' => $r[1],
            'description' => $r[2],
            'permissions' => json_encode($r[3]),
            'created_at' => now(),
            'updated_at' => now(),
        ], $roles));
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
