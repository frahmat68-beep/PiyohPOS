<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\Services\DailyRecapService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierOperationsAndRecapTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected Table $table;
    protected Product $product;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create([
            'name' => 'Piyoh Galaxy',
            'slug' => 'galaxy',
            'is_active' => true,
        ]);

        $this->table = Table::create([
            'outlet_id' => $this->outlet->id,
            'number' => '02',
            'qr_token' => 'TABLE-02-TOKEN',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Signature',
            'slug' => 'signature',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Piyoh Aren Latte',
            'slug' => 'piyoh-aren-latte',
            'base_price' => 25000,
            'stock_quantity' => 2,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'name' => 'Budi Kasir',
            'email' => 'cashier@piyohkopi.com',
        ]);
    }

    public function test_low_stock_helpers_and_zero_stock_availability(): void
    {
        // Stock = 2, threshold = 5 -> isLowStock() is true
        $this->assertTrue($this->product->isLowStock());
        $this->assertFalse($this->product->isOutOfStock());
        $this->assertTrue($this->product->isAvailableForOrdering());

        // Stock = 0 -> isOutOfStock() is true, isAvailableForOrdering() is false
        $this->product->update(['stock_quantity' => 0]);
        $this->assertTrue($this->product->isOutOfStock());
        $this->assertFalse($this->product->isAvailableForOrdering());
    }

    public function test_daily_recap_service_computes_accurate_aggregations_and_csv(): void
    {
        $today = Carbon::today();

        // Create 2 paid orders
        $order1 = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-REC-001',
            'customer_name' => 'Customer A',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'payment_method' => 'midtrans_qris',
            'midtrans_payment_type' => 'qris',
            'tax_amount' => 5000,
            'service_charge' => 2500,
            'total_amount' => 57500,
            'created_at' => $today->copy()->setHour(10),
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 25000,
            'created_at' => $today->copy()->setHour(10),
        ]);

        $order2 = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-REC-002',
            'customer_name' => 'Customer B',
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'tax_amount' => 2500,
            'service_charge' => 1250,
            'total_amount' => 28750,
            'created_at' => $today->copy()->setHour(14),
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 25000,
            'created_at' => $today->copy()->setHour(14),
        ]);

        $service = app(DailyRecapService::class);
        $recap = $service->compute($this->outlet->id, $today);

        // Assertions
        $this->assertEquals(2, $recap['total_orders']);
        $this->assertEquals(86250.0, $recap['total_revenue']);
        $this->assertEquals(28750.0, $recap['cash_revenue']);
        $this->assertEquals(57500.0, $recap['midtrans_revenue']);
        $this->assertEquals(57500.0, $recap['qris_revenue']);
        $this->assertEquals(7500.0, $recap['tax_total']);
        $this->assertEquals(3750.0, $recap['service_charge_total']);

        // Assert items summary
        $this->assertCount(1, $recap['items_summary']);
        $this->assertEquals(3, $recap['items_summary'][0]['quantity']);
        $this->assertEquals(75000.0, $recap['items_summary'][0]['total_sales']);

        // Assert CSV export
        $csv = $service->exportCsv($this->outlet->id, $today);
        $this->assertStringContainsString('GLX-REC-001', $csv);
        $this->assertStringContainsString('GLX-REC-002', $csv);
        $this->assertStringContainsString('Piyoh Aren Latte', $csv);
        $this->assertStringContainsString('MIDTRANS_QRIS', $csv);
        $this->assertStringContainsString('CASH', $csv);
    }
}
