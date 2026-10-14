<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApmSessionBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'services.apm_bridge.secret' => 'bridge-secret-test',
            'services.apm_bridge.token_ttl' => 3600,
        ]);
    }

    public function test_bridge_rejects_missing_secret(): void
    {
        $response = $this->postJson('/api/v1/internal/apm-session', [
            'token' => 'apm_fin_bridge_token_123456',
            'payload' => ['staff_id' => 7, 'permissions' => ['92']],
        ]);
        $response->assertStatus(401);
    }

    public function test_bridge_stores_token_in_finance_cache(): void
    {
        $token = 'apm_fin_bridge_token_abcdef';
        $response = $this->withHeaders([
            'X-Finance-Bridge-Token' => 'bridge-secret-test',
        ])->postJson('/api/v1/internal/apm-session', [
            'token' => $token,
            'payload' => [
                'staff_id' => 42,
                'permissions' => ['92'],
                'division_id' => 3,
                'role_id' => 10,
            ],
            'ttl' => 600,
        ]);
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $cached = Cache::get('risk_api_token:'.$token);
        $this->assertIsArray($cached);
        $this->assertSame(42, (int) $cached['staff_id']);
        $this->assertSame($token, $cached['api_token']);
    }
}
