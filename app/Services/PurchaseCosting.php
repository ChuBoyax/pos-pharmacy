<?php

namespace App\Services;

/**
 * Works out what a delivery really cost per sellable piece, after supplier discounts and
 * "10 + 2" style deals. public/js/pages/stock.js mirrors this for its live preview.
 *
 * Rules (to be confirmed against real supplier invoices):
 * - Deals count packs and only whole deals earn free packs: 15 packs on "10 + 2" gets 2 free.
 * - The percentage discount applies to the packs paid for.
 * - The amount actually paid, when entered, overrides the computed total.
 * - Effective discount compares what was paid with the catalog value of everything received.
 */
class PurchaseCosting
{
    /**
     * @return array{free_packs: int, quantity: int, total_cost: float, cost: float, effective_discount: float}
     */
    public static function compute(
        int $packs,
        int $packSize,
        float $listPrice = 0,
        float $discountPercent = 0,
        ?int $dealBuy = null,
        ?int $dealFree = null,
        ?float $amountPaid = null,
    ): array {
        $freePacks = $dealBuy && $dealFree ? (int) floor($packs / $dealBuy) * $dealFree : 0;
        $quantity = ($packs + $freePacks) * $packSize;
        $total = round($amountPaid ?? $packs * $listPrice * (1 - $discountPercent / 100), 2);
        $catalogValue = ($packs + $freePacks) * $listPrice;

        return [
            'free_packs' => $freePacks,
            'quantity' => $quantity,
            'total_cost' => $total,
            'cost' => round($total / $quantity, 4),
            'effective_discount' => $catalogValue > 0 ? round(max(0, 1 - $total / $catalogValue) * 100, 2) : 0.0,
        ];
    }
}
