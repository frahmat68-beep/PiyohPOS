<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrentCheckoutStockRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_checkout_when_stock_is_1_only_one_succeeds(): void
    {
        $outlet = Outlet::create([
            'name' => 'Galaxy', 'slug' => 'galaxy', 'is_active' => true,
        ]);

        $table1 = Table::create([
            'outlet_id' => $outlet->id,
            'number' => '01',
            'seating_capacity' => 4,
            'status' => 'occupied',
            'qr_token' => 'TOKEN-T01',
        ]);

        $table2 = Table::create([
            'outlet_id' => $outlet->id,
            'number' => '02',
            'seating_capacity' => 4,
            'status' => 'occupied',
            'qr_token' => 'TOKEN-T02',
        ]);

        $category = Category::create([
            'name' => 'Signature', 'slug' => 'signature', 'is_active' => true,
        ]);

        // Stock is exactly 1
        $limitedProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Limited Nitro Cold Brew',
            'slug' => 'limited-nitro-cold-brew',
            'base_price' => 35000,
            'stock_quantity' => 1,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ]);

        // Customer 1 on Table 1
        $session1 = TableSession::create([
            'table_id' => $table1->id,
            'session_code' => 'SESS-T1',
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // Customer 2 on Table 2
        $session2 = TableSession::create([
            'table_id' => $table2->id,
            'session_code' => 'SESS-T2',
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // Both add to cart
        $cartService = app(CartService::class);
        $orderService = app(OrderService::class);

        // Add to Table 1 cart
        session(['qr_session_code' => 'SESS-T1']);
        $cartService->add($limitedProduct->id, 1, [], null, 'device-cust-1');

        // Add to Table 2 cart
        session(['qr_session_code' => 'SESS-T2']);
        $cartService->add($limitedProduct->id, 1, [], null, 'device-cust-2');

        // Verify both carts currently have the item
        session(['qr_session_code' => 'SESS-T1']);
        $this->assertEquals(1, $cartService->count($session1));

        session(['qr_session_code' => 'SESS-T2']);
        $this->assertEquals(1, $cartService->count($session2));

        // Customer 1 checkouts first and completes payment (or cash checkout)
        session(['qr_session_code' => 'SESS-T1']);
        $order1 = $orderService->checkout($session1, 'Customer 1', 'device-cust-1', false);
        $this->assertNotNull($order1);
        
        // Stock drops to 0 (deducted upon cashier payment / midtrans settlement or out of stock)
        $limitedProduct->update(['stock_quantity' => 0]);

        // Customer 2 attempts checkout now
        session(['qr_session_code' => 'SESS-T2']);
        
        $exceptionCaught = false;
        try {
            $orderService->checkout($session2, 'Customer 2', 'device-cust-2', false);
        } catch (\Exception $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('tidak tersedia', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'Customer 2 checkout must fail with clear out of stock message.');
        
        // Verify Customer 2 cart had the out of stock item removed
        $itemsInCart2 = $cartService->get($session2);
        $this->assertEmpty($itemsInCart2);
    }
}
