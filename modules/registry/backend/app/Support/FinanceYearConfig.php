<?php

namespace App\Support;

use App\Services\RiskSettingsService;

/**
 * Africa CDC financial year configuration.
 * Default: FY starts 1 January (beginning of the calendar year).
 */
final class FinanceYearConfig
{
    public const SETTING_KEY = 'financial_year_start_month';

    public const DEFAULT_START_MONTH = 1;

    public function __construct(
        private readonly RiskSettingsService $settings = new RiskSettingsService,
    ) {}

    /**
     * Month (1–12) when the financial year begins.
     */
    public function startMonth(): int
    {
        $raw = (int) ($this->settings->get(self::SETTING_KEY, (string) self::DEFAULT_START_MONTH) ?? self::DEFAULT_START_MONTH);
        if ($raw < 1 || $raw > 12) {
            return self::DEFAULT_START_MONTH;
        }

        return $raw;
    }

    public function setStartMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Financial year start month must be 1–12.');
        }
        $this->settings->set(self::SETTING_KEY, (string) $month);
    }

    /**
     * @return array{start_month: int, start_month_name: string, default_start_month: int}
     */
    public function snapshot(): array
    {
        $month = $this->startMonth();

        return [
            'start_month' => $month,
            'start_month_name' => $this->monthName($month),
            'default_start_month' => self::DEFAULT_START_MONTH,
        ];
    }

    /**
     * FY year and quarter for a calendar period YYYY-MM.
     *
     * @return array{fy_year: int, quarter: int, month_offset: int, period_ym: string, quarter_label: string}
     */
    public function classifyPeriod(string $periodYm, ?int $startMonth = null): array
    {
        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $periodYm, $m)) {
            throw new \InvalidArgumentException('Invalid period_ym.');
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $start = $startMonth ?? $this->startMonth();

        if ($month >= $start) {
            $fyYear = $year;
        } else {
            $fyYear = $year - 1;
        }
        $offset = ($month - $start + 12) % 12;
        $quarter = intdiv($offset, 3) + 1;

        return [
            'fy_year' => $fyYear,
            'quarter' => $quarter,
            'month_offset' => $offset,
            'period_ym' => $periodYm,
            'quarter_label' => 'FY'.$fyYear.' Q'.$quarter,
        ];
    }

    /**
     * Current financial year (based on today).
     */
    public function currentFyYear(?int $startMonth = null): int
    {
        return $this->classifyPeriod(date('Y-m'), $startMonth)['fy_year'];
    }

    /**
     * Ordered list of YYYY-MM periods in a financial year.
     *
     * @return list<string>
     */
    public function periodsForFyYear(int $fyYear, ?int $startMonth = null): array
    {
        $start = $startMonth ?? $this->startMonth();
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $month = (($start - 1 + $i) % 12) + 1;
            $year = $fyYear + (int) floor(($start - 1 + $i) / 12);
            $out[] = sprintf('%04d-%02d', $year, $month);
        }

        return $out;
    }

    public function monthName(int $month): string
    {
        $names = [
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

        return $names[$month] ?? 'January';
    }

    /**
     * Short label for a period (e.g. Jan 2026).
     */
    public function periodLabel(string $periodYm): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $periodYm, $m)) {
            return $periodYm;
        }
        $month = (int) $m[2];
        $short = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return ($short[$month] ?? $m[2]).' '.$m[1];
    }
}
