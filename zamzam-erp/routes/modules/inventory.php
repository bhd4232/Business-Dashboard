<?php

use App\Http\Controllers\Web\Admin\Inventory\InventoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory Module Web Routes
|--------------------------------------------------------------------------
*/

// ─── Warehouses ──────────────────────────────────────────────────────────
Route::prefix('warehouses')->name('warehouses.')->group(function () {
    Route::get('/', [InventoryController::class, 'warehouses'])->name('index');
});

// ─── Stock ───────────────────────────────────────────────────────────────
Route::prefix('stock')->name('stock.')->group(function () {
    Route::get('/',              [InventoryController::class, 'stock'])->name('index');
    Route::get('/low-stock',     [InventoryController::class, 'lowStock'])->name('low-stock');
    Route::get('/{productId}',   [InventoryController::class, 'stockShow'])->name('show');
});

// ─── Stock Transfers ─────────────────────────────────────────────────────
Route::prefix('stock-transfers')->name('stock-transfers.')->group(function () {
    Route::get('/',                         [InventoryController::class, 'transfers'])->name('index');
    Route::get('/create',                   [InventoryController::class, 'transferCreate'])->name('create');
    Route::get('/{stockTransfer}',          [InventoryController::class, 'transferShow'])->name('show');
});

// ─── Stock Adjustments ───────────────────────────────────────────────────
Route::prefix('stock-adjustments')->name('stock-adjustments.')->group(function () {
    Route::get('/',                         [InventoryController::class, 'adjustments'])->name('index');
    Route::get('/create',                   [InventoryController::class, 'adjustmentCreate'])->name('create');
    Route::get('/{stockAdjustment}',        [InventoryController::class, 'adjustmentShow'])->name('show');
});

// ─── Barcodes ─────────────────────────────────────────────────────────────
Route::prefix('barcodes')->name('barcodes.')->group(function () {
    Route::get('/', [InventoryController::class, 'barcodes'])->name('index');
});

// ─── Inventory API Routes ────────────────────────────────────────────────
use App\Http\Controllers\Api\V1\Inventory\WarehouseController;
use App\Http\Controllers\Api\V1\Inventory\StockController;
use App\Http\Controllers\Api\V1\Inventory\StockTransferController;
use App\Http\Controllers\Api\V1\Inventory\StockAdjustmentController;
use App\Http\Controllers\Api\V1\Inventory\BarcodeController;

Route::prefix('api/v1')->middleware('web')->group(function () {
    // Warehouses (trashed/restore/force must be before {warehouse} wildcard)
    Route::get('/warehouses',                      [WarehouseController::class, 'index']);
    Route::post('/warehouses',                     [WarehouseController::class, 'store']);
    Route::get('/warehouses/trashed',              [WarehouseController::class, 'trashed']);
    Route::post('/warehouses/{id}/restore',        [WarehouseController::class, 'restore']);
    Route::delete('/warehouses/{id}/force',        [WarehouseController::class, 'forceDelete']);
    Route::get('/warehouses/{warehouse}',          [WarehouseController::class, 'show']);
    Route::put('/warehouses/{warehouse}',          [WarehouseController::class, 'update']);
    Route::delete('/warehouses/{warehouse}',       [WarehouseController::class, 'destroy']);

    // Stock
    Route::get('/stock',                       [StockController::class, 'index']);
    Route::get('/stock/low-stock',             [StockController::class, 'lowStock']);
    Route::get('/stock/valuation',             [StockController::class, 'valuation']);
    Route::get('/stock/{productId}',           [StockController::class, 'show']);
    Route::get('/stock-transactions',          [StockController::class, 'transactions']);

    // Transfers
    Route::get('/stock-transfers',                   [StockTransferController::class, 'index']);
    Route::post('/stock-transfers',                  [StockTransferController::class, 'store']);
    Route::get('/stock-transfers/{stockTransfer}',   [StockTransferController::class, 'show']);
    Route::post('/stock-transfers/{stockTransfer}/dispatch',  [StockTransferController::class, 'dispatch']);
    Route::post('/stock-transfers/{stockTransfer}/complete',  [StockTransferController::class, 'complete']);
    Route::post('/stock-transfers/{stockTransfer}/cancel',    [StockTransferController::class, 'cancel']);

    // Adjustments
    Route::get('/stock-adjustments',               [StockAdjustmentController::class, 'index']);
    Route::post('/stock-adjustments',              [StockAdjustmentController::class, 'store']);
    Route::get('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'show']);

    // Barcodes
    Route::get('/barcodes',                [BarcodeController::class, 'index']);
    Route::post('/barcodes/generate',      [BarcodeController::class, 'generate']);
    Route::post('/barcodes/bulk-generate', [BarcodeController::class, 'bulkGenerate']);
    Route::post('/barcodes/scan',          [BarcodeController::class, 'scan']);
    Route::get('/barcodes/settings',       [BarcodeController::class, 'getSettings']);
    Route::put('/barcodes/settings',       [BarcodeController::class, 'updateSettings']);
});
