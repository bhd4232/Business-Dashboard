<?php

use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\BackupDownloadController;
use App\Http\Controllers\Admin\CustomerCsvController;
use App\Http\Controllers\Admin\ProductCsvController;
use App\Http\Controllers\Admin\SupplierCsvController;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->get('/admin/orders/{order}/print', function (Order $order) {
    return view('orders.print', [
        'order' => $order->load(['customer', 'items.product']),
    ]);
})->name('orders.print');

Route::middleware('auth')
    ->get('/admin/reports/export/{type}', ReportExportController::class)
    ->name('reports.export');

Route::middleware('auth')->group(function (): void {
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
