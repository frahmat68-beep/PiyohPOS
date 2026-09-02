<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Table as TableModel;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableLockTimeoutAndManualUnlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_lock_automatically_expires_after_10_minutes(): void
    {
        $outlet = Outlet::create([
            'name' => 'Galaxy', 'slug' => 'galaxy', 'is_active' => true,
        ]);

        $table = TableModel::create([
            'outlet_id' => $outlet->id,
            'number' => '01',
            'seating_capacity' => 4,
            'status' => 'occupied',
            'qr_token' => 'test-table-token',
        ]);

        $session = TableSession::create([
            'table_id' => $table->id,
            'session_code' => 'TESTSESS01',
            'status' => 'open',
            'is_locked' => true,
            'locked_by_device' => 'device-1-uuid',
            'locked_at' => now()->subMinutes(11), // Locked 11 minutes ago
            'opened_at' => now()->subHour(),
        ]);

        // Device 2 checks lock
        $isLocked = $session->isLockedForDevice('device-2-uuid');

        // Should return FALSE because > 10 mins passed
        $this->assertFalse($isLocked);
        $session->refresh();
        $this->assertFalse($session->is_locked);
        $this->assertNull($session->locked_by_device);
    }

    public function test_cashier_can_force_unlock_table_lock(): void
    {
        $user = User::factory()->create();
        $outlet = Outlet::create([
            'name' => 'Galaxy', 'slug' => 'galaxy', 'is_active' => true,
        ]);

        $table = TableModel::create([
            'outlet_id' => $outlet->id,
            'number' => '02',
            'seating_capacity' => 4,
            'status' => 'occupied',
            'qr_token' => 'test-table-token-2',
        ]);

        $session = TableSession::create([
            'table_id' => $table->id,
            'session_code' => 'TESTSESS02',
            'status' => 'open',
            'is_locked' => true,
            'locked_by_device' => 'stuck-device-uuid',
            'locked_at' => now()->subMinutes(2),
            'opened_at' => now()->subHour(),
        ]);

        $this->actingAs($user);

        // Force unlock via method
        $session->forceUnlockCart();
        $session->refresh();

        $this->assertFalse($session->is_locked);
        $this->assertNull($session->locked_by_device);
        $this->assertNull($session->locked_at);
        $this->assertNotNull($session->force_unlocked_at);
    }

    public function test_midtrans_settlement_after_force_unlock_flags_order(): void
    {
        $outlet = Outlet::create(['name' => 'Galaxy', 'slug' => 'galaxy', 'is_active' => true]);
        $table = TableModel::create([
            'outlet_id' => $outlet->id, 'number' => '03', 'seating_capacity' => 4, 'status' => 'occupied', 'qr_token' => 'qr-03',
        ]);
        $session = TableSession::create([
            'table_id' => $table->id, 'session_code' => 'SESS03', 'status' => 'open',
            'is_locked' => false, 'force_unlocked_at' => now()->subMinutes(5),
            'opened_at' => now()->subHour(),
        ]);

        $order = \App\Models\Order::create([
            'outlet_id' => $outlet->id,
            'table_id' => $table->id,
            'order_number' => 'ORD-FORCE-TEST-01',
            'customer_name' => 'Customer Meja 3',
            'status' => \App\Models\Order::STATUS_PENDING_PAYMENT,
            'payment_status' => 'pending',
            'payment_method' => 'midtrans',
            'tax_amount' => 1000,
            'service_charge' => 500,
            'total_amount' => 11500,
        ]);

        // Trigger Midtrans settlement
        $midtransService = app(\App\Services\MidtransService::class);
        $serverKey = config('midtrans.server_key') ?: 'TEST-KEY';
        config(['midtrans.server_key' => $serverKey]);

        $orderId = $order->order_number;
        $statusCode = '200';
        $grossAmount = '11500.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'midtrans-trx-12345',
        ];

        $midtransService->processNotification($payload);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(\App\Models\Order::STATUS_CONFIRMED, $order->status);
        $this->assertNotNull($order->paid_after_force_unlock_at);
    }

    public function test_table_session_auto_closes_when_order_is_completed(): void
    {
        $outlet = Outlet::create(['name' => 'Galaxy', 'slug' => 'galaxy', 'is_active' => true]);
        $table = TableModel::create([
            'outlet_id' => $outlet->id, 'number' => '04', 'seating_capacity' => 4, 'status' => 'occupied', 'qr_token' => 'qr-04',
        ]);
        $session = TableSession::create([
            'table_id' => $table->id, 'session_code' => 'SESS04', 'status' => 'open',
            'opened_at' => now()->subHour(),
        ]);

        $order = \App\Models\Order::create([
            'outlet_id' => $outlet->id,
            'table_id' => $table->id,
            'order_number' => 'ORD-COMPLETE-01',
            'customer_name' => 'Customer Meja 4',
            'status' => \App\Models\Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'tax_amount' => 1000,
            'service_charge' => 500,
            'total_amount' => 11500,
        ]);

        // Advance order to preparing, ready, served, completed
        $order->transitionTo(\App\Models\Order::STATUS_PREPARING);
        $order->transitionTo(\App\Models\Order::STATUS_READY);
        $order->transitionTo(\App\Models\Order::STATUS_SERVED);
        $order->transitionTo(\App\Models\Order::STATUS_COMPLETED);

        $session->refresh();
        $this->assertEquals('closed', $session->status);
        $this->assertNotNull($session->closed_at);
    }
}
