<?php

namespace Tests\Unit;

use App\Services\RiskReviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskReviewServiceTest extends TestCase
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
        Schema::create('rr_risks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timeline', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('rr_risk_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('inherent_score');
            $table->string('inherent_rating', 32)->nullable();
            $table->text('mitigation_strategy')->nullable();
            $table->string('timeline', 255)->nullable();
            $table->unsignedInteger('author_staff_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_timeline_defaults_to_previous(): void
    {
        $riskId = DB::table('rr_risks')->insertGetId([
            'name' => 'R',
            'timeline' => 'Risk-level timeline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $svc = new RiskReviewService;
        $svc->create($riskId, [
            'year' => 2026,
            'quarter' => 1,
            'likelihood' => 3,
            'impact' => 4,
            'mitigation_strategy' => 'First',
            'timeline' => 'Q1 plan',
        ], 1);

        $second = $svc->create($riskId, [
            'year' => 2026,
            'quarter' => 2,
            'likelihood' => 2,
            'impact' => 4,
            'mitigation_strategy' => 'Second',
        ], 1);

        $this->assertSame('Q1 plan', $second['timeline']);
    }
}
