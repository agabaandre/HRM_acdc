<?php

namespace App\Models;

use App\Models\WeeklyBriefingContributor;
use App\Services\WeeklyBriefingCompletionSummary;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public static function periodMonday(int $isoYear, int $isoWeek): Carbon
    {
        return Carbon::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();
    }

    public function submissionDeadline(WeeklyBriefingSetting $settings): Carbon
    {
        $monday = Carbon::parse($this->period_start)->startOfDay();
        $targetDow = (int) $settings->submission_weekday;
        $daysAdd = ($targetDow - $monday->dayOfWeek + 7) % 7;

        return $monday->copy()->addDays($daysAdd)->setTimeFromTimeString($settings->submission_close_time);
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
