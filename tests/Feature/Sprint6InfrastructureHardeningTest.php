<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint6InfrastructureHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_endpoint_returns_extended_status()
    {
        // Set up jobs table mock if not exists
        if (!\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
            \Illuminate\Support\Facades\Schema::create('jobs', function ($table) {
                $table->id();
            });
        }

        $response = $this->get(route('api.health'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'services' => [
                'database',
                'queue',
                'storage',
                'cache',
            ],
        ]);
        $response->assertJsonFragment(['status' => 'OK']);
    }

    public function test_webhook_hmac_signature_verification_rejects_modified_payload()
    {
        \Illuminate\Support\Facades\Config::set('master-data.sync_token', 'test_sync_secret_2024');
        $payload = ['data' => 'test'];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'wrong_secret');

        $response = $this->postJson(route('api.sync.master_data'), $payload, [
            'Authorization' => 'Bearer test_sync_secret_2024',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Invalid webhook signature.']);
    }

    public function test_webhook_hmac_signature_verification_rejects_missing_header()
    {
        \Illuminate\Support\Facades\Config::set('master-data.sync_token', 'test_sync_secret_2024');
        $payload = ['data' => 'test'];

        $response = $this->postJson(route('api.sync.master_data'), $payload, [
            'Authorization' => 'Bearer test_sync_secret_2024',
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Header X-Hub-Signature-256 missing.']);
    }
}
