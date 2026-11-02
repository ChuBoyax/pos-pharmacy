<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'medicine_id', 'supplier_id', 'batch_no', 'quantity_received', 'quantity', 'cost', 'expiry_date', 'received_at',
    'packs_bought', 'free_packs', 'pack_size', 'list_price', 'discount_percent', 'deal_buy', 'deal_free',
    'total_cost', 'effective_discount',
])]
class Batch extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
            'quantity' => 'integer',
            'cost' => 'decimal:4',
            'packs_bought' => 'integer',
            'free_packs' => 'integer',
            'pack_size' => 'integer',
            'list_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'deal_buy' => 'integer',
            'deal_free' => 'integer',
            'total_cost' => 'decimal:2',
            'effective_discount' => 'decimal:2',
            'expiry_date' => 'date:Y-m-d',
            'received_at' => 'date:Y-m-d',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }
}
