<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Rolling created-at windows for the tickets list date filter.
 */
final class TicketListDatePreset
{
    public const ALL = 'all';

    public const TODAY = 'today';

    public const LAST_3_DAYS = 'last_3_days';

    public const LAST_5_DAYS = 'last_5_days';

    public const LAST_WEEK = 'last_week';

    public const LAST_MONTH = 'last_month';

    public const LAST_MONTHS = 'last_months';

    public static function createdSince(string $preset, ?CarbonInterface $now = null): ?CarbonInterface
    {
        $now = Carbon::parse($now ?? now());
        $startOfToday = $now->copy()->startOfDay();

        return match ($preset) {
            self::TODAY => $startOfToday,
            self::LAST_3_DAYS => $startOfToday->copy()->subDays(2),
            self::LAST_5_DAYS => $startOfToday->copy()->subDays(4),
            self::LAST_WEEK => $startOfToday->copy()->subDays(6),
            self::LAST_MONTH => $startOfToday->copy()->subMonth(),
            self::LAST_MONTHS => $startOfToday->copy()->subMonths(3),
            default => null,
        };
    }
}
