<?php

use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'home'])->name('website.home');
Route::get('/about', [WebsiteController::class, 'about'])->name('website.about');
Route::get('/contact', [WebsiteController::class, 'contact'])->name('website.contact');
Route::post('/contact', [WebsiteController::class, 'storeContact'])->name('website.contact.store');
Route::get('/products', [WebsiteController::class, 'products'])->name('website.products.index');
Route::get('/pages/{slug}', [WebsiteController::class, 'page'])->name('website.pages.show');
