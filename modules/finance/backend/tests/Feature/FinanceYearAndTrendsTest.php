<?php

namespace Tests\Feature;

use App\Support\FinancePermissions;
use App\Support\FinanceYearConfig;
use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceYearAndTrendsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.apm' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'cache.default' => 'array',
            'logging.default' => 'array',
        ]);
        DB::purge();
        DB::reconnect();
        DB::purge('apm');
        DB::reconnect('apm');
        Schema::create('fin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_sap_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('original_filename');
            $table->unsignedSmallInteger('budget_year');
            $table->string('period_ym', 7)->nullable();
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
            $table->string('budget_code', 128)->nullable();
            $table->string('fund_center_text', 255)->nullable();
            $table->decimal('total_released_budget', 18, 2)->default(0);
            $table->decimal('released_budget_balance', 18, 2)->default(0);
            $table->decimal('commitment', 18, 2)->default(0);
            $table->decimal('actual_expenditure', 18, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
        Schema::connection('apm')->create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('division_name');
            $table->string('division_short_name')->nullable();
        });
        Schema::connection('apm')->create('fund_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('division_id')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function test_financial_year_defaults_to_january_and_is_configurable(): void
    {
        $fy = new FinanceYearConfig;
        $this->assertSame(1, $fy->startMonth());
        $this->assertSame(2026, $fy->classifyPeriod('2026-03')['fy_year']);
        $this->assertSame(1, $fy->classifyPeriod('2026-03')['quarter']);

        $token = 'fy-settings';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $show = $this->withToken($token)->getJson('/api/v1/settings/financial-year');
        $show->assertOk();
        $show->assertJsonPath('data.start_month', 1);

        $save = $this->withToken($token)->putJson('/api/v1/settings/financial-year', [
            'start_month' => 7,
        ]);
        $save->assertOk();
        $save->assertJsonPath('data.start_month', 7);

        $fy2 = new FinanceYearConfig;
        $this->assertSame(7, $fy2->startMonth());
        // July FY: Jan 2026 is FY2025 Q3
        $jan = $fy2->classifyPeriod('2026-01');
        $this->assertSame(2025, $jan['fy_year']);
        $this->assertSame(3, $jan['quarter']);
        // July 2026 is FY2026 Q1
        $jul = $fy2->classifyPeriod('2026-07');
        $this->assertSame(2026, $jul['fy_year']);
        $this->assertSame(1, $jul['quarter']);
    }

    public function test_budget_execution_includes_monthly_and_quarterly_trends(): void
    {
        $token = 'fy-trends';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'role_id' => 1,
            'permissions' => [FinancePermissions::MODULE, RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        foreach (['2026-01' => 1000.0, '2026-02' => 1200.0, '2026-04' => 1500.0] as $period => $released) {
            $id = DB::table('fin_sap_imports')->insertGetId([
                'uploaded_by' => 1,
                'original_filename' => $period.'.xlsx',
                'budget_year' => 2026,
                'period_ym' => $period,
                'row_count' => 1,
                'total_row_count' => 1,
                'apm_updated_count' => 0,
                'sync_status' => 'synced',
                'is_latest' => $period === '2026-04',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('fin_sap_fund_centers')->insert([
                'import_id' => $id,
                'budget_year' => 2026,
                'fund_center' => 'CDC1',
                'budget_code' => 'PB-WDB12',
                'total_released_budget' => $released,
                'released_budget_balance' => $released * 0.25,
                'commitment' => 10,
                'actual_expenditure' => $released * 0.5,
                'payload' => json_encode([
                    'commitment' => 10,
                    'actual expenditure' => $released * 0.5,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $res = $this->withToken($token)->getJson('/api/v1/budget-execution?year=2026');
        $res->assertOk();
        $res->assertJsonPath('data.financial_year.start_month', 1);
        $res->assertJsonPath('data.financial_year.fy_year', 2026);
        $this->assertCount(12, $res->json('data.trends.monthly'));
        $this->assertCount(4, $res->json('data.trends.quarterly'));

        $jan = collect($res->json('data.trends.monthly'))->firstWhere('period_ym', '2026-01');
        $this->assertTrue((bool) $jan['has_data']);
        $this->assertEqualsWithDelta(1000.0, (float) $jan['approved_budget'], 0.01);

        $q1 = collect($res->json('data.trends.quarterly'))->firstWhere('quarter', 1);
        $this->assertTrue((bool) $q1['has_data']);
        // Latest month in Q1 is Feb → 1200 released
        $this->assertEqualsWithDelta(1200.0, (float) $q1['approved_budget'], 0.01);

        $q2 = collect($res->json('data.trends.quarterly'))->firstWhere('quarter', 2);
        $this->assertTrue((bool) $q2['has_data']);
        $this->assertEqualsWithDelta(1500.0, (float) $q2['approved_budget'], 0.01);
    }
}
