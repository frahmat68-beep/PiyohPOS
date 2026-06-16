<?php

namespace App\Http\Controllers;

use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MasterDataSyncController extends Controller
{
    public function __construct(protected SyncService $syncService) {}

    /**
     * Handle master data sync from PiyohWeb.
     *
     * Accepts a JSON payload with any combination of:
     *  - outlets[]
     *  - categories[]
     *  - products[]
     *  - prices[]
     *
     * All entities are upserted based on their external_id.
     * Processing order: outlets → categories → products → prices.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'outlets'             => ['nullable', 'array'],
            'outlets.*.id'        => ['required_with:outlets', 'string'],
            'outlets.*.name'      => ['required_with:outlets', 'string'],
            'outlets.*.slug'      => ['required_with:outlets', 'string'],
            'outlets.*.address'   => ['nullable', 'string'],
            'outlets.*.phone'     => ['nullable', 'string'],
            'outlets.*.is_active' => ['nullable', 'boolean'],

            'categories'               => ['nullable', 'array'],
            'categories.*.id'          => ['required_with:categories', 'string'],
            'categories.*.name'        => ['required_with:categories', 'string'],
            'categories.*.slug'        => ['required_with:categories', 'string'],
            'categories.*.sort_order'  => ['nullable', 'integer'],

            'products'                  => ['nullable', 'array'],
            'products.*.id'             => ['required_with:products', 'string'],
            'products.*.name'           => ['required_with:products', 'string'],
            'products.*.slug'           => ['required_with:products', 'string'],
            'products.*.category_id'    => ['nullable', 'string'],
            'products.*.description'    => ['nullable', 'string'],
            'products.*.base_price'     => ['required_with:products', 'numeric', 'min:0'],
            'products.*.sku'            => ['nullable', 'string'],
            'products.*.is_active'      => ['nullable', 'boolean'],

            'prices'                  => ['nullable', 'array'],
            'prices.*.id'             => ['required_with:prices', 'string'],
            'prices.*.product_id'     => ['required_with:prices', 'string'],
            'prices.*.outlet_id'      => ['required_with:prices', 'string'],
            'prices.*.price'          => ['required_with:prices', 'numeric', 'min:0'],
            'prices.*.is_available'   => ['nullable', 'boolean'],
        ]);

        try {
            $results = $this->syncService->syncAll($payload);

            Log::info('[MasterDataSync] Sync completed', [
                'ip'      => $request->ip(),
                'results' => $results,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Master data sync completed.',
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MasterDataSync] Sync failed', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
