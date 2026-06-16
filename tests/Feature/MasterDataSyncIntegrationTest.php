<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductPrice;
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

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->validToken];
    }

    // ─── Auth Tests ──────────────────────────────────────────────────────────

    public function test_invalid_token_is_rejected(): void
    {
        $response = $this->postJson(
            route('api.sync.master_data'),
            [],
            ['Authorization' => 'Bearer wrong_token']
        );
        $response->assertStatus(401);
    }

    public function test_missing_token_is_rejected(): void
    {
        $response = $this->postJson(route('api.sync.master_data'));
        $response->assertStatus(401);
    }

    // ─── Outlets ─────────────────────────────────────────────────────────────

    public function test_sync_creates_new_outlets(): void
    {
        $response = $this->postJson(route('api.sync.master_data'), [
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
        ], $this->authHeaders());

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

        $this->postJson(route('api.sync.master_data'), [
            'outlets' => [[
                'id'   => '10',
                'name' => 'Piyoh Galaxy Updated',
                'slug' => 'piyoh-galaxy-updated',
            ]],
        ], $this->authHeaders());

        $this->assertDatabaseHas('outlets', [
            'external_id' => '10',
            'name'        => 'Piyoh Galaxy Updated',
        ]);
        $this->assertDatabaseMissing('outlets', ['name' => 'Old Name']);
    }

    // ─── Categories ──────────────────────────────────────────────────────────

    public function test_sync_creates_new_categories(): void
    {
        $response = $this->postJson(route('api.sync.master_data'), [
            'categories' => [
                ['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee', 'sort_order' => 1],
                ['id' => '2', 'name' => 'Food', 'slug' => 'food', 'sort_order' => 2],
            ],
        ], $this->authHeaders());

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

        $this->postJson(route('api.sync.master_data'), [
            'categories' => [['id' => '1', 'name' => 'Coffee', 'slug' => 'coffee']],
        ], $this->authHeaders());

        $this->assertDatabaseHas('categories', ['external_id' => '1', 'name' => 'Coffee']);
        $this->assertDatabaseMissing('categories', ['name' => 'Old Coffee']);
    }

    // ─── Products ────────────────────────────────────────────────────────────

    public function test_sync_creates_new_products(): void
    {
        // Pre-sync category so product can link to it
        Category::create([
            'external_id'   => '1',
            'name'          => 'Coffee',
            'slug'          => 'coffee',
            'source_system' => 'piyohweb',
        ]);

        $response = $this->postJson(route('api.sync.master_data'), [
            'products' => [[
                'id'          => '100',
                'name'        => 'Americano',
                'slug'        => 'americano',
                'category_id' => '1',
                'base_price'  => 30000,
                'sku'         => 'AMRC-001',
                'is_active'   => true,
            ]],
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('results.products.synced', 1);

        $this->assertDatabaseHas('products', [
            'external_id' => '100',
            'slug'        => 'americano',
        ]);

        // Assert category was resolved correctly
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

        $this->postJson(route('api.sync.master_data'), [
            'products' => [[
                'id'         => '100',
                'name'       => 'Americano Special',
                'slug'       => 'americano-special',
                'base_price' => 35000,
            ]],
        ], $this->authHeaders());

        $this->assertDatabaseHas('products', [
            'external_id' => '100',
            'name'        => 'Americano Special',
            'base_price'  => 35000,
        ]);
    }

    // ─── Prices ──────────────────────────────────────────────────────────────

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

        $response = $this->postJson(route('api.sync.master_data'), [
            'prices' => [[
                'id'           => '500',
                'product_id'   => '100',
                'outlet_id'    => '10',
                'price'        => 32000,
                'is_available' => true,
            ]],
        ], $this->authHeaders());

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

        $response = $this->postJson(route('api.sync.master_data'), [
            'prices' => [[
                'id'         => '999',
                'product_id' => '999',  // Non-existent external_id
                'outlet_id'  => '10',
                'price'      => 20000,
            ]],
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('results.prices.skipped', 1);

        $this->assertDatabaseMissing('product_prices', ['external_id' => '999']);
    }

    // ─── Full Payload ────────────────────────────────────────────────────────

    public function test_full_sync_payload_processes_all_entities(): void
    {
        $response = $this->postJson(route('api.sync.master_data'), [
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
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('results.outlets.synced', 1)
            ->assertJsonPath('results.categories.synced', 1)
            ->assertJsonPath('results.products.synced', 1)
            ->assertJsonPath('results.prices.synced', 1);
    }
}
