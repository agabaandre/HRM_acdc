<?php

namespace App\Services;

/**
 * Sheet 3 residual risk methodology.
 *
 * Inherent = L × I. Residual L = max(1, L − reduction); Residual I = max(1, I − ⌊reduction/2⌋).
 * Until effectiveness is assessed, residual equals inherent.
 */
final class ResidualRiskCalculator
{
    /** @param callable(int): string $ratingForScore */
    public function __construct(private $ratingForScore) {}

    /**
     * @return array{
     *   inherent_score:int,
     *   inherent_rating:string,
     *   residual_likelihood:int,
     *   residual_impact:int,
     *   residual_score:int,
     *   residual_rating:string,
     *   movement:string
     * }
     */
    public function compute(int $likelihood, int $impact, int $effectivenessReduction, bool $effectivenessAssessed): array
    {
        $likelihood = max(1, min(5, $likelihood));
        $impact = max(1, min(5, $impact));
        $reduction = max(0, $effectivenessReduction);

        $inherentScore = $likelihood * $impact;
        $inherentRating = ($this->ratingForScore)($inherentScore);

        if (! $effectivenessAssessed) {
            return [
                'inherent_score' => $inherentScore,
                'inherent_rating' => $inherentRating,
                'residual_likelihood' => $likelihood,
                'residual_impact' => $impact,
                'residual_score' => $inherentScore,
                'residual_rating' => $inherentRating,
                'movement' => 'No Change',
            ];
        }

        $residualL = max(1, $likelihood - $reduction);
        $residualI = max(1, $impact - intdiv($reduction, 2));
        $residualScore = $residualL * $residualI;
        $residualRating = ($this->ratingForScore)($residualScore);

        $movement = match (true) {
            $residualScore < $inherentScore => 'Decreased',
            $residualScore > $inherentScore => 'Increased',
            default => 'No Change',
        };

        return [
            'inherent_score' => $inherentScore,
            'inherent_rating' => $inherentRating,
            'residual_likelihood' => $residualL,
            'residual_impact' => $residualI,
            'residual_score' => $residualScore,
            'residual_rating' => $residualRating,
            'movement' => $movement,
        ];
    }

    public static function defaultRatingForScore(int $score): string
    {
        return match (true) {
            $score <= 4 => 'Low',
            $score <= 9 => 'Medium',
            $score <= 15 => 'High',
            default => 'Critical',
        };
    }
}
