<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'                  => ['sometimes', 'required', 'integer', 'exists:customers,id'],
            'issue_date'                   => ['sometimes', 'required', 'date'],
            'due_date'                     => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes'                        => ['nullable', 'string', 'max:1000'],
            'discount_bdt'                 => ['nullable', 'numeric', 'min:0'],
            'delivery_charge_bdt'          => ['nullable', 'numeric', 'min:0'],
            'items'                        => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id'           => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.product_variant_id'   => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'             => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price_bdt'       => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
