<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /**
     * Record a sale and deduct stock from the batches that expire first (FEFO).
     *
     * @param  array{items: array<int, array{medicine_id: int, quantity: int}>, payment_method: string, amount_paid?: float|null, discount_type?: string|null, discount_id_no?: string|null, customer_name?: string|null, prescription_ref?: string|null, reference_no?: string|null}  $data
     */
    public function checkout(User $cashier, array $data, ?CarbonInterface $at = null): Sale
    {
        return DB::transaction(function () use ($cashier, $data, $at) {
            $lines = collect($data['items'])
                ->groupBy('medicine_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));

            $medicines = Medicine::whereIn('id', $lines->keys())->lockForUpdate()->get()->keyBy('id');
            $today = ($at ?? now())->toDateString();
            $allocations = [];
            $subtotal = 0.0;
            $needsPrescription = false;

            foreach ($lines as $medicineId => $quantity) {
                $medicine = $medicines->get($medicineId);

                if (! $medicine || ! $medicine->is_active) {
                    throw ValidationException::withMessages(['items' => 'One of the items is no longer available.']);
                }

                $batches = $medicine->batches()
                    ->where('quantity', '>', 0)
                    ->where('expiry_date', '>', $today)
                    ->orderBy('expiry_date')
                    ->lockForUpdate()
                    ->get();

                if ($batches->sum('quantity') < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Only {$batches->sum('quantity')} {$medicine->unit}(s) of {$medicine->name} left in stock.",
                    ]);
                }

                $remaining = $quantity;
                foreach ($batches as $batch) {
                    $take = min($remaining, $batch->quantity);
                    $batch->decrement('quantity', $take);
                    $allocations[] = [
                        'medicine_id' => $medicine->id,
                        'batch_id' => $batch->id,
                        'name' => $medicine->name,
                        'price' => $medicine->price,
                        'quantity' => $take,
                        'total' => round($medicine->price * $take, 2),
                    ];

                    if (($remaining -= $take) === 0) {
                        break;
                    }
                }

                $subtotal += $medicine->price * $quantity;
                $needsPrescription = $needsPrescription || $medicine->requires_prescription;
            }

            if ($needsPrescription && blank($data['prescription_ref'] ?? null)) {
                throw ValidationException::withMessages([
                    'prescription_ref' => 'A prescription reference is required for Rx medicines.',
                ]);
            }

            $discountType = $data['discount_type'] ?? 'none';
            $totals = $this->totals(round($subtotal, 2), $discountType);

            $amountPaid = $data['payment_method'] === 'cash'
                ? round((float) ($data['amount_paid'] ?? 0), 2)
                : $totals['total'];

            if ($amountPaid < $totals['total']) {
                throw ValidationException::withMessages(['amount_paid' => 'Amount tendered is less than the total due.']);
            }

            $sale = new Sale([
                'user_id' => $cashier->id,
                'customer_name' => $data['customer_name'] ?? null,
                'discount_type' => $discountType,
                'discount_id_no' => $discountType === 'none' ? null : ($data['discount_id_no'] ?? null),
                'prescription_ref' => $data['prescription_ref'] ?? null,
                'payment_method' => $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'amount_paid' => $amountPaid,
                'change' => round($amountPaid - $totals['total'], 2),
                'status' => Sale::STATUS_PAID,
                ...$totals,
            ]);

            if ($at) {
                $sale->created_at = $sale->updated_at = $at;
            }

            $sale->save();
            $sale->update(['invoice_no' => sprintf('INV-%06d', $sale->id)]);
            $sale->items()->createMany($allocations);

            return $sale->load('items', 'user:id,name');
        });
    }

    /**
     * Cancel a sale and return its quantities to the batches they came from.
     */
    public function void(Sale $sale, User $user, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $user, $reason) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== Sale::STATUS_PAID) {
                throw ValidationException::withMessages(['reason' => 'This sale has already been voided.']);
            }

            foreach ($sale->items as $item) {
                if ($item->batch_id) {
                    Batch::whereKey($item->batch_id)->increment('quantity', $item->quantity);
                }
            }

            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $reason,
            ]);

            return $sale;
        });
    }

    /**
     * Shelf prices are VAT-inclusive. Senior citizens and PWDs are VAT-exempt and get
     * their discount on the VAT-exclusive amount (RA 9994 / RA 10754).
     *
     * @return array{subtotal: float, discount: float, vat: float, total: float}
     */
    public function totals(float $subtotal, string $discountType): array
    {
        $vatRate = (float) Setting::valueOf('vat_rate') / 100;

        if ($discountType === 'none') {
            return [
                'subtotal' => $subtotal,
                'discount' => 0.0,
                'vat' => round($subtotal - $subtotal / (1 + $vatRate), 2),
                'total' => $subtotal,
            ];
        }

        $netOfVat = round($subtotal / (1 + $vatRate), 2);
        $discount = round($netOfVat * (float) Setting::valueOf('senior_discount_rate') / 100, 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'vat' => 0.0,
            'total' => round($netOfVat - $discount, 2),
        ];
    }
}
