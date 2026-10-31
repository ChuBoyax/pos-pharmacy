<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new delivery (batch) of a medicine.
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
            'quantity' => ['required', 'integer', 'min:1'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
