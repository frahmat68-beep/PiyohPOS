<?php

use App\Http\Controllers\MasterDataSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.token', \App\Http\Middleware\VerifyWebhookSignature::class])->group(function () {
    Route::post('/v1/sync/master-data', MasterDataSyncController::class)->name('api.sync.master_data');
});

Route::get('/health', [\App\Http\Controllers\Api\HealthCheckController::class, 'check'])->name('api.health');
