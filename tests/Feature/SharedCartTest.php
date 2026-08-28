<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharedCartTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected Table $table;
    protected Category $category;
    protected Product $product1;
    protected Product $product2;

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
            'number' => '05',
            'qr_token' => 'TABLE-05-TOKEN',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Coffee',
            'slug' => 'coffee',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->product1 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Kopi Susu Piyoh',
            'slug' => 'kopi-susu-piyoh',
            'base_price' => 22000,
            'stock_quantity' => 50,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $this->product2 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Manual Brew V60',
            'slug' => 'manual-brew-v60',
            'base_price' => 28000,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
    }

    public function test_two_devices_scan_same_qr_and_share_active_table_session(): void
    {
        // Device A scans table QR
        $responseA = $this->withCookie('piyoh_device_token', 'device-A-uuid')
            ->get("/scan/{$this->table->qr_token}");

        $responseA->assertRedirect('/qr/menu');
        $session = TableSession::where('table_id', $this->table->id)->where('status', 'open')->first();
        $this->assertNotNull($session);

        // Device B scans the SAME table QR 1 minute later
        $responseB = $this->withCookie('piyoh_device_token', 'device-B-uuid')
            ->get("/scan/{$this->table->qr_token}");

        $responseB->assertRedirect('/qr/menu');

        // Both devices share the SAME active TableSession ID (not creating duplicate or resetting)
        $sessionCount = TableSession::where('table_id', $this->table->id)->where('status', 'open')->count();
        $this->assertEquals(1, $sessionCount);
    }

    public function test_device_a_adds_item_and_device_b_sees_and_adds_another_item_in_same_cart(): void
    {
        // Setup shared session
        $tableSession = TableSession::create([
            'table_id'     => $this->table->id,
            'session_code' => 'SHARED-SESSION-123',
            'status'       => 'open',
            'opened_at'    => now(),
            'expires_at'   => now()->addHours(4),
        ]);

        // Device A adds Kopi Susu Piyoh (qty 2)
        $responseA = $this->withSession(['qr_session_code' => 'SHARED-SESSION-123'])
            ->withCookie('piyoh_device_token', 'device-A-uuid')
            ->postJson('/cart/add', [
                'product_id' => $this->product1->id,
                'quantity'   => 2,
                'notes'      => 'Less Sugar',
            ]);

        $responseA->assertStatus(200);
        $responseA->assertJsonPath('cart_count', 2);

        // Device B queries cart sync and verifies it sees Device A's item
        $syncB = $this->withSession(['qr_session_code' => 'SHARED-SESSION-123'])
            ->withCookie('piyoh_device_token', 'device-B-uuid')
            ->getJson('/cart/sync');

        $syncB->assertStatus(200);
        $syncB->assertJsonPath('cart_count', 2);
        $syncB->assertJsonPath('cart_total', 44000);

        // Device B adds Manual Brew V60 (qty 1)
        $responseB = $this->withSession(['qr_session_code' => 'SHARED-SESSION-123'])
            ->withCookie('piyoh_device_token', 'device-B-uuid')
            ->postJson('/cart/add', [
                'product_id' => $this->product2->id,
                'quantity'   => 1,
                'notes'      => 'Japanese Iced',
            ]);

        $responseB->assertStatus(200);
        $responseB->assertJsonPath('cart_count', 3);

        // Verify database state: 2 distinct cart_items rows linked to table_session_id
        $cartItems = CartItem::where('table_session_id', $tableSession->id)->get();
        $this->assertCount(2, $cartItems);

        // Device A queries sync and sees total count 3 and total price 72000
        $syncA = $this->withSession(['qr_session_code' => 'SHARED-SESSION-123'])
            ->withCookie('piyoh_device_token', 'device-A-uuid')
            ->getJson('/cart/sync');

        $syncA->assertStatus(200);
        $syncA->assertJsonPath('cart_count', 3);
        $syncA->assertJsonPath('cart_total', 72000);
    }

    public function test_checkout_locks_the_cart_for_other_devices(): void
    {
        $tableSession = TableSession::create([
            'table_id'     => $this->table->id,
            'session_code' => 'SHARED-SESSION-LOCK-TEST',
            'status'       => 'open',
            'opened_at'    => now(),
            'expires_at'   => now()->addHours(4),
        ]);

        // Add item
        CartItem::create([
            'table_session_id' => $tableSession->id,
            'product_id'       => $this->product1->id,
            'cart_key'         => "{$this->product1->id}||",
            'quantity'         => 1,
            'device_id'        => 'device-A',
        ]);

        // Lock cart by Device A
        $tableSession->lockCart('device-A');

        // Device B tries to add item -> blocked
        $responseB = $this->withSession(['qr_session_code' => 'SHARED-SESSION-LOCK-TEST'])
            ->withCookie('piyoh_device_token', 'device-B')
            ->postJson('/cart/add', [
                'product_id' => $this->product2->id,
                'quantity'   => 1,
            ]);

        $responseB->assertStatus(422);
        $responseB->assertJsonFragment(['error' => 'Meja sedang memproses checkout dari perangkat lain. Mohon tunggu sebentar.']);

        // Device B queries sync and verifies is_locked is true
        $syncB = $this->withSession(['qr_session_code' => 'SHARED-SESSION-LOCK-TEST'])
            ->withCookie('piyoh_device_token', 'device-B')
            ->getJson('/cart/sync');

        $syncB->assertJsonPath('is_locked', true);
    }
}
