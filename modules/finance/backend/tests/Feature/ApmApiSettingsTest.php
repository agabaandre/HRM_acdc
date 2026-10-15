<?php

namespace Tests\Feature;

use App\Support\ApmApiCredentials;
use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApmApiSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'apm.base_url' => 'http://apm.test',
            'apm.api_prefix' => '/api/apm/v1',
            'apm.email' => 'env-user@africacdc.org',
            'apm.password' => 'env-secret',
            'apm.timeout' => 10,
            // Default off so password-path tests stay isolated; bridge covered separately.
            'services.apm_bridge.secret' => '',
        ]);
        DB::purge();
        DB::reconnect();
        Schema::create('fin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_show_includes_apm_snapshot_from_env(): void
    {
        $token = 'apm-settings-show';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $response = $this->withToken($token)->getJson('/api/v1/settings/staff-api');
        $response->assertOk();
        $response->assertJsonPath('data.apm.resolved.email', 'env-user@africacdc.org');
        $response->assertJsonPath('data.apm.resolved.password_configured', true);
        $response->assertJsonPath('data.apm.sources.email', 'env');
    }

    public function test_update_persists_apm_db_overrides_and_probe_uses_them(): void
    {
        $token = 'apm-settings-save';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        Http::fake([
            'http://apm.db/api/apm/v1/auth/login' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'tok', 'token_type' => 'bearer', 'expires_in' => 3600],
            ], 200),
            'http://apm.db/api/apm/v1/fund-codes*' => Http::response([
                'success' => true,
                'data' => [],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 1, 'total' => 0],
            ], 200),
        ]);

        $save = $this->withToken($token)->putJson('/api/v1/settings/staff-api', [
            'section' => 'apm',
            'apm_api_base_url' => 'http://apm.db',
            'apm_api_email' => 'finance-sync@africacdc.org',
            'apm_api_password' => 'db-secret',
        ]);
        $save->assertOk();
        $save->assertJsonPath('success', true);

        $this->assertSame('finance-sync@africacdc.org', DB::table('fin_settings')->where('key', 'apm_api_email')->value('value'));
        $this->assertSame('db-secret', DB::table('fin_settings')->where('key', 'apm_api_password')->value('value'));

        $resolved = ApmApiCredentials::resolve();
        $this->assertSame('http://apm.db', $resolved['base_url']);
        $this->assertSame('finance-sync@africacdc.org', $resolved['email']);
        $this->assertSame('db', $resolved['sources']['email']);

        $probe = $this->withToken($token)->postJson('/api/v1/settings/staff-api/test', [
            'target' => 'apm',
        ]);
        $probe->assertOk();
        $probe->assertJsonPath('success', true);
        $this->assertStringContainsString('CONNECTED', (string) $probe->json('message'));
    }

    public function test_resolve_falls_back_to_staff_share_credentials(): void
    {
        config([
            'apm.email' => null,
            'apm.password' => null,
            'apm.base_url' => 'http://apm.staff-fallback',
        ]);
        putenv('APM_API_EMAIL');
        putenv('APM_API_PASSWORD');
        unset($_ENV['APM_API_EMAIL'], $_ENV['APM_API_PASSWORD'], $_SERVER['APM_API_EMAIL'], $_SERVER['APM_API_PASSWORD']);
        putenv('STAFF_API_USERNAME=shared@africacdc.org');
        putenv('STAFF_API_PASSWORD=shared-secret');
        $_ENV['STAFF_API_USERNAME'] = 'shared@africacdc.org';
        $_ENV['STAFF_API_PASSWORD'] = 'shared-secret';
        $_SERVER['STAFF_API_USERNAME'] = 'shared@africacdc.org';
        $_SERVER['STAFF_API_PASSWORD'] = 'shared-secret';

        $resolved = ApmApiCredentials::resolve();
        $this->assertSame('shared@africacdc.org', $resolved['email']);
        $this->assertSame('shared-secret', $resolved['password']);
        $this->assertSame('staff_api', $resolved['sources']['email']);
        $this->assertSame('staff_api', $resolved['sources']['password']);

        Http::fake([
            'http://apm.staff-fallback/api/apm/v1/auth/login' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'tok-staff', 'token_type' => 'bearer', 'expires_in' => 3600],
            ], 200),
            'http://apm.staff-fallback/api/apm/v1/fund-codes*' => Http::response([
                'success' => true,
                'data' => [],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 1, 'total' => 12],
            ], 200),
        ]);

        $probe = ApmApiCredentials::probe();
        $this->assertTrue($probe['success']);
        $this->assertStringContainsString('shared@africacdc.org', $probe['message']);
        $this->assertTrue(collect($probe['details'])->contains(fn ($line) => str_contains((string) $line, 'staff_api')));
    }

    public function test_resolve_does_not_mix_apm_email_with_staff_password(): void
    {
        config([
            'apm.email' => 'finance-sync@africacdc.org',
            'apm.password' => null,
            'apm.base_url' => 'http://apm.mix',
        ]);
        putenv('APM_API_PASSWORD');
        unset($_ENV['APM_API_PASSWORD'], $_SERVER['APM_API_PASSWORD']);
        putenv('STAFF_API_USERNAME=andrewa@africacdc.org');
        putenv('STAFF_API_PASSWORD=staff-secret');
        $_ENV['STAFF_API_USERNAME'] = 'andrewa@africacdc.org';
        $_ENV['STAFF_API_PASSWORD'] = 'staff-secret';
        $_SERVER['STAFF_API_USERNAME'] = 'andrewa@africacdc.org';
        $_SERVER['STAFF_API_PASSWORD'] = 'staff-secret';

        $resolved = ApmApiCredentials::resolve();
        $this->assertSame('andrewa@africacdc.org', $resolved['email']);
        $this->assertSame('staff-secret', $resolved['password']);
        $this->assertSame('staff_api', $resolved['sources']['email']);
        $this->assertSame('staff_api', $resolved['sources']['password']);
    }

    public function test_login_falls_back_to_staff_share_after_apm_422(): void
    {
        config([
            'apm.email' => 'stale-sync@africacdc.org',
            'apm.password' => 'wrong-secret',
            'apm.base_url' => 'http://apm.retry',
        ]);
        putenv('STAFF_API_USERNAME=andrewa@africacdc.org');
        putenv('STAFF_API_PASSWORD=staff-secret');
        $_ENV['STAFF_API_USERNAME'] = 'andrewa@africacdc.org';
        $_ENV['STAFF_API_PASSWORD'] = 'staff-secret';
        $_SERVER['STAFF_API_USERNAME'] = 'andrewa@africacdc.org';
        $_SERVER['STAFF_API_PASSWORD'] = 'staff-secret';

        Http::fake([
            'http://apm.retry/api/apm/v1/auth/login' => function ($request) {
                $email = data_get($request->data(), 'email');
                if ($email === 'stale-sync@africacdc.org') {
                    return Http::response([
                        'message' => 'The provided credentials are incorrect or the API user is inactive.',
                        'errors' => ['email' => ['The provided credentials are incorrect or the API user is inactive.']],
                    ], 422);
                }

                return Http::response([
                    'success' => true,
                    'data' => ['access_token' => 'tok-from-staff', 'token_type' => 'bearer', 'expires_in' => 3600],
                ], 200);
            },
        ]);

        $auth = ApmApiCredentials::login();
        $this->assertSame('tok-from-staff', $auth['access_token']);
        $this->assertSame('andrewa@africacdc.org', $auth['email']);
        $this->assertSame('staff_api', $auth['label']);
    }

    public function test_login_prefers_bridge_over_password(): void
    {
        config([
            'apm.email' => 'stale-sync@africacdc.org',
            'apm.password' => 'wrong-secret',
            'apm.base_url' => 'http://apm.bridge',
            'services.apm_bridge.secret' => 'shared-bridge-secret',
        ]);

        Http::fake([
            'http://apm.bridge/api/apm/v1/internal/finance-session' => Http::response([
                'success' => true,
                'data' => [
                    'access_token' => 'tok-from-bridge',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                    'email' => 'stale-sync@africacdc.org',
                ],
            ], 200),
            'http://apm.bridge/api/apm/v1/auth/login' => Http::response([
                'message' => 'should not be called',
            ], 500),
        ]);

        $auth = ApmApiCredentials::login();
        $this->assertSame('tok-from-bridge', $auth['access_token']);
        $this->assertSame('stale-sync@africacdc.org', $auth['email']);
        $this->assertSame('bridge', $auth['label']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://apm.bridge/api/apm/v1/internal/finance-session'
                && $request->header('X-Finance-Bridge-Token')[0] === 'shared-bridge-secret'
                && data_get($request->data(), 'email') === 'stale-sync@africacdc.org';
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/auth/login');
        });
    }

    public function test_probe_uses_bridge_when_secret_configured(): void
    {
        config([
            'apm.email' => 'bridge-user@africacdc.org',
            'apm.password' => 'unused',
            'apm.base_url' => 'http://apm.probe-bridge',
            'services.apm_bridge.secret' => 'probe-secret',
        ]);

        Http::fake([
            'http://apm.probe-bridge/api/apm/v1/internal/finance-session' => Http::response([
                'success' => true,
                'data' => [
                    'access_token' => 'tok-probe',
                    'email' => 'bridge-user@africacdc.org',
                ],
            ], 200),
            'http://apm.probe-bridge/api/apm/v1/fund-codes*' => Http::response([
                'success' => true,
                'data' => [],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 1, 'total' => 7],
            ], 200),
        ]);

        $probe = ApmApiCredentials::probe();
        $this->assertTrue($probe['success']);
        $this->assertStringContainsString('bridge', $probe['message']);
        $this->assertTrue(collect($probe['details'])->contains(fn ($line) => str_contains((string) $line, 'bridge: OK')));
    }
}
