<?php

namespace Tests\Unit;

use App\Services\StaleDraftArchiveSchedule;
use Carbon\Carbon;
use Tests\TestCase;

class StaleDraftArchiveScheduleTest extends TestCase
{
    public function test_next_weekly_run_is_monday_at_six_after_reference(): void
    {
        $schedule = new StaleDraftArchiveSchedule();
        $wednesday = Carbon::parse('2026-07-08 10:00:00');
        $next = $schedule->nextWeeklyRun($wednesday);

        $this->assertSame(Carbon::MONDAY, $next->dayOfWeek);
        $this->assertSame(6, $next->hour);
        $this->assertSame(0, $next->minute);
        $this->assertTrue($next->gt($wednesday));
    }

    public function test_scheduled_archive_uses_monday_run_after_cutoff(): void
    {
        $schedule = new StaleDraftArchiveSchedule();
        $eligible = Carbon::parse('2026-05-01 12:00:00');
        $archiveAt = $schedule->nextWeeklyRun($eligible);

        $this->assertTrue($archiveAt->gte($eligible));
        $this->assertSame(Carbon::MONDAY, $archiveAt->dayOfWeek);
        $this->assertSame(6, $archiveAt->hour);
    }
}
