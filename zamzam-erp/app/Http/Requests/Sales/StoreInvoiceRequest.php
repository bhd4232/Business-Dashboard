<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_order_id'               => ['nullable', 'integer', 'exists:sales_orders,id'],
            'customer_id'                  => ['required', 'integer', 'exists:customers,id'],
            'issue_date'                   => ['required', 'date'],
            'due_date'                     => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes'                        => ['nullable', 'string', 'max:1000'],
            'discount_bdt'                 => ['nullable', 'numeric', 'min:0'],
            'delivery_charge_bdt'          => ['nullable', 'numeric', 'min:0'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.product_id'           => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id'   => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'             => ['required', 'integer', 'min:1'],
            'items.*.unit_price_bdt'       => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
