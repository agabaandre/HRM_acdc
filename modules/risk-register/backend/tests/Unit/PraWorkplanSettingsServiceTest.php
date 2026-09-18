<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Workplan\Services\PraWorkplanSettingsService;
use Tests\TestCase;

class PraWorkplanSettingsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'workplan.pra.base_url' => 'https://pra.example.org/api/public/workplan',
            'workplan.pra.api_key' => 'env-key',
            'workplan.pra.tiers' => '3,4',
            'workplan.pra.fiscal_year' => 2026,
            'workplan.pra.divisions' => [],
            'workplan.pra.division_aliases' => ['MIS' => 'DHIS'],
            'workplan.pra.timeout' => 60,
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('workplan_pra_settings', function (Blueprint $table): void {
            $table->string('setting_key', 80)->primary();
            $table->json('setting_value');
            $table->timestamps();
        });
    }

    public function test_falls_back_to_env_config_when_nothing_saved(): void
    {
        $svc = app(PraWorkplanSettingsService::class);
        $resolved = $svc->resolved();

        $this->assertSame('https://pra.example.org/api/public/workplan', $resolved['base_url']);
        $this->assertSame('env-key', $resolved['api_key']);
        $this->assertTrue($svc->isConfigured());
        $this->assertSame(['MIS' => 'DHIS'], $resolved['division_aliases']);
        $this->assertNull($resolved['fiscal_year']);

        $form = $svc->formPayload();
        $this->assertTrue($form['api_key_set']);
        $this->assertArrayNotHasKey('api_key', $form);
        $this->assertSame('MIS:DHIS', $form['division_aliases']);
    }

    public function test_saved_values_override_env_and_blank_key_keeps_existing(): void
    {
        $svc = app(PraWorkplanSettingsService::class);
        $svc->save([
            'base_url' => 'https://pra.africacdc.org/api/public/workplan',
            'api_key' => 'ui-secret',
            'tiers' => '3',
            'fiscal_year' => 2026,
            'divisions' => 'MIS,HRM',
            'division_aliases' => 'MIS:DHIS,FOO:BAR',
            'timeout' => 45,
        ]);

        $svc = app(PraWorkplanSettingsService::class);
        $resolved = $svc->resolved();
        $this->assertSame('https://pra.africacdc.org/api/public/workplan', $resolved['base_url']);
        $this->assertSame('ui-secret', $resolved['api_key']);
        $this->assertSame('3', $resolved['tiers']);
        $this->assertSame(2026, $resolved['fiscal_year']);
        $this->assertSame(['MIS', 'HRM'], $resolved['divisions']);
        $this->assertSame(['MIS' => 'DHIS', 'FOO' => 'BAR'], $resolved['division_aliases']);
        $this->assertSame(45, $resolved['timeout']);

        $form = $svc->formPayload();
        $this->assertTrue($form['api_key_set']);
        $this->assertArrayNotHasKey('api_key', $form);
        $this->assertSame('MIS,HRM', $form['divisions']);

        $svc->save(['api_key' => '', 'tiers' => '3,4']);
        $this->assertSame('ui-secret', $svc->resolved()['api_key']);
        $this->assertSame('3,4', $svc->resolved()['tiers']);
    }

    public function test_empty_env_key_is_not_configured(): void
    {
        config(['workplan.pra.api_key' => '']);

        $svc = app(PraWorkplanSettingsService::class);

        $this->assertFalse($svc->isConfigured());
        $this->assertFalse($svc->formPayload()['api_key_set']);
    }
}
