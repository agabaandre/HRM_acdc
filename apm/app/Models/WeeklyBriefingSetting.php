<?php

namespace App\Models;

use App\Services\WeeklyBriefingScheduleGate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyBriefingSetting extends Model
{
    /** @var list<string> PHP weekday index 0=Sunday … 6=Saturday */
    public const SUBMISSION_WEEKDAY_LABELS = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
    ];

    protected $fillable = [
        'submission_weekday',
        'filing_iso_week_offset',
        'hod_reminder_time',
        'hod_reminder_days_before_deadline',
        'hod_reminder_clock',
        'director_review_reminder_days_before_deadline',
        'director_review_reminder_clock',
        'compiled_exclude_unreviewed_director_divisions',
        'submission_close_time',
        'summary_send_time',
        'compiled_recipient_emails',
        'cc_division_hod_on_compiled',
        'reminders_enabled',
        'division_directors_can_access_module',
        'report_unlock_override_enabled',
        'report_unlock_override_until',
        'report_unlock_override_scope',
        'report_unlock_override_division_id',
        'report_viewer_staff_ids',
    ];

    protected function casts(): array
    {
        return [
            'submission_weekday' => 'integer',
            'filing_iso_week_offset' => 'integer',
            'hod_reminder_days_before_deadline' => 'array',
            'director_review_reminder_days_before_deadline' => 'array',
            'compiled_exclude_unreviewed_director_divisions' => 'boolean',
            'cc_division_hod_on_compiled' => 'boolean',
            'reminders_enabled' => 'boolean',
            'division_directors_can_access_module' => 'boolean',
            'report_unlock_override_enabled' => 'boolean',
            'report_unlock_override_until' => 'datetime',
            'report_unlock_override_scope' => 'string',
            'report_unlock_override_division_id' => 'integer',
            'report_viewer_staff_ids' => 'array',
        ];
    }

    public static function current(): self
    {
        // Prefer the row that was saved most recently (avoids a duplicate stale row blocking director access).
        $row = static::query()->orderByDesc('updated_at')->orderByDesc('id')->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'submission_weekday' => 5,
            'hod_reminder_time' => '09:00',
            'hod_reminder_days_before_deadline' => [1, 0],
            'hod_reminder_clock' => 'hod_reminder_time',
            'director_review_reminder_days_before_deadline' => [1],
            'director_review_reminder_clock' => 'hod_reminder_time',
            'compiled_exclude_unreviewed_director_divisions' => false,
            'submission_close_time' => '14:00',
            'summary_send_time' => '14:10',
            'compiled_recipient_emails' => null,
            'cc_division_hod_on_compiled' => true,
            'reminders_enabled' => true,
            'division_directors_can_access_module' => true,
        ]);
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(WeeklyBriefingContributor::class, 'weekly_briefing_setting_id');
    }

    /**
     * ISO year/week used as the default “open filing” reporting week on the hub, for new reports
     * (when iso_year/iso_week are not supplied), HoD reminders, and the compiled-summary send.
     * filing_iso_week_offset: 0 = calendar current ISO week, 1 = the following ISO week (e.g. brief for next week).
     *
     * @return array{iso_year: int, iso_week: int}
     */
    public function filingIsoWeekPair(?Carbon $at = null): array
    {
        $at = $at ?? Carbon::now();
        $ref = ((int) ($this->filing_iso_week_offset ?? 0) === 1) ? $at->copy()->addWeek() : $at->copy();

        return [
            'iso_year' => (int) $ref->isoWeekYear(),
            'iso_week' => (int) $ref->isoWeek(),
        ];
    }

    /**
     * Submission deadline for the default filing ISO week (see {@see WeeklyBriefingReport::submissionDeadline}).
     * Used by the hub, contributor / director reminders, and compiled-summary scheduling so they stay aligned
     * with advance filing (`filing_iso_week_offset === 1`, configured weekday before the reporting week).
     */
    public function submissionWeekdayLabel(?int $weekday = null): string
    {
        $d = $weekday ?? (int) $this->submission_weekday;

        return self::SUBMISSION_WEEKDAY_LABELS[$d] ?? 'Unknown';
    }

    public function filingSubmissionDeadline(?Carbon $at = null): Carbon
    {
        $filing = $this->filingIsoWeekPair($at);

        return WeeklyBriefingReport::syntheticDeadlineForIsoWeek($this, $filing['iso_year'], $filing['iso_week']);
    }

    /**
     * Whether the current clock time matches a stored time column (exact minute or grace window).
     */
    public function matchesTimeNow(string $attribute): bool
    {
        return WeeklyBriefingScheduleGate::for($this)->isWithinScheduledClock($attribute);
    }

    /**
     * HoD / contributor reminders always use {@see hod_reminder_time} from settings
     * (same as before deadline-offset scheduling was added).
     */
    public function hodReminderClockColumn(): string
    {
        return 'hod_reminder_time';
    }

    /**
     * Day-before director reminders use HoD reminder time (not submission close).
     */
    public function directorDayBeforeReminderClockColumn(): string
    {
        return 'hod_reminder_time';
    }

    /** @deprecated Use {@see directorDayBeforeReminderClockColumn()} — submission close is not used for directors. */
    public function directorReviewReminderClockColumn(): string
    {
        return $this->directorDayBeforeReminderClockColumn();
    }

    public function hodReminderTimeHm(): string
    {
        return self::normalizeTimeHm((string) ($this->hod_reminder_time ?? ''));
    }

    public function submissionCloseTimeHm(): string
    {
        return self::normalizeTimeHm((string) ($this->submission_close_time ?? ''));
    }

    public function summarySendTimeHm(): string
    {
        return self::normalizeTimeHm((string) ($this->summary_send_time ?? ''));
    }

    protected static function normalizeTimeHm(string $value): string
    {
        $value = trim($value);

        return $value === '' ? '' : substr($value, 0, 5);
    }

    /**
     * @return list<int>
     */
    public function normalizedHodReminderDaysBeforeDeadline(): array
    {
        return self::normalizeDaysBeforeList($this->hod_reminder_days_before_deadline, [1, 0]);
    }

    /**
     * @return list<int>
     */
    public function normalizedDirectorReviewReminderDaysBeforeDeadline(): array
    {
        return self::normalizeDaysBeforeList($this->director_review_reminder_days_before_deadline, [1]);
    }

    /**
     * @param  mixed  $raw
     * @return list<int>
     */
    protected static function normalizeDaysBeforeList($raw, array $fallback): array
    {
        if (! is_array($raw) || $raw === []) {
            $raw = $fallback;
        }
        $out = [];
        foreach ($raw as $n) {
            $i = (int) $n;
            if ($i >= 0 && $i <= 30) {
                $out[$i] = $i;
            }
        }

        return $out === [] ? $fallback : array_values($out);
    }

    /**
     * Admin unlock window: contributors (and directors) may edit / submit past the normal deadline
     * for locked or late drafts, until {@see report_unlock_override_until}.
     */
    public function reportUnlockOverrideAppliesTo(WeeklyBriefingReport $report): bool
    {
        if (! $this->report_unlock_override_enabled) {
            return false;
        }
        $until = $this->report_unlock_override_until;
        if ($until === null) {
            return false;
        }
        if (Carbon::now()->greaterThan(Carbon::parse($until))) {
            return false;
        }
        $scope = (string) ($this->report_unlock_override_scope ?? 'all');
        if ($scope !== 'division') {
            return true;
        }
        $divId = (int) ($this->report_unlock_override_division_id ?? 0);
        if ($divId <= 0) {
            return false;
        }
        $key = (string) ($report->contribution_key ?? '');
        if (str_starts_with($key, 'd-')) {
            return (int) substr($key, 2) === $divId;
        }
        if (str_starts_with($key, 'dr-')) {
            return (int) ($report->division_id ?? 0) === $divId;
        }

        return false;
    }
}
