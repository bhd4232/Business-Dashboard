<?php

use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes (Guest only)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated ERP Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // ─── Dashboard ──────────────────────────────────────────
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // ─── Module routes ──────────────────────────────────────
    require __DIR__.'/modules/procurement.php';
    require __DIR__.'/modules/inventory.php';
    require __DIR__.'/modules/shipping.php';
    require __DIR__.'/modules/user.php';
    require __DIR__.'/modules/sales.php';
    // require __DIR__.'/modules/finance.php';
    // require __DIR__.'/modules/chat.php';
    // require __DIR__.'/modules/reports.php';
});
