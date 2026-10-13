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
        ]);
        DB::purge();
        DB::reconnect();
        Schema::create('rr_settings', function (Blueprint $table) {
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

        $this->assertSame('finance-sync@africacdc.org', DB::table('rr_settings')->where('key', 'apm_api_email')->value('value'));
        $this->assertSame('db-secret', DB::table('rr_settings')->where('key', 'apm_api_password')->value('value'));

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
}
