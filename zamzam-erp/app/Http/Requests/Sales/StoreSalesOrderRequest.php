<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_id'           => ['required', 'integer', 'exists:customers,id'],
            'type'                  => ['required', 'in:wholesale,retail'],
            'source'                => ['required', 'in:erp,storefront,whatsapp,messenger,woocommerce,reseller'],
            'price_tier_id'         => ['nullable', 'integer', 'exists:price_tiers,id'],
            'discount_bdt'          => ['nullable', 'numeric', 'min:0'],
            'discount_percent'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_charge_bdt'   => ['nullable', 'numeric', 'min:0'],
            'paid_bdt'              => ['nullable', 'numeric', 'min:0'],
            'delivery_address'      => ['nullable', 'string'],
            'delivery_city'         => ['nullable', 'string', 'max:100'],
            'delivery_area'         => ['nullable', 'string', 'max:100'],
            'notes'                 => ['nullable', 'string'],
            'internal_notes'        => ['nullable', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'        => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id'=> ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
            'items.*.unit_price_bdt'    => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer.',
            'items.required'       => 'At least one item is required.',
            'items.min'            => 'At least one item is required.',
        ];
    }
}
