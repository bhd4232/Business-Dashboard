<?php

use App\Http\Controllers\Web\Admin\Procurement\ProductController;
use App\Http\Controllers\Web\Admin\Procurement\PurchaseOrderController;
use App\Http\Controllers\Web\Admin\Procurement\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement Module Web Routes
|--------------------------------------------------------------------------
*/

// ─── Suppliers ───────────────────────────────────────────────────────────
Route::prefix('suppliers')->name('suppliers.')->group(function () {
    Route::get('/',           [SupplierController::class, 'index'])->name('index');
    Route::get('/create',     [SupplierController::class, 'create'])->name('create');
    Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
    Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
});

// ─── Products & Categories ───────────────────────────────────────────────
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/',          [ProductController::class, 'index'])->name('index');
    Route::get('/create',    [ProductController::class, 'create'])->name('create');
    Route::get('/{product}', [ProductController::class, 'show'])->name('show');
    Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
});

Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [ProductController::class, 'categories'])->name('index');
});

// ─── Purchase Orders ─────────────────────────────────────────────────────
Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
    Route::get('/',                    [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/create',              [PurchaseOrderController::class, 'create'])->name('create');
    Route::get('/{purchaseOrder}',     [PurchaseOrderController::class, 'show'])->name('show');
    Route::get('/{purchaseOrder}/edit',[PurchaseOrderController::class, 'edit'])->name('edit');
});

// ─── API Routes for Procurement ──────────────────────────────────────────
use App\Http\Controllers\Api\V1\Procurement\SupplierController as ApiSupplierController;
use App\Http\Controllers\Api\V1\Procurement\ProductController as ApiProductController;
use App\Http\Controllers\Api\V1\Procurement\PurchaseOrderController as ApiPurchaseOrderController;

Route::prefix('api/v1')->name('api.')->middleware('web')->group(function () {
    // Suppliers (trashed/restore/force must be before {supplier} wildcard)
    Route::get('/suppliers',                         [ApiSupplierController::class, 'index']);
    Route::post('/suppliers',                        [ApiSupplierController::class, 'store']);
    Route::get('/suppliers/trashed',                 [ApiSupplierController::class, 'trashed']);
    Route::post('/suppliers/{id}/restore',           [ApiSupplierController::class, 'restore']);
    Route::delete('/suppliers/{id}/force',           [ApiSupplierController::class, 'forceDelete']);
    Route::get('/suppliers/{supplier}',              [ApiSupplierController::class, 'show']);
    Route::put('/suppliers/{supplier}',              [ApiSupplierController::class, 'update']);
    Route::delete('/suppliers/{supplier}',           [ApiSupplierController::class, 'destroy']);
    Route::get('/suppliers/{supplier}/products',     [ApiSupplierController::class, 'products']);
    Route::get('/suppliers/{supplier}/orders',       [ApiSupplierController::class, 'orders']);
    Route::post('/suppliers/{supplier}/contacts',    [ApiSupplierController::class, 'storeContact']);
    Route::delete('/suppliers/{supplier}/contacts/{contactId}', [ApiSupplierController::class, 'destroyContact']);

    // Products (trashed/restore/force must be before {product} wildcard)
    Route::get('/products',                          [ApiProductController::class, 'index']);
    Route::post('/products',                         [ApiProductController::class, 'store']);
    Route::get('/products/search',                   [ApiProductController::class, 'search']);
    Route::get('/products/browse',                   [ApiProductController::class, 'browse']);
    Route::get('/products/trashed',                  [ApiProductController::class, 'trashed']);
    Route::post('/products/{id}/restore',            [ApiProductController::class, 'restore']);
    Route::delete('/products/{id}/force',            [ApiProductController::class, 'forceDelete']);
    Route::get('/products/{product}',                [ApiProductController::class, 'show']);
    Route::put('/products/{product}',                [ApiProductController::class, 'update']);
    Route::delete('/products/{product}',             [ApiProductController::class, 'destroy']);
    Route::post('/products/{product}/variants',      [ApiProductController::class, 'storeVariant']);
    Route::delete('/products/{product}/variants/{variant}', [ApiProductController::class, 'destroyVariant']);

    // Categories (trashed must be before {id} wildcard)
    Route::get('/categories/trashed',          [ApiProductController::class, 'trashedCategories']);
    Route::get('/categories',                        [ApiProductController::class, 'categories']);
    Route::post('/categories',                       [ApiProductController::class, 'storeCategory']);
    Route::put('/categories/{id}',             [ApiProductController::class, 'updateCategory']);
    Route::delete('/categories/{id}',          [ApiProductController::class, 'destroyCategory']);
    Route::post('/categories/{id}/restore',    [ApiProductController::class, 'restoreCategory']);

    // Purchase Orders (trashed/restore/force must be before {purchaseOrder} wildcard)
    Route::get('/purchase-orders',                          [ApiPurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders',                         [ApiPurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/trashed',                  [ApiPurchaseOrderController::class, 'trashed']);
    Route::post('/purchase-orders/{id}/restore',            [ApiPurchaseOrderController::class, 'restore']);
    Route::delete('/purchase-orders/{id}/force',            [ApiPurchaseOrderController::class, 'forceDelete']);
    Route::get('/purchase-orders/{purchaseOrder}',          [ApiPurchaseOrderController::class, 'show']);
    Route::put('/purchase-orders/{purchaseOrder}',          [ApiPurchaseOrderController::class, 'update']);
    Route::delete('/purchase-orders/{purchaseOrder}',       [ApiPurchaseOrderController::class, 'destroy']);
    Route::post('/purchase-orders/{purchaseOrder}/confirm', [ApiPurchaseOrderController::class, 'confirm']);
    Route::post('/purchase-orders/{purchaseOrder}/cancel',  [ApiPurchaseOrderController::class, 'cancel']);
});
