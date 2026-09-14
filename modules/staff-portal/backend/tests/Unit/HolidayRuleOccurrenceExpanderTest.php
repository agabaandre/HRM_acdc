<?php

namespace Tests\Unit;

use Modules\Leave\Services\HolidayRuleOccurrenceExpander;
use PHPUnit\Framework\TestCase;

class HolidayRuleOccurrenceExpanderTest extends TestCase
{
    public function test_yearly_md_expands_to_that_year(): void
    {
        $dates = (new HolidayRuleOccurrenceExpander)->datesForYear([
            ['recurrence' => 'yearly_md', 'month' => 12, 'day' => 25],
            ['recurrence' => 'yearly_md', 'month' => 1, 'day' => 1],
        ], 2026);

        $this->assertSame(['2026-01-01', '2026-12-25'], $dates);
    }

    public function test_once_only_matches_its_year(): void
    {
        $expander = new HolidayRuleOccurrenceExpander;

        $this->assertSame(
            ['2026-03-20'],
            $expander->datesForYear([
                ['recurrence' => 'once', 'once_date' => '2026-03-20'],
            ], 2026),
        );
        $this->assertSame(
            [],
            $expander->datesForYear([
                ['recurrence' => 'once', 'once_date' => '2026-03-20'],
            ], 2027),
        );
    }

    public function test_invalid_and_duplicate_dates_are_skipped(): void
    {
        $dates = (new HolidayRuleOccurrenceExpander)->datesForYear([
            ['recurrence' => 'yearly_md', 'month' => 2, 'day' => 30],
            ['recurrence' => 'yearly_md', 'month' => 12, 'day' => 25],
            ['recurrence' => 'yearly_md', 'month' => 12, 'day' => 25],
            ['recurrence' => 'once', 'once_date' => 'not-a-date'],
        ], 2026);

        $this->assertSame(['2026-12-25'], $dates);
    }
}
