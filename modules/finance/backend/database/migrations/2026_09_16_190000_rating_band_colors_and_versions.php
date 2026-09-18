<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rr_rating_bands', function (Blueprint $table) {
            $table->string('band_key', 32)->nullable()->after('rating');
            $table->string('fill_color', 7)->nullable()->after('max_score');
            $table->string('text_color', 7)->nullable()->after('fill_color');
        });

        $defaults = [
            'Low' => ['band_key' => 'low', 'fill_color' => '#00B050', 'text_color' => '#FFFFFF'],
            'Medium' => ['band_key' => 'medium', 'fill_color' => '#FFFF00', 'text_color' => '#1A1A1A'],
            'High' => ['band_key' => 'high', 'fill_color' => '#FFC000', 'text_color' => '#1A1A1A'],
            'Critical' => ['band_key' => 'critical', 'fill_color' => '#C00000', 'text_color' => '#FFFFFF'],
        ];
        foreach ($defaults as $rating => $cols) {
            DB::table('rr_rating_bands')->where('rating', $rating)->update($cols + ['updated_at' => now()]);
        }
        foreach (DB::table('rr_rating_bands')->whereNull('band_key')->orWhere('band_key', '')->get() as $row) {
            DB::table('rr_rating_bands')->where('id', $row->id)->update([
                'band_key' => strtolower(str_replace(' ', '_', (string) $row->rating)),
                'fill_color' => $row->fill_color ?: '#94A3B8',
                'text_color' => $row->text_color ?: '#0F172A',
                'updated_at' => now(),
            ]);
        }

        Schema::create('rr_rating_key_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('label', 128)->nullable();
            $table->json('bands_json');
            $table->timestamps();
        });

        Schema::table('rr_risks', function (Blueprint $table) {
            $table->unsignedInteger('rating_key_version')->nullable()->after('residual_rating');
            $table->string('inherent_rating_key', 32)->nullable()->after('inherent_rating');
            $table->string('residual_rating_key', 32)->nullable()->after('residual_rating');
        });

        Schema::table('rr_risk_reviews', function (Blueprint $table) {
            $table->unsignedInteger('rating_key_version')->nullable()->after('inherent_rating');
            $table->string('inherent_rating_key', 32)->nullable()->after('inherent_rating');
        });

        $bands = DB::table('rr_rating_bands')->orderBy('sort_order')->get()->map(static function ($b) {
            return [
                'band_key' => (string) ($b->band_key ?: strtolower((string) $b->rating)),
                'rating' => (string) $b->rating,
                'min_score' => (int) $b->min_score,
                'max_score' => (int) $b->max_score,
                'fill_color' => (string) ($b->fill_color ?: '#94A3B8'),
                'text_color' => (string) ($b->text_color ?: '#0F172A'),
                'sort_order' => (int) $b->sort_order,
            ];
        })->values()->all();

        if ($bands !== []) {
            DB::table('rr_rating_key_versions')->insert([
                'version' => 1,
                'label' => 'Initial (Excel)',
                'bands_json' => json_encode($bands),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rr_settings')->updateOrInsert(
                ['key' => 'active_rating_key_version'],
                ['value' => '1', 'updated_at' => now(), 'created_at' => now()]
            );

            // Backfill stored keys on existing risks from their rating labels (version 1).
            foreach (DB::table('rr_risks')->select('id', 'inherent_rating', 'residual_rating')->get() as $risk) {
                $inhKey = $risk->inherent_rating
                    ? strtolower(str_replace(' ', '_', (string) $risk->inherent_rating))
                    : null;
                $resKey = $risk->residual_rating
                    ? strtolower(str_replace(' ', '_', (string) $risk->residual_rating))
                    : null;
                DB::table('rr_risks')->where('id', $risk->id)->update([
                    'inherent_rating_key' => $inhKey,
                    'residual_rating_key' => $resKey,
                    'rating_key_version' => 1,
                ]);
            }
            foreach (DB::table('rr_risk_reviews')->select('id', 'inherent_rating')->get() as $rev) {
                DB::table('rr_risk_reviews')->where('id', $rev->id)->update([
                    'inherent_rating_key' => $rev->inherent_rating
                        ? strtolower(str_replace(' ', '_', (string) $rev->inherent_rating))
                        : null,
                    'rating_key_version' => 1,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('rr_risk_reviews', function (Blueprint $table) {
            $table->dropColumn(['rating_key_version', 'inherent_rating_key']);
        });
        Schema::table('rr_risks', function (Blueprint $table) {
            $table->dropColumn(['rating_key_version', 'inherent_rating_key', 'residual_rating_key']);
        });
        Schema::dropIfExists('rr_rating_key_versions');
        Schema::table('rr_rating_bands', function (Blueprint $table) {
            $table->dropColumn(['band_key', 'fill_color', 'text_color']);
        });
        DB::table('rr_settings')->where('key', 'active_rating_key_version')->delete();
    }
};
