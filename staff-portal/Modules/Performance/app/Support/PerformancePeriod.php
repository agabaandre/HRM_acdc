<?php

namespace Modules\Performance\Support;

final class PerformancePeriod
{
    public static function currentLabel(): string
    {
        $year = (int) date('Y');

        return "January {$year} to December {$year}";
    }

    public static function currentSlug(): string
    {
        return str_replace(' ', '-', self::currentLabel());
    }

    public static function toSlug(?string $period): ?string
    {
        if ($period === null || $period === '') {
            return null;
        }

        return str_replace(' ', '-', $period);
    }

    public static function toLabel(string $slug): string
    {
        return str_replace('-', ' ', $slug);
    }
}
