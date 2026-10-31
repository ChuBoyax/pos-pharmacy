<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Medicine;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Past deliveries of the same medicines from other suppliers at different prices,
 * so the supplier price comparison has something to compare. The batches are
 * already used up, so current stock levels are not affected.
 */
class SupplierPriceHistorySeeder extends Seeder
{
    public function run(): void
    {
        $alreadySeeded = Batch::whereNotNull('supplier_id')
            ->selectRaw('medicine_id')
            ->groupBy('medicine_id')
            ->havingRaw('count(distinct supplier_id) > 1')
            ->exists();

        $suppliers = Supplier::pluck('id');

        if ($alreadySeeded || $suppliers->count() < 2) {
            return;
        }

        mt_srand(2027);

        Medicine::all()->each(function (Medicine $medicine) use ($suppliers) {
            $others = $suppliers->reject(fn ($id) => $id === $medicine->supplier_id)->shuffle()->take(mt_rand(1, 3));

            foreach ($others as $supplierId) {
                // Each supplier has its own price level, roughly 10% below to 18% above ours.
                $level = mt_rand(90, 118) / 100;

                for ($i = 0, $n = mt_rand(1, 3); $i < $n; $i++) {
                    $received = today()->subDays(mt_rand(40, 240));
                    $quantity = mt_rand(5, 30) * 10;

                    Batch::create([
                        'medicine_id' => $medicine->id,
                        'supplier_id' => $supplierId,
                        'batch_no' => 'B'.mt_rand(10000, 99999),
                        'quantity_received' => $quantity,
                        'quantity' => 0,
                        'cost' => round($medicine->cost * $level * mt_rand(97, 103) / 100, 2),
                        'expiry_date' => $received->copy()->addMonths(mt_rand(8, 30)),
                        'received_at' => $received,
                    ]);
                }
            }
        });
    }
}
