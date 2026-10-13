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
                'total_released_budget' => 1000,
                'released_budget_balance' => 250,
                'payload' => json_encode(['commitment' => 100, 'actual expenditure' => 650]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'import_id' => $importId,
                'budget_year' => $year,
                'fund_center' => 'CDC2',
                'total_released_budget' => 500,
                'released_budget_balance' => 100,
                'payload' => json_encode(['commitment' => 50, 'actual expenditure' => 350]),
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
            $table->decimal('total_released_budget', 18, 2)->default(0);
            $table->decimal('released_budget_balance', 18, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }
}
