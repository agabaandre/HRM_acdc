<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Weekly auto-archive schedule for stale draft memos (see budget_draft_max_age_months).
 */
class StaleDraftArchiveSchedule
{
    public function __construct(
        private readonly BudgetCommitmentSettings $settings = new BudgetCommitmentSettings(),
        private readonly StaleDraftMemosService $staleDrafts = new StaleDraftMemosService()
    ) {}

    public function weeklyRunHour(): int
    {
        return 6;
    }

    public function weeklyRunWeekday(): int
    {
        return Carbon::MONDAY;
    }

    public function nextWeeklyRun(?Carbon $after = null): Carbon
    {
        $after = ($after ?? now())->copy();
        $run = $after->copy()->next($this->weeklyRunWeekday())->setTime($this->weeklyRunHour(), 0, 0);
        if ($after->isSameDay($run) && $after->gte($run)) {
            $run->addWeek();
        }

        return $run;
    }

    public function archiveEligibleAt(Carbon $updatedAt): Carbon
    {
        $months = $this->settings->draftMaxAgeMonths();

        return $updatedAt->copy()->addMonths(max(1, $months));
    }

    public function scheduledArchiveAt(Carbon $updatedAt): Carbon
    {
        $eligible = $this->archiveEligibleAt($updatedAt);

        return $this->nextWeeklyRun($eligible);
    }

    /**
     * @return array{
     *   months: int,
     *   eligible_at: \Carbon\Carbon,
     *   scheduled_archive_at: \Carbon\Carbon,
     *   is_stale: bool,
     *   next_weekly_run: \Carbon\Carbon
     * }|null
     */
    public function noticeForMemo(object $memo): ?array
    {
        if ((string) ($memo->overall_status ?? '') !== 'draft') {
            return null;
        }

        if (! $this->settings->staleDraftAutoArchiveEnabled() || $this->settings->draftMaxAgeMonths() <= 0) {
            return null;
        }

        if (! $this->staleDrafts->memoHoldsBudget($memo)) {
            return null;
        }

        $updatedAt = $memo->updated_at instanceof Carbon
            ? $memo->updated_at->copy()
            : ($memo->updated_at ? Carbon::parse($memo->updated_at) : null);

        if ($updatedAt === null) {
            return null;
        }

        $cutoff = $this->settings->draftBudgetCutoff();

        return [
            'months' => $this->settings->draftMaxAgeMonths(),
            'eligible_at' => $this->archiveEligibleAt($updatedAt),
            'scheduled_archive_at' => $this->scheduledArchiveAt($updatedAt),
            'is_stale' => $cutoff !== null && $updatedAt->lt($cutoff),
            'next_weekly_run' => $this->nextWeeklyRun(),
        ];
    }
}
