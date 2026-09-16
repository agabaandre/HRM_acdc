<?php

namespace Tests\Feature;

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

        $summary = $this->withToken($token)->getJson('/api/v1/portfolio/summary');
        $summary->assertOk();
        $summary->assertJsonPath('data.intramural.approved_budget', 1000);
        $summary->assertJsonPath('data.intramural.budget_balance', 250);
        $this->assertEqualsWithDelta(0.75, $summary->json('data.intramural.execution_rate'), 0.0001);

        $put = $this->withToken($token)->putJson('/api/v1/portfolio/sections/afef', [
            'rows' => [
                ['sort_order' => 0, 'data' => ['label' => 'AfEF b/f', 'amount' => 20054305]],
            ],
        ]);
        $put->assertOk();
        $put->assertJsonPath('data.rows.0.data.label', 'AfEF b/f');
        $this->assertSame(1, DB::table('fin_portfolio_entries')->where('section', 'afef')->count());
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
