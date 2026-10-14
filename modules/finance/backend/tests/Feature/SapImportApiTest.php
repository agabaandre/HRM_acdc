<?php

namespace Tests\Feature;

use App\Jobs\SyncSapImportToApmJob;
use App\Services\SapImportService;
use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsMinimalXlsx;
use Tests\TestCase;

class SapImportApiTest extends TestCase
{
    use BuildsMinimalXlsx;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'filesystems.disks.local.root' => sys_get_temp_dir().'/fin_sap_storage_'.uniqid(),
            'apm.base_url' => 'http://apm.test',
            'apm.api_prefix' => '/api/apm/v1',
            'apm.email' => 'finance-sync@africacdc.org',
            'apm.password' => 'secret',
            'apm.timeout' => 10,
        ]);
        DB::purge();
        DB::reconnect();
        Storage::fake('local');
        $this->createSapTables();
    }

    public function test_sap_upload_updates_matched_apm_codes_only(): void
    {
        Queue::fake();
        $year = (int) date('Y');
        Http::fake([
            'http://apm.test/api/apm/v1/auth/login' => Http::response([
                'success' => true,
                'data' => [
                    'access_token' => 'tok',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ],
            ], 200),
            'http://apm.test/api/apm/v1/fund-codes*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'id' => 9,
                        'code' => 'CDC0300001SP',
                        'year' => $year,
                        'is_active' => true,
                    ],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 100,
                    'total' => 1,
                ],
            ], 200),
            'http://apm.test/api/apm/v1/fund-codes/9' => Http::response([
                'success' => true,
                'data' => ['id' => 9],
            ], 200),
        ]);

        $token = 'sap-import-token';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 42,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $path = $this->makeMinimalXlsx([
            ['Fund center', 'Fund center Text', 'GL Account', 'Total Released Budget', 'Released Budget balance'],
            ['CDC0300001SP', 'Example', '', '6000000', '0'],
            ['CDC0300001SP', '', 'Total', '6000000', '35582.69'],
            ['CDC0300002SP', 'Other', '', '4500000', '0'],
            ['CDC0300002SP', '', 'Total', '4500000', '1990668.19'],
        ], 'Sheet1');

        $upload = new UploadedFile(
            $path,
            'EXPORT.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->withToken($token)->post('/api/v1/sap/import', ['file' => $upload]);
        $response->assertOk();
        $response->assertJsonPath('data.totals', 2);
        $response->assertJsonPath('data.apm_updated', 0);
        $response->assertJsonPath('data.sync_status', 'pending');

        $this->assertSame(4, DB::table('fin_sap_rows')->count());
        $this->assertSame(2, DB::table('fin_sap_fund_centers')->count());
        $this->assertSame(1, DB::table('fin_sap_imports')->where('is_latest', true)->count());

        $importId = (int) $response->json('data.import_id');
        Queue::assertPushed(SyncSapImportToApmJob::class, fn ($job) => $job->importId === $importId);

        $sync = app(SapImportService::class)->performApmSync($importId);
        $this->assertSame('synced', $sync['sync_status']);
        $this->assertSame(1, $sync['apm_updated']);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/fund-codes/9')) {
                return false;
            }
            $data = $request->data();

            return ($data['approved_budget'] ?? null) === '6000000.00'
                && ($data['uploaded_budget'] ?? null) === '6000000.00'
                && ($data['budget_balance'] ?? null) === '35582.69';
        });
    }

    public function test_duplicate_fund_centers_upsert_latest_balances(): void
    {
        Queue::fake();
        Http::fake([
            'http://apm.test/api/apm/v1/auth/login' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'tok', 'token_type' => 'bearer', 'expires_in' => 3600],
            ], 200),
            'http://apm.test/api/apm/v1/fund-codes*' => Http::response([
                'success' => true,
                'data' => [],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 100, 'total' => 0],
            ], 200),
        ]);

        $token = 'sap-dup-token';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 42,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $path = $this->makeMinimalXlsx([
            ['Fund center', 'Fund center Text', 'GL Account', 'Total Released Budget', 'Released Budget balance'],
            ['CDC0901031MB', 'A', '', '100', '0'],
            ['CDC0901031MB', '', 'Total', '100', '10'],
            ['CDC0901031MB', 'B', '', '250', '0'],
            ['CDC0901031MB', '', 'Total', '250', '77.25'],
            ['CDC0102004MB', '', 'Total', '50', '5'],
        ], 'Sheet1');

        $upload = new UploadedFile(
            $path,
            'EXPORT.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->withToken($token)->post('/api/v1/sap/import', ['file' => $upload]);
        $response->assertOk();
        $response->assertJsonPath('data.totals', 2);
        $response->assertJsonPath('data.sync_status', 'pending');
        Queue::assertPushed(SyncSapImportToApmJob::class);

        $this->assertSame(2, DB::table('fin_sap_fund_centers')->count());
        $row = DB::table('fin_sap_fund_centers')->where('fund_center', 'CDC0901031MB')->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(250.0, (float) $row->total_released_budget, 0.001);
        $this->assertEqualsWithDelta(77.25, (float) $row->released_budget_balance, 0.001);
    }

    private function createSapTables(): void
    {
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
        Schema::create('fin_sap_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->unsignedSmallInteger('budget_year');
            $table->string('fund_center', 64)->nullable();
            $table->string('budget_code', 64)->nullable();
            $table->string('cost_center', 64)->nullable();
            $table->string('fund_center_text', 255)->nullable();
            $table->string('gl_account', 64)->nullable();
            $table->string('gl_description', 255)->nullable();
            $table->boolean('is_total_row')->default(false);
            $table->boolean('is_expense_row')->default(false);
            $table->decimal('commitment', 18, 2)->default(0);
            $table->decimal('actual_expenditure', 18, 2)->default(0);
            $table->decimal('total_released_budget', 18, 2)->default(0);
            $table->json('payload');
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
            $table->unique(['import_id', 'fund_center']);
        });
    }

    public function test_period_replace_and_expense_drilldown(): void
    {
        Queue::fake();
        Http::fake([
            'http://apm.test/api/apm/v1/auth/login' => Http::response([
                'success' => true,
                'data' => ['access_token' => 'tok', 'token_type' => 'bearer', 'expires_in' => 3600],
            ], 200),
            'http://apm.test/api/apm/v1/fund-codes*' => Http::response([
                'success' => true,
                'data' => [],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 100, 'total' => 0],
            ], 200),
        ]);

        $token = 'sap-period-token';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 42,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $path = $this->makeMinimalXlsx([
            ['Fund center', 'Fund', 'Fund center Text', 'GL Account', 'GL Description', 'Total Released Budget', 'Commitment', 'Actual Expenditure', 'Released Budget balance'],
            ['CDC100205MB', 'PB-WDB12', 'CHSHP', '0000505000', 'Travel Ticket.', '394550', '0', '77465.83', '77101'],
            ['CDC100205MB', 'PB-WDB12', '', 'Total', '', '394550', '90404', '227045', '77101'],
        ], 'Sheet1');

        $upload = new UploadedFile($path, 'EXPORT.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $first = $this->withToken($token)->post('/api/v1/sap/import', [
            'file' => $upload,
            'period_ym' => '2026-09',
        ]);
        $first->assertOk();
        $first->assertJsonPath('data.period_ym', '2026-09');
        $this->assertSame(1, DB::table('fin_sap_imports')->count());

        $path2 = $this->makeMinimalXlsx([
            ['Fund center', 'Fund', 'Fund center Text', 'GL Account', 'GL Description', 'Total Released Budget', 'Commitment', 'Actual Expenditure', 'Released Budget balance'],
            ['CDC100205MB', 'PB-WDB12', 'CHSHP', '0000505001', 'DSA', '394550', '101110', '28918', '77101'],
            ['CDC100205MB', 'PB-WDB12', '', 'Total', '', '394550', '90404', '227045', '77101'],
        ], 'Sheet1');
        $upload2 = new UploadedFile($path2, 'EXPORT-2.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $second = $this->withToken($token)->post('/api/v1/sap/import', [
            'file' => $upload2,
            'period_ym' => '2026-09',
        ]);
        $second->assertOk();
        $this->assertSame(1, DB::table('fin_sap_imports')->count());
        $this->assertSame('EXPORT-2.xlsx', DB::table('fin_sap_imports')->value('original_filename'));

        $fc = DB::table('fin_sap_fund_centers')->where('fund_center', 'CDC100205MB')->first();
        $this->assertNotNull($fc);
        $this->assertSame('PB-WDB12', $fc->budget_code);

        $expenses = $this->withToken($token)->getJson('/api/v1/sap/fund-centers/CDC100205MB/expenses');
        $expenses->assertOk();
        $expenses->assertJsonPath('data.count', 1);
        $expenses->assertJsonPath('data.expenses.0.budget_code', 'PB-WDB12');
        $expenses->assertJsonPath('data.expenses.0.gl_account', '0000505001');
        $this->assertEqualsWithDelta(28918.0, (float) $expenses->json('data.expenses.0.actual_expenditure'), 0.01);

        // Legacy rows: GL present but is_expense_row never backfilled.
        $importId = (int) DB::table('fin_sap_imports')->value('id');
        DB::table('fin_sap_rows')->where('import_id', $importId)->update(['is_expense_row' => false]);
        $legacy = $this->withToken($token)->getJson('/api/v1/sap/fund-centers/CDC100205MB/expenses');
        $legacy->assertOk();
        $legacy->assertJsonPath('data.count', 1);
        $this->assertEqualsWithDelta(28918.0, (float) $legacy->json('data.expenses.0.actual_expenditure'), 0.01);
    }
}
