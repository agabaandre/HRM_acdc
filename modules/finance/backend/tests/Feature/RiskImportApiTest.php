<?php

namespace Tests\Feature;

use App\Services\ExcelRiskImportService;
use App\Services\StaffPortalOrgClient;
use App\Support\RiskPermissions;
use Database\Seeders\RiskLookupSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RiskImportApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'filesystems.disks.local.root' => sys_get_temp_dir().'/rr_storage_'.uniqid(),
        ]);
        DB::purge();
        DB::reconnect();
        Storage::fake('local');
        $this->createTables();
        $this->seed(RiskLookupSeeder::class);
        DB::table('rr_settings')->insert([
            'key' => 'import_enabled',
            'value' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_preview_commit_and_manual_map(): void
    {
        $org = [
            'divisions' => [
                [
                    'division_id' => 35,
                    'division_short_name' => 'CHSHP',
                    'division_name' => 'Community Health Systems & Health Promotion',
                    'directorate_id' => 1,
                    'division_head' => 169,
                ],
                [
                    'division_id' => 24,
                    'division_short_name' => 'DERSE',
                    'division_name' => 'External Relations',
                    'directorate_id' => null,
                    'division_head' => 50,
                ],
            ],
            'directorates' => [
                ['id' => 1, 'name' => 'Centre for Primary Health Care', 'aliases' => ['PHC']],
            ],
        ];
        $client = $this->createMock(StaffPortalOrgClient::class);
        $client->method('fetchOrg')->willReturn($org);
        $this->app->instance(StaffPortalOrgClient::class, $client);

        $token = 'import-token';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 35,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $path = $this->makeFixtureXlsx([
            ['Business Unit', 'Enterprise Risk Theme', 'Risk Name', 'Risk Consequence', 'Root Causes', 'Risk Type', 'Likelihood (L) of Risk happening', 'Score of Likelihood', 'Impact (I) if Risk happens', 'Score of Impact', 'Inherent Risk Score', 'Mitigation', 'Management Response', 'Timeline', 'Status Update', 'Responsible Owner', 'Action Update', 'Date of Update', 'Status Update verified by Africa CDC OIO', 'Inherent Risk Rating', 'Mitigation Effectiveness (reviewer assessed)'],
            ['PHC/CHSHP', '2. Workforce sustainability and institutional capability', 'Matched risk', 'C', 'R', 'Operational', 'Certain', '5', 'Critical', '5', '25', 'M', '', '', 'Open - Extended', 'HOD', '', '', '', 'Critical', ''],
            ['Partnerships', '1. Sustainable financing and resource mobilization', 'Unmatched risk', 'C', 'R', 'Reputational', 'Likely', '3', 'Major', '4', '12', 'M', '', '', 'Open - Extended', 'HOD', '', '', '', 'High', ''],
        ]);

        $upload = new UploadedFile($path, 'fixture.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $preview = $this->withToken($token)->post('/api/v1/import/preview', ['file' => $upload]);
        $preview->assertOk();
        $batchId = (int) $preview->json('data.batch_id');
        $this->assertSame(1, $preview->json('data.matched'));
        $this->assertSame(1, $preview->json('data.unmatched'));

        $commit = $this->withToken($token)->postJson("/api/v1/import/{$batchId}/commit-matched");
        $commit->assertOk();
        $this->assertSame(1, $commit->json('data.imported'));
        $this->assertSame(1, $commit->json('data.staged'));
        $this->assertSame(1, DB::table('rr_risks')->count());

        $apply = $this->withToken($token)->postJson("/api/v1/import/{$batchId}/apply-mappings", [
            'mappings' => [
                ['business_unit' => 'Partnerships', 'division_id' => 24],
            ],
        ]);
        $apply->assertOk();
        $this->assertSame(1, $apply->json('data.imported'));
        $this->assertSame(0, $apply->json('data.remaining'));
        $this->assertSame(2, DB::table('rr_risks')->count());
        $mapped = DB::table('rr_risks')->where('name', 'Unmatched risk')->first();
        $this->assertSame(24, (int) $mapped->division_id);
        $this->assertNull($mapped->unmapped_business_unit);

        @unlink($path);
    }

    public function test_import_disabled_blocks_preview(): void
    {
        DB::table('rr_settings')->where('key', 'import_enabled')->update(['value' => '0']);
        $token = 'import-token-2';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::MANAGE],
            'api_token' => $token,
        ], 3600);

        $path = $this->makeFixtureXlsx([
            ['Business Unit', 'Risk Name'],
            ['EPR', 'X'],
        ]);
        $upload = new UploadedFile($path, 'fixture.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->withToken($token)->post('/api/v1/import/preview', ['file' => $upload])->assertForbidden();
        @unlink($path);
    }

    private function createTables(): void
    {
        foreach ([
            'rr_likelihoods' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('label', 64);
                $table->unsignedTinyInteger('score')->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            }),
            'rr_impacts' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('label', 64);
                $table->unsignedTinyInteger('score')->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            }),
            'rr_risk_types' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('name', 128)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            }),
            'rr_enterprise_themes' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->nullable();
                $table->string('name', 255)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            }),
            'rr_statuses' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            }),
            'rr_mitigation_effectiveness' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->unique();
                $table->unsignedTinyInteger('likelihood_reduction')->default(0);
                $table->boolean('is_assessed')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            }),
            'rr_rating_bands' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('rating', 32);
                $table->unsignedTinyInteger('min_score');
                $table->unsignedTinyInteger('max_score');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            }),
            'rr_risks' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('import_row_number')->nullable();
                $table->string('source_business_unit', 255)->nullable();
                $table->unsignedInteger('division_id')->nullable();
                $table->unsignedInteger('directorate_id')->nullable();
                $table->string('unmapped_business_unit', 255)->nullable();
                $table->unsignedBigInteger('enterprise_theme_id')->nullable();
                $table->string('name', 512);
                $table->text('consequence')->nullable();
                $table->text('root_causes')->nullable();
                $table->unsignedBigInteger('risk_type_id')->nullable();
                $table->unsignedTinyInteger('inherent_likelihood')->nullable();
                $table->unsignedTinyInteger('inherent_impact')->nullable();
                $table->unsignedTinyInteger('inherent_score')->nullable();
                $table->string('inherent_rating', 32)->nullable();
                $table->text('mitigation')->nullable();
                $table->text('management_response')->nullable();
                $table->string('timeline', 255)->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->text('action_update')->nullable();
                $table->date('date_of_update')->nullable();
                $table->text('oio_verification_notes')->nullable();
                $table->unsignedBigInteger('mitigation_effectiveness_id')->nullable();
                $table->unsignedTinyInteger('residual_likelihood')->nullable();
                $table->unsignedTinyInteger('residual_impact')->nullable();
                $table->unsignedTinyInteger('residual_score')->nullable();
                $table->string('residual_rating', 32)->nullable();
                $table->string('risk_movement', 64)->nullable();
                $table->string('workflow_state', 64)->default('draft');
                $table->boolean('imported')->default(false);
                $table->timestamps();
            }),
            'rr_risk_owners' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('risk_id');
                $table->unsignedInteger('staff_id');
                $table->boolean('is_default_hod')->default(false);
                $table->timestamps();
            }),
            'rr_settings' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            }),
            'rr_import_batches' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->string('original_filename', 255);
                $table->string('stored_path', 512);
                $table->string('status', 32)->default('preview');
                $table->unsignedInteger('matched_count')->default(0);
                $table->unsignedInteger('unmatched_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->unsignedInteger('imported_count')->default(0);
                $table->unsignedInteger('staged_count')->default(0);
                $table->unsignedInteger('mapped_imported_count')->default(0);
                $table->json('unmatched_summary')->nullable();
                $table->unsignedInteger('created_by_staff_id')->nullable();
                $table->timestamps();
            }),
            'rr_import_staged_rows' => fn (Blueprint $t) => tap($t, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id');
                $table->unsignedInteger('excel_row')->nullable();
                $table->string('business_unit', 255)->nullable();
                $table->json('payload');
                $table->string('status', 32)->default('pending');
                $table->unsignedInteger('mapped_division_id')->nullable();
                $table->unsignedInteger('mapped_directorate_id')->nullable();
                $table->unsignedBigInteger('imported_risk_id')->nullable();
                $table->timestamps();
            }),
        ] as $name => $cb) {
            Schema::create($name, $cb);
        }
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeFixtureXlsx(array $rows): string
    {
        $path = sys_get_temp_dir().'/rr_imp_'.uniqid('', true).'.xlsx';
        $shared = [];
        $sharedIndex = [];
        $sheetRowsXml = '';
        foreach ($rows as $rIdx => $cols) {
            $rowNum = $rIdx + 1;
            $cellsXml = '';
            foreach ($cols as $cIdx => $value) {
                if (! isset($sharedIndex[$value])) {
                    $sharedIndex[$value] = count($shared);
                    $shared[] = $value;
                }
                $ref = $this->colLetters($cIdx + 1).$rowNum;
                $cellsXml .= '<c r="'.$ref.'" t="s"><v>'.$sharedIndex[$value].'</v></c>';
            }
            $sheetRowsXml .= '<row r="'.$rowNum.'">'.$cellsXml.'</row>';
        }
        $sharedXml = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        foreach ($shared as $s) {
            $sharedXml .= '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>';
        }
        $sharedXml .= '</sst>';
        $sheetXml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRowsXml.'</sheetData></worksheet>';
        $workbookXml = '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Risk Register" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>';
        $contentTypes = '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>';
        $rootRels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
        $zip->close();

        return $path;
    }

    private function colLetters(int $index): string
    {
        $s = '';
        while ($index > 0) {
            $index--;
            $s = chr(65 + ($index % 26)).$s;
            $index = intdiv($index, 26);
        }

        return $s;
    }
}
