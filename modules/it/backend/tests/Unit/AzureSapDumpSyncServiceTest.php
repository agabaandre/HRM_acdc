<?php

namespace Tests\Unit;

use App\Services\AzureBlobStorageClient;
use App\Services\AzureSapDumpSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AzureSapDumpSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'azure_sap.account_name' => 'sapcbp',
            'azure_sap.account_key' => 'dGVzdA==',
            'azure_sap.container' => 'sap',
            'azure_sap.prefix' => '',
            'azure_sap.delete_after_fetch' => true,
            'azure_sap.poll_hours' => 4,
        ]);
        DB::purge();
        DB::reconnect();
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

    public function test_sync_imports_and_deletes_remote_blob(): void
    {
        Queue::fake();
        $body = "Fund Center,G/L Account,Total Released Budget,Released Budget Balance,Fund,Commitment Amount,Actual Expenditure,Fund center Description\n"
            ."FC1,Total,100,40,BG,10,50,Test FC\n"
            ."FC1,400001,100,40,BG,10,50,Test FC\n";

        $client = new class (['account_name' => 'sapcbp', 'account_key' => 'dGVzdA==', 'container' => 'sap'], $body) extends AzureBlobStorageClient
        {
            public array $deleted = [];

            public function __construct(array $config, private readonly string $csvBody)
            {
                parent::__construct($config);
            }

            public function listBlobs(?string $prefix = null): array
            {
                return [[
                    'name' => 'testing.csv',
                    'size' => strlen($this->csvBody),
                    'last_modified' => 'Wed, 14 Oct 2026 12:00:00 GMT',
                    'content_type' => 'text/csv',
                    'is_directory' => false,
                ]];
            }

            public function download(string $blobName): array
            {
                return [
                    'body' => $this->csvBody,
                    'content_type' => 'text/csv',
                    'content_length' => strlen($this->csvBody),
                ];
            }

            public function delete(string $blobName): void
            {
                $this->deleted[] = $blobName;
            }
        };

        $sync = new AzureSapDumpSyncService(client: $client);
        $result = $sync->sync(true);

        $this->assertTrue($result['configured']);
        $this->assertSame(1, $result['files_seen']);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['imported']);
        $this->assertSame('testing.csv', $result['imported'][0]['name']);
        $this->assertTrue($result['imported'][0]['deleted']);
        $this->assertSame(['testing.csv'], $client->deleted);
        $this->assertSame('2026-10', $result['imported'][0]['period_ym']);

        $status = $sync->lastSyncStatus();
        $this->assertNotNull($status['last_sync_at']);
        $this->assertSame(1, $status['summary']['imported_count'] ?? null);
        $this->assertSame(1, $status['summary']['deleted_count'] ?? null);
    }

    public function test_sync_skips_when_not_configured(): void
    {
        config([
            'azure_sap.account_name' => '',
            'azure_sap.account_key' => '',
            'azure_sap.connection_string' => '',
        ]);
        $sync = new AzureSapDumpSyncService;
        $result = $sync->sync();
        $this->assertFalse($result['configured']);
        $this->assertNotEmpty($result['errors']);
    }
}
