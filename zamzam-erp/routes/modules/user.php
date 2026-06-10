<?php

use App\Http\Controllers\Api\V1\UserPreferenceController;
use App\Http\Controllers\Api\V1\Settings\InvoiceSettingController as ApiInvoiceSettingController;
use App\Http\Controllers\Web\Admin\Settings\SettingsController;
use App\Http\Controllers\Web\Admin\Settings\InvoiceSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Settings & User Preference Routes
|--------------------------------------------------------------------------
*/

// ─── Web routes (Inertia pages) ─────────────────────────────────────────────
Route::get('/settings',         [SettingsController::class,        'index'])->name('settings.index');
Route::get('/settings/invoice', [InvoiceSettingController::class,  'index'])->name('settings.invoice');

// ─── API routes ──────────────────────────────────────────────────────────────
Route::prefix('api/v1')->middleware('web')->group(function () {
    Route::put('/user/preferences',  [UserPreferenceController::class,     'update'])->name('api.user.preferences.update');
    Route::get('/settings/invoice',  [ApiInvoiceSettingController::class,  'show']);
    Route::put('/settings/invoice',  [ApiInvoiceSettingController::class,  'update']);
});
