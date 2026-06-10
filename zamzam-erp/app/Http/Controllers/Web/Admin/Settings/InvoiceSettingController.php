<?php

namespace App\Http\Controllers\Web\Admin\Settings;

use App\Models\Settings\InvoiceSetting;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceSettingController
{
    public function index(): Response
    {
        return Inertia::render('Settings/InvoiceSettings', [
            'settings' => InvoiceSetting::instance(),
        ]);
    }
}
