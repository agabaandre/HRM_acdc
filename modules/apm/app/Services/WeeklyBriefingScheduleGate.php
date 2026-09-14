<?php

namespace App\Services;

use App\Models\WeeklyBriefingSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Self-gating for weekly-briefing Artisan commands (scheduler runs every minute).
 *
 * Uses a short grace window after the configured clock time so a slightly late
 * schedule:run can still dispatch once per slot.
 */
class WeeklyBriefingScheduleGate
{
    public const DISPATCH_GRACE_MINUTES = 20;

    /** Longer window for compiled / HoD PDF / director packs on the submission deadline day. */
    public const COMPILED_DISPATCH_GRACE_MINUTES = 240;

    /** Scheduled director nudge on the deadline calendar day (not at submission close). */
    public const DIRECTOR_HOURS_BEFORE_CLOSE_REMINDER = 4;

    public function __construct(
        private readonly WeeklyBriefingSetting $settings,
        private readonly ?Carbon $now = null,
    ) {}

    public static function for(WeeklyBriefingSetting $settings, ?Carbon $at = null): self
    {
        return new self($settings, $at);
    }

    public function now(): Carbon
    {
        return $this->now ?? Carbon::now();
    }

    public function filingDeadline(): Carbon
    {
        return $this->settings->filingSubmissionDeadline($this->now());
    }

    /**
     * @return array{iso_year: int, iso_week: int}
     */
    public function filingWeek(): array
    {
        return $this->settings->filingIsoWeekPair($this->now());
    }

    /**
     * Calendar day (start of day) matches one of the configured offsets before the filing deadline.
     *
     * @param  list<int>  $daysBeforeOffsets
     */
    public function isReminderDay(Carbon $deadline, array $daysBeforeOffsets): bool
    {
        $today = $this->now()->copy()->startOfDay();
        $deadlineDay = $deadline->copy()->startOfDay();
        foreach ($daysBeforeOffsets as $offset) {
            if ($today->equalTo($deadlineDay->copy()->subDays((int) $offset))) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the configured time column matches now (exact minute) or we are still inside the grace window.
     */
    public function isWithinScheduledClock(string $timeAttribute, ?int $graceMinutes = null): bool
    {
        $scheduledAt = $this->scheduledDateTimeToday($timeAttribute);
        if ($scheduledAt === null) {
            return false;
        }

        return $this->isWithinScheduledClockAt($scheduledAt, $graceMinutes ?? self::DISPATCH_GRACE_MINUTES);
    }

    public function isWithinScheduledClockAt(Carbon $scheduledAt, ?int $graceMinutes = null): bool
    {
        $graceMinutes = $graceMinutes ?? self::DISPATCH_GRACE_MINUTES;
        $now = $this->now();
        if ($now->format('H:i') === $scheduledAt->format('H:i')) {
            return true;
        }

        if (! $now->isSameDay($scheduledAt)) {
            return false;
        }

        $graceEnd = $scheduledAt->copy()->addMinutes($graceMinutes);

        return $now->greaterThan($scheduledAt) && $now->lessThanOrEqualTo($graceEnd);
    }

    /**
     * Reminder sends are keyed off the deadline calendar day, not the exact close time
     * (so a director reminder at submission close is not blocked once the clock passes close).
     */
    public function isOnOrBeforeDeadlineCalendarDay(): bool
    {
        return ! $this->now()->copy()->startOfDay()->greaterThan(
            $this->filingDeadline()->copy()->startOfDay()
        );
    }

    public function hasDispatched(string $kind, string $slotKey): bool
    {
        return Cache::has('weekly_briefing_dispatch:'.$kind.':'.md5($slotKey));
    }

    public function tryClaimDispatch(string $kind, string $slotKey): bool
    {
        $cacheKey = 'weekly_briefing_dispatch:'.$kind.':'.md5($slotKey);

        return Cache::add($cacheKey, $this->now()->toIso8601String(), $this->now()->copy()->addDays(3));
    }

    public function releaseDispatch(string $kind, string $slotKey): void
    {
        Cache::forget('weekly_briefing_dispatch:'.$kind.':'.md5($slotKey));
    }

    public function passesHodReminderSchedule(bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        if (! $this->settings->reminders_enabled) {
            return false;
        }

        if (! $this->isOnOrBeforeDeadlineCalendarDay()) {
            return false;
        }

        $deadline = $this->filingDeadline();
        if (! $this->isReminderDay($deadline, $this->settings->normalizedHodReminderDaysBeforeDeadline())) {
            return false;
        }

        return $this->isWithinScheduledClock($this->settings->hodReminderClockColumn());
    }

    public function hodReminderSlotKey(): string
    {
        $deadline = $this->filingDeadline();
        $filing = $this->filingWeek();
        $offsets = $this->settings->normalizedHodReminderDaysBeforeDeadline();

        return implode(':', [
            'hod',
            $filing['iso_year'],
            $filing['iso_week'],
            $this->now()->toDateString(),
            $this->matchedReminderOffset($deadline, $offsets),
            $this->settings->hodReminderClockColumn(),
        ]);
    }

    public function passesDirectorReviewReminderSchedule(bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        if (! $this->settings->reminders_enabled) {
            return false;
        }

        if (! $this->isOnOrBeforeDeadlineCalendarDay()) {
            return false;
        }

        $deadline = $this->filingDeadline();

        if ($this->isWithinDirectorFourHoursBeforeCloseWindow($deadline)) {
            return true;
        }

        $dayBeforeOffsets = $this->directorDayBeforeReminderOffsets();
        if ($dayBeforeOffsets !== [] && $this->isReminderDay($deadline, $dayBeforeOffsets)) {
            return $this->isWithinScheduledClock($this->settings->directorDayBeforeReminderClockColumn());
        }

        return false;
    }

    public function directorFourHoursBeforeCloseAt(Carbon $deadline): Carbon
    {
        return $deadline->copy()->subHours(self::DIRECTOR_HOURS_BEFORE_CLOSE_REMINDER);
    }

    public function isWithinDirectorFourHoursBeforeCloseWindow(Carbon $deadline): bool
    {
        $scheduledAt = $this->directorFourHoursBeforeCloseAt($deadline);

        return $this->isWithinScheduledClockAt($scheduledAt, self::DISPATCH_GRACE_MINUTES);
    }

    /**
     * Director day-offset reminders (e.g. 1 = day before deadline); offset 0 is handled by the 4-hour rule.
     *
     * @return list<int>
     */
    public function directorDayBeforeReminderOffsets(): array
    {
        return array_values(array_filter(
            $this->settings->normalizedDirectorReviewReminderDaysBeforeDeadline(),
            static fn (int $offset): bool => $offset > 0
        ));
    }

    public function directorReviewReminderSlotKey(): string
    {
        $deadline = $this->filingDeadline();
        $filing = $this->filingWeek();

        if ($this->isWithinDirectorFourHoursBeforeCloseWindow($deadline)) {
            return implode(':', [
                'director',
                'four_hours_before_close',
                $filing['iso_year'],
                $filing['iso_week'],
                $this->now()->toDateString(),
            ]);
        }

        $offsets = $this->directorDayBeforeReminderOffsets();

        return implode(':', [
            'director',
            'day_before',
            $filing['iso_year'],
            $filing['iso_week'],
            $this->now()->toDateString(),
            $this->matchedReminderOffset($deadline, $offsets),
            $this->settings->directorDayBeforeReminderClockColumn(),
        ]);
    }

    public function passesCompiledSummarySchedule(bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        if (! $this->settings->reminders_enabled) {
            return false;
        }

        $deadline = $this->filingDeadline();
        if (! $this->now()->copy()->startOfDay()->equalTo($deadline->copy()->startOfDay())) {
            return false;
        }

        $scheduledAt = $this->scheduledDateTimeToday('summary_send_time');
        if ($scheduledAt === null) {
            return false;
        }

        $now = $this->now();
        if ($this->isWithinScheduledClockAt($scheduledAt, self::COMPILED_DISPATCH_GRACE_MINUTES)) {
            return true;
        }

        // Same deadline day after configured send time until end of day if the pack never went out.
        return $this->isCompiledSummaryCatchUpWindow($deadline, $scheduledAt);
    }

    public function compiledSummarySlotKey(): string
    {
        $filing = $this->filingWeek();

        return implode(':', [
            'compiled',
            $filing['iso_year'],
            $filing['iso_week'],
            $this->now()->toDateString(),
        ]);
    }

    /**
     * Human-readable clock label for settings UI.
     */
    public function clockLabel(string $column): string
    {
        $time = match ($column) {
            'hod_reminder_time' => $this->settings->hodReminderTimeHm(),
            'submission_close_time' => $this->settings->submissionCloseTimeHm(),
            'summary_send_time' => $this->settings->summarySendTimeHm(),
            default => substr((string) $this->settings->getAttribute($column), 0, 5),
        };

        return match ($column) {
            'hod_reminder_time' => "HoD reminder time ({$time})",
            'submission_close_time' => "Submission closes ({$time})",
            'summary_send_time' => "Compiled summary send ({$time})",
            default => "{$column} ({$time})",
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function scheduleStatus(): array
    {
        $deadline = $this->filingDeadline();
        $filing = $this->filingWeek();
        $hodClock = $this->settings->hodReminderClockColumn();
        $dirDayClock = $this->settings->directorDayBeforeReminderClockColumn();
        $summaryAt = $this->scheduledDateTimeToday('summary_send_time');
        $dirFourHoursAt = $this->directorFourHoursBeforeCloseAt($deadline);
        $dirDayBeforeOffsets = $this->directorDayBeforeReminderOffsets();

        return [
            'timezone' => config('app.timezone'),
            'now' => $this->now(),
            'reminders_enabled' => (bool) $this->settings->reminders_enabled,
            'filing' => $filing,
            'deadline' => $deadline,
            'hod_clock_column' => $hodClock,
            'hod_clock_label' => $this->clockLabel($hodClock),
            'hod_scheduled_at' => $this->scheduledDateTimeToday($hodClock),
            'hod_is_reminder_day' => $this->isReminderDay($deadline, $this->settings->normalizedHodReminderDaysBeforeDeadline()),
            'hod_within_clock' => $this->isWithinScheduledClock($hodClock),
            'hod_would_dispatch' => $this->passesHodReminderSchedule(false),
            'director_day_before_clock_label' => $this->clockLabel($dirDayClock),
            'director_day_before_offsets' => $dirDayBeforeOffsets,
            'director_is_day_before_reminder_day' => $dirDayBeforeOffsets !== [] && $this->isReminderDay($deadline, $dirDayBeforeOffsets),
            'director_four_hours_before_at' => $dirFourHoursAt,
            'director_within_four_hours_window' => $this->isWithinDirectorFourHoursBeforeCloseWindow($deadline),
            'director_within_day_before_clock' => $this->isWithinScheduledClock($dirDayClock),
            'director_would_dispatch' => $this->passesDirectorReviewReminderSchedule(false),
            'compiled_scheduled_at' => $summaryAt,
            'compiled_is_deadline_day' => $this->now()->copy()->startOfDay()->equalTo($deadline->copy()->startOfDay()),
            'compiled_within_clock' => $summaryAt !== null && $this->isWithinScheduledClockAt($summaryAt, self::COMPILED_DISPATCH_GRACE_MINUTES),
            'compiled_in_catch_up_window' => $summaryAt !== null && $this->isCompiledSummaryCatchUpWindow($deadline, $summaryAt),
            'compiled_slot_claimed' => $this->hasDispatched('compiled', $this->compiledSummarySlotKey()),
            'compiled_block_reason' => $this->compiledSummaryBlockReason(),
            'compiled_would_dispatch' => $this->passesCompiledSummarySchedule(false),
        ];
    }

    /**
     * Why the compiled pack is not eligible to run right now (null = schedule gate would pass).
     */
    public function compiledSummaryBlockReason(): ?string
    {
        if (! $this->settings->reminders_enabled) {
            return 'Reminders are disabled in weekly briefing settings.';
        }

        $deadline = $this->filingDeadline();
        if (! $this->now()->copy()->startOfDay()->equalTo($deadline->copy()->startOfDay())) {
            return 'Today is not the submission deadline calendar day ('.$deadline->format('l, M j, Y').').';
        }

        $scheduledAt = $this->scheduledDateTimeToday('summary_send_time');
        if ($scheduledAt === null) {
            return 'Compiled summary send time is not configured.';
        }

        if ($this->passesCompiledSummarySchedule(false)) {
            return null;
        }

        if ($this->now()->lessThan($scheduledAt)) {
            return 'Before compiled summary send time ('.$scheduledAt->format('g:i A').').';
        }

        if ($this->hasDispatched('compiled', $this->compiledSummarySlotKey())) {
            return 'This week’s compiled slot was already claimed today (check logs or run with --force).';
        }

        return 'Outside the compiled send / catch-up window for today.';
    }

    public function isCompiledSummaryCatchUpWindow(Carbon $deadline, Carbon $scheduledAt): bool
    {
        $now = $this->now();

        return $now->greaterThanOrEqualTo($scheduledAt)
            && $now->lessThanOrEqualTo($deadline->copy()->endOfDay());
    }

    public function scheduledDateTimeToday(string $timeAttribute): ?Carbon
    {
        $value = $this->settings->getAttribute($timeAttribute);
        if ($value === null || $value === '') {
            return null;
        }
        $hm = is_string($value) ? substr(trim($value), 0, 5) : Carbon::parse($value)->format('H:i');
        if ($hm === '') {
            return null;
        }

        return $this->now()->copy()->startOfDay()->setTimeFromTimeString(strlen($hm) === 5 ? $hm.':00' : $hm);
    }

    /**
     * @param  list<int>  $offsets
     */
    private function matchedReminderOffset(Carbon $deadline, array $offsets): int
    {
        $today = $this->now()->copy()->startOfDay();
        $deadlineDay = $deadline->copy()->startOfDay();
        foreach ($offsets as $offset) {
            if ($today->equalTo($deadlineDay->copy()->subDays((int) $offset))) {
                return (int) $offset;
            }
        }

        return -1;
    }
}
