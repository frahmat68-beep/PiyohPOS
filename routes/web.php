<?php

use App\Http\Controllers\CustomerOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Customer QR Ordering Flow
Route::get('/scan/{token}', [CustomerOrderController::class, 'scan'])->name('qr.scan');

Route::middleware('qr.session')->group(function () {
    Route::get('/menu', [CustomerOrderController::class, 'menu'])->name('qr.menu');
    Route::get('/cart', [CustomerOrderController::class, 'cart'])->name('qr.cart');
    Route::post('/cart/add', [CustomerOrderController::class, 'addToCart'])->name('qr.cart.add');
    Route::post('/checkout', [CustomerOrderController::class, 'checkout'])->name('qr.checkout');
});
