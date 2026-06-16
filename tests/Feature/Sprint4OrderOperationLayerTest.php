<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Sprint4OrderOperationLayerTest extends TestCase
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

    public function test_valid_order_status_pipeline_transitions()
    {
        $order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260616-002',
            'customer_name' => 'John Doe',
            'status' => Order::STATUS_PENDING,
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        // pending -> confirmed
        $order->transitionTo(Order::STATUS_CONFIRMED, 'Confirmed by cashier');
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertNotNull($order->confirmed_at);

        // confirmed -> preparing
        $order->transitionTo(Order::STATUS_PREPARING, 'Cooking started');
        $this->assertEquals(Order::STATUS_PREPARING, $order->status);
        $this->assertNotNull($order->preparing_at);

        // preparing -> ready
        $order->transitionTo(Order::STATUS_READY, 'Food ready');
        $this->assertEquals(Order::STATUS_READY, $order->status);
        $this->assertNotNull($order->ready_at);

        // ready -> served
        $order->transitionTo(Order::STATUS_SERVED, 'Served to customer');
        $this->assertEquals(Order::STATUS_SERVED, $order->status);
        $this->assertNotNull($order->served_at);

        // served -> completed
        $order->transitionTo(Order::STATUS_COMPLETED, 'Order paid and completed');
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_order_status_pipeline_rejects_skipping_status()
    {
        $order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260616-003',
            'customer_name' => 'John Doe',
            'status' => Order::STATUS_PENDING,
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        // Attempting to skip to preparing directly should fail
        $this->expectException(\Exception::class);
        $order->transitionTo(Order::STATUS_PREPARING);
    }

    public function test_order_status_pipeline_rejects_backward_transitions()
    {
        $order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260616-004',
            'customer_name' => 'John Doe',
            'status' => Order::STATUS_CONFIRMED,
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        // Attempting to go backward to pending should fail
        $this->expectException(\Exception::class);
        $order->transitionTo(Order::STATUS_PENDING);
    }

    public function test_timeline_records_automatically_created()
    {
        $order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260616-005',
            'customer_name' => 'John Doe',
            'status' => Order::STATUS_PENDING,
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        // Initial pending timeline created via Observer
        $this->assertDatabaseHas('order_timelines', [
            'order_id' => $order->id,
            'status' => Order::STATUS_PENDING,
        ]);

        // Advance and check
        $order->transitionTo(Order::STATUS_CONFIRMED, 'Confirmed notes');
        $this->assertDatabaseHas('order_timelines', [
            'order_id' => $order->id,
            'status' => Order::STATUS_CONFIRMED,
            'notes' => 'Confirmed notes',
        ]);
    }
}
