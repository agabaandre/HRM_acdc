<?php

namespace Tests\Feature;

use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AzureSapStorageSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'azure_sap.connection_string' => '',
            'azure_sap.account_name' => 'env-account',
            'azure_sap.account_key' => 'ZW52LWtleQ==',
            'azure_sap.container' => 'sap',
            'azure_sap.prefix' => '',
            'azure_sap.endpoint_suffix' => 'core.windows.net',
            'azure_sap.timeout' => 10,
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

    private function token(string $name = 'azure-sap'): string
    {
        Cache::put('risk_api_token:'.$name, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $name,
        ], 3600);

        return $name;
    }

    public function test_show_resolves_env_credentials(): void
    {
        $token = $this->token('azure-show');
        $response = $this->withToken($token)->getJson('/api/v1/settings/azure-sap-storage');
        $response->assertOk();
        $response->assertJsonPath('data.resolved.account_name', 'env-account');
        $response->assertJsonPath('data.resolved.container', 'sap');
        $response->assertJsonPath('data.resolved.account_key_configured', true);
        $response->assertJsonPath('data.sources.account_name', 'env');
    }

    public function test_update_persists_db_overrides(): void
    {
        $token = $this->token('azure-save');
        $save = $this->withToken($token)->putJson('/api/v1/settings/azure-sap-storage', [
            'azure_sap_account_name' => 'db-account',
            'azure_sap_container' => 'sap-prod',
            'azure_sap_prefix' => 'exports/',
            'azure_sap_account_key' => 'ZGIta2V5',
        ]);
        $save->assertOk();
        $save->assertJsonPath('success', true);

        $this->assertSame('db-account', DB::table('fin_settings')->where('key', 'azure_sap_account_name')->value('value'));
        $this->assertSame('sap-prod', DB::table('fin_settings')->where('key', 'azure_sap_container')->value('value'));
        $this->assertSame('exports/', DB::table('fin_settings')->where('key', 'azure_sap_prefix')->value('value'));
        $this->assertSame('ZGIta2V5', DB::table('fin_settings')->where('key', 'azure_sap_account_key')->value('value'));

        $show = $this->withToken($token)->getJson('/api/v1/settings/azure-sap-storage');
        $show->assertJsonPath('data.resolved.account_name', 'db-account');
        $show->assertJsonPath('data.resolved.container', 'sap-prod');
        $show->assertJsonPath('data.sources.account_name', 'db');
    }

    public function test_dump_list_requires_configuration(): void
    {
        config([
            'azure_sap.account_name' => '',
            'azure_sap.account_key' => '',
            'azure_sap.connection_string' => '',
        ]);
        $token = $this->token('azure-dump');
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::VIEW_ALL],
            'api_token' => $token,
        ], 3600);

        $response = $this->withToken($token)->getJson('/api/v1/sap/dump/files');
        $response->assertStatus(422);
        $response->assertJsonPath('data.configured', false);
    }
}
