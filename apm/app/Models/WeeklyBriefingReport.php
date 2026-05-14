<?php

namespace App\Models;

use App\Services\WeeklyBriefingCompletionSummary;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class WeeklyBriefingReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'division_id',
        'directorate_id',
        'contribution_key',
        'report_iso_week_year',
        'report_iso_week',
        'period_start',
        'status',
        'section1_major_happenings',
        'section2_bottlenecks',
        'submitted_at',
        'submitted_by_staff_id',
        'director_reviewed_at',
        'director_reviewed_by_staff_id',
        'director_review_trail',
    ];

    protected function casts(): array
    {
        return [
            'division_id' => 'integer',
            'directorate_id' => 'integer',
            'report_iso_week_year' => 'integer',
            'report_iso_week' => 'integer',
            'period_start' => 'date',
            'section1_major_happenings' => 'array',
            'section2_bottlenecks' => 'array',
            'submitted_at' => 'datetime',
            'submitted_by_staff_id' => 'integer',
            'contribution_key' => 'string',
            'director_reviewed_at' => 'datetime',
            'director_reviewed_by_staff_id' => 'integer',
            'director_review_trail' => 'array',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'submitted_by_staff_id', 'staff_id');
    }

    public function directorReviewedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'director_reviewed_by_staff_id', 'staff_id');
    }

    /**
     * Division row for this report when contribution is a division brief (d-*).
     */
    public function divisionForContribution(): ?Division
    {
        $k = (string) ($this->contribution_key ?? '');
        if (! str_starts_with($k, 'd-')) {
            return null;
        }
        $id = (int) substr($k, 2);

        return $this->relationLoaded('division') && $this->division && (int) $this->division->id === $id
            ? $this->division
            : Division::query()->find($id);
    }

    /**
     * Directorate whose director (directorates.director_id) may review this report: dr-* is explicit;
     * d-* resolves via the division's directorate_id.
     */
    public function directorateForDirectorReview(): ?Directorate
    {
        $k = (string) ($this->contribution_key ?? '');
        if (str_starts_with($k, 'dr-')) {
            $id = (int) substr($k, 3);

            return $id > 0 ? Directorate::query()->find($id) : null;
        }
        if (str_starts_with($k, 'd-')) {
            $divId = (int) substr($k, 2);
            $div = Division::query()->find($divId);
            $dirId = (int) ($div?->directorate_id ?? 0);

            return $dirId > 0 ? Directorate::query()->find($dirId) : null;
        }

        return null;
    }

    public function requiresDirectorReview(): bool
    {
        $dir = $this->directorateForDirectorReview();

        return $dir !== null && (int) ($dir->director_id ?? 0) > 0;
    }

    public function isDirectorReviewed(): bool
    {
        return $this->director_reviewed_at !== null;
    }

    /**
     * @param  'edited'|'reviewed'  $action
     */
    public function appendDirectorReviewTrail(string $action, int $staffId): void
    {
        $trail = $this->director_review_trail;
        if (! is_array($trail)) {
            $trail = [];
        }
        $trail[] = [
            'at' => now()->toIso8601String(),
            'staff_id' => $staffId,
            'action' => $action,
        ];
        $this->director_review_trail = $trail;
    }

    public function directorReviewSummaryLine(): string
    {
        if (! $this->requiresDirectorReview()) {
            return 'N/A (no directorate director)';
        }
        if ($this->status !== self::STATUS_SUBMITTED) {
            return '—';
        }
        if ($this->director_reviewed_at) {
            return 'Reviewed by director';
        }

        return 'Not reviewed by director';
    }

    public function directorReviewTrailSummary(): string
    {
        $trail = $this->director_review_trail;
        if (! is_array($trail) || $trail === []) {
            return '—';
        }
        $parts = [];
        foreach ($trail as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $at = isset($entry['at']) ? (string) $entry['at'] : '';
            $act = isset($entry['action']) ? (string) $entry['action'] : '';
            $sid = isset($entry['staff_id']) ? (int) $entry['staff_id'] : 0;
            if ($at === '' && $act === '') {
                continue;
            }
            $parts[] = trim($act.' staff #'.$sid.' @ '.$at);
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    public static function periodMonday(int $isoYear, int $isoWeek): Carbon
    {
        return Carbon::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();
    }

    /**
     * User-facing reporting window: Monday–Sunday for the ISO week, with optional ISO suffix.
     */
    public static function humanIsoWeekRange(int $isoYear, int $isoWeek, bool $includeIsoSuffix = true): string
    {
        $mon = self::periodMonday($isoYear, $isoWeek);
        $sun = $mon->copy()->addDays(6);
        $main = 'Week start: '.$mon->format('l, M j, Y').' · Week end: '.$sun->format('l, M j, Y');
        if ($includeIsoSuffix) {
            $main .= ' (ISO W'.$isoWeek.'/'.$isoYear.')';
        }

        return $main;
    }

    public function isoWeekDateRangeLabel(bool $includeIsoSuffix = true): string
    {
        return self::humanIsoWeekRange((int) $this->report_iso_week_year, (int) $this->report_iso_week, $includeIsoSuffix);
    }

    public function submissionDeadline(WeeklyBriefingSetting $settings): Carbon
    {
        $monday = Carbon::parse($this->period_start)->startOfDay();

        // Next ISO week on the hub (`filing_iso_week_offset === 1`): contributors file ahead for the
        // upcoming reporting week; close on the Friday immediately *before* that week starts (not the
        // Friday inside the reporting week).
        if (((int) ($settings->filing_iso_week_offset ?? 0)) === 1) {
            return $monday->copy()->subDays(3)->setTimeFromTimeString($settings->submission_close_time);
        }

        $targetDow = (int) $settings->submission_weekday;
        $daysAdd = ($targetDow - $monday->dayOfWeek + 7) % 7;

        return $monday->copy()->addDays($daysAdd)->setTimeFromTimeString($settings->submission_close_time);
    }

    public static function syntheticDeadlineForIsoWeek(WeeklyBriefingSetting $settings, int $isoYear, int $isoWeek): Carbon
    {
        $r = new self([
            'period_start' => self::periodMonday($isoYear, $isoWeek)->toDateString(),
        ]);

        return $r->submissionDeadline($settings);
    }

    /**
     * Organisation-wide compiled PDF / central completion summary: optionally omit briefs
     * that still require director review when a directorate director is assigned (`d-*` and `dr-*`).
     *
     * @param  Collection<int, self>  $reports
     * @return Collection<int, self>
     */
    public static function filterForOrganisationCompiledExport(Collection $reports, WeeklyBriefingSetting $settings): Collection
    {
        if (! (bool) ($settings->compiled_exclude_unreviewed_director_divisions ?? false)) {
            return $reports;
        }

        return $reports->filter(function (self $r) {
            $k = (string) ($r->contribution_key ?? '');
            if (! str_starts_with($k, 'd-') && ! str_starts_with($k, 'dr-')) {
                return true;
            }
            if (! $r->requiresDirectorReview()) {
                return true;
            }

            return $r->isDirectorReviewed();
        })->values();
    }

    public function contributionEntityLabel(): string
    {
        $k = (string) ($this->contribution_key ?? '');
        if ($k === '') {
            return (string) ($this->division?->division_name ?? '—');
        }

        $custom = WeeklyBriefingContributor::displayNameForContributionKey($k);
        if ($custom !== null) {
            return $custom;
        }

        return WeeklyBriefingCompletionSummary::labelForKey($k);
    }
}
