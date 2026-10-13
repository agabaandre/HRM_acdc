<?php

namespace Tests\Feature;

use App\Support\FinancePermissions;
use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortfolioApiTest extends TestCase
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
        $this->createTables();
    }

    public function test_summary_and_section_replace(): void
    {
        $year = (int) date('Y');
        DB::table('fin_sap_imports')->insert([
            'uploaded_by' => 1,
            'original_filename' => 'x.xlsx',
            'budget_year' => $year,
            'row_count' => 1,
            'total_row_count' => 1,
            'apm_updated_count' => 0,
            'sync_status' => 'synced',
            'is_latest' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $importId = (int) DB::table('fin_sap_imports')->max('id');
        DB::table('fin_sap_fund_centers')->insert([
            'import_id' => $importId,
            'budget_year' => $year,
            'fund_center' => 'CDC1',
            'total_released_budget' => 1000,
            'released_budget_balance' => 250,
            'payload' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = 'portfolio-token';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        // Point APM connection at the same in-memory sqlite used by the test.
        config(['database.connections.apm' => config('database.connections.sqlite')]);
        DB::purge('apm');
        Schema::connection('apm')->create('divisions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('division_name');
            $table->string('division_short_name')->nullable();
        });
        Schema::connection('apm')->create('fund_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('division_id')->nullable();
            $table->boolean('is_active')->default(true);
        });
        DB::connection('apm')->table('divisions')->insert([
            'id' => 6,
            'division_name' => 'Emergency Preparedness and Response',
            'division_short_name' => 'EPR',
        ]);
        DB::connection('apm')->table('fund_codes')->insert([
            'code' => ' CDC1 ',
            'year' => $year,
            'division_id' => 6,
            'is_active' => 1,
        ]);

        $summary = $this->withToken($token)->getJson('/api/v1/portfolio/summary');
        $summary->assertOk();
        $summary->assertJsonPath('data.intramural.approved_budget', 1000);
        $summary->assertJsonPath('data.intramural.budget_balance', 250);
        $this->assertEqualsWithDelta(0.75, $summary->json('data.intramural.execution_rate'), 0.0001);
        $summary->assertJsonPath('data.schemas.afef.fields.0.key', 'category');
        $summary->assertJsonPath('data.intramural.divisions_source', 'apm');
        $summary->assertJsonPath('data.intramural.mapped_count', 1);
        $summary->assertJsonPath('data.intramural.by_division.0.division_short_name', 'EPR');
        $summary->assertJsonPath('data.kpis.intramural.approved_budget', 1000);
        $summary->assertJsonPath('data.kpis.intramural.fund_center_count', 1);
        $summary->assertJsonPath('data.performance.0.division_short_name', 'EPR');
        $summary->assertJsonPath('data.performance.0.rank', 1);
        $this->assertEqualsWithDelta(0.75, $summary->json('data.performance.0.execution_rate'), 0.0001);

        $intra = $this->withToken($token)->getJson('/api/v1/portfolio/sections/intramural');
        $intra->assertOk();
        $intra->assertJsonPath('data.read_only', true);
        $intra->assertJsonPath('data.intramural.fund_center_count', 1);
        $intra->assertJsonPath('data.intramural.apm_divisions.0.division_name', 'Emergency Preparedness and Response');

        $blocked = $this->withToken($token)->putJson('/api/v1/portfolio/sections/intramural', [
            'rows' => [['sort_order' => 0, 'data' => ['label' => 'x']]],
        ]);
        $blocked->assertStatus(422);

        $put = $this->withToken($token)->putJson('/api/v1/portfolio/sections/afef', [
            'rows' => [
                ['sort_order' => 0, 'data' => ['label' => 'AfEF b/f', 'amount' => 20054305, 'category' => 'opening']],
            ],
        ]);
        $put->assertOk();
        $put->assertJsonPath('data.rows.0.data.label', 'AfEF b/f');
        $this->assertSame(1, DB::table('fin_portfolio_entries')->where('section', 'afef')->count());

        Schema::connection('apm')->create('funders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });
        DB::connection('apm')->table('funders')->insert([
            'name' => 'World Bank',
            'is_active' => 1,
        ]);
        $extra = $this->withToken($token)->getJson('/api/v1/portfolio/sections/extramural');
        $extra->assertOk();
        $extra->assertJsonPath('data.funders.0.name', 'World Bank');
        $extra->assertJsonPath('data.schema.0.type', 'funder');
        $extra->assertJsonPath('data.can_edit', true);

        $viewer = 'portfolio-viewer';
        Cache::put('risk_api_token:'.$viewer, [
            'staff_id' => 2,
            'division_id' => 1,
            'permissions' => [FinancePermissions::MODULE],
            'api_token' => $viewer,
        ], 3600);
        $denied = $this->withToken($viewer)->putJson('/api/v1/portfolio/sections/extramural', [
            'rows' => [['sort_order' => 0, 'data' => ['grant' => 'x']]],
        ]);
        $denied->assertStatus(403);

        $officer = 'portfolio-officer';
        Cache::put('risk_api_token:'.$officer, [
            'staff_id' => 3,
            'division_id' => 1,
            'permissions' => [FinancePermissions::MANAGE_FUNDING_PORTFOLIO],
            'api_token' => $officer,
        ], 3600);
        $allowed = $this->withToken($officer)->putJson('/api/v1/portfolio/sections/extramural', [
            'rows' => [['sort_order' => 0, 'data' => ['grant' => 'Officer grant', 'approved_budget' => 100]]],
        ]);
        $allowed->assertOk();
        $allowed->assertJsonPath('data.rows.0.data.grant', 'Officer grant');

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('region_name', 100);
        });
        Schema::create('nationalities', function (Blueprint $table) {
            $table->increments('nationality_id');
            $table->string('nationality', 50);
            $table->string('nationality_name', 50)->nullable();
            $table->string('continent', 50);
            $table->unsignedInteger('region_id')->default(0);
            $table->string('iso2', 2)->nullable();
        });
        Schema::create('duty_stations', function (Blueprint $table) {
            $table->increments('duty_station_id');
            $table->string('duty_station_name', 100);
            $table->string('country', 50)->default('');
            $table->string('type', 50)->default('');
            $table->string('city', 100)->default('');
        });
        DB::table('regions')->insert(['id' => 2, 'region_name' => 'AU_Eastern']);
        DB::table('nationalities')->insert([
            'nationality' => 'Ethiopia',
            'nationality_name' => 'Ethiopia',
            'continent' => 'AFRICA',
            'region_id' => 2,
            'iso2' => 'ET',
        ]);
        DB::table('duty_stations')->insert([
            'duty_station_name' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'type' => '',
            'city' => 'Addis Ababa',
        ]);

        $invest = $this->withToken($officer)->getJson('/api/v1/portfolio/sections/investments');
        $invest->assertOk();
        $invest->assertJsonPath('data.regions.0.name', 'AU_Eastern');
        $invest->assertJsonPath('data.countries.0.name', 'Ethiopia');
        $invest->assertJsonPath('data.locations.0.name', 'Addis Ababa');
        $this->assertTrue(
            collect($invest->json('data.schema'))->contains(fn ($f) => ($f['type'] ?? '') === 'region')
        );
    }

    private function createTables(): void
    {
        Schema::create('fin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_sap_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('original_filename');
            $table->unsignedSmallInteger('budget_year');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('total_row_count')->default(0);
            $table->unsignedInteger('apm_updated_count')->default(0);
            $table->string('sync_status', 32)->default('pending');
            $table->text('sync_error')->nullable();
            $table->boolean('is_latest')->default(false);
            $table->timestamps();
        });
        Schema::create('fin_sap_fund_centers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->unsignedSmallInteger('budget_year');
            $table->string('fund_center', 64);
            $table->decimal('total_released_budget', 18, 2)->default(0);
            $table->decimal('released_budget_balance', 18, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_portfolio_entries', function (Blueprint $table) {
            $table->id();
            $table->string('section', 64);
            $table->unsignedSmallInteger('budget_year');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data');
            $table->timestamps();
        });
    }
}
