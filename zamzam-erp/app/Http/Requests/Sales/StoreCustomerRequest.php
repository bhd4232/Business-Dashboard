<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.create');
    }

    public function rules(): array
    {
        return [
            'name'               => 'required|string|max:255',
            'business_name'      => 'nullable|string|max:255',
            'type'               => 'required|in:wholesale,retail',
            'phone'              => 'required|string|max:20',
            'email'              => 'nullable|email|max:255',
            'address'            => 'nullable|string',
            'city'               => 'nullable|string|max:100',
            'area'               => 'nullable|string|max:100',
            'district'           => 'nullable|string|max:100',
            'trade_license_no'   => 'nullable|string|max:100',
            'nid_no'             => 'nullable|string|max:50',
            'credit_limit_bdt'   => 'nullable|numeric|min:0',
            'price_tier_id'      => 'nullable|exists:price_tiers,id',
            'source'             => 'nullable|in:direct,referral,messenger,whatsapp,woocommerce,other',
            'source_detail'      => 'nullable|string|max:255',
            'rating'             => 'nullable|integer|min:1|max:5',
            'assigned_salesman_id' => 'nullable|exists:users,id',
            'notes'              => 'nullable|string',
            'tag_ids'            => 'nullable|array',
            'tag_ids.*'          => 'exists:customer_tags,id',
        ];
    }
}
