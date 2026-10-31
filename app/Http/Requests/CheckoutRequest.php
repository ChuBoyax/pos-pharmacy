<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A sale rung up at the POS. Stock and prescription checks happen in SaleService.
 */
class CheckoutRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in(Sale::PAYMENT_METHODS)],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'reference_no' => ['nullable', 'required_unless:payment_method,cash', 'string', 'max:100'],
            'discount_type' => ['nullable', Rule::in(Sale::DISCOUNT_TYPES)],
            'discount_id_no' => ['nullable', 'required_if:discount_type,senior,pwd', 'string', 'max:50'],
            'customer_name' => ['nullable', 'required_if:discount_type,senior,pwd', 'string', 'max:255'],
            'prescription_ref' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference_no.required_unless' => 'Enter the GCash / card reference number.',
            'discount_id_no.required_if' => 'Senior citizen / PWD ID number is required.',
            'customer_name.required_if' => 'Customer name is required for senior citizen / PWD discounts.',
        ];
    }
}
