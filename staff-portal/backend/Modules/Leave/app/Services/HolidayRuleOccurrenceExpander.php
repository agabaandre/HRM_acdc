<?php

namespace Modules\Leave\Services;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

class HolidayRuleOccurrenceExpander
{
    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<string>
     */
    public function datesForYear(array $rules, int $year): array
    {
        $dates = [];
        foreach ($rules as $rule) {
            $date = $this->dateForRule($rule, $year);
            if ($date === null) {
                continue;
            }
            $dates[$date] = $date;
        }

        $list = array_values($dates);
        sort($list);

        return $list;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function dateForRule(array $rule, int $year): ?string
    {
        $recurrence = (string) ($rule['recurrence'] ?? '');
        if ($recurrence === 'once') {
            $raw = $rule['once_date'] ?? null;
            if (! is_string($raw) || $raw === '') {
                return null;
            }
            try {
                $parsed = Carbon::parse($raw)->startOfDay();
            } catch (InvalidFormatException) {
                return null;
            }
            if ((int) $parsed->year !== $year) {
                return null;
            }

            return $parsed->toDateString();
        }

        if ($recurrence !== 'yearly_md') {
            return null;
        }

        $month = (int) ($rule['month'] ?? 0);
        $day = (int) ($rule['day'] ?? 0);
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
