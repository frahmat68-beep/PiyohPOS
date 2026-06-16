<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Table;
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
    }

    public function test_a_customer_cannot_access_menu_without_scanning_qr()
    {
        $response = $this->get(route('qr.menu'));
        $response->assertStatus(403);
    }

    public function test_scanning_a_valid_qr_token_opens_a_session_and_redirects_to_menu()
    {
        $response = $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        $response->assertRedirect(route('qr.menu'));
        $this->assertTrue(session()->has('qr_session_code'));
        $this->assertEquals($this->table->id, session()->get('qr_table_id'));

        // Assert database session created
        $this->assertDatabaseHas('table_sessions', [
            'table_id' => $this->table->id,
            'status' => 'open',
        ]);
    }

    public function test_a_customer_can_view_menu_with_active_session()
    {
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        $response = $this->get(route('qr.menu'));
        $response->assertStatus(200);
        $response->assertSee('Es Kopi Susu Piyoh');
    }

    public function test_a_customer_can_add_items_to_cart()
    {
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        $response = $this->post(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'notes' => 'Less sugar',
        ]);

        $response->assertRedirect(route('qr.menu'));
        $this->assertTrue(session()->has('qr_cart'));

        $cart = session()->get('qr_cart');
        $this->assertArrayHasKey($this->product->id, $cart);
        $this->assertEquals(2, $cart[$this->product->id]['quantity']);
        $this->assertEquals('Less sugar', $cart[$this->product->id]['notes']);
    }

    public function test_a_customer_can_checkout_and_create_order()
    {
        $this->get(route('qr.scan', ['token' => $this->table->qr_token]));

        // Add to cart
        $this->post(route('qr.cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'notes' => 'Less sugar',
        ]);

        // Place order
        $response = $this->post(route('qr.checkout'), [
            'customer_name' => 'John Doe',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Order Placed Successfully!');

        // Verify Order in DB
        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEquals('John Doe', $order->customer_name);
        $this->assertEquals($this->outlet->id, $order->outlet_id);
        $this->assertEquals($this->table->id, $order->table_id);

        // Assert Order Number Format: GLX-YYYYMMDD-001
        $expectedOrderPrefix = 'GLX-'.now()->format('Ymd').'-001';
        $this->assertEquals($expectedOrderPrefix, $order->order_number);

        // Verify OrderItem in DB
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 20000.00,
            'notes' => 'Less sugar',
        ]);

        // Assert cart is cleared
        $this->assertFalse(session()->has('qr_cart'));
    }
}
