<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * Sample catalogue, stock and 30 days of sales so every screen has something to show.
 * Skips itself when medicines already exist, so it never duplicates data.
 */
class PharmacySeeder extends Seeder
{
    public function run(SaleService $sales): void
    {
        if (Medicine::exists()) {
            return;
        }

        mt_srand(2026);

        $categories = collect(['Analgesics', 'Antibiotics', 'Antihistamines', 'Cardiovascular', 'Diabetes Care', 'Gastrointestinal', 'Respiratory', 'Vitamins & Supplements', 'Personal Care', 'First Aid'])
            ->mapWithKeys(fn ($name) => [$name => Category::create(['name' => $name])->id]);

        $suppliers = collect([
            ['name' => 'Zuellig Pharma Corp.', 'contact_person' => 'Mark Tan', 'phone' => '(02) 8908 2222', 'email' => 'orders@zuellig.test', 'address' => 'Makati City'],
            ['name' => 'United Laboratories', 'contact_person' => 'Grace Lim', 'phone' => '(02) 8858 1000', 'email' => 'sales@unilab.test', 'address' => 'Mandaluyong City'],
            ['name' => 'Metro Drug Inc.', 'contact_person' => 'Joel Cruz', 'phone' => '(02) 8634 5000', 'email' => 'supply@metrodrug.test', 'address' => 'Pasig City'],
            ['name' => 'Pharmawealth Distribution', 'contact_person' => 'Ivy Santos', 'phone' => '(02) 8721 3344', 'email' => 'po@pharmawealth.test', 'address' => 'Quezon City'],
        ])->map(fn ($s) => Supplier::create($s)->id);

        // [sku, name, generic, category, unit, price, cost, reorder, rx, starting stock]
        $catalogue = [
            ['MED-0001', 'Biogesic 500mg', 'Paracetamol', 'Analgesics', 'tablet', 5.50, 3.20, 200, false, 1800],
            ['MED-0002', 'Medicol Advance 400mg', 'Ibuprofen', 'Analgesics', 'capsule', 8.75, 5.10, 120, false, 900],
            ['MED-0003', 'Dolfenal 500mg', 'Mefenamic Acid', 'Analgesics', 'tablet', 12.00, 7.40, 100, false, 60],
            ['MED-0004', 'Alaxan FR', 'Ibuprofen + Paracetamol', 'Analgesics', 'capsule', 9.50, 6.00, 120, false, 700],
            ['MED-0005', 'Amoxil 500mg', 'Amoxicillin', 'Antibiotics', 'capsule', 14.00, 8.50, 100, true, 650],
            ['MED-0006', 'Zithromax 500mg', 'Azithromycin', 'Antibiotics', 'tablet', 95.00, 62.00, 30, true, 140],
            ['MED-0007', 'Ciprobay 500mg', 'Ciprofloxacin', 'Antibiotics', 'tablet', 38.00, 24.00, 40, true, 25],
            ['MED-0008', 'Cefalin 500mg', 'Cefalexin', 'Antibiotics', 'capsule', 16.50, 10.20, 60, true, 300],
            ['MED-0009', 'Virlix 10mg', 'Cetirizine', 'Antihistamines', 'tablet', 22.00, 13.00, 80, false, 520],
            ['MED-0010', 'Claritin 10mg', 'Loratadine', 'Antihistamines', 'tablet', 28.00, 17.50, 60, false, 380],
            ['MED-0011', 'Allerkid Syrup 60ml', 'Cetirizine', 'Antihistamines', 'bottle', 165.00, 105.00, 20, false, 15],
            ['MED-0012', 'Cozaar 50mg', 'Losartan', 'Cardiovascular', 'tablet', 32.00, 20.00, 100, true, 780],
            ['MED-0013', 'Norvasc 5mg', 'Amlodipine', 'Cardiovascular', 'tablet', 26.00, 16.00, 100, true, 820],
            ['MED-0014', 'Lipitor 20mg', 'Atorvastatin', 'Cardiovascular', 'tablet', 48.00, 30.00, 60, true, 400],
            ['MED-0015', 'Glucophage 500mg', 'Metformin', 'Diabetes Care', 'tablet', 7.50, 4.20, 150, true, 110],
            ['MED-0016', 'Diamicron MR 60mg', 'Gliclazide', 'Diabetes Care', 'tablet', 24.00, 15.00, 60, true, 340],
            ['MED-0017', 'Omepron 20mg', 'Omeprazole', 'Gastrointestinal', 'capsule', 18.00, 10.50, 80, false, 70],
            ['MED-0018', 'Diatabs 2mg', 'Loperamide', 'Gastrointestinal', 'capsule', 7.00, 4.00, 80, false, 480],
            ['MED-0019', 'Kremil-S Advance', 'Aluminum Hydroxide + Simeticone', 'Gastrointestinal', 'tablet', 9.00, 5.50, 80, false, 560],
            ['MED-0020', 'Ventolin Nebules 2.5mg', 'Salbutamol', 'Respiratory', 'nebule', 26.00, 16.00, 50, true, 30],
            ['MED-0021', 'Solmux 500mg', 'Carbocisteine', 'Respiratory', 'capsule', 11.50, 7.00, 100, false, 640],
            ['MED-0022', 'Lagundi Syrup 120ml', 'Vitex negundo', 'Respiratory', 'bottle', 145.00, 90.00, 20, false, 90],
            ['MED-0023', 'Neozep Forte', 'Phenylephrine + Paracetamol', 'Respiratory', 'tablet', 7.25, 4.30, 120, false, 900],
            ['MED-0024', 'Ceelin 500mg', 'Ascorbic Acid', 'Vitamins & Supplements', 'tablet', 6.50, 3.80, 200, false, 1500],
            ['MED-0025', 'Enervon-C', 'Multivitamins + Vitamin C', 'Vitamins & Supplements', 'tablet', 8.00, 4.90, 150, false, 1100],
            ['MED-0026', 'Myra-E 400IU', 'Vitamin E', 'Vitamins & Supplements', 'capsule', 10.50, 6.40, 100, false, 700],
            ['MED-0027', 'Poten-Cee 500mg', 'Ascorbic Acid', 'Vitamins & Supplements', 'tablet', 7.50, 4.40, 120, false, 820],
            ['MED-0028', 'Betadine Solution 60ml', 'Povidone-Iodine', 'First Aid', 'bottle', 185.00, 120.00, 15, false, 60],
            ['MED-0029', 'Band-Aid Strips (10s)', 'Adhesive bandage', 'First Aid', 'pack', 45.00, 28.00, 30, false, 160],
            ['MED-0030', 'Green Cross Alcohol 500ml', 'Isopropyl Alcohol 70%', 'Personal Care', 'bottle', 95.00, 62.00, 30, false, 210],
            ['MED-0031', 'Safeguard Soap 135g', 'Antibacterial soap', 'Personal Care', 'bar', 52.00, 36.00, 30, false, 180],
            ['MED-0032', 'Face Mask 3-Ply (50s)', 'Surgical mask', 'Personal Care', 'box', 120.00, 75.00, 20, false, 0],
        ];

        $medicines = collect($catalogue)->map(function ($m) use ($categories, $suppliers) {
            [$sku, $name, $generic, $category, $unit, $price, $cost, $reorder, $rx, $stock] = $m;

            $medicine = Medicine::create([
                'sku' => $sku,
                'name' => $name,
                'generic_name' => $generic,
                'category_id' => $categories[$category],
                'supplier_id' => $suppliers->random(),
                'unit' => $unit,
                'price' => $price,
                'cost' => $cost,
                'reorder_level' => $reorder,
                'requires_prescription' => $rx,
            ]);

            // Fast movers get enough stock to survive a month of sample sales.
            if ($stock > $reorder * 2) {
                $stock *= 3;
            }

            if ($stock > 0) {
                // Older batch that expires soon, plus a fresh one.
                $older = (int) round($stock * 0.35);
                $this->batch($medicine, $older, today()->addDays(mt_rand(10, 75)), today()->subMonths(5));
                $this->batch($medicine, $stock - $older, today()->addMonths(mt_rand(10, 24)), today()->subDays(35));
            }

            return $medicine;
        });

        // A batch that already expired and still has quantity on hand, to show the warning state.
        $this->batch($medicines[22], 40, today()->subDays(4), today()->subYear());

        $this->seedSales($sales, $medicines);
        $this->call(SupplierPriceHistorySeeder::class);
    }

    private function batch(Medicine $medicine, int $quantity, $expiry, $received): void
    {
        Batch::create([
            'medicine_id' => $medicine->id,
            'supplier_id' => $medicine->supplier_id,
            'batch_no' => 'B'.mt_rand(10000, 99999),
            'quantity_received' => $quantity,
            'quantity' => $quantity,
            'cost' => $medicine->cost,
            'expiry_date' => $expiry,
            'received_at' => $received,
        ]);
    }

    private function seedSales(SaleService $sales, $medicines): void
    {
        $staff = User::whereIn('role', [User::ROLE_PHARMACIST, User::ROLE_CASHIER, User::ROLE_ADMIN])->get();
        // Keep the deliberately low-stock items out of the random sales.
        $sellable = $medicines->reject(fn ($m) => in_array($m->sku, ['MED-0003', 'MED-0007', 'MED-0011', 'MED-0015', 'MED-0017', 'MED-0020', 'MED-0032']))->values();
        $names = ['Maria Santos', 'John Reyes', 'Ana Dela Cruz', 'Pedro Garcia', 'Lea Mendoza', 'Jose Bautista', 'Carmen Aquino', 'Ramon Torres'];

        for ($day = 29; $day >= 0; $day--) {
            $date = today()->subDays($day);
            $count = $day === 0 ? mt_rand(18, 26) : mt_rand(22, 38) + ($date->isWeekend() ? 10 : 0);
            $lastHour = $day === 0 ? min(21, max(8, now()->hour)) : 21;

            for ($i = 0; $i < $count; $i++) {
                $items = $sellable->random(mt_rand(1, 4))->map(fn ($m) => [
                    'medicine_id' => $m->id,
                    'quantity' => in_array($m->unit, ['bottle', 'box', 'pack', 'bar']) ? 1 : mt_rand(2, 12),
                ]);
                $rx = $sellable->whereIn('id', $items->pluck('medicine_id'))->contains('requires_prescription', true);
                $discount = mt_rand(1, 100) <= 15 ? (mt_rand(0, 1) ? 'senior' : 'pwd') : 'none';
                $method = ['cash', 'cash', 'cash', 'gcash', 'gcash', 'card'][mt_rand(0, 5)];
                $at = $date->copy()->setTime(mt_rand(7, $lastHour), mt_rand(0, 59));

                if ($at->isFuture()) {
                    $at = now()->subMinutes(mt_rand(5, 90));
                }

                try {
                    $sale = $sales->checkout($staff->random(), [
                        'items' => $items->all(),
                        'payment_method' => $method,
                        'amount_paid' => 100000,
                        'reference_no' => $method === 'cash' ? null : strtoupper(substr(md5(uniqid()), 0, 10)),
                        'discount_type' => $discount,
                        'discount_id_no' => $discount === 'none' ? null : 'ID-'.mt_rand(100000, 999999),
                        'customer_name' => $discount !== 'none' || mt_rand(0, 2) === 0 ? $names[array_rand($names)] : null,
                        'prescription_ref' => $rx ? 'RX-'.mt_rand(10000, 99999) : null,
                    ], $at);
                } catch (ValidationException) {
                    continue; // ran out of one of the items; skip this customer
                }

                // Pretend the customer paid with a round bill.
                if ($method === 'cash') {
                    $paid = (float) (ceil($sale->total / 100) * 100);
                    $sale->update(['amount_paid' => $paid, 'change' => round($paid - $sale->total, 2)]);
                }
            }
        }

        // A couple of voided sales for the transactions screen.
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        Sale::inRandomOrder()->limit(3)->get()->each(fn ($s) => $sales->void($s, $admin, 'Customer cancelled the purchase'));
    }
}
