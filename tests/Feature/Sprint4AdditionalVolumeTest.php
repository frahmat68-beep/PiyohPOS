<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Sprint4AdditionalVolumeTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected Table $table;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_cannot_cancel_order_after_confirmed_if_passed_preparing()
    {
        $order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260616-101',
            'customer_name' => 'John Doe',
            'status' => Order::STATUS_PENDING,
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        $order->transitionTo(Order::STATUS_CONFIRMED);
        $order->transitionTo(Order::STATUS_PREPARING);

        // Attempting to cancel prepared order fails
        $this->expectException(\Exception::class);
        $order->transitionTo(Order::STATUS_CANCELLED);
    }

    public function test_cancelled_orders_cannot_change_status()
    {
        $order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260616-102',
            'customer_name' => 'John Doe',
            'status' => Order::STATUS_PENDING,
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        $order->transitionTo(Order::STATUS_CANCELLED);

        // Attempting to transition from cancelled fails
        $this->expectException(\Exception::class);
        $order->transitionTo(Order::STATUS_CONFIRMED);
    }

    public function test_completed_orders_cannot_change_status()
    {
        $order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260616-103',
            'customer_name' => 'John Doe',
            'status' => Order::STATUS_PENDING,
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        $order->transitionTo(Order::STATUS_CONFIRMED);
        $order->transitionTo(Order::STATUS_PREPARING);
        $order->transitionTo(Order::STATUS_READY);
        $order->transitionTo(Order::STATUS_SERVED);
        $order->transitionTo(Order::STATUS_COMPLETED);

        // Attempting to change completed order fails
        $this->expectException(\Exception::class);
        $order->transitionTo(Order::STATUS_SERVED);
    }
}
