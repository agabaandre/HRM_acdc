<?php

namespace Modules\Performance\Support;

/**
 * Port of CI3 calculate_endterm_overall_rating().
 * Formula: sum(appraiser_rating × weight) / 5, then banded 80 / 51 / >0.
 *
 * @phpstan-type RatingArray array{
 *     score: float,
 *     category: string,
 *     label: string,
 *     annotation: string
 * }
 */
final class EndtermScore
{
    /**
     * @param  mixed  $objectives
     * @return array{score: float, category: string, label: string, annotation: string}
     */
    public static function fromObjectives(mixed $objectives): array
    {
        if (is_string($objectives) && $objectives !== '') {
            $decoded = json_decode($objectives, true);
            $objectives = is_array($decoded) ? $decoded : [];
        } elseif (is_object($objectives)) {
            $objectives = json_decode(json_encode($objectives), true) ?: [];
        }

        if (! is_array($objectives) || $objectives === []) {
            return self::band(0.0);
        }

        $total = 0.0;
        foreach ($objectives as $obj) {
            if (! is_array($obj)) {
                continue;
            }
            $rating = (float) ($obj['appraiser_rating'] ?? 0);
            $weight = (float) ($obj['weight'] ?? 0);
            if ($rating > 0 && $weight > 0) {
                $total += $rating * $weight;
            }
        }

        $score = $total > 0 ? round($total / 5, 2) : 0.0;

        return self::band($score);
    }

    /**
     * @return array{score: float, category: string, label: string, annotation: string}
     */
    public static function band(float $score): array
    {
        if ($score >= 80) {
            return [
                'score' => $score,
                'category' => 'outstanding',
                'label' => 'Outstanding Performance',
                'annotation' => 'Outstanding Performance - Overall performance is superior and significantly exceeds expectations',
            ];
        }

        if ($score >= 51) {
            return [
                'score' => $score,
                'category' => 'satisfactory',
                'label' => 'Satisfactory Performance',
                'annotation' => 'Satisfactory Performance - Overall performance is consistent with expectations',
            ];
        }

        if ($score > 0) {
            return [
                'score' => $score,
                'category' => 'poor',
                'label' => 'Poor Performance',
                'annotation' => 'Poor Performance - Overall Performance fails to meet the expectations',
            ];
        }

        return [
            'score' => 0.0,
            'category' => 'not_rated',
            'label' => 'Not Rated – New in Position',
            'annotation' => 'Not Rated – New in Position',
        ];
    }
}
