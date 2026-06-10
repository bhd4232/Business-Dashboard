<?php

namespace App\Http\Requests\Shipping;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('shipments.edit');
    }

    public function rules(): array
    {
        return [
            'notes'    => 'nullable|string',
            'location' => 'nullable|string|max:255',
        ];
    }
}
