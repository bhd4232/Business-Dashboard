<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('warehouses.manage');
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:20|unique:warehouses,code',
            'address'    => 'nullable|string',
            'city'       => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ];
    }
}
