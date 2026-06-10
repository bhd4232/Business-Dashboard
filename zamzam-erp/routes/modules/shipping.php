<?php

use App\Http\Controllers\Api\V1\Shipping\ShipmentController as ApiShipmentController;
use App\Http\Controllers\Web\Admin\Shipping\InternationalShipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shipping Module Routes
|--------------------------------------------------------------------------
*/

// ─── Web (Inertia) Routes ────────────────────────────────────────────────
Route::prefix('shipping/international')->name('shipments.')->group(function () {
    Route::get('/',              [InternationalShipmentController::class, 'index'])->name('index');
    Route::get('/create',        [InternationalShipmentController::class, 'create'])->name('create');
    Route::get('/{shipment}',    [InternationalShipmentController::class, 'show'])->name('show');
    Route::get('/{shipment}/landing-cost', [InternationalShipmentController::class, 'landingCost'])->name('landing-cost');
});

// ─── API Routes ──────────────────────────────────────────────────────────
Route::prefix('api/v1')->name('api.shipping.')->middleware('web')->group(function () {

    // Shipments CRUD
    Route::get('/shipments',              [ApiShipmentController::class, 'index'])->name('index');
    Route::post('/shipments',             [ApiShipmentController::class, 'store'])->name('store');
    Route::get('/shipments/{shipment}',   [ApiShipmentController::class, 'show'])->name('show');
    Route::put('/shipments/{shipment}',   [ApiShipmentController::class, 'update'])->name('update');
    Route::delete('/shipments/{shipment}',[ApiShipmentController::class, 'destroy'])->name('destroy');

    // Status
    Route::post('/shipments/{shipment}/advance-status', [ApiShipmentController::class, 'advanceStatus'])->name('advance-status');
    Route::get('/shipments/{shipment}/status-history',  [ApiShipmentController::class, 'statusHistory'])->name('status-history');

    // Items
    Route::post('/shipments/{shipment}/items',                     [ApiShipmentController::class, 'storeItem'])->name('items.store');
    Route::put('/shipments/{shipment}/items/{item}',               [ApiShipmentController::class, 'updateItem'])->name('items.update');
    Route::delete('/shipments/{shipment}/items/{item}',            [ApiShipmentController::class, 'destroyItem'])->name('items.destroy');

    // Costs
    Route::post('/shipments/{shipment}/costs',                     [ApiShipmentController::class, 'storeCost'])->name('costs.store');
    Route::put('/shipments/{shipment}/costs/{cost}',               [ApiShipmentController::class, 'updateCost'])->name('costs.update');
    Route::delete('/shipments/{shipment}/costs/{cost}',            [ApiShipmentController::class, 'destroyCost'])->name('costs.destroy');

    // Documents
    Route::post('/shipments/{shipment}/documents',                 [ApiShipmentController::class, 'storeDocument'])->name('documents.store');
    Route::delete('/shipments/{shipment}/documents/{document}',    [ApiShipmentController::class, 'destroyDocument'])->name('documents.destroy');

    // Landing Cost
    Route::get('/shipments/{shipment}/landing-cost',               [ApiShipmentController::class, 'getLandingCost'])->name('landing-cost.get');
    Route::post('/shipments/{shipment}/calculate-landing-cost',    [ApiShipmentController::class, 'calculateAndSaveLandingCost'])->name('landing-cost.calculate');
});
