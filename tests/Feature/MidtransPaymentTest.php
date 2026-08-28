<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected Table $table;
    protected Product $product;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['midtrans.server_key' => 'TEST-SERVER-KEY']);

        $this->outlet = Outlet::create([
            'name' => 'Piyoh Galaxy',
            'slug' => 'galaxy',
            'is_active' => true,
        ]);

        $this->table = Table::create([
            'outlet_id' => $this->outlet->id,
            'number' => '01',
            'qr_token' => 'TABLE-01-TOKEN',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Coffee',
            'slug' => 'coffee',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kopi Susu Piyoh',
            'slug' => 'kopi-susu-piyoh',
            'base_price' => 20000,
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'outlet_id' => $this->outlet->id,
            'table_id' => $this->table->id,
            'order_number' => 'GLX-20260829-001',
            'customer_name' => 'Kiki Test',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => 'pending',
            'tax_amount' => 2000,
            'service_charge' => 1000,
            'total_amount' => 23000,
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 20000,
        ]);
    }

    public function test_midtrans_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'order_id' => $this->order->order_number,
            'status_code' => '200',
            'gross_amount' => '23000.00',
            'signature_key' => 'FAKE-SIGNATURE-KEY-12345',
            'transaction_status' => 'settlement',
        ];

        $response = $this->postJson('/api/midtrans/notification', $payload);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Invalid Midtrans signature.');
    }

    public function test_midtrans_webhook_settlement_confirms_order_and_deducts_stock(): void
    {
        $serverKey = config('midtrans.server_key');
        $grossAmount = '23000.00';
        $statusCode = '200';
        $validSignature = hash('sha512', $this->order->order_number . $statusCode . $grossAmount . $serverKey);

        $payload = [
            'order_id' => $this->order->order_number,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $validSignature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'TRX-MIDTRANS-999',
        ];

        $response = $this->postJson('/api/midtrans/notification', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // Verify order is confirmed and marked paid
        $this->order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $this->order->status);
        $this->assertEquals('paid', $this->order->payment_status);
        $this->assertEquals('midtrans_qris', $this->order->payment_method);
        $this->assertEquals('qris', $this->order->midtrans_payment_type);

        // Verify payment record exists
        $payment = Payment::where('order_id', $this->order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('paid', $payment->payment_status);
        $this->assertEquals(23000, (int)$payment->amount);

        // Verify atomic stock deduction (10 - 1 = 9)
        $this->product->refresh();
        $this->assertEquals(9, $this->product->stock_quantity);
    }

    public function test_midtrans_webhook_failed_payment_cancels_order(): void
    {
        $serverKey = config('midtrans.server_key');
        $grossAmount = '23000.00';
        $statusCode = '202';
        $validSignature = hash('sha512', $this->order->order_number . $statusCode . $grossAmount . $serverKey);

        $payload = [
            'order_id' => $this->order->order_number,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $validSignature,
            'transaction_status' => 'expire',
            'payment_type' => 'qris',
        ];

        $response = $this->postJson('/api/midtrans/notification', $payload);

        $response->assertStatus(200);

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $this->order->status);
        $this->assertEquals('failed', $this->order->payment_status);
    }
}
