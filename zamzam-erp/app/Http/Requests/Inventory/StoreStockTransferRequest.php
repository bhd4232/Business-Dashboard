<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('stock_transfers.create');
    }

    public function rules(): array
    {
        return [
            'from_warehouse_id'          => 'required|exists:warehouses,id|different:to_warehouse_id',
            'to_warehouse_id'            => 'required|exists:warehouses,id',
            'notes'                      => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity'           => 'required|integer|min:1',
        ];
    }
}
