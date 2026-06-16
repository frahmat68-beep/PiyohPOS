<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * System Health Check Endpoint.
     */
    public function check(): JsonResponse
    {
        // 1. Database Check
        $dbConnected = false;
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {}

        // 2. Queue Check (verifies database queue job table connectivity)
        $queueHealthy = false;
        try {
            $queueHealthy = DB::table('jobs')->count() >= 0;
        } catch (\Exception $e) {}

        // 3. Storage Check
        $storageHealthy = false;
        try {
            Storage::disk('local')->put('healthcheck.txt', 'OK');
            $storageHealthy = Storage::disk('local')->get('healthcheck.txt') === 'OK';
            Storage::disk('local')->delete('healthcheck.txt');
        } catch (\Exception $e) {}

        // 4. Cache Check
        $cacheHealthy = false;
        try {
            Cache::put('healthcheck_key', 'OK', 10);
            $cacheHealthy = Cache::get('healthcheck_key') === 'OK';
            Cache::forget('healthcheck_key');
        } catch (\Exception $e) {}

        $healthy = $dbConnected && $queueHealthy && $storageHealthy && $cacheHealthy;
        $status = $healthy ? 'OK' : 'ERROR';
        $code = $healthy ? 200 : 500;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $dbConnected ? 'connected' : 'disconnected',
                'queue' => $queueHealthy ? 'healthy' : 'failed',
                'storage' => $storageHealthy ? 'healthy' : 'failed',
                'cache' => $cacheHealthy ? 'healthy' : 'failed',
            ],
        ], $code);
    }
}
