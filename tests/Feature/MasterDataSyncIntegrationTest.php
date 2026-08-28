<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MasterDataSyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $validToken = 'test_sync_secret_2024';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('master-data.sync_token', $this->validToken);
    }

    private function authHeaders(array $payload = []): array
    {
        $jsonPayload = json_encode($payload);
        $secret = env('WEBHOOK_HMAC_SECRET', 'piyoh_webhook_secure_secret_2026!');
        $signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);

        return [
            'Authorization' => 'Bearer '.$this->validToken,
            'X-Hub-Signature-256' => $signature,
        ];
    }

    // ─── Auth & Webhook Signature Verification Tests ─────────────────────────

    public function test_sync_succeeds_with_valid_token_and_signature(): void
    {
        $payload = [
            'outlets' => [
                ['id' => '1', 'name' => 'Piyoh Galaxy', 'slug' => 'piyoh-galaxy'],
            ],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Master data sync completed.');
    }

    public function test_sync_rejected_if_signature_is_missing(): void
    {
        $payload = [
            'outlets' => [
                ['id' => '1', 'name' => 'Outlet Test', 'slug' => 'outlet-test'],
            ],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, [
            'Authorization' => 'Bearer '.$this->validToken,
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Header X-Hub-Signature-256 missing.']);
    }

    public function test_sync_rejected_if_signature_is_invalid(): void
    {
        $payload = [
            'outlets' => [
                ['id' => '1', 'name' => 'Outlet Test', 'slug' => 'outlet-test'],
            ],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, [
            'Authorization' => 'Bearer '.$this->validToken,
            'X-Hub-Signature-256' => 'sha256=invalid_hmac_signature_hash_12345',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid webhook signature.']);
    }

    public function test_sync_rejected_if_bearer_token_is_missing(): void
    {
        $payload = [
            'outlets' => [
                ['id' => '1', 'name' => 'Outlet Test', 'slug' => 'outlet-test'],
            ],
        ];
        $jsonPayload = json_encode($payload);
        $secret = env('WEBHOOK_HMAC_SECRET', 'piyoh_webhook_secure_secret_2026!');
        $signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);

        $response = $this->postJson(route('api.sync.master_data'), $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing sync token.',
            ]);
    }

    public function test_sync_rejected_if_bearer_token_is_invalid(): void
    {
        $payload = [
            'outlets' => [
                ['id' => '1', 'name' => 'Outlet Test', 'slug' => 'outlet-test'],
            ],
        ];
        $jsonPayload = json_encode($payload);
        $secret = env('WEBHOOK_HMAC_SECRET', 'piyoh_webhook_secure_secret_2026!');
        $signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);

        $response = $this->postJson(route('api.sync.master_data'), $payload, [
            'Authorization' => 'Bearer wrong_token_999',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing sync token.',
            ]);
    }

    // ─── Payload Validation & Error Response (422 Not 500) ────────────────────

    public function test_sync_rejects_incomplete_outlet_data_with_validation_error(): void
    {
        $incompletePayload = [
            'outlets' => [
                ['id' => '1'], // Missing 'name' and 'slug'
            ],
        ];

        $response = $this->postJson(
            route('api.sync.master_data'),
            $incompletePayload,
            $this->authHeaders($incompletePayload)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['outlets.0.name', 'outlets.0.slug']);
    }

    public function test_sync_rejects_invalid_product_price_with_validation_error(): void
    {
        $invalidProductPayload = [
            'products' => [
                [
                    'id' => '100',
                    'name' => 'Invalid Product',
                    'slug' => 'invalid-product',
                    'base_price' => -1000, // Negative price is invalid
                ],
            ],
        ];

        $response = $this->postJson(
            route('api.sync.master_data'),
            $invalidProductPayload,
            $this->authHeaders($invalidProductPayload)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.base_price']);
    }

    public function test_sync_rejects_price_override_missing_relations_with_validation_error(): void
    {
        $invalidPricePayload = [
            'prices' => [
                [
                    'id' => '500',
                    'price' => 25000,
                    // Missing product_id and outlet_id
                ],
            ],
        ];

        $response = $this->postJson(
            route('api.sync.master_data'),
            $invalidPricePayload,
            $this->authHeaders($invalidPricePayload)
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prices.0.product_id', 'prices.0.outlet_id']);
    }

    // ─── Idempotency Tests ───────────────────────────────────────────────────

    public function test_sync_is_idempotent_when_payload_sent_multiple_times(): void
    {
        $payload = [
            'outlets' => [
                ['id' => '10', 'name' => 'Piyoh Galaxy', 'slug' => 'piyoh-galaxy', 'address' => 'Galaxy Street 1', 'phone' => '0812345678', 'is_active' => true],
            ],
            'categories' => [
                ['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee', 'sort_order' => 1],
            ],
            'products' => [
                ['id' => '100', 'name' => 'Kopi Susu Piyoh', 'slug' => 'kopi-susu-piyoh', 'category_id' => '1', 'base_price' => 28000, 'is_active' => true],
            ],
            'prices' => [
                ['id' => '500', 'product_id' => '100', 'outlet_id' => '10', 'price' => 28000, 'is_available' => true],
            ],
        ];

        // 1st transmission
        $response1 = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));
        $response1->assertStatus(200)->assertJsonPath('success', true);

        $this->assertEquals(1, Outlet::where('external_id', '10')->count());
        $this->assertEquals(1, Category::where('external_id', '1')->count());
        $this->assertEquals(1, Product::where('external_id', '100')->count());
        $this->assertEquals(1, ProductPrice::where('external_id', '500')->count());

        // 2nd transmission with exact same payload
        $response2 = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));
        $response2->assertStatus(200)->assertJsonPath('success', true);

        // Database records must remain exactly 1 per model (no duplication)
        $this->assertDatabaseCount('outlets', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_prices', 1);

        $this->assertDatabaseHas('outlets', ['external_id' => '10', 'slug' => 'piyoh-galaxy']);
        $this->assertDatabaseHas('categories', ['external_id' => '1', 'slug' => 'coffee']);
        $this->assertDatabaseHas('products', ['external_id' => '100', 'slug' => 'kopi-susu-piyoh']);
        $this->assertDatabaseHas('product_prices', ['external_id' => '500', 'price' => 28000]);
    }

    // ─── Entity CRUD Sync Tests ──────────────────────────────────────────────

    public function test_sync_creates_new_outlets(): void
    {
        $payload = [
            'outlets' => [
                [
                    'id'        => '10',
                    'name'      => 'Piyoh Galaxy',
                    'slug'      => 'piyoh-galaxy',
                    'address'   => 'Galaxy Mall Lt. 1',
                    'phone'     => '0811234567',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('outlets', [
            'external_id'   => '10',
            'slug'          => 'piyoh-galaxy',
            'source_system' => 'piyohweb',
        ]);
    }

    public function test_sync_updates_existing_outlet(): void
    {
        Outlet::create([
            'external_id'   => '10',
            'name'          => 'Old Name',
            'slug'          => 'old-slug',
            'source_system' => 'piyohweb',
        ]);

        $payload = [
            'outlets' => [[
                'id'   => '10',
                'name' => 'Piyoh Galaxy Updated',
                'slug' => 'piyoh-galaxy-updated',
            ]],
        ];

        $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $this->assertDatabaseHas('outlets', [
            'external_id' => '10',
            'name'        => 'Piyoh Galaxy Updated',
        ]);
        $this->assertDatabaseMissing('outlets', ['name' => 'Old Name']);
    }

    public function test_sync_creates_new_categories(): void
    {
        $payload = [
            'categories' => [
                ['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee', 'sort_order' => 1],
                ['id' => '2', 'name' => 'Food', 'slug' => 'food', 'sort_order' => 2],
            ],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $response->assertStatus(200)
            ->assertJsonPath('results.categories.synced', 2);

        $this->assertDatabaseHas('categories', ['external_id' => '1', 'slug' => 'coffee']);
        $this->assertDatabaseHas('categories', ['external_id' => '2', 'slug' => 'food']);
    }

    public function test_sync_updates_existing_category(): void
    {
        Category::create([
            'external_id'   => '1',
            'name'          => 'Old Coffee',
            'slug'          => 'old-coffee',
            'source_system' => 'piyohweb',
        ]);

        $payload = [
            'categories' => [['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee']],
        ];

        $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $this->assertDatabaseHas('categories', ['external_id' => '1', 'name' => 'Coffee']);
        $this->assertDatabaseMissing('categories', ['name' => 'Old Coffee']);
    }

    public function test_sync_creates_new_products(): void
    {
        Category::create([
            'external_id'   => '1',
            'name'          => 'Coffee',
            'slug'          => 'coffee',
            'source_system' => 'piyohweb',
        ]);

        $payload = [
            'products' => [[
                'id'          => '100',
                'name'        => 'Americano',
                'slug'        => 'americano',
                'category_id' => '1',
                'base_price'  => 30000,
                'sku'         => 'AMRC-001',
                'is_active'   => true,
            ]],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $response->assertStatus(200)
            ->assertJsonPath('results.products.synced', 1);

        $this->assertDatabaseHas('products', [
            'external_id' => '100',
            'slug'        => 'americano',
        ]);

        $product = Product::where('external_id', '100')->first();
        $this->assertNotNull($product->category_id);
    }

    public function test_sync_updates_existing_product(): void
    {
        $category = Category::create([
            'external_id'   => '1',
            'name'          => 'Coffee',
            'slug'          => 'coffee',
            'source_system' => 'piyohweb',
        ]);

        Product::create([
            'external_id'   => '100',
            'name'          => 'Old Americano',
            'slug'          => 'old-americano',
            'base_price'    => 25000,
            'source_system' => 'piyohweb',
            'category_id'   => $category->id,
        ]);

        $payload = [
            'products' => [[
                'id'         => '100',
                'name'       => 'Americano Special',
                'slug'       => 'americano-special',
                'base_price' => 35000,
            ]],
        ];

        $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $this->assertDatabaseHas('products', [
            'external_id' => '100',
            'name'        => 'Americano Special',
            'base_price'  => 35000,
        ]);
    }

    public function test_sync_creates_product_prices(): void
    {
        $outlet = Outlet::create([
            'external_id'   => '10',
            'name'          => 'Piyoh Galaxy',
            'slug'          => 'piyoh-galaxy',
            'source_system' => 'piyohweb',
        ]);

        $category = Category::create([
            'external_id'   => '1',
            'name'          => 'Coffee',
            'slug'          => 'coffee',
            'source_system' => 'piyohweb',
        ]);

        $product = Product::create([
            'external_id'   => '100',
            'name'          => 'Americano',
            'slug'          => 'americano',
            'base_price'    => 30000,
            'source_system' => 'piyohweb',
            'category_id'   => $category->id,
        ]);

        $payload = [
            'prices' => [[
                'id'           => '500',
                'product_id'   => '100',
                'outlet_id'    => '10',
                'price'        => 32000,
                'is_available' => true,
            ]],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $response->assertStatus(200)
            ->assertJsonPath('results.prices.synced', 1);

        $this->assertDatabaseHas('product_prices', [
            'external_id' => '500',
            'product_id'  => $product->id,
            'outlet_id'   => $outlet->id,
            'price'       => 32000,
        ]);
    }

    public function test_price_sync_skips_if_product_not_found(): void
    {
        Outlet::create([
            'external_id'   => '10',
            'name'          => 'Galaxy',
            'slug'          => 'galaxy',
            'source_system' => 'piyohweb',
        ]);

        $payload = [
            'prices' => [[
                'id'         => '999',
                'product_id' => '999',
                'outlet_id'  => '10',
                'price'      => 20000,
            ]],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $response->assertStatus(200)
            ->assertJsonPath('results.prices.skipped', 1);

        $this->assertDatabaseMissing('product_prices', ['external_id' => '999']);
    }

    public function test_full_sync_payload_processes_all_entities(): void
    {
        $payload = [
            'outlets' => [
                ['id' => '10', 'name' => 'Piyoh Galaxy', 'slug' => 'piyoh-galaxy'],
            ],
            'categories' => [
                ['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee'],
            ],
            'products' => [
                ['id' => '100', 'name' => 'Latte', 'slug' => 'latte', 'category_id' => '1', 'base_price' => 28000],
            ],
            'prices' => [
                ['id' => '500', 'product_id' => '100', 'outlet_id' => '10', 'price' => 30000],
            ],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('results.outlets.synced', 1)
            ->assertJsonPath('results.categories.synced', 1)
            ->assertJsonPath('results.products.synced', 1)
            ->assertJsonPath('results.prices.synced', 1);
    }

    public function test_sync_records_operations_in_sync_logs(): void
    {
        $payload = [
            'outlets' => [
                ['id' => '10', 'name' => 'Piyoh Galaxy', 'slug' => 'piyoh-galaxy'],
            ],
            'categories' => [
                ['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee'],
            ],
        ];

        $response = $this->postJson(route('api.sync.master_data'), $payload, $this->authHeaders($payload));
        $response->assertStatus(200);

        $this->assertDatabaseHas('sync_logs', [
            'entity_type' => 'outlet',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('sync_logs', [
            'entity_type' => 'category',
            'status' => 'success',
        ]);
    }

    public function test_sync_automatically_deactivates_stale_categories_and_products_not_in_latest_payload(): void
    {
        // 1st Transmission: Products A (101), B (102), C (103) and Categories 1, 2
        $payload1 = [
            'outlets' => [
                ['id' => '10', 'name' => 'Piyoh Galaxy', 'slug' => 'piyoh-galaxy'],
            ],
            'categories' => [
                ['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee', 'sort_order' => 1],
                ['id' => '2', 'name' => 'Pastry', 'slug' => 'pastry', 'sort_order' => 2],
            ],
            'products' => [
                ['id' => '101', 'name' => 'Product A', 'slug' => 'product-a', 'category_id' => '1', 'base_price' => 20000, 'is_active' => true],
                ['id' => '102', 'name' => 'Product B', 'slug' => 'product-b', 'category_id' => '1', 'base_price' => 25000, 'is_active' => true],
                ['id' => '103', 'name' => 'Product C', 'slug' => 'product-c', 'category_id' => '2', 'base_price' => 30000, 'is_active' => true],
            ],
            'prices' => [
                ['id' => '501', 'product_id' => '101', 'outlet_id' => '10', 'price' => 20000, 'is_available' => true],
                ['id' => '502', 'product_id' => '102', 'outlet_id' => '10', 'price' => 25000, 'is_available' => true],
                ['id' => '503', 'product_id' => '103', 'outlet_id' => '10', 'price' => 30000, 'is_available' => true],
            ],
        ];

        $response1 = $this->postJson(route('api.sync.master_data'), $payload1, $this->authHeaders($payload1));
        $response1->assertStatus(200);

        $this->assertTrue(Product::where('external_id', '101')->first()->is_active);
        $this->assertTrue(Product::where('external_id', '102')->first()->is_active);
        $this->assertTrue(Product::where('external_id', '103')->first()->is_active);
        $this->assertTrue(Category::where('external_id', '1')->first()->is_active);
        $this->assertTrue(Category::where('external_id', '2')->first()->is_active);

        // 2nd Transmission: Only Product A (101) and Category 1 remain in payload
        $payload2 = [
            'outlets' => [
                ['id' => '10', 'name' => 'Piyoh Galaxy', 'slug' => 'piyoh-galaxy'],
            ],
            'categories' => [
                ['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee', 'sort_order' => 1],
            ],
            'products' => [
                ['id' => '101', 'name' => 'Product A Updated', 'slug' => 'product-a', 'category_id' => '1', 'base_price' => 22000, 'is_active' => true],
            ],
            'prices' => [
                ['id' => '501', 'product_id' => '101', 'outlet_id' => '10', 'price' => 22000, 'is_available' => true],
            ],
        ];

        $response2 = $this->postJson(route('api.sync.master_data'), $payload2, $this->authHeaders($payload2));
        $response2->assertStatus(200);

        // Verify Product A is active, while B and C are deactivated
        $productA = Product::where('external_id', '101')->first();
        $productB = Product::where('external_id', '102')->first();
        $productC = Product::where('external_id', '103')->first();

        $this->assertTrue($productA->is_active);
        $this->assertFalse($productB->is_active);
        $this->assertFalse($productC->is_active);

        // Verify Category 1 is active, while Category 2 is deactivated
        $category1 = Category::where('external_id', '1')->first();
        $category2 = Category::where('external_id', '2')->first();

        $this->assertTrue($category1->is_active);
        $this->assertFalse($category2->is_active);

        // Verify prices for stale products are deactivated (is_available = false)
        $this->assertTrue(ProductPrice::where('external_id', '501')->first()->is_available);
        $this->assertFalse(ProductPrice::where('external_id', '502')->first()->is_available);
        $this->assertFalse(ProductPrice::where('external_id', '503')->first()->is_available);

        // Verify NO rows were deleted (foreign keys remain intact)
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('product_prices', 3);
    }
}
