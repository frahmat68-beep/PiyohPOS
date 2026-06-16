<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncService
{
    protected string $sourceSystem = 'piyohweb';

    /**
     * Sync outlets from PiyohWeb payload.
     * Upserts by external_id or slug.
     *
     * @param  array<array{id: int, name: string, slug: string, address?: string|null, phone?: string|null, is_active?: bool}>  $outlets
     * @return array{synced: int, skipped: int, errors: array}
     */
    public function syncOutlets(array $outlets): array
    {
        $synced = 0;
        $skipped = 0;
        $errors = [];

        foreach ($outlets as $data) {
            try {
                $externalId = (string) ($data['id'] ?? '');
                if (empty($externalId) || empty($data['slug'])) {
                    $skipped++;
                    continue;
                }

                Outlet::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'source_system'  => $this->sourceSystem,
                        'name'           => $data['name'],
                        'slug'           => $data['slug'],
                        'address'        => $data['address'] ?? null,
                        'phone'          => $data['phone'] ?? null,
                        'is_active'      => $data['is_active'] ?? true,
                        'last_synced_at' => now(),
                    ]
                );
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = ['item' => $data, 'error' => $e->getMessage()];
                Log::error('[SyncService] Outlet sync error', ['data' => $data, 'error' => $e->getMessage()]);
            }
        }

        Log::info('[SyncService] Outlets synced', compact('synced', 'skipped', 'errors'));

        return compact('synced', 'skipped', 'errors');
    }

    /**
     * Sync categories from PiyohWeb payload.
     *
     * @param  array<array{id: int, name: string, slug: string, sort_order?: int}>  $categories
     * @return array{synced: int, skipped: int, errors: array}
     */
    public function syncCategories(array $categories): array
    {
        $synced = 0;
        $skipped = 0;
        $errors = [];

        foreach ($categories as $data) {
            try {
                $externalId = (string) ($data['id'] ?? '');
                if (empty($externalId) || empty($data['slug'])) {
                    $skipped++;
                    continue;
                }

                Category::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'source_system'  => $this->sourceSystem,
                        'name'           => $data['name'],
                        'slug'           => $data['slug'],
                        'sort_order'     => $data['sort_order'] ?? 0,
                        'last_synced_at' => now(),
                    ]
                );
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = ['item' => $data, 'error' => $e->getMessage()];
                Log::error('[SyncService] Category sync error', ['data' => $data, 'error' => $e->getMessage()]);
            }
        }

        Log::info('[SyncService] Categories synced', compact('synced', 'skipped', 'errors'));

        return compact('synced', 'skipped', 'errors');
    }

    /**
     * Sync products from PiyohWeb payload.
     * Requires categories to be synced first.
     *
     * @param  array<array{id: int, name: string, slug: string, category_id?: int|null, description?: string|null, base_price: numeric, sku?: string|null, is_active?: bool}>  $products
     * @return array{synced: int, skipped: int, errors: array}
     */
    public function syncProducts(array $products): array
    {
        $synced = 0;
        $skipped = 0;
        $errors = [];

        foreach ($products as $data) {
            try {
                $externalId = (string) ($data['id'] ?? '');
                if (empty($externalId) || empty($data['slug'])) {
                    $skipped++;
                    continue;
                }

                // Resolve local category_id from external_id if provided
                $localCategoryId = null;
                $hasCategoryPayload = array_key_exists('category_id', $data) && ! empty($data['category_id']);
                if ($hasCategoryPayload) {
                    $cat = Category::where('external_id', (string) $data['category_id'])->first();
                    $localCategoryId = $cat?->id;
                }

                $attributes = [
                    'source_system'  => $this->sourceSystem,
                    'name'           => $data['name'],
                    'slug'           => $data['slug'],
                    'description'    => $data['description'] ?? null,
                    'base_price'     => $data['base_price'],
                    'sku'            => $data['sku'] ?? null,
                    'is_active'      => $data['is_active'] ?? true,
                    'last_synced_at' => now(),
                ];

                // Only set category_id when explicitly provided in payload
                if ($hasCategoryPayload) {
                    $attributes['category_id'] = $localCategoryId;
                }

                Product::updateOrCreate(
                    ['external_id' => $externalId],
                    $attributes
                );
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = ['item' => $data, 'error' => $e->getMessage()];
                Log::error('[SyncService] Product sync error', ['data' => $data, 'error' => $e->getMessage()]);
            }
        }

        Log::info('[SyncService] Products synced', compact('synced', 'skipped', 'errors'));

        return compact('synced', 'skipped', 'errors');
    }

    /**
     * Sync product prices from PiyohWeb payload.
     * Requires products and outlets to be synced first.
     *
     * @param  array<array{id: int, product_id: int, outlet_id: int, price: numeric, is_available?: bool}>  $prices
     * @return array{synced: int, skipped: int, errors: array}
     */
    public function syncPrices(array $prices): array
    {
        $synced = 0;
        $skipped = 0;
        $errors = [];

        foreach ($prices as $data) {
            try {
                $externalId = (string) ($data['id'] ?? '');
                if (empty($externalId)) {
                    $skipped++;
                    continue;
                }

                // Resolve local product_id and outlet_id from external_ids
                $product = Product::where('external_id', (string) ($data['product_id'] ?? ''))->first();
                $outlet  = Outlet::where('external_id', (string) ($data['outlet_id'] ?? ''))->first();

                if (! $product || ! $outlet) {
                    $skipped++;
                    Log::warning('[SyncService] Price skipped — missing product or outlet', ['data' => $data]);
                    continue;
                }

                ProductPrice::updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'source_system'  => $this->sourceSystem,
                        'product_id'     => $product->id,
                        'outlet_id'      => $outlet->id,
                        'price'          => $data['price'],
                        'is_available'   => $data['is_available'] ?? true,
                        'last_synced_at' => now(),
                    ]
                );
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = ['item' => $data, 'error' => $e->getMessage()];
                Log::error('[SyncService] Price sync error', ['data' => $data, 'error' => $e->getMessage()]);
            }
        }

        Log::info('[SyncService] Prices synced', compact('synced', 'skipped', 'errors'));

        return compact('synced', 'skipped', 'errors');
    }

    /**
     * Run a full sync from a master data payload.
     * Order: outlets → categories → products → prices.
     *
     * @param  array{outlets?: array, categories?: array, products?: array, prices?: array}  $payload
     * @return array{outlets: array, categories: array, products: array, prices: array}
     */
    public function syncAll(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $results = [];

            if (! empty($payload['outlets'])) {
                try {
                    $results['outlets'] = $this->syncOutlets($payload['outlets']);
                    \App\Models\SyncLog::create([
                        'entity_type' => 'outlet',
                        'status' => empty($results['outlets']['errors']) ? 'success' : 'failed',
                        'payload' => $payload['outlets'],
                        'error_message' => empty($results['outlets']['errors']) ? null : json_encode($results['outlets']['errors']),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $ex) {
                    \App\Models\SyncLog::create([
                        'entity_type' => 'outlet',
                        'status' => 'failed',
                        'payload' => $payload['outlets'],
                        'error_message' => $ex->getMessage(),
                        'created_at' => now(),
                    ]);
                }
            }

            if (! empty($payload['categories'])) {
                try {
                    $results['categories'] = $this->syncCategories($payload['categories']);
                    \App\Models\SyncLog::create([
                        'entity_type' => 'category',
                        'status' => empty($results['categories']['errors']) ? 'success' : 'failed',
                        'payload' => $payload['categories'],
                        'error_message' => empty($results['categories']['errors']) ? null : json_encode($results['categories']['errors']),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $ex) {
                    \App\Models\SyncLog::create([
                        'entity_type' => 'category',
                        'status' => 'failed',
                        'payload' => $payload['categories'],
                        'error_message' => $ex->getMessage(),
                        'created_at' => now(),
                    ]);
                }
            }

            if (! empty($payload['products'])) {
                try {
                    $results['products'] = $this->syncProducts($payload['products']);
                    \App\Models\SyncLog::create([
                        'entity_type' => 'product',
                        'status' => empty($results['products']['errors']) ? 'success' : 'failed',
                        'payload' => $payload['products'],
                        'error_message' => empty($results['products']['errors']) ? null : json_encode($results['products']['errors']),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $ex) {
                    \App\Models\SyncLog::create([
                        'entity_type' => 'product',
                        'status' => 'failed',
                        'payload' => $payload['products'],
                        'error_message' => $ex->getMessage(),
                        'created_at' => now(),
                    ]);
                }
            }

            if (! empty($payload['prices'])) {
                try {
                    $results['prices'] = $this->syncPrices($payload['prices']);
                    \App\Models\SyncLog::create([
                        'entity_type' => 'price',
                        'status' => empty($results['prices']['errors']) ? 'success' : 'failed',
                        'payload' => $payload['prices'],
                        'error_message' => empty($results['prices']['errors']) ? null : json_encode($results['prices']['errors']),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $ex) {
                    \App\Models\SyncLog::create([
                        'entity_type' => 'price',
                        'status' => 'failed',
                        'payload' => $payload['prices'],
                        'error_message' => $ex->getMessage(),
                        'created_at' => now(),
                    ]);
                }
            }

            return $results;
        });
    }
}
