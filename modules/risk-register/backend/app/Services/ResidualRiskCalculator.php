<?php

namespace App\Services;

/**
 * Sheet 3 residual risk methodology.
 *
 * Inherent = L × I. Residual L = max(1, L − reduction); Residual I = max(1, I − ⌊reduction/2⌋).
 * Until effectiveness is assessed, residual equals inherent.
 *
 * Ratings come from RatingBandResolver (versioned keys); callable fallback kept for tests.
 */
final class ResidualRiskCalculator
{
    /** @param callable(int): string $ratingForScore */
    public function __construct(
        private $ratingForScore,
        private readonly ?RatingBandResolver $bands = null,
    ) {}

    /**
     * @return array{
     *   inherent_score:int,
     *   inherent_rating:string,
     *   inherent_rating_key:string,
     *   residual_likelihood:int,
     *   residual_impact:int,
     *   residual_score:int,
     *   residual_rating:string,
     *   residual_rating_key:string,
     *   rating_key_version:int|null,
     *   movement:string
     * }
     */
    public function compute(int $likelihood, int $impact, int $effectivenessReduction, bool $effectivenessAssessed): array
    {
        $likelihood = max(1, min(5, $likelihood));
        $impact = max(1, min(5, $impact));
        $reduction = max(0, $effectivenessReduction);

        $inherentScore = $likelihood * $impact;

        if (! $effectivenessAssessed) {
            $resolved = $this->resolveScore($inherentScore);

            return [
                'inherent_score' => $inherentScore,
                'inherent_rating' => $resolved['rating'],
                'inherent_rating_key' => $resolved['band_key'],
                'residual_likelihood' => $likelihood,
                'residual_impact' => $impact,
                'residual_score' => $inherentScore,
                'residual_rating' => $resolved['rating'],
                'residual_rating_key' => $resolved['band_key'],
                'rating_key_version' => $resolved['version'],
                'movement' => 'No Change',
            ];
        }

        $residualL = max(1, $likelihood - $reduction);
        $residualI = max(1, $impact - intdiv($reduction, 2));
        $residualScore = $residualL * $residualI;

        $inh = $this->resolveScore($inherentScore);
        $res = $this->resolveScore($residualScore);

        $movement = match (true) {
            $residualScore < $inherentScore => 'Decreased',
            $residualScore > $inherentScore => 'Increased',
            default => 'No Change',
        };

        return [
            'inherent_score' => $inherentScore,
            'inherent_rating' => $inh['rating'],
            'inherent_rating_key' => $inh['band_key'],
            'residual_likelihood' => $residualL,
            'residual_impact' => $residualI,
            'residual_score' => $residualScore,
            'residual_rating' => $res['rating'],
            'residual_rating_key' => $res['band_key'],
            'rating_key_version' => $inh['version'] ?? $res['version'],
            'movement' => $movement,
        ];
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

        $rating = ($this->ratingForScore)($score);

        return [
            'rating' => $rating,
            'band_key' => strtolower(str_replace(' ', '_', $rating)),
            'version' => null,
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

    public static function withResolver(?RatingBandResolver $resolver = null): self
    {
        try {
            $resolver ??= app(RatingBandResolver::class);
            $resolver->activeVersion();

            return new self($resolver->ratingCallable(), $resolver);
        } catch (\Throwable) {
            return new self(self::defaultRatingForScore(...));
        }
    }
}
