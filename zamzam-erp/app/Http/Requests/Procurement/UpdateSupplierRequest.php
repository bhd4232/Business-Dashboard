<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.edit');
    }

    public function rules(): array
    {
        return [
            'name_chinese'      => 'required|string|max:255',
            'name_english'      => 'required|string|max:255',
            'company_name'      => 'nullable|string|max:255',
            'wechat_id'         => 'nullable|string|max:100',
            'phone'             => 'nullable|string|max:20',
            'email'             => 'nullable|email|max:255',
            'address'           => 'nullable|string',
            'city'              => 'nullable|string|max:100',
            'province'          => 'nullable|string|max:100',
            'country'           => 'nullable|string|size:2',
            'website'           => 'nullable|url|max:500',
            'rating'            => 'nullable|integer|min:1|max:5',
            'payment_terms'     => 'nullable|string|max:255',
            'preferred_currency'=> 'nullable|string|size:3',
            'bank_details'      => 'nullable|array',
            'notes'             => 'nullable|string',
            'is_active'         => 'nullable|boolean',
        ];
    }
}
