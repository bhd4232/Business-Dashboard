<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchase_orders.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id'            => 'required|exists:suppliers,id',
            'currency_id'            => 'required|exists:currencies,id',
            'exchange_rate'          => 'required|numeric|min:0.000001',
            'order_date'             => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:order_date',
            'notes'                  => 'nullable|string',
            'terms_and_conditions'   => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.supplier_price_cny' => 'required|numeric|min:0.01',
            'items.*.quantity'           => 'required|integer|min:1',
            'items.*.approx_weight_kg'   => 'nullable|numeric|min:0',
            'items.*.notes'              => 'nullable|string',
        ];
    }
}
