<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Compute memo / activity budget totals from budget_breakdown JSON.
 * Each calculator mirrors the matching show view (views are the source of truth).
 */
class BudgetBreakdownTotal
{
    /** Activity, matrix activity, single memo — requires unit_cost + units keys. */
    public const STYLE_TRAVEL_STRICT = 'travel_strict';

    /** Special memo show — unit_cost × units|quantity × days. */
    public const STYLE_TRAVEL_LENIENT = 'travel_lenient';

    /** Non-travel memo show — quantity × unit_cost. */
    public const STYLE_NON_TRAVEL = 'non_travel';

    /** Change request show — days applied only when days > 1; unit_cost falls back to cost. */
    public const STYLE_CHANGE_REQUEST = 'change_request';

    /**
     * Planned total for one fund code within a breakdown.
     */
    public static function forFundCode(mixed $breakdown, int $fundCodeId, string $style = self::STYLE_TRAVEL_STRICT): float
    {
        $totals = self::fundCodeTotals($breakdown, $style);

        return $totals[$fundCodeId] ?? 0.0;
    }

    /**
     * Full memo total across all fund codes.
     */
    public static function memoGrandTotal(mixed $breakdown, string $style = self::STYLE_TRAVEL_STRICT): float
    {
        return match ($style) {
            self::STYLE_NON_TRAVEL => self::fromNonTravelBreakdown($breakdown),
            self::STYLE_CHANGE_REQUEST => self::fromChangeRequestBreakdown($breakdown),
            self::STYLE_TRAVEL_LENIENT => self::fromTravelLenientBreakdown($breakdown),
            default => self::fromTravelStrictBreakdown($breakdown),
        };
    }

    /**
     * Activity amount for a fund code: prefer budget_breakdown (view truth) over activity_budgets sum.
     */
    public static function activityAmountForFundCode(mixed $breakdown, int $fundCodeId, float $activityBudgetSum): float
    {
        $normalized = self::normalize($breakdown);
        if (self::hasFundCodeEntries($normalized, $fundCodeId)) {
            return self::forFundCode($normalized, $fundCodeId, self::STYLE_TRAVEL_STRICT);
        }

        return round(max(0, $activityBudgetSum), 2);
    }

    public static function originalMemoTotalForSource(string $sourceType, mixed $breakdown): float
    {
        return match ($sourceType) {
            'non_travel_memo' => self::fromNonTravelBreakdown($breakdown),
            // Service request create/edit table: multiply days only when days > 1.
            default => self::fromChangeRequestBreakdown($breakdown),
        };
    }

    /**
     * Original Memo Budget on the service-request form.
     * Always prefer the source memo table; never the stored SR breakdown
     * (that payload is reconstructed from selected cost rows and often cannot be summed).
     */
    public static function originalTotalForServiceRequestForm(
        string $sourceType,
        mixed $sourceBreakdown,
        mixed $storedRequestBreakdown = null,
        float $storedOriginal = 0.0,
    ): float {
        $fromSource = self::originalMemoTotalForSource($sourceType, $sourceBreakdown);
        if ($fromSource > 0) {
            return $fromSource;
        }

        $fromStoredBreakdown = self::originalMemoTotalForSource($sourceType, $storedRequestBreakdown);
        if ($fromStoredBreakdown > 0) {
            return $fromStoredBreakdown;
        }

        return round(max(0, $storedOriginal), 2);
    }

    /**
     * @return array<int, float> fund code id => planned total
     */
    public static function fundCodeTotals(mixed $breakdown, string $style = self::STYLE_TRAVEL_STRICT): array
    {
        $breakdown = self::normalize($breakdown);
        if ($breakdown === []) {
            return [];
        }

        $totals = [];
        foreach ($breakdown as $key => $entries) {
            if ($key === 'grand_total' || ! is_array($entries)) {
                continue;
            }
            $fundCodeId = (int) $key;
            if ($fundCodeId <= 0) {
                continue;
            }
            $sum = self::sumEntries($entries, $style);
            if ($sum > 0) {
                $totals[$fundCodeId] = round($sum, 2);
            }
        }

        return $totals;
    }

    /**
     * @return array<int, float>
     */
    public static function fundCodeTotalsFromFundCodeBreakdown(mixed $breakdown): array
    {
        return self::fundCodeTotals($breakdown, self::STYLE_TRAVEL_STRICT);
    }

    /**
     * @return array<int, float>
     */
    public static function fundCodeTotalsFromNonTravelBreakdown(mixed $breakdown): array
    {
        return self::fundCodeTotals($breakdown, self::STYLE_NON_TRAVEL);
    }

    /**
     * @return array<int, float>
     */
    public static function fundCodeTotalsFromExecutionBreakdown(mixed $breakdown, bool $nonTravel = false): array
    {
        return self::fundCodeTotals(
            $breakdown,
            $nonTravel ? self::STYLE_NON_TRAVEL : self::STYLE_TRAVEL_STRICT
        );
    }

    /** @deprecated Use memoGrandTotal() with explicit style. */
    public static function fromFundCodeBreakdown(mixed $breakdown): float
    {
        return self::fromTravelStrictBreakdown($breakdown);
    }

    public static function fromTravelStrictBreakdown(mixed $breakdown): float
    {
        return self::sumAllFundCodes($breakdown, self::STYLE_TRAVEL_STRICT);
    }

    public static function fromTravelLenientBreakdown(mixed $breakdown): float
    {
        return self::sumAllFundCodes($breakdown, self::STYLE_TRAVEL_LENIENT);
    }

    public static function fromNonTravelBreakdown(mixed $breakdown): float
    {
        $breakdown = self::normalize($breakdown);
        if ($breakdown === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($breakdown as $key => $entries) {
            if ($key === 'grand_total' || ! is_array($entries)) {
                continue;
            }
            $total += self::sumEntries($entries, self::STYLE_NON_TRAVEL);
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

    public static function fromChangeRequestBreakdown(mixed $breakdown): float
    {
        return self::sumAllFundCodes($breakdown, self::STYLE_CHANGE_REQUEST);
    }

    public static function hasFundCodeEntries(mixed $breakdown, int $fundCodeId): bool
    {
        $breakdown = self::normalize($breakdown);
        foreach ($breakdown as $key => $entries) {
            if ((int) $key === $fundCodeId && is_array($entries) && $entries !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * SQL prefilter: budget_breakdown JSON keys are fund code ids (e.g. "277":[...]).
     *
     * @param  EloquentBuilder<mixed>|QueryBuilder  $query
     * @return EloquentBuilder<mixed>|QueryBuilder
     */
    public static function constrainNonEmptyBreakdown(EloquentBuilder|QueryBuilder $query, string $column): EloquentBuilder|QueryBuilder
    {
        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->where($column, '!=', '[]')
            ->where($column, '!=', '{}');
    }

    /**
     * Narrow queries to rows whose breakdown includes a given fund code id key.
     *
     * @param  EloquentBuilder<mixed>|QueryBuilder  $query
     * @return EloquentBuilder<mixed>|QueryBuilder
     */
    public static function constrainFundCodeId(EloquentBuilder|QueryBuilder $query, string $column, int $fundCodeId): EloquentBuilder|QueryBuilder
    {
        if ($fundCodeId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return self::constrainNonEmptyBreakdown($query, $column)
            ->where($column, 'like', '%"' . $fundCodeId . '":%');
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(mixed $breakdown): array
    {
        if (is_string($breakdown) && $breakdown !== '') {
            $decoded = json_decode($breakdown, true);
            if (! is_array($decoded)) {
                $decoded = json_decode(stripslashes($breakdown), true);
            }
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            $breakdown = $decoded;
        }

        return is_array($breakdown) ? $breakdown : [];
    }

    private static function sumAllFundCodes(mixed $breakdown, string $style): float
    {
        $breakdown = self::normalize($breakdown);
        if ($breakdown === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($breakdown as $key => $entries) {
            if ($key === 'grand_total' || ! is_array($entries)) {
                continue;
            }
            $total += self::sumEntries($entries, $style);
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
     * @param  array<int, array<string, mixed>>  $entries
     */
    private static function sumEntries(array $entries, string $style): float
    {
        $sum = 0.0;
        foreach ($entries as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sum += self::lineTotal($item, $style);
        }

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function lineTotal(array $item, string $style): float
    {
        return match ($style) {
            self::STYLE_NON_TRAVEL => self::sanitizeNumber($item['quantity'] ?? 1, 1.0)
                * self::sanitizeNumber($item['unit_cost'] ?? 0),
            self::STYLE_CHANGE_REQUEST => self::changeRequestLineTotal($item),
            self::STYLE_TRAVEL_LENIENT => self::sanitizeNumber($item['unit_cost'] ?? 0)
                * self::sanitizeNumber($item['units'] ?? $item['quantity'] ?? 1, 1.0)
                * self::sanitizeNumber($item['days'] ?? 1, 1.0),
            default => self::travelStrictLineTotal($item),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function travelStrictLineTotal(array $item): float
    {
        if (! array_key_exists('unit_cost', $item) || ! array_key_exists('units', $item)) {
            return 0.0;
        }

        $unitCost = self::sanitizeNumber($item['unit_cost']);
        $units = self::sanitizeNumber($item['units']);
        $days = self::sanitizeNumber($item['days'] ?? 1, 1.0);

        return $unitCost * $units * $days;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function changeRequestLineTotal(array $item): float
    {
        $unitCost = self::sanitizeNumber($item['unit_cost'] ?? $item['cost'] ?? 0);
        $units = self::sanitizeNumber($item['units'] ?? 0);
        $days = self::sanitizeNumber($item['days'] ?? 1, 1.0);

        if ($days > 1) {
            return $unitCost * $units * $days;
        }

        return $unitCost * $units;
    }

    private static function sanitizeNumber(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (float) str_replace(',', '', (string) $value);
    }
}
