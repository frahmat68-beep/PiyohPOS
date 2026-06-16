<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MasterDataSyncTest extends TestCase
{
    public function test_it_requires_an_authorization_token_to_sync()
    {
        $response = $this->postJson(route('api.sync.master_data'));
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing sync token.',
            ]);
    }

    public function test_it_rejects_invalid_tokens()
    {
        $response = $this->postJson(route('api.sync.master_data'), [], [
            'Authorization' => 'Bearer wrong_token_123',
        ]);
        $response->assertStatus(401);
    }

    public function test_it_allows_sync_with_valid_token()
    {
        Config::set('master-data.sync_token', 'test_secret_token');

        $response = $this->postJson(route('api.sync.master_data'), [], [
            'Authorization' => 'Bearer test_secret_token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'sync endpoint ready',
            ]);
    }
}
