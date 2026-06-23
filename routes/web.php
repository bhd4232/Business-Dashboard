<?php

use App\Http\Controllers\Admin\BackupDownloadController;
use App\Http\Controllers\Admin\CompanySwitchController;
use App\Http\Controllers\Admin\CustomerCsvController;
use App\Http\Controllers\Admin\OrderPdfController;
use App\Http\Controllers\Admin\ProductCsvController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\ReportPdfController;
use App\Http\Controllers\Admin\SupplierCsvController;
use App\Http\Controllers\InstallController;
use App\Models\Order;
use App\Models\User;
use App\Services\CompanySettingsService;
use App\Support\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('marketing.home');
})->name('marketing.home');

Route::view('/pricing', 'marketing.pricing')->name('marketing.pricing');

Route::view('/docs', 'marketing.docs')->name('marketing.docs');

Route::get('/health/version', function () {
    $roleOptions = User::roleOptions();
    $release = AppRelease::current();

    return response()
        ->json([
            'app' => 'zamzam-erp',
            'app_name' => config('app.name'),
            'version' => $release['version'],
            'release_type' => $release['type'],
            'release_label' => $release['type_label'],
            'release_date' => $release['date'],
            'marker' => 'roles-built-in-v2',
            'commit' => $release['commit'],
            'role_option_keys' => array_keys($roleOptions),
            'has_sales_staff_role' => array_key_exists('sales_staff', $roleOptions),
        ])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->name('health.version');

Route::get('/install', [InstallController::class, 'create'])->name('install.create');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

Route::middleware('auth')->get('/admin/orders/{order}/print', function (Order $order, Request $request) {
    abort_unless($request->user()?->canPerformModelAbility('view', Order::class), 403);

    return view('orders.print', [
        'order' => $order->load(['company', 'customer', 'items.product']),
        'company' => app(CompanySettingsService::class)->profile($order->company),
    ]);
})->name('orders.print');

Route::middleware('auth')
    ->get('/admin/orders/{order}/pdf', OrderPdfController::class)
    ->name('orders.pdf');

Route::middleware('auth')
    ->get('/admin/reports/export/{type}', ReportExportController::class)
    ->name('reports.export');

Route::middleware('auth')
    ->get('/admin/reports/export/{type}/pdf', ReportPdfController::class)
    ->name('reports.export.pdf');

Route::middleware('auth')->group(function (): void {
    Route::post('/admin/company/switch', CompanySwitchController::class)
        ->name('admin.company.switch');

    Route::get('/admin/products/export/csv', [ProductCsvController::class, 'export'])
        ->name('products.export.csv');

    Route::get('/admin/products/import/sample', [ProductCsvController::class, 'sample'])
        ->name('products.import.sample');

    Route::get('/admin/customers/export/csv', [CustomerCsvController::class, 'export'])
        ->name('customers.export.csv');

    Route::get('/admin/customers/import/sample', [CustomerCsvController::class, 'sample'])
        ->name('customers.import.sample');

    Route::get('/admin/suppliers/export/csv', [SupplierCsvController::class, 'export'])
        ->name('suppliers.export.csv');

    Route::get('/admin/suppliers/import/sample', [SupplierCsvController::class, 'sample'])
        ->name('suppliers.import.sample');

    Route::get('/admin/backups/download/{filename}', BackupDownloadController::class)
        ->where('filename', '[A-Za-z0-9._-]+')
        ->name('backups.download');
});
