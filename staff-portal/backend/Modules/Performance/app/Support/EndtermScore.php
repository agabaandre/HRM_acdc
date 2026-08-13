<?php

namespace Modules\Performance\Support;

/**
 * Port of CI3 calculate_endterm_overall_rating() for analytics bands/averages.
 */
final class EndtermScore
{
    /**
     * @param  mixed  $objectives
     * @return array{score: float, category: string}
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
            return ['score' => 0.0, 'category' => 'not_rated'];
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

        return [
            'score' => $score,
            'category' => match (true) {
                $score >= 80 => 'outstanding',
                $score >= 51 => 'satisfactory',
                $score > 0 => 'poor',
                default => 'not_rated',
            },
        ];
    }
}
