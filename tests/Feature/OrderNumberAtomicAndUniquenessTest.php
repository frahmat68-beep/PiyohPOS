<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyOrderSequence;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberAtomicAndUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected Product $product;
    protected OrderService $orderService;
    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create([
            'name'      => 'Piyoh Galaxy',
            'slug'      => 'piyoh-galaxy',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name'      => 'Signature',
            'slug'      => 'signature',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Es Kopi Susu Aren',
            'slug'           => 'es-kopi-susu-aren',
            'base_price'     => 22000,
            'stock_quantity' => 100,
            'is_active'      => true,
        ]);

        $this->orderService = app(OrderService::class);
        $this->cartService  = app(CartService::class);
    }

    public function test_two_simultaneous_checkouts_generate_unique_order_numbers(): void
    {
        $table1 = Table::create([
            'outlet_id'        => $this->outlet->id,
            'number'           => '01',
            'seating_capacity' => 4,
            'status'           => 'occupied',
            'qr_token'         => 'TOKEN-ORD-01',
        ]);

        $table2 = Table::create([
            'outlet_id'        => $this->outlet->id,
            'number'           => '02',
            'seating_capacity' => 4,
            'status'           => 'occupied',
            'qr_token'         => 'TOKEN-ORD-02',
        ]);

        $session1 = TableSession::create([
            'table_id'     => $table1->id,
            'session_code' => 'SESS-ORD-01',
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        $session2 = TableSession::create([
            'table_id'     => $table2->id,
            'session_code' => 'SESS-ORD-02',
            'status'       => 'open',
            'opened_at'    => now(),
        ]);

        // Cart items for Table 1
        session(['qr_session_code' => 'SESS-ORD-01']);
        $this->cartService->add($this->product->id, 1, [], null, 'device-t1');

        // Cart items for Table 2
        session(['qr_session_code' => 'SESS-ORD-02']);
        $this->cartService->add($this->product->id, 2, [], null, 'device-t2');

        // Checkout Table 1
        session(['qr_session_code' => 'SESS-ORD-01']);
        $order1 = $this->orderService->checkout('Customer Table 1', 'device-t1', false);

        // Checkout Table 2
        session(['qr_session_code' => 'SESS-ORD-02']);
        $order2 = $this->orderService->checkout('Customer Table 2', 'device-t2', false);

        $this->assertNotNull($order1);
        $this->assertNotNull($order2);

        // Verifikasi TIDAK ADA nomor pesanan yang sama
        $this->assertNotEquals($order1->order_number, $order2->order_number);

        // Format harus GLX-YYYYMMDD-[A-Z][0-9]{2}
        $todayStr = now()->format('Ymd');
        $pattern = "/^GLX-{$todayStr}-[A-Z][0-9]{2,}$/";
        $this->assertMatchesRegularExpression($pattern, $order1->order_number);
        $this->assertMatchesRegularExpression($pattern, $order2->order_number);

        // Sequence di tabel daily_order_sequences harus tercatat 2
        $dailySeq = DailyOrderSequence::where('outlet_id', $this->outlet->id)
            ->where('order_date', today()->toDateString())
            ->first();

        $this->assertNotNull($dailySeq);
        $this->assertEquals(2, $dailySeq->last_sequence);
    }

    public function test_multiple_sequential_order_numbers_are_all_unique_and_increment_counter(): void
    {
        $numbers = [];

        for ($i = 0; $i < 20; $i++) {
            $num = $this->orderService->generateOrderNumber($this->outlet->id);
            $numbers[] = $num;
        }

        // Semua 20 order number harus unik
        $this->assertCount(20, array_unique($numbers));

        // Daily counter harus tepat 20
        $dailySeq = DailyOrderSequence::where('outlet_id', $this->outlet->id)
            ->where('order_date', today()->toDateString())
            ->first();

        $this->assertEquals(20, $dailySeq->last_sequence);
    }
}
