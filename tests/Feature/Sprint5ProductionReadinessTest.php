<?php

namespace Tests\Feature;

use App\Models\SyncLog;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint5ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_health_check_endpoint_returns_success()
    {
        $response = $this->get(route('api.health'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'services' => [
                'database',
            ],
        ]);
        $response->assertJsonFragment(['status' => 'OK']);
    }

    public function test_sync_dashboard_logs_display_correctly()
    {
        SyncLog::create([
            'entity_type' => 'product',
            'status' => 'success',
            'payload' => [['id' => 1, 'name' => 'Kopi']],
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('sync_logs', [
            'entity_type' => 'product',
            'status' => 'success',
        ]);
    }

    public function test_qr_token_regeneration_on_table_resource()
    {
        $outlet = \App\Models\Outlet::create([
            'name' => 'Piyoh Galaxy',
            'slug' => 'piyoh-galaxy',
            'address' => 'Galaxy',
            'phone' => '08123',
            'is_active' => true,
        ]);

        $table = Table::create([
            'outlet_id' => $outlet->id,
            'number' => '05',
            'seating_capacity' => 4,
            'status' => 'vacant',
            'qr_token' => 'old_token_123',
        ]);

        $oldToken = $table->qr_token;
        $table->update(['qr_token' => \Illuminate\Support\Str::random(32)]);

        $this->assertNotEquals($oldToken, $table->fresh()->qr_token);
    }
}
