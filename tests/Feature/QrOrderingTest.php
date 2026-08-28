<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;

    protected Table $table;

    protected Category $category;

    protected Product $product;

    protected Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base data
        $this->outlet = Outlet::create([
            'name' => 'Piyoh Galaxy',
            'slug' => 'piyoh-galaxy',
            'address' => 'Galaxy, Bekasi',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $this->table = Table::create([
            'outlet_id' => $this->outlet->id,
            'number' => '01',
            'seating_capacity' => 4,
            'status' => 'vacant',
            'qr_token' => Str::random(32),
        ]);

        $this->category = Category::create([
            'name' => 'Coffee',
            'slug' => 'coffee',
            'sort_order' => 1,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Es Kopi Susu Piyoh',
            'slug' => 'es-kopi-susu-piyoh',
            'description' => 'Es Kopi Susu Signature',
            'base_price' => 20000.00,
            'sku' => 'KPS-001',
            'is_active' => true,
        ]);

        $this->product2 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Americano',
            'slug' => 'americano',
            'description' => 'Black Coffee',
            'base_price' => 18000.00,
            'sku' => 'AMR-001',
            'is_active' => true,
        ]);
    }

    // ─── Happy Path: Full End-to-End Flow ─────────────────────────────────────

    public function test_full_happy_path_ordering_lifecycle_from_qr_scan_to_kitchen_kds_and_cashier_settlement()
    {
        // 1. Scan QR Token at Table
        $scanResponse = $this->get(route('qr.scan', ['token' => $this->table->qr_token]));
        $scanResponse->assertRedirect(route('qr.menu'));

        $this->assertTrue(session()->has('qr_session_code'));
        $this->assertEquals($this->table->id, session()->get('qr_table_id'));

        $sessionCode = session()->get('qr_session_code');
        $this->assertDatabaseHas('table_sessions', [
            'table_id' => $this->table->id,
            'session_code' => $sessionCode,
            'status' => 'open',
        ]);

        // 2. Browse Menu
        $menuResponse = $this->get(route('qr.menu'));
        $menuResponse->assertStatus(200);
        $menuResponse->assertSee('Es Kopi Susu Piyoh');
        $menuResponse->assertSee('Americano');

        // 3. Add multiple items with notes/variants
        $addResponse1 = $this->post(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'notes' => 'Less sugar, Extra ice',
        ]);
        $addResponse1->assertRedirect(route('qr.menu'));

        $addResponse2 = $this->post(route('qr.cart.add'), [
            'product_id' => $this->product2->id,
            'quantity' => 1,
            'notes' => 'Hot',
        ]);
        $addResponse2->assertRedirect(route('qr.menu'));

        // 4. View Cart
        $cartResponse = $this->get(route('qr.cart'));
        $cartResponse->assertStatus(200);
        $cartResponse->assertSee('Es Kopi Susu Piyoh');
        $cartResponse->assertSee('Americano');

        // 5. Checkout
        $checkoutResponse = $this->post(route('qr.checkout'), [
            'customer_name' => 'Budi Santoso',
        ]);
        $checkoutResponse->assertStatus(200);
        $checkoutResponse->assertSee('Pesanan Berhasil Terkirim');

        // 6. Verify Order in Database & Calculation (Subtotal 58,000 + 10% tax 5,800 + 5% service 2,900 = 66,700)
        $order = Order::with('orderItems')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Budi Santoso', $order->customer_name);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals(5800.00, (float) $order->tax_amount);
        $this->assertEquals(2900.00, (float) $order->service_charge);
        $this->assertEquals(66700.00, (float) $order->total_amount);
        $this->assertCount(2, $order->orderItems);

        // Verify Live Status API endpoint
        $statusResponse = $this->get(route('qr.order.status', ['orderNumber' => $order->order_number]));
        $statusResponse->assertStatus(200);
        $statusResponse->assertJsonFragment([
            'order_number' => $order->order_number,
            'status' => 'pending',
            'progress_step' => 1,
        ]);

        // Assert cart is cleared
        $this->assertFalse(session()->has('qr_cart'));

        // 7. Cashier Panel Action: Cashier Confirms Order
        $order->transitionTo(Order::STATUS_CONFIRMED, 'Confirmed by cashier');
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->confirmed_at);

        // 8. Kitchen Display System (KDS): Visible in Queue, Start Cooking
        $kdsOrders = Order::whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY])->get();
        $this->assertTrue($kdsOrders->contains('id', $order->id));

        $order->transitionTo(Order::STATUS_PREPARING, 'Kitchen started cooking');
        $this->assertEquals(Order::STATUS_PREPARING, $order->fresh()->status);

        // 9. Kitchen Marks Ready
        $order->transitionTo(Order::STATUS_READY, 'Food is ready to serve');
        $this->assertEquals(Order::STATUS_READY, $order->fresh()->status);

        // 10. Waitstaff Serves to Table
        $order->transitionTo(Order::STATUS_SERVED, 'Delivered to table');
        $this->assertEquals(Order::STATUS_SERVED, $order->fresh()->status);

        // 11. Payment Settlement: Cashier Completes Order
        $order->transitionTo(Order::STATUS_COMPLETED, 'Paid via Cash');
        $order->update(['payment_status' => 'paid']);
        $this->assertEquals(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertEquals('paid', $order->fresh()->payment_status);
    }

    // ─── Edge Cases ───────────────────────────────────────────────────────────

    public function test_scanning_invalid_qr_token_returns_404()
    {
        $response = $this->get(route('qr.scan', ['token' => 'non_existent_token_xyz999']));
        $response->assertStatus(404);
    }

    public function test_customer_cannot_access_menu_or_cart_without_active_session()
    {
        $menuResponse = $this->get(route('qr.menu'));
        $menuResponse->assertStatus(403);

        $cartResponse = $this->get(route('qr.cart'));
        $cartResponse->assertStatus(403);
    }

    public function test_expired_table_session_is_closed_and_blocks_further_actions()
    {
        // Scan QR
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));
        $sessionCode = session()->get('qr_session_code');

        // Artificially expire the table session
        $tableSession = TableSession::where('session_code', $sessionCode)->first();
        $tableSession->update([
            'expires_at' => now()->subHours(5),
        ]);

        // Attempting to access cart or checkout should now be blocked
        $response = $this->get(route('qr.menu'));
        $response->assertStatus(403);
    }

    public function test_two_concurrent_scans_on_same_table_invalidates_first_session()
    {
        // Customer 1 scans table 01
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));
        $customer1SessionCode = session()->get('qr_session_code');

        // Customer 2 scans table 01 concurrently (new device / browser)
        // Controller closes previous sessions on scan
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));
        $customer2SessionCode = session()->get('qr_session_code');

        $this->assertNotEquals($customer1SessionCode, $customer2SessionCode);

        // Verify Customer 1's database session is now closed
        $this->assertDatabaseHas('table_sessions', [
            'session_code' => $customer1SessionCode,
            'status' => 'closed',
        ]);

        // Verify Customer 2's session is open
        $this->assertDatabaseHas('table_sessions', [
            'session_code' => $customer2SessionCode,
            'status' => 'open',
        ]);

        // Simulate Customer 1 trying to make a request with their old closed session
        session(['qr_session_code' => $customer1SessionCode]);
        $response = $this->get(route('qr.menu'));
        $response->assertStatus(403);

        // Customer 2 can still browse
        session(['qr_session_code' => $customer2SessionCode]);
        $response2 = $this->get(route('qr.menu'));
        $response2->assertStatus(200);
    }

    public function test_customer_cannot_checkout_with_an_empty_cart()
    {
        // Scan QR
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // Attempt checkout immediately with empty cart
        $response = $this->post(route('qr.checkout'), [
            'customer_name' => 'Empty Cart User',
        ]);

        // Should redirect back with error message and create 0 orders
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Cart is empty.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customer_checkout_json_returns_400_when_cart_is_empty()
    {
        // Scan QR
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // Attempt JSON checkout with empty cart
        $response = $this->postJson(route('qr.checkout'), [
            'customer_name' => 'Empty Cart User',
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Cart is empty.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_outlet_specific_pricing_override_is_accurately_applied_in_cart_and_order()
    {
        // Create an outlet price override: Americano base price 18,000 -> Galaxy price 22,000
        ProductPrice::create([
            'product_id' => $this->product2->id,
            'outlet_id' => $this->outlet->id,
            'price' => 22000.00,
            'is_available' => true,
        ]);

        // Scan QR
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // Add to cart
        $this->post(route('qr.cart.add'), [
            'product_id' => $this->product2->id,
            'quantity' => 1,
        ]);

        // Checkout
        $this->post(route('qr.checkout'), ['customer_name' => 'Override Test']);

        // Assert OrderItem uses 22,000, not base_price 18,000
        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product2->id,
            'price' => 22000.00,
            'quantity' => 1,
        ]);
    }

    public function test_checkout_automatically_removes_unavailable_items_and_notifies_customer()
    {
        // Scan QR
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // Add 2 products to cart
        $this->post(route('qr.cart.add'), ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->post(route('qr.cart.add'), ['product_id' => $this->product2->id, 'quantity' => 2]);

        // Deactivate product 1 in DB mid-session (e.g. barista marks sold out)
        $this->product->update(['is_active' => false]);

        // Customer checkouts
        $response = $this->post(route('qr.checkout'), ['customer_name' => 'Partial Stock User']);

        $response->assertStatus(200);
        $response->assertSee('Item berikut dihapus dari pesananmu karena sedang habis: Es Kopi Susu Piyoh');

        // Verify that the created order ONLY contains Product 2
        $order = Order::with('orderItems')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->orderItems);
        $this->assertEquals($this->product2->id, $order->orderItems->first()->product_id);
        $this->assertEquals(2, $order->orderItems->first()->quantity);
    }

    public function test_checkout_fails_and_redirects_to_cart_when_all_items_in_cart_become_unavailable()
    {
        // Scan QR
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // Add 1 product to cart
        $this->post(route('qr.cart.add'), ['product_id' => $this->product->id, 'quantity' => 1]);

        // Deactivate product in DB
        $this->product->update(['is_active' => false]);

        // Customer attempts checkout
        $response = $this->post(route('qr.checkout'), ['customer_name' => 'All Stock Out User']);

        // Should redirect back to cart with error message and create 0 orders
        $response->assertRedirect(route('qr.cart'));
        $response->assertSessionHas('error', 'Semua item di keranjangmu sedang tidak tersedia saat ini.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cannot_add_product_with_null_or_zero_base_price_to_cart(): void
    {
        $nullPriceProduct = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Import Beans',
            'slug' => 'import-beans',
            'description' => 'Single origin import',
            'base_price' => 0,
            'is_active' => true,
        ]);

        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // 1. Web/Form request
        $webResponse = $this->post(route('qr.cart.add'), [
            'product_id' => $nullPriceProduct->id,
            'quantity' => 1,
        ]);

        $webResponse->assertSessionHas('error', 'Item ini harus dipesan langsung ke kasir, silakan hubungi staff kami.');

        // 2. JSON request
        $jsonResponse = $this->postJson(route('qr.cart.add'), [
            'product_id' => $nullPriceProduct->id,
            'quantity' => 1,
        ]);

        $jsonResponse->assertStatus(422)
            ->assertJson(['error' => 'Item ini harus dipesan langsung ke kasir, silakan hubungi staff kami.']);

        // Assert nothing added to cart
        $this->assertEquals([], session('qr_cart', []));
    }

    public function test_menu_page_renders_tanya_barista_for_null_or_zero_price_items(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Import Beans',
            'slug' => 'import-beans',
            'description' => 'Single origin import',
            'base_price' => 0,
            'is_active' => true,
        ]);

        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        $response = $this->get(route('qr.menu'));
        $response->assertStatus(200);
        $response->assertSee('Tanya Barista');
        $response->assertSee('Pesan langsung ke kasir/barista');
    }
}
