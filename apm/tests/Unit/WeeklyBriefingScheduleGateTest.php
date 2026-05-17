<?php

namespace Tests\Unit;

use App\Models\WeeklyBriefingSetting;
use App\Services\WeeklyBriefingScheduleGate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WeeklyBriefingScheduleGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function baseSettings(): WeeklyBriefingSetting
    {
        return new WeeklyBriefingSetting([
            'submission_weekday' => 5,
            'filing_iso_week_offset' => 0,
            'submission_close_time' => '20:00:00',
            'hod_reminder_time' => '16:46:00',
            'hod_reminder_days_before_deadline' => [1, 0],
            'director_review_reminder_days_before_deadline' => [1],
            'summary_send_time' => '21:38:00',
            'reminders_enabled' => true,
        ]);
    }

    public function test_director_reminder_at_four_hours_before_close_not_at_close(): void
    {
        $settings = $this->baseSettings();
        $ref = Carbon::parse('2026-05-15 12:00:00');
        $deadline = $settings->filingSubmissionDeadline($ref);
        $atClose = $deadline->copy();
        $atFourHoursBefore = $deadline->copy()->subHours(WeeklyBriefingScheduleGate::DIRECTOR_HOURS_BEFORE_CLOSE_REMINDER);

        $this->assertFalse(
            WeeklyBriefingScheduleGate::for($settings, $atClose)->passesDirectorReviewReminderSchedule(false)
        );
        $this->assertTrue(
            WeeklyBriefingScheduleGate::for($settings, $atFourHoursBefore)->passesDirectorReviewReminderSchedule(false)
        );
    }

    public function test_director_day_before_reminder_at_hod_time(): void
    {
        $settings = $this->baseSettings();
        $ref = Carbon::parse('2026-05-14 16:46:00');
        $gate = WeeklyBriefingScheduleGate::for($settings, $ref);

        $this->assertTrue($gate->passesDirectorReviewReminderSchedule(false));
    }

    public function test_compiled_summary_catch_up_same_deadline_day_after_grace(): void
    {
        $settings = $this->baseSettings();
        $ref = Carbon::parse('2026-05-15 12:00:00');
        $deadline = $settings->filingSubmissionDeadline($ref);
        $at = $deadline->copy()->setTime(23, 0, 0);

        $gate = WeeklyBriefingScheduleGate::for($settings, $at);

        $this->assertTrue($gate->passesCompiledSummarySchedule(false));
    }

    public function test_compiled_summary_not_before_send_time_on_deadline_day(): void
    {
        $settings = $this->baseSettings();
        $ref = Carbon::parse('2026-05-15 08:00:00');
        $deadline = $settings->filingSubmissionDeadline($ref);
        $at = $deadline->copy()->setTime(8, 0, 0);

        $gate = WeeklyBriefingScheduleGate::for($settings, $at);

        $this->assertFalse($gate->passesCompiledSummarySchedule(false));
    }
}
