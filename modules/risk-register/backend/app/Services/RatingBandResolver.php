<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Versioned score→rating key bands (Excel heat-map colours).
 *
 * Working copy lives in rr_rating_bands. Publishing freezes a JSON snapshot.
 * Risks/reviews store rating_key_version + *_rating_key so later band edits
 * never reclassify historical scores.
 */
final class RatingBandResolver
{
    public const LOOKUPS_CACHE_KEY = 'rr_lookups_payload_v2';

    /**
     * @return list<array{band_key:string,rating:string,min_score:int,max_score:int,fill_color:string,text_color:string,sort_order:int}>
     */
    public function workingBands(): array
    {
        return DB::table('rr_rating_bands')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($b) => $this->normalizeBand((array) $b))
            ->values()
            ->all();
    }

    public function activeVersion(): int
    {
        $v = (int) (DB::table('rr_settings')->where('key', 'active_rating_key_version')->value('value') ?? 0);
        if ($v > 0) {
            return $v;
        }

        $latest = (int) (DB::table('rr_rating_key_versions')->max('version') ?? 0);
        if ($latest > 0) {
            return $latest;
        }

        return $this->publish('Bootstrap');
    }

    /**
     * @return list<array{band_key:string,rating:string,min_score:int,max_score:int,fill_color:string,text_color:string,sort_order:int}>
     */
    public function bandsForVersion(?int $version = null): array
    {
        $version ??= $this->activeVersion();
        $json = DB::table('rr_rating_key_versions')->where('version', $version)->value('bands_json');
        if (! is_string($json) || $json === '') {
            return $this->workingBands();
        }
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return $this->workingBands();
        }

        return array_values(array_map(fn ($b) => $this->normalizeBand(is_array($b) ? $b : []), $decoded));
    }

    /**
     * @return array{band_key:string,rating:string,min_score:int,max_score:int,fill_color:string,text_color:string,sort_order:int}
     */
    public function resolve(int $score, ?int $version = null): array
    {
        $score = max(1, min(25, $score));
        $bands = $this->bandsForVersion($version);
        foreach ($bands as $band) {
            if ($score >= $band['min_score'] && $score <= $band['max_score']) {
                return $band;
            }
        }

        return $bands[array_key_last($bands)] ?? [
            'band_key' => 'unrated',
            'rating' => 'Unrated',
            'min_score' => $score,
            'max_score' => $score,
            'fill_color' => '#94A3B8',
            'text_color' => '#0F172A',
            'sort_order' => 0,
        ];
    }

    /**
     * @return array{version:int,inherent_rating:string,inherent_rating_key:string,residual_rating:string,residual_rating_key:string}
     */
    public function resolvePair(int $inherentScore, int $residualScore): array
    {
        $version = $this->activeVersion();
        $inh = $this->resolve($inherentScore, $version);
        $res = $this->resolve($residualScore, $version);

        return [
            'version' => $version,
            'inherent_rating' => $inh['rating'],
            'inherent_rating_key' => $inh['band_key'],
            'residual_rating' => $res['rating'],
            'residual_rating_key' => $res['band_key'],
        ];
    }

    public function ratingForScore(int $score, ?int $version = null): string
    {
        return $this->resolve($score, $version)['rating'];
    }

    /**
     * @return callable(int): string
     */
    public function ratingCallable(?int $version = null): callable
    {
        $version ??= $this->activeVersion();

        return fn (int $score): string => $this->ratingForScore($score, $version);
    }

    /**
     * @return array{fill_color:string,text_color:string,rating:?string,band_key:?string}
     */
    public function styleForStored(?string $bandKey, ?string $rating, ?int $version): array
    {
        $bands = $this->bandsForVersion($version);
        if ($bandKey) {
            foreach ($bands as $band) {
                if ($band['band_key'] === $bandKey) {
                    return [
                        'fill_color' => $band['fill_color'],
                        'text_color' => $band['text_color'],
                        'rating' => $band['rating'],
                        'band_key' => $band['band_key'],
                    ];
                }
            }
        }
        if ($rating) {
            foreach ($bands as $band) {
                if (strcasecmp($band['rating'], $rating) === 0) {
                    return [
                        'fill_color' => $band['fill_color'],
                        'text_color' => $band['text_color'],
                        'rating' => $band['rating'],
                        'band_key' => $band['band_key'],
                    ];
                }
            }
        }

        return [
            'fill_color' => '#E2E8F0',
            'text_color' => '#334155',
            'rating' => $rating,
            'band_key' => $bandKey,
        ];
    }

    public function activateRatingVersion(int $version): void
    {
        $exists = DB::table('rr_rating_key_versions')->where('version', $version)->exists();
        if (! $exists) {
            throw new \RuntimeException('Rating key version not found.');
        }
        DB::table('rr_settings')->updateOrInsert(
            ['key' => 'active_rating_key_version'],
            ['value' => (string) $version, 'updated_at' => now(), 'created_at' => now()]
        );

        // Sync working copy to the activated snapshot so Settings editor matches profiling.
        $bands = $this->bandsForVersion($version);
        DB::table('rr_rating_bands')->delete();
        foreach ($bands as $i => $band) {
            DB::table('rr_rating_bands')->insert([
                'rating' => $band['rating'],
                'band_key' => $band['band_key'],
                'min_score' => $band['min_score'],
                'max_score' => $band['max_score'],
                'fill_color' => $band['fill_color'],
                'text_color' => $band['text_color'],
                'sort_order' => $band['sort_order'] ?: ($i + 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->forgetLookupsCache();
    }

    /**
     * True when any risk/review still references this band key or rating label.
     */
    public function bandIsInUse(string $bandKey, ?string $rating = null): bool
    {
        $key = strtolower(trim($bandKey));
        $ratingLabel = $rating !== null ? trim($rating) : null;

        $riskQ = DB::table('rr_risks')->where(function ($q) use ($key, $ratingLabel) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('rr_risks', 'inherent_rating_key')) {
                $q->where('inherent_rating_key', $key)->orWhere('residual_rating_key', $key);
            }
            if ($ratingLabel) {
                $q->orWhere('inherent_rating', $ratingLabel)->orWhere('residual_rating', $ratingLabel);
            }
        });
        if ($riskQ->exists()) {
            return true;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('rr_risk_reviews')) {
            return false;
        }

        return DB::table('rr_risk_reviews')->where(function ($q) use ($key, $ratingLabel) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('rr_risk_reviews', 'inherent_rating_key')) {
                $q->where('inherent_rating_key', $key);
            }
            if ($ratingLabel) {
                $q->orWhere('inherent_rating', $ratingLabel);
            }
        })->exists();
    }

    public function publish(?string $label = null): int
    {
        $next = ((int) (DB::table('rr_rating_key_versions')->max('version') ?? 0)) + 1;
        $bands = $this->workingBands();
        DB::table('rr_rating_key_versions')->insert([
            'version' => $next,
            'label' => $label ?: ('Version '.$next),
            'bands_json' => json_encode($bands),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('rr_settings')->updateOrInsert(
            ['key' => 'active_rating_key_version'],
            ['value' => (string) $next, 'updated_at' => now(), 'created_at' => now()]
        );
        $this->forgetLookupsCache();

        return $next;
    }

    public function forgetLookupsCache(): void
    {
        Cache::forget(self::LOOKUPS_CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $b
     * @return array{band_key:string,rating:string,min_score:int,max_score:int,fill_color:string,text_color:string,sort_order:int}
     */
    private function normalizeBand(array $b): array
    {
        $rating = (string) ($b['rating'] ?? 'Unrated');
        $key = (string) ($b['band_key'] ?? '');
        if ($key === '') {
            $key = strtolower(str_replace(' ', '_', $rating));
        }

        return [
            'id' => isset($b['id']) ? (int) $b['id'] : null,
            'band_key' => $key,
            'rating' => $rating,
            'min_score' => (int) ($b['min_score'] ?? 1),
            'max_score' => (int) ($b['max_score'] ?? 25),
            'fill_color' => $this->hex((string) ($b['fill_color'] ?? '#94A3B8')),
            'text_color' => $this->hex((string) ($b['text_color'] ?? '#0F172A')),
            'sort_order' => (int) ($b['sort_order'] ?? 0),
        ];
    }

    private function hex(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return strtoupper($color);
        }
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
            return '#'.strtoupper($color);
        }

        return '#94A3B8';
    }
}
