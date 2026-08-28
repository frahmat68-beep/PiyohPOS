<?php

use App\Http\Controllers\MasterDataSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.token', \App\Http\Middleware\VerifyWebhookSignature::class, 'throttle:60,1'])->group(function () {
    Route::post('/v1/sync/master-data', MasterDataSyncController::class)->name('api.sync.master_data');
});

Route::get('/health', [\App\Http\Controllers\Api\HealthCheckController::class, 'check'])->middleware('throttle:60,1')->name('api.health');

// Midtrans Payment Webhook Notification
Route::post('/midtrans/notification', [\App\Http\Controllers\MidtransWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('api.midtrans.notification');

