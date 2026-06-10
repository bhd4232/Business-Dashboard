<?php

namespace App\Http\Requests\Shipping;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('shipments.create');
    }

    public function rules(): array
    {
        return [
            'purchase_order_id'      => 'nullable|exists:purchase_orders,id',
            'shipping_type'          => 'required|in:sea,air,rail,courier',
            'carrier'                => 'nullable|string|max:255',
            'container_no'           => 'nullable|string|max:50',
            'container_type'         => 'nullable|in:20ft,40ft,40HC,LCL',
            'bl_number'              => 'nullable|string|max:100',
            'port_loading'           => 'nullable|string|max:100',
            'port_discharge'         => 'nullable|string|max:100',
            'etd'                    => 'nullable|date',
            'eta'                    => 'nullable|date|after_or_equal:etd',
            'cost_allocation_method' => 'nullable|in:weight,volume,value,quantity,manual',
            'customs_agent'          => 'nullable|string|max:255',
            'tracking_url'           => 'nullable|url|max:500',
            'notes'                  => 'nullable|string',
        ];
    }
}
