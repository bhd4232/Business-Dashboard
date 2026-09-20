<?php

use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Middleware\ResolveCompanyFromApiKey;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External Website Integration API (v1)
|--------------------------------------------------------------------------
|
| Server-to-server routes for an external company website (e.g. Tasneem
| Knitting Industry's) to sync products/stock, submit orders and inquiries,
| and read order status. Auth is a per-company Bearer API key (see
| CompanyApiKey + ResolveCompanyFromApiKey), which also sets CompanyContext
| for the rest of the request — every route below relies on that.
|
| See docs/api/website-integration.md and
| docs/api/website-integration.openapi.yaml for the external-facing contract.
*/

Route::prefix('v1')
    ->middleware(['throttle:120,1', ResolveCompanyFromApiKey::class])
    ->group(function (): void {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{sku}', [ProductController::class, 'show']);
        Route::patch('products/{sku}', [ProductController::class, 'update']);

        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order_number}', [OrderController::class, 'show']);

        Route::post('leads', [LeadController::class, 'store']);
    });
