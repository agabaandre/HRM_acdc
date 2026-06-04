<?php

namespace Modules\Performance\Support;

final class PerformanceMonth
{
    /** @return array<int, string> */
    public static function options(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }

    public static function label(?int $month): string
    {
        if ($month === null || $month < 1 || $month > 12) {
            return '—';
        }

        return self::options()[$month];
    }

    public static function normalize(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $m = (int) $value;

            return ($m >= 1 && $m <= 12) ? $m : null;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}/', $value)) {
            return (int) date('n', strtotime($value));
        }

        return null;
    }
}
