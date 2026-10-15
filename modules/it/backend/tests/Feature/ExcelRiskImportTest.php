<?php

namespace Tests\Feature;

use App\Services\ExcelRiskImportService;
use App\Services\StaffPortalOrgClient;
use Database\Seeders\RiskLookupSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsMinimalXlsx;
use Tests\TestCase;

class ExcelRiskImportTest extends TestCase
{
    use BuildsMinimalXlsx;
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();
        DB::reconnect();

        $this->createRiskTables();
        $this->seed(RiskLookupSeeder::class);
    }

    public function test_import_inserts_risk_with_hod_owner(): void
    {
        $path = $this->makeFixtureXlsx([
            ['Business Unit', 'Enterprise Risk Theme', 'Risk Name', 'Risk Consequence', 'Root Causes', 'Risk Type', 'Likelihood (L) of Risk happening', 'Score of Likelihood', 'Impact (I) if Risk happens', 'Score of Impact', 'Inherent Risk Score', 'Mitigation', 'Management Response', 'Timeline', 'Status Update', 'Responsible Owner', 'Action Update', 'Date of Update', 'Status Update verified by Africa CDC OIO', 'Inherent Risk Rating', 'Mitigation Effectiveness (reviewer assessed)'],
            ['PHC/CHSHP', '2. Workforce sustainability and institutional capability', 'Fixture short-term contracts risk', 'Consequence text', 'Root cause text', 'Operational', 'Certain', '5', 'Critical', '5', '25', 'Mitigate', '', '', 'Open - Extended', 'HOD', '', '', '', 'Critical', ''],
        ]);

        $org = [
            'divisions' => [
                [
                    'division_id' => 35,
                    'division_short_name' => 'CHSHP',
                    'division_name' => 'Community Health Systems & Health Promotion',
                    'directorate_id' => 1,
                    'division_head' => 169,
                ],
            ],
            'directorates' => [
                [
                    'id' => 1,
                    'name' => 'Centre for Primary Health Care',
                    'aliases' => ['PHC'],
                ],
            ],
        ];

        $client = $this->createMock(StaffPortalOrgClient::class);
        $client->method('fetchOrg')->willReturn($org);
        $this->app->instance(StaffPortalOrgClient::class, $client);

        $result = app(ExcelRiskImportService::class)->import($path, $org);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['unmatched']);
        $this->assertSame(1, $result['owners_defaulted']);

        $risk = DB::table('rr_risks')->first();
        $this->assertNotNull($risk);
        $this->assertSame(35, (int) $risk->division_id);
        $this->assertSame(1, (int) $risk->directorate_id);
        $this->assertSame('active_imported', $risk->workflow_state);
        $this->assertSame(25, (int) $risk->inherent_score);
        $this->assertSame(25, (int) $risk->residual_score);

        $owner = DB::table('rr_risk_owners')->where('risk_id', $risk->id)->first();
        $this->assertNotNull($owner);
        $this->assertSame(169, (int) $owner->staff_id);
        $this->assertTrue((bool) $owner->is_default_hod);

        @unlink($path);
    }

    private function createRiskTables(): void
    {
        Schema::create('rr_likelihoods', function (Blueprint $table) {
            $table->id();
            $table->string('label', 64);
            $table->unsignedTinyInteger('score')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('rr_impacts', function (Blueprint $table) {
            $table->id();
            $table->string('label', 64);
            $table->unsignedTinyInteger('score')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('rr_risk_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('rr_enterprise_themes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->nullable();
            $table->string('name', 255)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('rr_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('rr_mitigation_effectiveness', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->unsignedTinyInteger('likelihood_reduction')->default(0);
            $table->boolean('is_assessed')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('rr_rating_bands', function (Blueprint $table) {
            $table->id();
            $table->string('rating', 32);
            $table->unsignedTinyInteger('min_score');
            $table->unsignedTinyInteger('max_score');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('rr_risks', function (Blueprint $table) {
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
        });
        Schema::create('rr_risk_owners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_id');
            $table->unsignedInteger('staff_id');
            $table->boolean('is_default_hod')->default(false);
            $table->timestamps();
        });
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeFixtureXlsx(array $rows): string
    {
        return $this->makeMinimalXlsx($rows, 'Risk Register');
    }
}
