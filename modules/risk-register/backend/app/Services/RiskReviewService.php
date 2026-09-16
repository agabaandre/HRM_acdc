<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RiskReviewService
{
    /**
     * @param  array{year:int,quarter:int,likelihood:int,impact:int,mitigation_strategy?:?string,timeline?:?string}  $payload
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

        $likelihood = max(1, min(5, (int) $payload['likelihood']));
        $impact = max(1, min(5, (int) $payload['impact']));
        $score = $likelihood * $impact;
        $rating = ResidualRiskCalculator::defaultRatingForScore($score);

        $timeline = array_key_exists('timeline', $payload) && $payload['timeline'] !== null && $payload['timeline'] !== ''
            ? (string) $payload['timeline']
            : $this->defaultTimeline($riskId, (string) ($risk->timeline ?? ''));

        $id = DB::table('rr_risk_reviews')->insertGetId([
            'risk_id' => $riskId,
            'year' => $year,
            'quarter' => $quarter,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'inherent_score' => $score,
            'inherent_rating' => $rating,
            'mitigation_strategy' => $payload['mitigation_strategy'] ?? null,
            'timeline' => $timeline,
            'author_staff_id' => $authorStaffId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('rr_risk_reviews')->where('id', $id)->first();
    }

    /**
     * @return array{quarters: list<array<string,mixed>>, annual: list<array<string,mixed>>}
     */
    public function trends(int $riskId): array
    {
        $quarters = DB::table('rr_risk_reviews')
            ->where('risk_id', $riskId)
            ->orderBy('year')
            ->orderBy('quarter')
            ->get()
            ->map(fn ($r) => (array) $r)
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
                'rating' => ResidualRiskCalculator::defaultRatingForScore($avg),
                'reviews' => count($scores),
            ];
        }

        return ['quarters' => $quarters, 'annual' => $annual];
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
