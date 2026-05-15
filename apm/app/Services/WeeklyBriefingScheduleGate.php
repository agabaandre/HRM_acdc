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
    public function isWithinScheduledClock(string $timeAttribute): bool
    {
        $scheduledAt = $this->scheduledDateTimeToday($timeAttribute);
        if ($scheduledAt === null) {
            return false;
        }

        $now = $this->now();
        if ($now->format('H:i') === $scheduledAt->format('H:i')) {
            return true;
        }

        if (! $now->isSameDay($scheduledAt)) {
            return false;
        }

        $graceEnd = $scheduledAt->copy()->addMinutes(self::DISPATCH_GRACE_MINUTES);

        return $now->greaterThan($scheduledAt) && $now->lessThanOrEqualTo($graceEnd);
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

        $deadline = $this->filingDeadline();
        if ($this->now()->greaterThan($deadline)) {
            return false;
        }

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

        $deadline = $this->filingDeadline();
        if ($this->now()->greaterThan($deadline)) {
            return false;
        }

        if (! $this->isReminderDay($deadline, $this->settings->normalizedDirectorReviewReminderDaysBeforeDeadline())) {
            return false;
        }

        return $this->isWithinScheduledClock($this->settings->directorReviewReminderClockColumn());
    }

    public function directorReviewReminderSlotKey(): string
    {
        $deadline = $this->filingDeadline();
        $filing = $this->filingWeek();
        $offsets = $this->settings->normalizedDirectorReviewReminderDaysBeforeDeadline();

        return implode(':', [
            'director',
            $filing['iso_year'],
            $filing['iso_week'],
            $this->now()->toDateString(),
            $this->matchedReminderOffset($deadline, $offsets),
            $this->settings->directorReviewReminderClockColumn(),
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

        return $this->isWithinScheduledClock('summary_send_time');
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
        $dirClock = $this->settings->directorReviewReminderClockColumn();

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
            'director_clock_label' => $this->clockLabel($dirClock),
            'compiled_scheduled_at' => $this->scheduledDateTimeToday('summary_send_time'),
            'compiled_is_deadline_day' => $this->now()->copy()->startOfDay()->equalTo($deadline->copy()->startOfDay()),
            'compiled_within_clock' => $this->isWithinScheduledClock('summary_send_time'),
            'compiled_would_dispatch' => $this->passesCompiledSummarySchedule(false),
        ];
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
