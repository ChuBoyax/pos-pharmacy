<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const DEFAULTS = [
        'store_name' => 'PharmaPOS Drugstore',
        'store_address' => '123 Rizal Avenue, Manila',
        'store_phone' => '(02) 8123 4567',
        'store_tin' => '000-123-456-000',
        'vat_rate' => '12',
        'senior_discount_rate' => '20',
        'daily_target' => '30000',
        'expiry_warning_days' => '60',
        'receipt_footer' => 'Thank you for your purchase! Get well soon.',
    ];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * Every setting, with defaults filled in for keys that were never saved.
     *
     * @return array<string, string>
     */
    public static function allValues(): array
    {
        return Cache::rememberForever('settings', fn () => array_merge(
            self::DEFAULTS,
            static::query()->pluck('value', 'key')->all(),
        ));
    }

    public static function valueOf(string $key): ?string
    {
        return static::allValues()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('settings');
    }
}
