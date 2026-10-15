<?php

namespace Tests\Feature;

use App\Support\FinancePermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetExecutionApiTest extends TestCase
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
        $this->seedSap();
    }

    public function test_staff_sees_only_own_division(): void
    {
        $token = 'be-staff';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 50,
            'division_id' => 6,
            'role_id' => 1,
            'permissions' => [],
            'api_token' => $token,
        ], 3600);

        $res = $this->withToken($token)->getJson('/api/v1/budget-execution');
        $res->assertOk();
        $res->assertJsonPath('data.scope', 'division');
        $res->assertJsonPath('data.can_view_all', false);
        $res->assertJsonPath('data.show_org_charts', false);
        $res->assertJsonPath('data.show_budget_code_charts', true);
        $res->assertJsonPath('data.divisions.0.division_short_name', 'EPR');
        $this->assertCount(1, $res->json('data.divisions'));
        $res->assertJsonPath('data.kpis.approved_budget', 1000);
        $this->assertNotEmpty($res->json('data.budget_codes'));
    }

    public function test_finance_user_sees_all_divisions(): void
    {
        $token = 'be-finance';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 51,
            'division_id' => 99,
            'role_id' => 1,
            'permissions' => [FinancePermissions::MODULE],
            'api_token' => $token,
        ], 3600);

        $res = $this->withToken($token)->getJson('/api/v1/budget-execution');
        $res->assertOk();
        $res->assertJsonPath('data.scope', 'all');
        $res->assertJsonPath('data.can_view_all', true);
        $res->assertJsonPath('data.show_org_charts', true);
        $res->assertJsonPath('data.show_budget_code_charts', false);
        $this->assertGreaterThanOrEqual(2, count($res->json('data.divisions')));
        $this->assertNotEmpty($res->json('data.last_updated_at'));
        $this->assertNotEmpty($res->json('data.data_as_at'));
        $this->assertNotEmpty($res->json('data.sap_import.original_filename'));
    }

    public function test_apm_warm_token_sees_all_even_without_role_or_perms(): void
    {
        // Mimics a Redis/bridge payload that lost role_id/permissions — previously
        // wiped by_division to [] while Finance SPA (real user session) still had data.
        $token = 'apm_fin_warm_1_testhash';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 0,
            'role_id' => 0,
            'permissions' => [],
            'api_token' => $token,
            'source' => 'apm_finance_budget_execution_warm',
        ], 3600);

        $res = $this->withToken($token)->getJson('/api/v1/budget-execution');
        $res->assertOk();
        $res->assertJsonPath('data.can_view_all', true);
        $this->assertGreaterThanOrEqual(2, count($res->json('data.divisions')));
        $this->assertGreaterThan(0, (float) $res->json('data.kpis.approved_budget'));
    }

    public function test_admin_sees_all_and_can_filter(): void
    {
        $token = 'be-admin';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'role_id' => 10,
            'permissions' => [],
            'api_token' => $token,
        ], 3600);

        $all = $this->withToken($token)->getJson('/api/v1/budget-execution');
        $all->assertOk();
        $all->assertJsonPath('data.can_view_all', true);

        $filtered = $this->withToken($token)->getJson('/api/v1/budget-execution?division_id=6');
        $filtered->assertOk();
        $this->assertCount(1, $filtered->json('data.divisions'));
        $filtered->assertJsonPath('data.divisions.0.division_id', 6);

        $byCenter = $this->withToken($token)->getJson('/api/v1/budget-execution?fund_center=CDC1');
        $byCenter->assertOk();
        $this->assertCount(1, $byCenter->json('data.divisions'));
        $byCenter->assertJsonPath('data.kpis.fund_center_count', 1);
        $byCenter->assertJsonPath('data.kpis.approved_budget', 1000);
        $this->assertNotEmpty($byCenter->json('data.filter_catalog.fund_centers'));

        $byFund = $this->withToken($token)->getJson('/api/v1/budget-execution?fund=PB-WDB12');
        $byFund->assertOk();
        $this->assertCount(1, $byFund->json('data.divisions'));
        $byFund->assertJsonPath('data.divisions.0.fund_centers.0.fund_center', 'CDC1');
    }

    private function seedSap(): void
    {
        $year = (int) date('Y');
        DB::table('fin_sap_imports')->insert([
            'uploaded_by' => 1,
            'original_filename' => 'x.xlsx',
            'budget_year' => $year,
            'row_count' => 2,
            'total_row_count' => 2,
            'apm_updated_count' => 0,
            'sync_status' => 'synced',
            'is_latest' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $importId = (int) DB::table('fin_sap_imports')->max('id');
        DB::table('fin_sap_fund_centers')->insert([
            [
                'import_id' => $importId,
                'budget_year' => $year,
                'fund_center' => 'CDC1',
                'budget_code' => 'PB-WDB12',
                'total_released_budget' => 1000,
                'released_budget_balance' => 250,
                'commitment' => 100,
                'actual_expenditure' => 650,
                'payload' => json_encode(['fund' => 'PB-WDB12', 'commitment' => 100, 'actual expenditure' => 650]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'import_id' => $importId,
                'budget_year' => $year,
                'fund_center' => 'CDC2',
                'budget_code' => 'PB-KOR01',
                'total_released_budget' => 500,
                'released_budget_balance' => 100,
                'commitment' => 50,
                'actual_expenditure' => 350,
                'payload' => json_encode(['fund' => 'PB-KOR01', 'commitment' => 50, 'actual expenditure' => 350]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

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
            ['id' => 6, 'division_name' => 'Emergency Preparedness and Response', 'division_short_name' => 'EPR'],
            ['id' => 7, 'division_name' => 'Laboratory Systems', 'division_short_name' => 'LSN'],
        ]);
        DB::connection('apm')->table('fund_codes')->insert([
            ['code' => 'CDC1', 'year' => $year, 'division_id' => 6, 'is_active' => 1],
            ['code' => 'CDC2', 'year' => $year, 'division_id' => 7, 'is_active' => 1],
        ]);
    }

    private function createTables(): void
    {
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
            $table->string('budget_code', 128)->nullable();
            $table->string('fund_center_text', 255)->nullable();
            $table->decimal('total_released_budget', 18, 2)->default(0);
            $table->decimal('released_budget_balance', 18, 2)->default(0);
            $table->decimal('commitment', 18, 2)->default(0);
            $table->decimal('actual_expenditure', 18, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_sap_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->unsignedSmallInteger('budget_year');
            $table->string('fund_center', 64)->nullable();
            $table->string('budget_code', 64)->nullable();
            $table->string('gl_account', 64)->nullable();
            $table->boolean('is_total_row')->default(false);
            $table->boolean('is_expense_row')->default(false);
            $table->json('payload');
            $table->timestamps();
        });
    }
}
