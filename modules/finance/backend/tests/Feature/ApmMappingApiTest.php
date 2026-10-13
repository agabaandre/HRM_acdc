<?php

namespace Tests\Feature;

use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApmMappingApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'logging.default' => 'array',
        ]);
        DB::purge();
        DB::reconnect();
        Schema::create('fin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        config(['database.connections.apm' => config('database.connections.sqlite')]);
        DB::purge('apm');
        Schema::connection('apm')->create('divisions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('division_name');
            $table->string('division_short_name')->nullable();
        });
        Schema::connection('apm')->create('funders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deleted_at')->nullable();
        });
        DB::connection('apm')->table('divisions')->insert([
            'id' => 10,
            'division_name' => 'Planning Reporting & Accountability',
            'division_short_name' => 'PRA',
        ]);
        DB::connection('apm')->table('funders')->insert([
            ['name' => 'ADM', 'is_active' => 1, 'deleted_at' => null],
            ['name' => 'World Bank', 'is_active' => 1, 'deleted_at' => null],
            ['name' => 'Gavi', 'is_active' => 1, 'deleted_at' => null],
            ['name' => 'CEPI', 'is_active' => 1, 'deleted_at' => null],
            ['name' => 'Inactive Funder', 'is_active' => 0, 'deleted_at' => null],
        ]);
    }

    public function test_show_fetch_and_save_apm_mapping(): void
    {
        $token = 'apm-mapping-token';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $show = $this->withToken($token)->getJson('/api/v1/settings/apm-mapping');
        $show->assertOk();
        $show->assertJsonPath('data.apm_ready', true);
        $show->assertJsonPath('data.divisions.0.division_short_name', 'PRA');
        $this->assertGreaterThanOrEqual(2, count($show->json('data.funders')));
        $this->assertNotEmpty($show->json('data.division_aliases'));
        $this->assertNotEmpty($show->json('data.funder_aliases'));

        $pra = collect($show->json('data.division_aliases'))
            ->firstWhere('label', 'Planning Reporting & Accountability');
        $this->assertNotNull($pra);
        $this->assertSame(10, $pra['division_id']);
        $this->assertSame('auto', $pra['match_source']);

        $wb = collect($show->json('data.funder_aliases'))->firstWhere('label', 'World Bank');
        $this->assertNotNull($wb);
        $this->assertSame('World Bank', $wb['funder_name']);
        $this->assertNotSame('ADM', $wb['funder_name']);

        $gavi = collect($show->json('data.funder_aliases'))->firstWhere('label', 'Gavi');
        $this->assertSame('Gavi', $gavi['funder_name']);

        // Corrupt prior auto match to ADM — fetch should rematch by name.
        DB::table('fin_settings')->updateOrInsert(
            ['key' => 'finance_apm_funder_aliases'],
            [
                'value' => json_encode([
                    ['label' => 'World Bank', 'funder_id' => 1, 'funder_name' => 'ADM', 'match_source' => 'auto'],
                    ['label' => 'Gavi', 'funder_id' => 1, 'funder_name' => 'ADM', 'match_source' => 'auto'],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $fetch = $this->withToken($token)->postJson('/api/v1/settings/apm-mapping/fetch');
        $fetch->assertOk();
        $fetch->assertJsonPath('success', true);
        $wbAfter = collect($fetch->json('data.funder_aliases'))->firstWhere('label', 'World Bank');
        $this->assertSame('World Bank', $wbAfter['funder_name']);
        $gaviAfter = collect($fetch->json('data.funder_aliases'))->firstWhere('label', 'Gavi');
        $this->assertSame('Gavi', $gaviAfter['funder_name']);

        $save = $this->withToken($token)->putJson('/api/v1/settings/apm-mapping', [
            'division_aliases' => [
                ['label' => 'Planning Reporting & Accountability', 'division_id' => 10, 'match_source' => 'manual'],
            ],
            'funder_aliases' => [
                ['label' => 'World Bank', 'funder_id' => 2, 'match_source' => 'manual'],
                ['label' => 'Gavi', 'funder_id' => 3, 'match_source' => 'manual'],
            ],
        ]);
        $save->assertOk();
        $save->assertJsonPath('data.division_aliases.0.division_id', 10);
        $savedWb = collect($save->json('data.funder_aliases'))->firstWhere('label', 'World Bank');
        $this->assertSame(2, $savedWb['funder_id']);
        $this->assertSame('World Bank', $savedWb['funder_name']);
    }

    public function test_extramural_section_includes_apm_funders(): void
    {
        Schema::create('fin_portfolio_entries', function (Blueprint $table) {
            $table->id();
            $table->string('section', 64);
            $table->unsignedSmallInteger('budget_year');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data');
            $table->timestamps();
        });

        $token = 'extramural-funders';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $res = $this->withToken($token)->getJson('/api/v1/portfolio/sections/extramural');
        $res->assertOk();
        $res->assertJsonPath('data.schema.0.key', 'funder_id');
        $res->assertJsonPath('data.schema.0.type', 'funder');
        $names = collect($res->json('data.funders'))->pluck('name')->all();
        $this->assertContains('World Bank', $names);
        $this->assertContains('Gavi', $names);
        $this->assertNotContains('Inactive Funder', $names);
    }
}
