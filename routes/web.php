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
    Route::get('/cart/sync', [CustomerOrderController::class, 'sync'])->name('qr.cart.sync');
    Route::post('/cart/add', [CustomerOrderController::class, 'addToCart'])->middleware('throttle:60,1')->name('qr.cart.add');
    Route::post('/cart/update', [CustomerOrderController::class, 'updateCart'])->middleware('throttle:60,1')->name('qr.cart.update');
    Route::post('/cart/remove', [CustomerOrderController::class, 'removeFromCart'])->middleware('throttle:60,1')->name('qr.cart.remove');
    Route::post('/cart/unlock', [CustomerOrderController::class, 'unlockCart'])->middleware('throttle:60,1')->name('qr.cart.unlock');
    Route::post('/checkout', [CustomerOrderController::class, 'checkout'])->middleware('throttle:15,1')->name('qr.checkout');
});

// Live Order Status Tracking (Secure with Order Number & Tracking Token)
Route::get('/orders/{orderNumber}/tracking/{trackingToken}', [CustomerOrderController::class, 'orderTracking'])->name('qr.order.tracking');
Route::get('/orders/{orderNumber}/status/{trackingToken?}', [CustomerOrderController::class, 'orderStatus'])->name('qr.order.status');
