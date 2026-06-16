<?php

use App\Http\Controllers\MasterDataSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->group(function () {
    Route::post('/v1/sync/master-data', MasterDataSyncController::class)->name('api.sync.master_data');
});
