<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.create');
    }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'name_chinese'   => 'nullable|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'unit'           => 'required|string|max:20',
            'weight_kg'      => 'nullable|numeric|min:0',
            'volume_cm3'     => 'nullable|numeric|min:0',
            'description'    => 'nullable|string',
            'sku'            => 'nullable|string|max:100|unique:products,sku',
            'min_stock_alert'=> 'nullable|integer|min:0',
            'regular_price'  => 'nullable|numeric|min:0',
            'selling_price'  => 'nullable|numeric|min:0',
            'image'          => 'nullable|string|max:500',
            // Variants
            'variants'                      => 'nullable|array',
            'variants.*.variant_name'       => 'required_with:variants|string|max:255',
            'variants.*.sku'                => 'nullable|string|max:100|distinct|unique:product_variants,sku',
            'variants.*.weight_kg'          => 'nullable|numeric|min:0',
            'variants.*.attributes'         => 'nullable|array',
        ];
    }
}
