<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Settings\Services\DivisionRiskFocalService;
use Tests\TestCase;

class RiskFocalPersonMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('divisions', function (Blueprint $table): void {
            $table->increments('division_id');
            $table->string('division_name');
            $table->unsignedInteger('focal_person')->nullable();
            $table->unsignedInteger('division_head')->nullable();
        });
    }

    public function test_risk_focal_defaults_from_focal_person(): void
    {
        DB::table('divisions')->insert([
            'division_id' => 1,
            'division_name' => 'Test',
            'focal_person' => 62,
            'division_head' => 169,
        ]);

        DivisionRiskFocalService::ensureColumnAndBackfill();

        $this->assertTrue(Schema::hasColumn('divisions', 'risk_focal_person'));
        $this->assertSame(62, (int) DB::table('divisions')->where('division_id', 1)->value('risk_focal_person'));
    }

    public function test_backfill_does_not_overwrite_existing_risk_focal(): void
    {
        DivisionRiskFocalService::ensureColumnAndBackfill();
        DB::table('divisions')->insert([
            'division_id' => 2,
            'division_name' => 'Other',
            'focal_person' => 10,
            'division_head' => 11,
            'risk_focal_person' => 99,
        ]);

        DivisionRiskFocalService::ensureColumnAndBackfill();

        $this->assertSame(99, (int) DB::table('divisions')->where('division_id', 2)->value('risk_focal_person'));
    }
}
