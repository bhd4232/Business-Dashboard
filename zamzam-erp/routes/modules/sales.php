<?php

use App\Http\Controllers\Web\Admin\Sales\CustomerController;
use App\Http\Controllers\Web\Admin\Sales\InvoiceController;
use App\Http\Controllers\Web\Admin\Sales\SalesOrderController;
use App\Http\Controllers\Api\V1\Sales\CustomerController as ApiCustomerController;
use App\Http\Controllers\Api\V1\Sales\CustomerTagController as ApiTagController;
use App\Http\Controllers\Api\V1\Sales\InvoiceController as ApiInvoiceController;
use App\Http\Controllers\Api\V1\Sales\SalesOrderController as ApiSalesOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales Module Routes
|--------------------------------------------------------------------------
*/

// ─── Sales Orders (Web / Inertia) ────────────────────────────────────────
Route::prefix('sales-orders')->name('sales-orders.')->group(function () {
    Route::get('/',                     [SalesOrderController::class, 'index'])->name('index');
    Route::get('/create',               [SalesOrderController::class, 'create'])->name('create');
    Route::get('/{salesOrder}',         [SalesOrderController::class, 'show'])->name('show');
    Route::get('/{salesOrder}/edit',    [SalesOrderController::class, 'edit'])->name('edit');
});

// ─── Customers (Web / Inertia) ───────────────────────────────────────────
Route::prefix('customers')->name('customers.')->group(function () {
    Route::get('/',                  [CustomerController::class, 'index'])->name('index');
    Route::get('/create',            [CustomerController::class, 'create'])->name('create');
    Route::get('/{customer}',        [CustomerController::class, 'show'])->name('show');
    Route::get('/{customer}/edit',   [CustomerController::class, 'edit'])->name('edit');
});

// ─── Invoices (Web / Inertia) ────────────────────────────────────────────
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/',                  [InvoiceController::class, 'index'])->name('index');
    Route::get('/create',            [InvoiceController::class, 'create'])->name('create');
    Route::get('/{invoice}',         [InvoiceController::class, 'show'])->name('show');
    Route::get('/{invoice}/edit',    [InvoiceController::class, 'edit'])->name('edit');
});

// ─── Customer Tags (Web page — single index page) ────────────────────────
Route::get('/customer-tags', function () {
    return \Inertia\Inertia::render('Sales/Customers/Tags/Index', [
        'tags'       => \App\Models\Sales\CustomerTag::with('linkedPriceTier')->orderBy('sort_order')->get(),
        'priceTiers' => \App\Models\Sales\PriceTier::active()->get(['id','name','discount_percent']),
    ]);
})->name('customer-tags.index');

// ─── API Routes for Sales ────────────────────────────────────────────────
Route::prefix('api/v1')->middleware('web')->group(function () {

    // Sales Orders — static segments before {salesOrder} wildcard
    Route::get('/sales-orders/trashed',                     [ApiSalesOrderController::class, 'trashed']);
    Route::get('/sales-orders',                             [ApiSalesOrderController::class, 'index']);
    Route::post('/sales-orders',                            [ApiSalesOrderController::class, 'store']);
    Route::post('/sales-orders/bulk-status',                [ApiSalesOrderController::class, 'bulkStatusChange']);
    Route::post('/sales-orders/{id}/restore',               [ApiSalesOrderController::class, 'restore']);
    Route::delete('/sales-orders/{id}/force',               [ApiSalesOrderController::class, 'forceDelete']);
    Route::get('/sales-orders/{salesOrder}',                [ApiSalesOrderController::class, 'show']);
    Route::put('/sales-orders/{salesOrder}',                [ApiSalesOrderController::class, 'update']);
    Route::delete('/sales-orders/{salesOrder}',             [ApiSalesOrderController::class, 'destroy']);
    Route::post('/sales-orders/{salesOrder}/confirm',       [ApiSalesOrderController::class, 'confirm']);
    Route::post('/sales-orders/{salesOrder}/cancel',        [ApiSalesOrderController::class, 'cancel']);
    Route::get('/sales-orders/{salesOrder}/payments',                    [ApiSalesOrderController::class, 'payments']);
    Route::post('/sales-orders/{salesOrder}/payments',                   [ApiSalesOrderController::class, 'receivePayment']);
    Route::put('/sales-orders/{salesOrder}/payments/{payment}',          [ApiSalesOrderController::class, 'updatePayment']);
    Route::get('/sales-orders/{salesOrder}/attachments',                  [ApiSalesOrderController::class, 'attachments']);
    Route::post('/sales-orders/{salesOrder}/attachments',                 [ApiSalesOrderController::class, 'storeAttachment']);
    Route::delete('/sales-orders/{salesOrder}/attachments/{attachment}',  [ApiSalesOrderController::class, 'destroyAttachment']);

    // Customers — static segments before {customer} wildcard
    Route::get('/customers/search',           [ApiCustomerController::class, 'search']);
    Route::get('/customers/trashed',          [ApiCustomerController::class, 'trashed']);
    Route::get('/customers',                  [ApiCustomerController::class, 'index']);
    Route::post('/customers',                 [ApiCustomerController::class, 'store']);
    Route::get('/customers/{customer}',       [ApiCustomerController::class, 'show']);
    Route::put('/customers/{customer}',       [ApiCustomerController::class, 'update']);
    Route::delete('/customers/{customer}',    [ApiCustomerController::class, 'destroy']);
    Route::post('/customers/{id}/restore',    [ApiCustomerController::class, 'restore']);
    Route::delete('/customers/{id}/force',    [ApiCustomerController::class, 'forceDelete']);
    Route::patch('/customers/{customer}/toggle-active', [ApiCustomerController::class, 'toggleActive']);

    // Customer Tags
    Route::get('/customer-tags',              [ApiTagController::class, 'index']);
    Route::post('/customer-tags',             [ApiTagController::class, 'store']);
    Route::put('/customer-tags/{customerTag}',[ApiTagController::class, 'update']);
    Route::delete('/customer-tags/{customerTag}', [ApiTagController::class, 'destroy']);
    Route::get('/price-tiers',                [ApiTagController::class, 'priceTiers']);

    // Invoices — static segments before {invoice} wildcard
    Route::get('/invoices',                          [ApiInvoiceController::class, 'index']);
    Route::post('/invoices',                         [ApiInvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}',                [ApiInvoiceController::class, 'show']);
    Route::put('/invoices/{invoice}',                [ApiInvoiceController::class, 'update']);
    Route::post('/invoices/{invoice}/issue',         [ApiInvoiceController::class, 'issue']);
    Route::post('/invoices/{invoice}/cancel',        [ApiInvoiceController::class, 'cancel']);
    Route::post('/invoices/{invoice}/sync-payment',  [ApiInvoiceController::class, 'syncPayment']);
});
