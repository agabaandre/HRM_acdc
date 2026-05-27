<?php

namespace App\Support;

/**
 * Compute memo / activity budget totals from budget_breakdown JSON.
 *
 * Activity and matrix views always sum line items (unit_cost × units × days) and
 * ignore a stale grand_total key. Service requests previously trusted grand_total,
 * which caused mismatches such as SR #360 vs activity #577.
 */
class BudgetBreakdownTotal
{
    /**
     * Fund-code keyed breakdown (activity, matrix activity, special memo).
     * Matches resources/views/activities/show.blade.php.
     */
    public static function fromFundCodeBreakdown(mixed $breakdown): float
    {
        if (is_string($breakdown)) {
            $breakdown = json_decode($breakdown, true);
        }
        if (! is_array($breakdown)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($breakdown as $key => $entries) {
            if ($key === 'grand_total') {
                continue;
            }
            if (! is_array($entries)) {
                continue;
            }
            foreach ($entries as $item) {
                if (! is_array($item) || ! isset($item['unit_cost'], $item['units'])) {
                    continue;
                }
                $unitCost = self::sanitizeNumber($item['unit_cost']);
                $units = self::sanitizeNumber($item['units']);
                $days = self::sanitizeNumber($item['days'] ?? 1, 1.0);
                $total += $unitCost * $units * $days;
            }
        }

        if ($total > 0) {
            return round($total, 2);
        }

        $stored = $breakdown['grand_total'] ?? null;
        if ($stored !== null && $stored !== '') {
            return round((float) str_replace(',', '', (string) $stored), 2);
        }

        return 0.0;
    }

    /**
     * Non-travel memo breakdown (quantity × unit_cost).
     */
    public static function fromNonTravelBreakdown(mixed $breakdown): float
    {
        if (is_string($breakdown)) {
            $breakdown = json_decode($breakdown, true);
        }
        if (! is_array($breakdown)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($breakdown as $key => $entries) {
            if ($key === 'grand_total') {
                continue;
            }
            if (! is_array($entries)) {
                continue;
            }
            foreach ($entries as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $qty = self::sanitizeNumber($item['quantity'] ?? $item['units'] ?? 1, 1.0);
                $unitCost = self::sanitizeNumber($item['unit_cost'] ?? 0);
                $total += $qty * $unitCost;
            }
        }

        if ($total > 0) {
            return round($total, 2);
        }

        $stored = $breakdown['grand_total'] ?? null;
        if ($stored !== null && $stored !== '') {
            return round((float) str_replace(',', '', (string) $stored), 2);
        }

        return 0.0;
    }

    public static function originalMemoTotalForSource(string $sourceType, mixed $breakdown): float
    {
        return match ($sourceType) {
            'non_travel_memo' => self::fromNonTravelBreakdown($breakdown),
            default => self::fromFundCodeBreakdown($breakdown),
        };
    }

    private static function sanitizeNumber(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (float) str_replace(',', '', (string) $value);
    }
}
