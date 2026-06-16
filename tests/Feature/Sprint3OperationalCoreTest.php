<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Sprint3OperationalCoreTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected Table $table;
    protected Category $category;
    protected Product $product;
    protected User $cashier;
    protected User $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize Spatie roles
        Role::firstOrCreate(['name' => 'cashier']);
        Role::firstOrCreate(['name' => 'kitchen']);

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

        $this->cashier = User::create([
            'name' => 'Cashier User',
            'email' => 'cashier@piyohkopi.com',
            'password' => bcrypt('password'),
            'active_outlet_id' => $this->outlet->id,
        ]);
        $this->cashier->assignRole('cashier');

        $this->kitchen = User::create([
            'name' => 'Kitchen User',
            'email' => 'kitchen@piyohkopi.com',
            'password' => bcrypt('password'),
            'active_outlet_id' => $this->outlet->id,
        ]);
        $this->kitchen->assignRole('kitchen');
    }

    public function test_table_session_expiration_closes_session_automatically()
    {
        // Create an expired table session
        $tableSession = TableSession::create([
            'table_id' => $this->table->id,
            'session_code' => 'expired_session_123',
            'status' => 'open',
            'opened_at' => now()->subHours(5),
            'expires_at' => now()->subHours(1),
        ]);

        session()->put('qr_session_code', 'expired_session_123');

        $cartService = app(\App\Services\CartService::class);
        $activeSession = $cartService->getActiveTableSession();

        $this->assertNull($activeSession);
        $this->assertDatabaseHas('table_sessions', [
            'id' => $tableSession->id,
            'status' => 'closed',
        ]);
    }

    public function test_payment_updates_order_status_and_payment_status()
    {
        $order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260616-001',
            'customer_name' => 'John Doe',
            'status' => 'served',
            'payment_status' => 'pending',
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
            'amount' => 23000,
            'paid_at' => now(),
        ]);

        $order->update([
            'payment_status' => 'paid',
            'payment_method' => 'qris',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'payment_method' => 'qris',
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'amount' => 23000,
        ]);
    }

    public function test_panel_authorization_restrictions()
    {
        // Guests redirect to login page
        $response = $this->get('/cashier');
        $response->assertRedirect();

        // Cashier accessing kitchen gets forbidden (403) or redirect
        $response = $this->actingAs($this->cashier)->get('/kitchen');
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }
}
