<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RiskReviewService
{
    public function __construct(private readonly ?RatingBandResolver $bands = null) {}

    /**
     * @param  array{year:int,quarter:int,likelihood?:int,impact?:int,mitigation_strategy?:?string,timeline?:?string,action_update?:?string,oio_verification_notes?:?string}  $payload
     * @return array<string, mixed>
     */
    public function create(int $riskId, array $payload, ?int $authorStaffId = null): array
    {
        $risk = DB::table('rr_risks')->where('id', $riskId)->first();
        if (! $risk) {
            throw new RuntimeException('Risk not found');
        }

        $year = (int) $payload['year'];
        $quarter = (int) $payload['quarter'];
        if ($quarter < 1 || $quarter > 4) {
            throw new RuntimeException('quarter must be 1–4');
        }

        $existing = DB::table('rr_risk_reviews')
            ->where('risk_id', $riskId)
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->first();

        $likelihood = array_key_exists('likelihood', $payload) && $payload['likelihood'] !== null && $payload['likelihood'] !== ''
            ? max(1, min(5, (int) $payload['likelihood']))
            : (int) ($existing->likelihood ?? $risk->inherent_likelihood ?? 3);
        $impact = array_key_exists('impact', $payload) && $payload['impact'] !== null && $payload['impact'] !== ''
            ? max(1, min(5, (int) $payload['impact']))
            : (int) ($existing->impact ?? $risk->inherent_impact ?? 3);
        $likelihood = max(1, min(5, $likelihood));
        $impact = max(1, min(5, $impact));
        $score = $likelihood * $impact;
        $resolved = $this->resolveScore($score);

        $timeline = array_key_exists('timeline', $payload) && $payload['timeline'] !== null && $payload['timeline'] !== ''
            ? (string) $payload['timeline']
            : ($existing->timeline ?? $this->defaultTimeline($riskId, (string) ($risk->timeline ?? '')));

        $mitigationStrategy = array_key_exists('mitigation_strategy', $payload)
            && $payload['mitigation_strategy'] !== null
            && trim((string) $payload['mitigation_strategy']) !== ''
            ? (string) $payload['mitigation_strategy']
            : (string) ($existing->mitigation_strategy ?? $risk->mitigation ?? '');

        $values = [
            'likelihood' => $likelihood,
            'impact' => $impact,
            'inherent_score' => $score,
            'inherent_rating' => $resolved['rating'],
            'inherent_rating_key' => $resolved['band_key'],
            'rating_key_version' => $resolved['version'],
            'mitigation_strategy' => $mitigationStrategy,
            'timeline' => $timeline,
            'author_staff_id' => $authorStaffId,
            'updated_at' => now(),
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('rr_risk_reviews', 'action_update')) {
            if (array_key_exists('action_update', $payload)) {
                $values['action_update'] = $payload['action_update'] !== null && $payload['action_update'] !== ''
                    ? (string) $payload['action_update']
                    : null;
            } elseif ($existing && isset($existing->action_update)) {
                $values['action_update'] = $existing->action_update;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('rr_risk_reviews', 'oio_verification_notes')) {
            if (array_key_exists('oio_verification_notes', $payload)) {
                $values['oio_verification_notes'] = $payload['oio_verification_notes'] !== null
                    && $payload['oio_verification_notes'] !== ''
                    ? (string) $payload['oio_verification_notes']
                    : null;
            } elseif ($existing && isset($existing->oio_verification_notes)) {
                $values['oio_verification_notes'] = $existing->oio_verification_notes;
            }
        }

        // Drop key/version columns when the test schema is older.
        if (! \Illuminate\Support\Facades\Schema::hasColumn('rr_risk_reviews', 'inherent_rating_key')) {
            unset($values['inherent_rating_key'], $values['rating_key_version']);
        }

        if ($existing) {
            DB::table('rr_risk_reviews')->where('id', $existing->id)->update($values);
            $row = (array) DB::table('rr_risk_reviews')->where('id', $existing->id)->first();
        } else {
            $id = DB::table('rr_risk_reviews')->insertGetId([
                'risk_id' => $riskId,
                'year' => $year,
                'quarter' => $quarter,
                ...$values,
                'created_at' => now(),
            ]);
            $row = (array) DB::table('rr_risk_reviews')->where('id', $id)->first();
        }

        // Keep risk-level OIO fields in sync with the latest saved quarterly review.
        $riskUpdate = [];
        if (array_key_exists('timeline', $values)) {
            $riskUpdate['timeline'] = $values['timeline'];
        }
        if (array_key_exists('action_update', $values)) {
            $riskUpdate['action_update'] = $values['action_update'];
        }
        if (array_key_exists('oio_verification_notes', $values)) {
            $riskUpdate['oio_verification_notes'] = $values['oio_verification_notes'];
        }
        if ($riskUpdate !== []) {
            $riskUpdate['updated_at'] = now();
            DB::table('rr_risks')->where('id', $riskId)->update($riskUpdate);
        }

        return $row;
    }

    /**
     * @return array{quarters: list<array<string,mixed>>, months: list<array<string,mixed>>, annual: list<array<string,mixed>>}
     */
    public function trends(int $riskId): array
    {
        $risk = DB::table('rr_risks')->where('id', $riskId)->first();
        $riskResidual = $risk && $risk->residual_score !== null ? (int) $risk->residual_score : null;
        $riskResidualRating = $risk ? ($risk->residual_rating ?? null) : null;

        $quarters = DB::table('rr_risk_reviews')
            ->where('risk_id', $riskId)
            ->orderBy('year')
            ->orderBy('quarter')
            ->get()
            ->map(function ($r) use ($riskResidual, $riskResidualRating) {
                $arr = (array) $r;
                if (! array_key_exists('residual_score', $arr) || $arr['residual_score'] === null) {
                    $arr['residual_score'] = $riskResidual;
                    $arr['residual_rating'] = $riskResidualRating;
                }

                return $arr;
            })
            ->all();

        $byYear = [];
        foreach ($quarters as $q) {
            $y = (int) $q['year'];
            $byYear[$y] ??= ['year' => $y, 'scores' => []];
            $byYear[$y]['scores'][] = (int) $q['inherent_score'];
        }
        $annual = [];
        foreach ($byYear as $row) {
            $scores = $row['scores'];
            $avg = (int) round(array_sum($scores) / max(1, count($scores)));
            $annual[] = [
                'year' => $row['year'],
                'avg_score' => $avg,
                'rating' => $this->resolveScore($avg)['rating'],
                'rating_key' => $this->resolveScore($avg)['band_key'],
                'reviews' => count($scores),
            ];
        }

        $months = [];
        foreach ($quarters as $q) {
            $year = (int) $q['year'];
            $quarter = (int) $q['quarter'];
            $startMonth = (($quarter - 1) * 3) + 1;
            for ($m = 0; $m < 3; $m++) {
                $month = $startMonth + $m;
                $months[] = [
                    'year' => $year,
                    'month' => $month,
                    'quarter' => $quarter,
                    'likelihood' => $q['likelihood'] ?? null,
                    'impact' => $q['impact'] ?? null,
                    'inherent_score' => (int) ($q['inherent_score'] ?? 0),
                    'inherent_rating' => $q['inherent_rating'] ?? null,
                    'residual_score' => $q['residual_score'] ?? null,
                    'residual_rating' => $q['residual_rating'] ?? null,
                ];
            }
        }

        return ['quarters' => $quarters, 'months' => $months, 'annual' => $annual];
    }

    /**
     * @return array{rating:string,band_key:string,version:int|null}
     */
    private function resolveScore(int $score): array
    {
        if ($this->bands) {
            $version = $this->bands->activeVersion();
            $band = $this->bands->resolve($score, $version);

            return [
                'rating' => $band['rating'],
                'band_key' => $band['band_key'],
                'version' => $version,
            ];
        }

        $rating = ResidualRiskCalculator::defaultRatingForScore($score);

        return [
            'rating' => $rating,
            'band_key' => strtolower(str_replace(' ', '_', $rating)),
            'version' => null,
        ];
    }

    private function defaultTimeline(int $riskId, string $riskTimeline): string
    {
        $prev = DB::table('rr_risk_reviews')
            ->where('risk_id', $riskId)
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->value('timeline');
        if (is_string($prev) && $prev !== '') {
            return $prev;
        }

        return $riskTimeline;
    }
}
