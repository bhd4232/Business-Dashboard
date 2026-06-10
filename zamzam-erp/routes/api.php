<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mobile App (React Native) + External integrations
|--------------------------------------------------------------------------
| All routes are prefixed with /api/v1
| Authentication: Laravel Sanctum (Bearer token)
*/

Route::prefix('v1')->group(function () {

    // ─── Public API ──────────────────────────────────────
    Route::post('/auth/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);

    // ─── Protected API ───────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        Route::get('/auth/me',     [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);

        // Future: products, orders, customers API endpoints
    });
});
