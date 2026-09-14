<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Straight-line depreciation for IT assets — current value recalculated on every read.
 */
final class ItAssetValuationService
{
    /**
     * @return array{
     *   age_years: float,
     *   age_months: int,
     *   current_value: float,
     *   depreciation_per_year: float,
     *   useful_life_years: int,
     * }
     */
    public function snapshot(
        ?string $purchaseDate,
        float $purchaseCost,
        float $salvageValue,
        int $usefulLifeYears
    ): array {
        $usefulLifeYears = max(1, $usefulLifeYears);
        $purchaseCost = max(0, round($purchaseCost, 2));
        $salvageValue = max(0, min($purchaseCost, round($salvageValue, 2)));

        if (! $purchaseDate) {
            return [
                'age_years' => 0.0,
                'age_months' => 0,
                'current_value' => $purchaseCost,
                'depreciation_per_year' => round(($purchaseCost - $salvageValue) / $usefulLifeYears, 2),
                'useful_life_years' => $usefulLifeYears,
            ];
        }

        $start = Carbon::parse($purchaseDate)->startOfDay();
        $now = now()->startOfDay();
        $ageMonths = max(0, (int) $start->diffInMonths($now));
        $ageYears = round($ageMonths / 12, 2);
        $depreciable = $purchaseCost - $salvageValue;
        $annualDepreciation = $depreciable / $usefulLifeYears;
        $accumulated = min($depreciable, $annualDepreciation * $ageYears);
        $currentValue = round(max($salvageValue, $purchaseCost - $accumulated), 2);

        return [
            'age_years' => $ageYears,
            'age_months' => $ageMonths,
            'current_value' => $currentValue,
            'depreciation_per_year' => round($annualDepreciation, 2),
            'useful_life_years' => $usefulLifeYears,
        ];
    }
}
