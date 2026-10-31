<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store and receipt settings. Every key here must also exist in Setting::DEFAULTS.
 */
class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:255'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'store_tin' => ['nullable', 'string', 'max:50'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'senior_discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'daily_target' => ['required', 'numeric', 'min:0'],
            'expiry_warning_days' => ['required', 'integer', 'min:1', 'max:365'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
