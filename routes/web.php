<?php

use App\Http\Controllers\CustomerOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Customer QR Ordering Flow
Route::get('/scan/{token}', [CustomerOrderController::class, 'scan'])->middleware('throttle:30,1')->name('qr.scan');

Route::middleware('qr.session')->group(function () {
    Route::get('/qr/menu', [CustomerOrderController::class, 'menu'])->name('qr.menu');
    Route::get('/menu', [CustomerOrderController::class, 'menu'])->name('qr.menu.legacy');
    Route::get('/cart', [CustomerOrderController::class, 'cart'])->name('qr.cart');
    Route::post('/cart/add', [CustomerOrderController::class, 'addToCart'])->name('qr.cart.add');
    Route::post('/checkout', [CustomerOrderController::class, 'checkout'])->middleware('throttle:15,1')->name('qr.checkout');
});

// Live Order Status Tracking (Public with Order Number)
Route::get('/orders/{orderNumber}/status', [CustomerOrderController::class, 'orderStatus'])->name('qr.order.status');
