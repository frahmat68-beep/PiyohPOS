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
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $order->status);
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
            'status' => 'pending_payment',
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

    public function test_two_concurrent_scans_on_same_table_shares_active_session()
    {
        // Customer 1 scans table 01
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));
        $customer1SessionCode = session()->get('qr_session_code');

        // Customer 2 scans table 01 concurrently (new device / browser)
        // Controller reuses existing open session so both customers share the table cart
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));
        $customer2SessionCode = session()->get('qr_session_code');

        $this->assertEquals($customer1SessionCode, $customer2SessionCode);

        // Verify database session remains open
        $this->assertDatabaseHas('table_sessions', [
            'session_code' => $customer1SessionCode,
            'status' => 'open',
        ]);
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
    }

    public function test_preset_chips_customization_notes_are_saved_properly_in_cart_and_order(): void
    {
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        $customNotes = 'Level Es: Less Ice, Level Gula: No Sugar — Tolong jangan terlalu manis';

        $response = $this->postJson(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'notes' => $customNotes,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'product_id' => $this->product->id,
                'quantity' => 2,
                'cart_count' => 2,
            ]);

        // Checkout
        $checkoutResponse = $this->postJson(route('qr.checkout'), [
            'customer_name' => 'Budi Santoso',
        ]);

        $checkoutResponse->assertStatus(200);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'notes' => $customNotes,
        ]);
    }

    public function test_cross_sell_additional_item_can_be_added_separately_to_cart(): void
    {
        $additionalCat = Category::create([
            'name' => 'Additional',
            'slug' => 'additional',
            'sort_order' => 11,
        ]);

        $extraIceCream = Product::create([
            'category_id' => $additionalCat->id,
            'name' => 'Ice Cream',
            'slug' => 'ice-cream',
            'description' => 'Single scoop vanilla',
            'base_price' => 5000.00,
            'is_active' => true,
        ]);

        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // 1. Add main drink
        $this->postJson(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'notes' => 'Level Es: Normal, Level Gula: Normal',
        ]);

        // 2. Add cross-sell extra item
        $crossSellResponse = $this->postJson(route('qr.cart.add'), [
            'product_id' => $extraIceCream->id,
            'quantity' => 1,
        ]);

        $crossSellResponse->assertStatus(200)
            ->assertJson([
                'product_id' => $extraIceCream->id,
                'quantity' => 1,
                'cart_count' => 2,
            ]);

        // Verify cart has both items
        $cartResponse = $this->getJson(route('qr.cart'));
        $cartResponse->assertStatus(200);
        $this->assertCount(2, $cartResponse->json('items'));
    }

    public function test_quantity_stepper_increments_decrements_and_removes_at_zero(): void
    {
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // 1. Add initial product — capture the cart_key returned by the server
        $addResponse = $this->postJson(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);
        $addResponse->assertStatus(200);
        $cartKey = $addResponse->json('cart_key');
        $this->assertNotNull($cartKey, 'addToCart must return a cart_key');

        // 2. Increment quantity to 3 via stepper (uses cart_key, not product_id)
        $updateResponse = $this->postJson(route('qr.cart.update'), [
            'cart_key' => $cartKey,
            'quantity' => 3,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'cart_key'   => $cartKey,
                'product_id' => $this->product->id,
                'quantity'   => 3,
                'cart_count' => 3,
            ]);

        // 3. Decrement quantity to 1
        $decResponse = $this->postJson(route('qr.cart.update'), [
            'cart_key' => $cartKey,
            'quantity' => 1,
        ]);

        $decResponse->assertStatus(200)
            ->assertJson([
                'cart_key'   => $cartKey,
                'product_id' => $this->product->id,
                'quantity'   => 1,
                'cart_count' => 1,
            ]);

        // 4. Decrement quantity to 0 -> auto-removes item
        $zeroResponse = $this->postJson(route('qr.cart.update'), [
            'cart_key' => $cartKey,
            'quantity' => 0,
        ]);

        $zeroResponse->assertStatus(200)
            ->assertJson([
                'cart_key'   => $cartKey,
                'product_id' => $this->product->id,
                'quantity'   => 0,
                'cart_count' => 0,
            ]);

        // Verify cart is now completely empty
        $cartResponse = $this->getJson(route('qr.cart'));
        $cartResponse->assertStatus(200);
        $this->assertCount(0, $cartResponse->json('items'));
    }

    /**
     * BAGIAN B1 — Core regression test.
     *
     * The same product ordered with DIFFERENT customizations must create
     * SEPARATE cart line items (not be merged). The same product with the
     * EXACT SAME customization must be merged (quantity incremented).
     */
    public function test_same_product_with_different_customizations_creates_separate_cart_entries(): void
    {
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // 1. Add Taro with Normal Ice, Normal Sugar
        $res1 = $this->postJson(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'notes'      => 'Level Es: Normal, Level Gula: Normal',
        ]);
        $res1->assertStatus(200);
        $cartKey1 = $res1->json('cart_key');
        $this->assertNotNull($cartKey1);
        $this->assertEquals(1, $res1->json('quantity'));
        $this->assertEquals(1, $res1->json('cart_count'));

        // 2. Add SAME product with DIFFERENT customization (Less Ice)
        //    → must create a NEW cart entry, NOT merge with the first
        $res2 = $this->postJson(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'notes'      => 'Level Es: Less Ice, Level Gula: Normal',
        ]);
        $res2->assertStatus(200);
        $cartKey2 = $res2->json('cart_key');

        $this->assertNotNull($cartKey2);
        $this->assertNotEquals($cartKey1, $cartKey2, 'Different customizations must yield different cart_keys');
        $this->assertEquals(1, $res2->json('quantity'),   'The new entry should have qty 1, not 2');
        $this->assertEquals(2, $res2->json('cart_count'), 'Total cart count must be 2 (1 Normal + 1 Less Ice)');

        // Cart page must show 2 distinct line items
        $cartJson = $this->getJson(route('qr.cart'));
        $cartJson->assertStatus(200);
        $this->assertCount(2, $cartJson->json('items'), 'Cart must have 2 separate rows for 2 different customizations');

        // 3. Add the SAME product with the SAME customization as entry 1 (Normal Ice, Normal Sugar)
        //    → must MERGE into entry 1 (same cart_key, quantity incremented)
        $res3 = $this->postJson(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'notes'      => 'Level Es: Normal, Level Gula: Normal',
        ]);
        $res3->assertStatus(200);

        $this->assertEquals($cartKey1, $res3->json('cart_key'), 'Identical customization must reuse the first cart_key');
        $this->assertEquals(2, $res3->json('quantity'),   'Merged entry should now have qty 2');
        $this->assertEquals(3, $res3->json('cart_count'), 'Total cart count must be 3 (2 Normal + 1 Less Ice)');

        // Cart must still show 2 rows (not 3)
        $cartJson2 = $this->getJson(route('qr.cart'));
        $this->assertCount(2, $cartJson2->json('items'), 'Still 2 distinct rows after merge');

        // 4. Checkout must create 2 separate order_item rows
        $checkoutRes = $this->postJson(route('qr.checkout'), [
            'customer_name' => 'B1 Tester',
        ]);
        $checkoutRes->assertStatus(200);

        $order = \App\Models\Order::with('orderItems')->first();
        $this->assertNotNull($order);
        $this->assertCount(2, $order->orderItems, 'Order must contain 2 separate line items for 2 customization variants');
    }
}
