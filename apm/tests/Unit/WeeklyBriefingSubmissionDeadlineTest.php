<?php

namespace Tests\Unit;

use App\Models\WeeklyBriefingReport;
use PHPUnit\Framework\TestCase;

class WeeklyBriefingSubmissionDeadlineTest extends TestCase
{
    public function test_current_week_filing_uses_submission_weekday_inside_reporting_week(): void
    {
        $monday = WeeklyBriefingReport::periodMonday(2026, 21);
        $deadline = WeeklyBriefingReport::submissionCloseAt($monday, 4, '20:00:00', false);

        $this->assertSame('Thursday', $deadline->format('l'));
        $this->assertSame('2026-05-21', $deadline->toDateString());
        $this->assertSame('20:00', $deadline->format('H:i'));
    }

    public function test_next_week_filing_uses_submission_weekday_before_reporting_week(): void
    {
        $monday = WeeklyBriefingReport::periodMonday(2026, 21);
        $deadline = WeeklyBriefingReport::submissionCloseAt($monday, 6, '20:00:00', true);

        $this->assertSame('Saturday', $deadline->format('l'));
        $this->assertSame('2026-05-16', $deadline->toDateString());
    }

    public function test_next_week_filing_saturday_is_not_hardcoded_friday(): void
    {
        $monday = WeeklyBriefingReport::periodMonday(2026, 21);
        $fridayHardcoded = $monday->copy()->subDays(3);
        $saturday = WeeklyBriefingReport::submissionCloseAt($monday, 6, '20:00:00', true);

        $this->assertNotSame($fridayHardcoded->toDateString(), $saturday->toDateString());
        $this->assertSame('Saturday', $saturday->format('l'));
    }
}
