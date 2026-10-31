<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sku', 'name', 'generic_name', 'category_id', 'supplier_id', 'unit', 'price', 'cost',
    'reorder_level', 'requires_prescription', 'is_active', 'description', 'image_path',
])]
#[Hidden(['image_path'])]
#[Appends(['image_url'])]
class Medicine extends Model
{
    /**
     * Relative URL so it works whatever host the app is served from.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path ? '/storage/'.$this->image_path : null);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'reorder_level' => 'integer',
            'stock' => 'integer',
            'requires_prescription' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * Sellable stock: the remaining quantity of every batch that has not expired yet.
     */
    private static function stockSql(): string
    {
        return '(select coalesce(sum(quantity), 0) from batches where batches.medicine_id = medicines.id and batches.expiry_date > ?)';
    }

    public function scopeWithStock(Builder $query): void
    {
        $query->addSelect('medicines.*')->selectRaw(self::stockSql().' as stock', [today()->toDateString()]);
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->whereRaw(self::stockSql().' <= medicines.reorder_level', [today()->toDateString()]);
    }

    public function scopeOutOfStock(Builder $query): void
    {
        $query->whereRaw(self::stockSql().' = 0', [today()->toDateString()]);
    }

    public function scopeInStock(Builder $query): void
    {
        $query->whereRaw(self::stockSql().' > 0', [today()->toDateString()]);
    }
}
