<?php

namespace App\Http\Requests\Shipping;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('shipments.create');
    }

    public function rules(): array
    {
        return [
            'cost_type'   => 'required|in:freight,customs_duty,vat,ait,labour,transport,customs_fee,demurrage,other',
            'description' => 'nullable|string|max:255',
            'amount_bdt'  => 'required|numeric|min:0',
            'amount_cny'  => 'nullable|numeric|min:0',
            'amount_usd'  => 'nullable|numeric|min:0',
            'voucher_no'  => 'nullable|string|max:100',
            'paid_at'     => 'nullable|date',
        ];
    }
}
