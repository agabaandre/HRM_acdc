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

        $dirId = (int) ($this->directorate_id ?? 0);
        if ($dirId > 0) {
            return $this->relationLoaded('directorate') && $this->directorate
                ? $this->directorate
                : Directorate::query()->find($dirId);
        }

        $divId = (int) ($this->division_id ?? 0);
        if ($divId > 0) {
            $div = $this->relationLoaded('division') && $this->division
                ? $this->division
                : Division::query()->find($divId);
            $dirId = (int) ($div?->directorate_id ?? 0);

            return $dirId > 0 ? Directorate::query()->find($dirId) : null;
        }

        return null;
    }

    /**
     * @return list<array{major_happening?: string, description_key_actions?: string, strategic_relevance?: string}>
     */
    public function section1RowsForForm(): array
    {
        return self::normalizeSectionRows($this->section1_major_happenings, [
            'major_happening' => '',
            'description_key_actions' => '',
            'strategic_relevance' => '',
        ]);
    }

    /**
     * @return list<array{issue?: string, impact_risk?: string, required_action?: string}>
     */
    public function section2RowsForForm(): array
    {
        return self::normalizeSectionRows($this->section2_bottlenecks, [
            'issue' => '',
            'impact_risk' => '',
            'required_action' => '',
        ]);
    }

    /**
     * @param  array<string, string>  $emptyRow
     * @return list<array<string, string>>
     */
    private static function normalizeSectionRows(mixed $raw, array $emptyRow): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $merged = array_merge($emptyRow, $row);
            $out[] = array_map(fn ($v) => is_string($v) ? $v : (string) ($v ?? ''), $merged);
        }

        return $out;
    }

    public function requiresDirectorReview(): bool
    {
        $divId = (int) ($this->division_id ?? 0);
        if ($divId <= 0 && str_starts_with((string) ($this->contribution_key ?? ''), 'd-')) {
            $divId = (int) substr((string) $this->contribution_key, 2);
        }
        if ($divId > 0) {
            $div = Division::query()->find($divId);
            if ($div && (int) ($div->director_id ?? 0) > 0) {
                return true;
            }
        }

        $dir = $this->directorateForDirectorReview();
        if ($dir === null) {
            return false;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('directorates', 'director_id')) {
            return (int) ($dir->director_id ?? 0) > 0;
        }

        return false;
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

    public function appendSubmissionFiledOnBehalfTrail(int $filerStaffId, int $attributedToStaffId): void
    {
        $trail = $this->director_review_trail;
        if (! is_array($trail)) {
            $trail = [];
        }
        $trail[] = [
            'at' => now()->toIso8601String(),
            'staff_id' => $filerStaffId,
            'action' => 'submitted_on_behalf',
            'attributed_to_staff_id' => $attributedToStaffId,
        ];
        $this->director_review_trail = $trail;
    }

    public function submissionFiledOnBehalfByStaffId(): ?int
    {
        $trail = $this->director_review_trail;
        if (! is_array($trail)) {
            return null;
        }
        foreach (array_reverse($trail) as $entry) {
            if (! is_array($entry) || ($entry['action'] ?? '') !== 'submitted_on_behalf') {
                continue;
            }
            $sid = (int) ($entry['staff_id'] ?? 0);

            return $sid > 0 ? $sid : null;
        }

        return null;
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
            return 'Reviewed';
        }

        return 'Yet to be Reviewed';
    }

    /**
     * Staff id of the director who should review this report (division director, else directorate director).
     */
    public function assignedDirectorStaffId(): int
    {
        $divId = (int) ($this->division_id ?? 0);
        if ($divId <= 0 && str_starts_with((string) ($this->contribution_key ?? ''), 'd-')) {
            $divId = (int) substr((string) $this->contribution_key, 2);
        }
        if ($divId > 0) {
            $div = $this->relationLoaded('division') && $this->division && (int) $this->division->id === $divId
                ? $this->division
                : Division::query()->find($divId);
            $divisionDirectorId = (int) ($div->director_id ?? 0);
            if ($divisionDirectorId > 0) {
                return $divisionDirectorId;
            }
        }

        $dir = $this->directorateForDirectorReview();

        return (int) ($dir?->director_id ?? 0);
    }

    /**
     * Director name shown on the edit page (division director or directorate director).
     */
    public function assignedDirectorDisplayName(): string
    {
        $divId = (int) ($this->division_id ?? 0);
        if ($divId <= 0 && str_starts_with((string) ($this->contribution_key ?? ''), 'd-')) {
            $divId = (int) substr((string) $this->contribution_key, 2);
        }
        if ($divId > 0) {
            $div = $this->relationLoaded('division') && $this->division && (int) $this->division->id === $divId
                ? $this->division
                : Division::query()->find($divId);
            $dirStaffId = (int) ($div->director_id ?? 0);
            if ($dirStaffId > 0) {
                $staff = Staff::query()->find($dirStaffId);
                if ($staff !== null) {
                    $name = trim((string) $staff->name);
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
        }

        $dir = $this->directorateForDirectorReview();
        if ($dir !== null) {
            $dir->loadMissing('director');
            $name = trim((string) ($dir->director?->name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return $this->hubDirectorateDisplayRow()['director_name'];
    }

    public function directorReviewTrailSummary(): string
    {
        $trail = $this->director_review_trail;
        if (! is_array($trail) || $trail === []) {
            return '—';
        }
        $nameCache = [];
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
            $when = $at !== '' ? $this->formatTrailTimestamp($at) : '';
            $who = $this->staffDisplayNameForTrail($sid, $nameCache);
            if ($act === 'submitted_on_behalf') {
                $attr = isset($entry['attributed_to_staff_id']) ? (int) $entry['attributed_to_staff_id'] : 0;
                $onBehalfOf = $this->staffDisplayNameForTrail($attr, $nameCache);
                $parts[] = trim('Submitted on behalf of '.$onBehalfOf.' by '.$who.($when !== '' ? ' · '.$when : ''));

                continue;
            }
            if ($act === 'reviewed') {
                $parts[] = trim('Reviewed by '.$who.($when !== '' ? ' · '.$when : ''));

                continue;
            }
            if ($act === 'edited') {
                $parts[] = trim('Edited by '.$who.($when !== '' ? ' · '.$when : ''));

                continue;
            }
            $parts[] = trim($act.' · '.$who.($when !== '' ? ' · '.$when : ''));
        }

        return $parts === [] ? '—' : implode('; ', $parts);
    }

    /**
     * @param  array<int, string>  $cache
     */
    private function staffDisplayNameForTrail(int $staffId, array &$cache): string
    {
        if ($staffId <= 0) {
            return '—';
        }
        if (! array_key_exists($staffId, $cache)) {
            $staff = Staff::query()->find($staffId);
            $cache[$staffId] = $staff !== null ? trim((string) $staff->name) : '';
        }

        return $cache[$staffId] !== '' ? $cache[$staffId] : 'Staff #'.$staffId;
    }

    private function formatTrailTimestamp(string $at): string
    {
        try {
            return Carbon::parse($at)->timezone(config('app.timezone'))->format('M j, Y g:i A');
        } catch (\Throwable) {
            return $at;
        }
    }

    public static function periodMonday(int $isoYear, int $isoWeek): Carbon
    {
        return Carbon::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();
    }

    /**
     * @return array{iso_year: int, iso_week: int}
     */
    public static function previousIsoWeekPair(int $isoYear, int $isoWeek): array
    {
        $monday = self::periodMonday($isoYear, $isoWeek)->subWeek();

        return [
            'iso_year' => (int) $monday->isoWeekYear(),
            'iso_week' => (int) $monday->isoWeek(),
        ];
    }

    /**
     * Compact Mon–Sun range for hub labels (short weekday names).
     */
    public static function humanIsoWeekRangeInline(int $isoYear, int $isoWeek, bool $includeIsoSuffix = true): string
    {
        $mon = self::periodMonday($isoYear, $isoWeek);
        $sun = $mon->copy()->addDays(6);
        $main = $mon->format('D, M j').' – '.$sun->format('D, M j, Y');
        if ($includeIsoSuffix) {
            $main .= ' (W'.$isoWeek.'/'.$isoYear.')';
        }

        return $main;
    }

    /**
     * Label for ISO week &lt;select&gt; options on the All reports tab.
     */
    public static function isoWeekFilterOptionLabel(int $isoYear, int $isoWeek): string
    {
        $mon = self::periodMonday($isoYear, $isoWeek);
        $sun = $mon->copy()->addDays(6);

        return 'W'.$isoWeek.' · '.$mon->format('D, M j').' – '.$sun->format('D, M j, Y');
    }

    /**
     * User-facing reporting window: Monday–Sunday for the ISO week, with optional ISO suffix.
     */
    public static function humanIsoWeekRange(int $isoYear, int $isoWeek, bool $includeIsoSuffix = true, bool $shortDays = false): string
    {
        $mon = self::periodMonday($isoYear, $isoWeek);
        $sun = $mon->copy()->addDays(6);
        $dayFormat = $shortDays ? 'D, M j, Y' : 'l, M j, Y';
        $main = 'Week start: '.$mon->format($dayFormat).' · Week end: '.$sun->format($dayFormat);
        if ($includeIsoSuffix) {
            $main .= ' (W'.$isoWeek.'/'.$isoYear.')';
        }

        return $main;
    }

    public function isoWeekDateRangeLabel(bool $includeIsoSuffix = true, bool $shortDays = false): string
    {
        return self::humanIsoWeekRange(
            (int) $this->report_iso_week_year,
            (int) $this->report_iso_week,
            $includeIsoSuffix,
            $shortDays
        );
    }

    public function isoWeekStartEndLabel(bool $shortDays = true): string
    {
        if (! $this->period_start) {
            return '—';
        }
        $mon = Carbon::parse($this->period_start)->startOfDay();
        $sun = $mon->copy()->addDays(6);
        $fmt = $shortDays ? 'D, M j, Y' : 'M j, Y';

        return $mon->format($fmt).' → '.$sun->format($fmt);
    }

    /**
     * Submission close datetime for an ISO reporting week.
     *
     * @param  Carbon  $reportWeekMonday  Monday 00:00 of the reporting ISO week
     * @param  bool  $beforeWeekStarts  When true (next-week filing), the configured weekday in the calendar week before that Monday
     */
    public static function submissionCloseAt(
        Carbon $reportWeekMonday,
        int $submissionWeekday,
        string $closeTime,
        bool $beforeWeekStarts,
    ): Carbon {
        $anchorMonday = $beforeWeekStarts ? $reportWeekMonday->copy()->subWeek() : $reportWeekMonday->copy();
        $daysAdd = ($submissionWeekday - $anchorMonday->dayOfWeek + 7) % 7;

        return $anchorMonday->copy()->addDays($daysAdd)->setTimeFromTimeString($closeTime);
    }

    public function submissionDeadline(WeeklyBriefingSetting $settings): Carbon
    {
        $monday = Carbon::parse($this->period_start)->startOfDay();

        return self::submissionCloseAt(
            $monday,
            (int) $settings->submission_weekday,
            (string) $settings->submission_close_time,
            ((int) ($settings->filing_iso_week_offset ?? 0)) === 1,
        );
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

    /**
     * Directorate name and assigned director for hub tables; empty strings when the contribution has no directorate.
     *
     * @return array{directorate_name: string, director_name: string}
     */
    public function hubDirectorateDisplayRow(): array
    {
        $k = trim((string) ($this->contribution_key ?? ''));
        $directorate = null;

        if (str_starts_with($k, 'd-')) {
            $divId = (int) substr($k, 2);
            if ($divId <= 0) {
                return ['directorate_name' => '', 'director_name' => ''];
            }
            $div = $this->relationLoaded('division') && $this->division && (int) $this->division->id === $divId
                ? $this->division
                : Division::query()->with(['directorate.director'])->find($divId);
            $directorate = $div?->directorate;
        } elseif (str_starts_with($k, 'dr-')) {
            $dirId = (int) substr($k, 3);
            if ($dirId <= 0) {
                return ['directorate_name' => '', 'director_name' => ''];
            }
            $directorate = $this->relationLoaded('directorate') && $this->directorate && (int) $this->directorate->id === $dirId
                ? $this->directorate
                : Directorate::query()->with(['director'])->find($dirId);
        }

        if (! $directorate) {
            return ['directorate_name' => '', 'director_name' => ''];
        }
        $directorate->loadMissing('director');

        return [
            'directorate_name' => trim((string) ($directorate->name ?? '')),
            'director_name' => $directorate->director ? trim((string) $directorate->director->name) : '',
        ];
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
