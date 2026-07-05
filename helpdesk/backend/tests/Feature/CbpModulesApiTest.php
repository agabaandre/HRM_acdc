<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CbpModulesApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_receives_cbp_modules_from_staff_share_api(): void
    {
        config([
            'helpdesk.staff_api.username' => 'api@test.org',
            'helpdesk.staff_api.password' => 'secret',
            'helpdesk.staff_api.token' => 'testtoken',
            'helpdesk.staff_api.base_url' => 'http://staff.test',
            'helpdesk.reference_data_cache_ttl' => 60,
        ]);

        Http::fake([
            'http://staff.test/share/cbp_modules/testtoken*' => Http::response([
                'success' => true,
                'data' => [
                    'home' => [
                        'id' => 'cbp_home',
                        'label' => 'CBP Home',
                        'description' => '',
                        'href' => 'http://staff.test/home/index',
                        'is_active' => false,
                    ],
                    'modules' => [
                        [
                            'id' => 'approvals_management',
                            'label' => 'APM',
                            'description' => '',
                            'href' => 'http://staff.test/apm',
                            'icon' => 'fa fa-sitemap',
                            'opens_in_new_tab' => false,
                            'is_active' => false,
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 558,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_portal_permissions' => ['85'],
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cbp-modules');

        $response->assertOk()
            ->assertJsonPath('meta.source', 'staff_share_api')
            ->assertJsonPath('meta.degraded', false)
            ->assertJsonPath('data.modules.0.label', 'APM');
    }

    #[Test]
    public function cbp_modules_rewrites_stale_staff_portal_host_on_localhost(): void
    {
        config([
            'helpdesk.staff_api.username' => 'api@test.org',
            'helpdesk.staff_api.password' => 'secret',
            'helpdesk.staff_api.token' => 'testtoken',
            'helpdesk.staff_api.base_url' => 'http://staff.test',
            'helpdesk.reference_data_cache_ttl' => 60,
        ]);

        Http::fake([
            'http://staff.test/share/cbp_modules/testtoken*' => Http::response([
                'success' => true,
                'data' => [
                    'home' => [
                        'id' => 'cbp_home',
                        'label' => 'CBP Home',
                        'description' => '',
                        'href' => 'https://Users-MacBook-Pro.local/staff/home/index',
                        'is_active' => false,
                    ],
                    'modules' => [],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 558,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_portal_permissions' => ['85'],
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cbp-modules', ['HTTP_HOST' => 'localhost']);

        $response->assertOk()
            ->assertJsonPath('data.home.href', 'http://localhost/staff/home/index');
    }

    #[Test]
    public function staff_share_api_failure_returns_degraded_fallback_instead_of_5xx(): void
    {
        config([
            'helpdesk.staff_api.username' => 'api@test.org',
            'helpdesk.staff_api.password' => 'secret',
            'helpdesk.staff_api.token' => 'testtoken',
            'helpdesk.staff_api.base_url' => 'http://staff.test',
            'helpdesk.staff_portal_url' => 'http://staff.test',
            'helpdesk.apm_base_url' => 'http://staff.test/apm',
            'helpdesk.reference_data_cache_ttl' => 60,
        ]);

        Http::fake([
            'http://staff.test/share/cbp_modules/testtoken*' => Http::response([
                'success' => false,
                'error' => 'Database error: example',
            ], 500),
        ]);

        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 558,
            'role' => HelpdeskProfile::ROLE_ADMIN,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cbp-modules');

        $response->assertOk()
            ->assertJsonPath('meta.degraded', true)
            ->assertJsonPath('meta.source', 'fallback')
            ->assertJsonPath('data.home.label', 'CBP Home')
            ->assertJsonPath('data.modules.0.id', 'approvals_management');
    }
}
