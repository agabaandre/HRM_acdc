<?php

namespace Tests\Feature;

use App\Support\RiskPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskDashboardApiTest extends TestCase
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
        Schema::create('rr_risks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('division_id')->nullable();
            $table->string('residual_rating', 32)->nullable();
            $table->unsignedTinyInteger('residual_score')->nullable();
            $table->unsignedTinyInteger('inherent_likelihood')->nullable();
            $table->unsignedTinyInteger('inherent_impact')->nullable();
            $table->unsignedBigInteger('risk_type_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->unsignedBigInteger('mitigation_effectiveness_id')->nullable();
            $table->string('source_business_unit', 255)->nullable();
            $table->string('unmapped_business_unit', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('rr_risk_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('rr_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('rr_mitigation_effectiveness', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        DB::table('rr_risks')->insert([
            ['name' => 'A', 'division_id' => 1, 'residual_rating' => 'Critical', 'residual_score' => 25, 'inherent_likelihood' => 5, 'inherent_impact' => 5, 'source_business_unit' => 'EPR', 'unmapped_business_unit' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'B', 'division_id' => 1, 'residual_rating' => 'Low', 'residual_score' => 2, 'inherent_likelihood' => 1, 'inherent_impact' => 2, 'source_business_unit' => null, 'unmapped_business_unit' => 'Weird', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_dashboard_kpi_totals_match_seeded_risks(): void
    {
        $token = 'dash-token';
        Cache::put('risk_api_token:'.$token, [
            'staff_id' => 1,
            'division_id' => 1,
            'permissions' => [RiskPermissions::VIEW_ALL],
            'api_token' => $token,
        ], 3600);

        $res = $this->withToken($token)->getJson('/api/v1/dashboard/summary');
        $res->assertOk();
        $this->assertSame(2, $res->json('total'));
        $this->assertSame(1, $res->json('unmatched_bu'));
        $this->assertSame(1, (int) $res->json('by_rating.Critical'));
    }
}
