<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\InvoiceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceSettingController extends Controller
{
    /**
     * GET /api/v1/settings/invoice
     * Return the current invoice settings.
     */
    public function show(): JsonResponse
    {
        return response()->json(InvoiceSetting::instance());
    }

    /**
     * PUT /api/v1/settings/invoice
     * Update invoice settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Company / Branding
            'company_name'               => ['sometimes', 'string', 'max:100'],
            'company_tagline'            => ['sometimes', 'nullable', 'string', 'max:150'],
            'address'                    => ['sometimes', 'nullable', 'string', 'max:255'],
            'hotline_1'                  => ['sometimes', 'nullable', 'string', 'max:30'],
            'hotline_2'                  => ['sometimes', 'nullable', 'string', 'max:30'],
            'hotline_3'                  => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'                      => ['sometimes', 'nullable', 'string', 'max:100'],
            'website'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'facebook'                   => ['sometimes', 'nullable', 'string', 'max:150'],

            // Invoice Configuration
            'invoice_prefix'             => ['sometimes', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/'],
            'default_payment_terms_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'default_notes'              => ['sometimes', 'nullable', 'string', 'max:1000'],
            'thank_you_message'          => ['sometimes', 'string', 'max:200'],

            // Print / PDF Display Options
            'show_product_images'        => ['sometimes', 'boolean'],
            'show_product_weight'        => ['sometimes', 'boolean'],
            'show_delivery_partner'      => ['sometimes', 'boolean'],
        ]);

        $settings = InvoiceSetting::instance();
        $settings->update($validated);

        return response()->json([
            'message'  => 'Invoice settings saved.',
            'settings' => $settings->fresh(),
        ]);
    }
}
