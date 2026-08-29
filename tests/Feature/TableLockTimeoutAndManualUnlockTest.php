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
        $session->unlockCart();
        $session->refresh();

        $this->assertFalse($session->is_locked);
        $this->assertNull($session->locked_by_device);
        $this->assertNull($session->locked_at);
    }
}
