<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_no', 'user_id', 'customer_name', 'discount_type', 'discount_id_no', 'prescription_ref',
    'subtotal', 'discount', 'vat', 'total', 'payment_method', 'reference_no', 'amount_paid', 'change',
    'status', 'voided_at', 'voided_by', 'void_reason',
])]
class Sale extends Model
{
    public const STATUS_PAID = 'paid';

    public const STATUS_VOIDED = 'voided';

    public const PAYMENT_METHODS = ['cash', 'gcash', 'card'];

    public const DISCOUNT_TYPES = ['none', 'senior', 'pwd'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('sales.status', self::STATUS_PAID);
    }
}
