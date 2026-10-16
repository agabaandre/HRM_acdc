<?php

namespace Tests\Feature;

use App\Support\RiskPermissions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CbpModulesApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
    }

    public function test_cbp_modules_returns_share_api_payload(): void
    {
        $token = 'fin-cbp-modules-share';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 558,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        Http::fake([
            '*/share/cbp_modules/*' => Http::response([
                'success' => true,
                'data' => [
                    'home' => [
                        'id' => 'cbp_home',
                        'label' => 'CBP Home',
                        'href' => 'http://localhost/staff/',
                        'is_active' => false,
                    ],
                    'modules' => [
                        [
                            'id' => 'staff_portal',
                            'label' => 'Human Resource',
                            'href' => 'http://localhost/staff/',
                            'is_active' => false,
                        ],
                        [
                            'id' => 'finance_management',
                            'label' => 'Finance Management',
                            'href' => 'http://localhost/staff/finance',
                            'is_active' => true,
                        ],
                        [
                            'id' => 'risk_register',
                            'label' => 'Risk Register',
                            'href' => 'http://localhost/staff/risk-register',
                            'is_active' => false,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/cbp-modules?active=finance_management');
        $response->assertOk();
        $response->assertJsonPath('meta.source', 'staff_share_api');
        $response->assertJsonPath('data.modules.1.id', 'finance_management');
        $this->assertCount(3, $response->json('data.modules'));
    }

    public function test_cbp_modules_falls_back_to_static_list_when_share_fails(): void
    {
        $token = 'fin-cbp-modules-fallback';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 999001,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        Http::fake([
            '*/share/cbp_modules/*' => Http::response([
                'success' => false,
                'error' => 'Staff member not found or has no portal user account',
            ], 404),
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/cbp-modules');
        $response->assertOk();
        $response->assertJsonPath('meta.degraded', true);
        $ids = collect($response->json('data.modules'))->pluck('id')->all();
        $this->assertContains('finance_management', $ids);
        $this->assertContains('staff_portal', $ids);
        $this->assertContains('risk_register', $ids);
        $this->assertContains('helpdesk_itsm', $ids);
        $this->assertContains('approvals_management', $ids);
    }
}
