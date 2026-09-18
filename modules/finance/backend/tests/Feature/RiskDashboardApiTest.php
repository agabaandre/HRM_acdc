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
            $table->string('inherent_rating', 32)->nullable();
            $table->unsignedTinyInteger('inherent_score')->nullable();
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
        Schema::create('rr_rating_bands', function (Blueprint $table) {
            $table->id();
            $table->string('rating', 32);
            $table->string('band_key', 32)->nullable();
            $table->unsignedTinyInteger('min_score')->default(1);
            $table->unsignedTinyInteger('max_score')->default(25);
            $table->string('fill_color', 7)->nullable();
            $table->string('text_color', 7)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('rr_rating_key_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('label', 128)->nullable();
            $table->json('bands_json');
            $table->timestamps();
        });
        Schema::create('rr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('rr_rating_bands')->insert([
            ['rating' => 'Low', 'band_key' => 'low', 'min_score' => 1, 'max_score' => 4, 'fill_color' => '#00B050', 'text_color' => '#FFFFFF', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['rating' => 'Critical', 'band_key' => 'critical', 'min_score' => 16, 'max_score' => 25, 'fill_color' => '#C00000', 'text_color' => '#FFFFFF', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('rr_risks')->insert([
            ['name' => 'A', 'division_id' => 1, 'residual_rating' => 'Critical', 'residual_score' => 25, 'inherent_rating' => 'Critical', 'inherent_score' => 25, 'inherent_likelihood' => 5, 'inherent_impact' => 5, 'source_business_unit' => 'EPR', 'unmapped_business_unit' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'B', 'division_id' => 1, 'residual_rating' => 'Low', 'residual_score' => 2, 'inherent_rating' => 'Low', 'inherent_score' => 2, 'inherent_likelihood' => 1, 'inherent_impact' => 2, 'source_business_unit' => null, 'unmapped_business_unit' => 'Weird', 'created_at' => now(), 'updated_at' => now()],
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
        $this->assertSame(2, (int) $res->json('kpis.total_risks'));
        $this->assertSame(1, (int) $res->json('kpis.critical_inherent'));
        $this->assertSame(1, (int) $res->json('kpis.low_inherent'));
    }
}
