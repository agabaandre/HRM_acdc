<?php

namespace Tests\Unit;

use App\Services\RatingBandResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RatingBandResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('rr_rating_key_versions');
        Schema::dropIfExists('rr_rating_bands');
        Schema::dropIfExists('rr_settings');

        Schema::create('rr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('rr_rating_bands', function (Blueprint $table) {
            $table->id();
            $table->string('rating', 32);
            $table->string('band_key', 32)->nullable();
            $table->unsignedTinyInteger('min_score');
            $table->unsignedTinyInteger('max_score');
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

        DB::table('rr_rating_bands')->insert([
            ['rating' => 'Low', 'band_key' => 'low', 'min_score' => 1, 'max_score' => 4, 'fill_color' => '#00B050', 'text_color' => '#FFFFFF', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['rating' => 'Medium', 'band_key' => 'medium', 'min_score' => 5, 'max_score' => 9, 'fill_color' => '#FFFF00', 'text_color' => '#1A1A1A', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['rating' => 'High', 'band_key' => 'high', 'min_score' => 10, 'max_score' => 15, 'fill_color' => '#FFC000', 'text_color' => '#1A1A1A', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['rating' => 'Critical', 'band_key' => 'critical', 'min_score' => 16, 'max_score' => 25, 'fill_color' => '#C00000', 'text_color' => '#FFFFFF', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_publish_freezes_bands_and_new_ranges_do_not_affect_old_version(): void
    {
        $resolver = new RatingBandResolver;
        $v1 = $resolver->publish('v1');
        $this->assertSame(1, $v1);
        $this->assertSame('critical', $resolver->resolve(20, 1)['band_key']);
        $this->assertSame('#C00000', $resolver->resolve(20, 1)['fill_color']);

        DB::table('rr_rating_bands')->where('band_key', 'critical')->update([
            'min_score' => 20,
            'max_score' => 25,
        ]);
        DB::table('rr_rating_bands')->where('band_key', 'high')->update([
            'min_score' => 10,
            'max_score' => 19,
        ]);
        $v2 = $resolver->publish('v2-tighter-critical');
        $this->assertSame(2, $v2);

        // Historical v1 still calls 16–19 Critical
        $this->assertSame('critical', $resolver->resolve(16, 1)['band_key']);
        // Active v2 reclassifies 16 as High
        $this->assertSame('high', $resolver->resolve(16, 2)['band_key']);
        // Stored key colour from v1 remains Critical red
        $style = $resolver->styleForStored('critical', 'Critical', 1);
        $this->assertSame('#C00000', $style['fill_color']);
    }

    public function test_calculator_stores_keys_from_active_version(): void
    {
        $resolver = new RatingBandResolver;
        $resolver->publish('calc');
        $calc = \App\Services\ResidualRiskCalculator::withResolver($resolver);
        $out = $calc->compute(4, 5, 0, false);
        $this->assertSame(20, $out['inherent_score']);
        $this->assertSame('Critical', $out['inherent_rating']);
        $this->assertSame('critical', $out['inherent_rating_key']);
        $this->assertSame(1, $out['rating_key_version']);
    }
}
