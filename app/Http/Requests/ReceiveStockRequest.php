<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new delivery (batch) of a medicine. The quantity and unit cost are computed from these
 * purchase details by App\Services\PurchaseCosting.
 */
class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'medicine_id' => ['required', 'exists:medicines,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'batch_no' => ['required', 'string', 'max:50'],
            'packs_bought' => ['required', 'integer', 'min:1', 'max:100000'],
            'pack_size' => ['required', 'integer', 'min:1', 'max:100000'],
            'list_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deal_buy' => ['nullable', 'required_with:deal_free', 'integer', 'min:1'],
            'deal_free' => ['nullable', 'required_with:deal_buy', 'integer', 'min:1'],
            'total_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'deal_buy.required_with' => 'Enter how many packs must be bought for this deal.',
            'deal_free.required_with' => 'Enter how many packs come free with this deal.',
        ];
    }
}
