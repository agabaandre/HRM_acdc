<?php

namespace Modules\Jobs\Services;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\PerformanceApprovalService;
use Modules\Performance\Services\PerformanceService;
use Modules\Performance\Services\PpaSettingsService;

class PerformanceReminderService
{
    /** @var list<int> Active / due / under renewal — exclude expired (3) and former/separated (4). */
    private array $allowedStatuses = [1, 2, 7];

    /** @var list<int> */
    private array $excludedContractTypes = [1, 3, 5, 7];

    public function __construct(
        private EmailNotificationService $mail,
        private PpaSettingsService $ppaSettings,
        private PerformanceApprovalService $approval,
        private PerformanceService $performance,
    ) {}

    /**
     * Daily bundle: PPA + Midterm + Endterm supervisor/staff reminders.
     *
     * @return array<string, int>
     */
    public function runDailyNotifications(): array
    {
        return [
            'ppa_supervisors' => $this->notifySupervisorsPendingPpas(),
            'midterm_supervisors' => $this->notifySupervisorsPendingMidterms(),
            'endterm' => $this->notifySupervisorsPendingEndterms(),
        ];
    }

    public function notifySupervisorsPendingPpas(): int
    {
        $periods = $this->reminderPeriods();
        $deadline = $this->deadlineLabel(PerformancePhase::Ppa);
        $queued = 0;

        foreach ($this->candidateSupervisorIdsForPhase(PerformancePhase::Ppa, $periods) as $supervisorId) {
            if (! $this->staffHasAllowedContract($supervisorId)) {
                continue;
            }

            $pending = $this->performance->pendingApprovals($supervisorId)
                ->filter(fn ($row) => in_array((string) ($row->performance_period ?? ''), $periods, true))
                ->filter(fn ($row) => $this->staffHasAllowedContract((int) $row->staff_id, (string) $row->performance_period))
                ->values();

            if ($pending->isEmpty()) {
                continue;
            }

            $supervisor = $this->staffRow($supervisorId);
            if (! $supervisor || trim((string) ($supervisor->work_email ?? '')) === '') {
                continue;
            }

            $name = $this->displayName($supervisor);
            $periodLabel = $pending->pluck('performance_period')->unique()->implode(', ');
            $body = $this->mail->render('supervisor_reminder', [
                'supervisor_name' => $name,
                'period' => $periodLabel,
                'deadline' => $deadline,
                'pending_list' => $this->mapPendingList($pending),
            ]);
            $to = $this->mail->appendSystemInbox((string) $supervisor->work_email);
            $entryId = md5($supervisorId.'-SUPPPAREM-'.date('Y-m-d'));
            if ($this->mail->queue('Staff Portal System', $to, $body, "Reminder: Pending PPA Approvals for {$periodLabel}", $supervisorId, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        foreach ($periods as $period) {
            $queued += $this->notifyUnsubmittedPpas($period, $deadline);
        }
        $this->mail->purgeTestRecipients();

        return $queued;
    }

    public function notifySupervisorsPendingMidterms(): int
    {
        $periods = $this->reminderPeriods();
        $deadline = $this->deadlineLabel(PerformancePhase::Midterm);
        $queued = 0;

        foreach ($this->candidateSupervisorIdsForPhase(PerformancePhase::Midterm, $periods) as $supervisorId) {
            if (! $this->staffHasAllowedContract($supervisorId)) {
                continue;
            }

            $pending = $this->approval->pendingActionsFor($supervisorId)
                ->filter(fn ($row) => ($row->approval_type ?? '') === 'midterm')
                ->filter(fn ($row) => in_array((string) ($row->performance_period ?? ''), $periods, true))
                ->filter(fn ($row) => $this->staffHasAllowedContract((int) $row->staff_id, (string) $row->performance_period))
                ->values();

            if ($pending->isEmpty()) {
                continue;
            }

            $supervisor = $this->staffRow($supervisorId);
            if (! $supervisor || trim((string) ($supervisor->work_email ?? '')) === '') {
                continue;
            }

            $entryId = md5($supervisorId.'-SUPMIDREM-'.date('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                continue;
            }

            $name = $this->displayName($supervisor);
            $periodLabel = $pending->pluck('performance_period')->unique()->implode(', ');
            $body = $this->mail->render('supervisor_reminder_midterm', [
                'supervisor_name' => $name,
                'period' => $periodLabel,
                'deadline' => $deadline,
                'pending_list' => $this->mapPendingList($pending),
            ]);
            $to = $this->mail->appendSystemInbox((string) $supervisor->work_email);
            if ($this->mail->queue('Staff Portal System', $to, $body, "Reminder: Pending Midterm Approvals for {$periodLabel}", $supervisorId, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        foreach ($periods as $period) {
            $queued += $this->notifyUnsubmittedMidterms($period, $deadline);
        }
        $this->mail->purgeTestRecipients();

        return $queued;
    }

    public function notifySupervisorsPendingEndterms(): int
    {
        $period = $this->endtermPeriodKey();
        $deadline = $this->deadlineLabel(PerformancePhase::Endterm);
        $queued = 0;
        $queued += $this->notifyEndtermSupervisorsForPeriod($period, $deadline);
        $queued += $this->notifyStaffConsentPendingEndterms($period, $deadline);
        $queued += $this->notifyUnsubmittedEndterms($period, $deadline);

        return $queued;
    }

    public function notifySupervisorsPendingPerformanceApproval(): int
    {
        $queued = 0;
        $supervisorIds = $this->candidateSupervisorIdsForPhase(PerformancePhase::Ppa, null)
            ->merge($this->candidateSupervisorIdsForPhase(PerformancePhase::Midterm, null))
            ->merge($this->candidateSupervisorIdsForPhase(PerformancePhase::Endterm, null))
            ->unique()
            ->values();

        foreach ($supervisorIds as $supervisorId) {
            if (! $this->staffHasAllowedContract($supervisorId)) {
                continue;
            }
            $pending = $this->allPendingApprovalsForSupervisor($supervisorId);
            if ($pending === []) {
                continue;
            }
            $entryId = md5($supervisorId.'-SUPPERFAPPREM-'.date('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                continue;
            }
            $supervisor = $this->staffRow($supervisorId);
            if (! $supervisor || trim((string) ($supervisor->work_email ?? '')) === '') {
                continue;
            }
            $typeCounts = ['ppa' => 0, 'midterm' => 0, 'endterm' => 0];
            foreach ($pending as $row) {
                $key = strtolower((string) ($row['approval_type'] ?? 'ppa'));
                if (isset($typeCounts[$key])) {
                    $typeCounts[$key]++;
                }
            }
            $name = $this->displayName($supervisor);
            $portal = (string) config('jobs.schedule.portal_base_url');
            $body = $this->mail->render('supervisor_reminder_performance_approval', [
                'supervisor_name' => $name,
                'generated_on' => date('d M Y H:i'),
                'type_counts' => $typeCounts,
                'pending_list' => $pending,
                'pending_url' => $portal.'performance?tab=pending',
            ]);
            $to = $this->mail->appendSystemInbox((string) $supervisor->work_email);
            if ($this->mail->queue('Staff Portal System', $to, $body, 'Reminder: Pending performance approvals', $supervisorId, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        return $queued;
    }

    protected function notifyUnsubmittedPpas(string $period, string $deadline): int
    {
        $days = $this->daysToDeadline(PerformancePhase::Ppa);
        if ($days === null || $days > 15) {
            return 0;
        }
        $queued = 0;
        foreach ($this->staffWithoutPhase($period, 'ppa') as $staff) {
            if (! $this->staffHasAllowedContract((int) $staff->staff_id, $period)) {
                continue;
            }
            $name = $this->displayName($staff);
            $body = $this->mail->render('staff_reminder', [
                'name' => $name,
                'period' => $period,
                'deadline' => $deadline,
            ]);
            $to = $this->mail->appendSystemInbox((string) $staff->work_email);
            $entryId = md5($staff->staff_id.'-PPAREM-'.$period.'-'.date('Y-m-d'));
            if ($this->mail->queue('Staff Portal System', $to, $body, "Staff PPA Reminder: Submit your PPA ($period)", (int) $staff->staff_id, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        return $queued;
    }

    protected function notifyUnsubmittedMidterms(string $period, string $deadline): int
    {
        $days = $this->daysToDeadline(PerformancePhase::Midterm);
        if ($days === null || $days > 40) {
            return 0;
        }
        $queued = 0;
        foreach ($this->staffWithoutPhase($period, 'midterm') as $staff) {
            if (! $this->staffHasAllowedContract((int) $staff->staff_id, $period)) {
                continue;
            }
            $entryId = md5($staff->staff_id.'-empMIDTERMREM-'.$period.'-'.date('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                continue;
            }
            $name = $this->displayName($staff);
            $body = $this->mail->render('staff_reminder_midterm', [
                'name' => $name,
                'period' => $period,
                'deadline' => $deadline,
            ]);
            $to = $this->mail->appendSystemInbox((string) $staff->work_email);
            if ($this->mail->queue('Staff Portal System', $to, $body, "Midterm Review Reminder: Submit your Midterm ($period)", (int) $staff->staff_id, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        return $queued;
    }

    protected function notifyUnsubmittedEndterms(string $period, string $deadline): int
    {
        $days = $this->daysToDeadline(PerformancePhase::Endterm);
        if ($days === null || $days > 40) {
            return 0;
        }
        $queued = 0;
        foreach ($this->staffWithoutPhase($period, 'endterm') as $staff) {
            if (! $this->staffHasAllowedContract((int) $staff->staff_id, $period)) {
                continue;
            }
            $entryId = md5($staff->staff_id.'-empENDTERMREM-'.$period.'-'.date('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                continue;
            }
            $name = $this->displayName($staff);
            $body = $this->mail->render('staff_reminder_endterm', [
                'name' => $name,
                'period' => $period,
                'deadline' => $deadline,
            ]);
            $to = $this->mail->appendSystemInbox((string) $staff->work_email);
            if ($this->mail->queue('Staff Portal System', $to, $body, "Endterm Review Reminder: Submit your Endterm ($period)", (int) $staff->staff_id, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        return $queued;
    }

    protected function notifyEndtermSupervisorsForPeriod(string $period, string $deadline): int
    {
        $queued = 0;
        $candidates = $this->candidateSupervisorIdsForPhase(PerformancePhase::Endterm, [$period]);

        foreach ($candidates as $supervisorId) {
            if (! $this->staffHasAllowedContract($supervisorId)) {
                continue;
            }

            $pending = $this->approval->pendingActionsFor($supervisorId)
                ->filter(fn ($row) => ($row->approval_type ?? '') === 'endterm')
                ->filter(fn ($row) => (string) ($row->performance_period ?? '') === $period)
                ->filter(fn ($row) => $this->staffHasAllowedContract((int) $row->staff_id, $period))
                ->values();

            if ($pending->isEmpty()) {
                continue;
            }

            $supervisor = $this->staffRow($supervisorId);
            if (! $supervisor || trim((string) ($supervisor->work_email ?? '')) === '') {
                continue;
            }

            $firstStep = $pending->filter(fn ($row) => str_contains(strtolower((string) ($row->overall_status ?? '')), 'first'));
            $secondStep = $pending->filter(fn ($row) => str_contains(strtolower((string) ($row->overall_status ?? '')), 'second'));
            $firstIds = $firstStep->pluck('entry_id')->all();
            $secondIds = $secondStep->pluck('entry_id')->all();

            if ($firstStep->isNotEmpty()) {
                $queued += $this->queueEndtermBundle($supervisorId, $supervisor, $firstStep, $period, $deadline, 'first', 'supervisor_reminder_endterm_first', 'SUP1ENDREM');
            }
            if ($secondStep->isNotEmpty()) {
                $queued += $this->queueEndtermBundle($supervisorId, $supervisor, $secondStep, $period, $deadline, 'second', 'supervisor_reminder_endterm_second', 'SUP2ENDREM');
            }
            // Any other endterm pending step for this supervisor (fallback template).
            $other = $pending->reject(fn ($row) => in_array($row->entry_id, $firstIds, true) || in_array($row->entry_id, $secondIds, true));
            if ($other->isNotEmpty()) {
                $queued += $this->queueEndtermBundle($supervisorId, $supervisor, $other, $period, $deadline, 'first', 'supervisor_reminder_endterm_first', 'SUPENDREM');
            }
        }

        return $queued;
    }

    /**
     * @param  Collection<int, object>  $pending
     */
    protected function queueEndtermBundle(
        int $supervisorId,
        object $supervisor,
        Collection $pending,
        string $period,
        string $deadline,
        string $which,
        string $view,
        string $keyPrefix,
    ): int {
        $entryId = md5($supervisorId.'-'.$keyPrefix.'-'.$period.'-'.date('Y-m-d'));
        if ($this->mail->entryExists($entryId)) {
            return 0;
        }

        $body = $this->mail->render($view, [
            'supervisor_name' => $this->displayName($supervisor),
            'period' => $period,
            'deadline' => $deadline,
            'pending_list' => $this->mapPendingList($pending),
        ]);
        $to = $this->mail->appendSystemInbox((string) $supervisor->work_email);
        $subject = $which === 'second'
            ? "Reminder: Pending Endterm Second Approvals for {$period}"
            : "Reminder: Pending Endterm Approvals for {$period}";

        return $this->mail->queue('Staff Portal System', $to, $body, $subject, $supervisorId, date('Y-m-d'), date('Y-m-d'), $entryId) ? 1 : 0;
    }

    protected function notifyStaffConsentPendingEndterms(string $period, string $deadline): int
    {
        if (! $this->ppaSettings->endtermRequiresEmployeeConsent()) {
            return 0;
        }

        $queued = 0;
        $staffIds = DB::table('ppa_entries')
            ->where('performance_period', $period)
            ->where('endterm_draft_status', 0)
            ->whereNotNull('endterm_created_at')
            ->pluck('staff_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($staffIds as $staffId) {
            if (! $this->staffHasAllowedContract($staffId, $period)) {
                continue;
            }
            $pending = $this->approval->pendingActionsFor($staffId)
                ->filter(fn ($row) => ($row->approval_type ?? '') === 'endterm')
                ->filter(fn ($row) => (string) ($row->performance_period ?? '') === $period)
                ->filter(fn ($row) => str_contains(strtolower((string) ($row->overall_status ?? '')), 'consent'))
                ->values();

            foreach ($pending as $row) {
                $entryKey = md5($staffId.'-STAFFCONSENTENDREM-'.$row->entry_id.'-'.date('Y-m-d'));
                if ($this->mail->entryExists($entryKey)) {
                    continue;
                }
                $staff = $this->staffRow($staffId);
                if (! $staff || trim((string) ($staff->work_email ?? '')) === '') {
                    continue;
                }
                $body = $this->mail->render('staff_consent_reminder_endterm', [
                    'name' => $this->displayName($staff),
                    'period' => $period,
                    'deadline' => $deadline,
                    'entry_id' => $row->entry_id,
                    'staff_id' => $staffId,
                ]);
                $to = $this->mail->appendSystemInbox((string) $staff->work_email);
                if ($this->mail->queue('Staff Portal System', $to, $body, "Endterm Consent Reminder ($period)", $staffId, date('Y-m-d'), date('Y-m-d'), $entryKey)) {
                    $queued++;
                }
            }
        }

        return $queued;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function allPendingApprovalsForSupervisor(int $supervisorId): array
    {
        $portal = (string) config('jobs.schedule.portal_base_url');
        $out = [];

        foreach ($this->approval->pendingActionsFor($supervisorId) as $row) {
            $type = (string) ($row->approval_type ?? 'ppa');
            if (! in_array($type, ['ppa', 'midterm', 'endterm'], true)) {
                continue;
            }
            // Consent items are for the employee, not supervisors.
            if (str_contains(strtolower((string) ($row->overall_status ?? '')), 'consent')) {
                continue;
            }
            if (! $this->staffHasAllowedContract((int) $row->staff_id, (string) ($row->performance_period ?? ''))) {
                continue;
            }
            $out[] = [
                'staff_name' => $row->staff_name ?? ('#'.$row->staff_id),
                'approval_type' => $type,
                'period' => $row->performance_period ?? '',
                'status' => (string) ($row->overall_status ?? 'Pending approval'),
                'submitted_at' => $row->updated_at ?? $row->created_at ?? $row->midterm_updated_at ?? $row->endterm_updated_at ?? null,
                'review_url' => $portal.'performance/form/'.$type.'/'.$row->entry_id.'/'.$row->staff_id,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, object>  $pending
     * @return list<object|array<string, mixed>>
     */
    protected function mapPendingList(Collection $pending): array
    {
        return $pending->map(fn ($row) => (object) [
            'entry_id' => $row->entry_id,
            'staff_id' => $row->staff_id,
            'staff_name' => $row->staff_name ?? ('#'.$row->staff_id),
            'performance_period' => $row->performance_period ?? '',
            'created_at' => $row->updated_at ?? $row->created_at ?? $row->midterm_updated_at ?? $row->endterm_updated_at ?? null,
        ])->all();
    }

    /**
     * @param  list<string>|null  $periods
     * @return Collection<int, int>
     */
    protected function candidateSupervisorIdsForPhase(PerformancePhase $phase, ?array $periods): Collection
    {
        $q = DB::table('ppa_entries');
        if ($periods !== null && $periods !== []) {
            $q->whereIn('performance_period', $periods);
        }

        $ids = match ($phase) {
            PerformancePhase::Ppa => $q->where('draft_status', 0)
                ->get(['supervisor_id', 'supervisor2_id'])
                ->flatMap(fn ($r) => [(int) ($r->supervisor_id ?? 0), (int) ($r->supervisor2_id ?? 0)]),
            PerformancePhase::Midterm => $q->where('midterm_draft_status', 0)
                ->whereNotNull('midterm_created_at')
                ->get(['midterm_supervisor_1', 'midterm_supervisor_2'])
                ->flatMap(fn ($r) => [(int) ($r->midterm_supervisor_1 ?? 0), (int) ($r->midterm_supervisor_2 ?? 0)]),
            PerformancePhase::Endterm => $q->where('endterm_draft_status', 0)
                ->whereNotNull('endterm_created_at')
                ->get(['endterm_supervisor_1', 'endterm_supervisor_2'])
                ->flatMap(fn ($r) => [(int) ($r->endterm_supervisor_1 ?? 0), (int) ($r->endterm_supervisor_2 ?? 0)]),
        };

        return $ids->filter(fn ($id) => $id > 0)->unique()->values();
    }

    /**
     * @return list<object>
     */
    protected function staffWithoutPhase(string $period, string $phase): array
    {
        $latest = '(SELECT staff_id, MAX(staff_contract_id) AS cid FROM staff_contracts GROUP BY staff_id)';
        $staff = DB::table('staff as s')
            ->join(DB::raw($latest.' as latest'), 'latest.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'latest.cid')
            ->whereIn('sc.status_id', [1, 2, 7])
            ->whereNotIn('sc.contract_type_id', $this->excludedContractTypes)
            ->whereRaw("TRIM(COALESCE(s.work_email, '')) != ''")
            ->where('s.work_email', 'not like', 'xx%')
            ->get(['s.staff_id', 's.title', 's.fname', 's.lname', 's.work_email']);

        if ($staff->isEmpty()) {
            return [];
        }

        $ids = $staff->pluck('staff_id')->map(fn ($id) => (int) $id)->all();
        $q = DB::table('ppa_entries')->whereIn('staff_id', $ids)->where('performance_period', $period);
        if ($phase === 'ppa') {
            $submitted = $q->where('draft_status', '!=', 1)->pluck('staff_id')->map(fn ($id) => (int) $id)->all();
        } elseif ($phase === 'midterm') {
            $hasPpa = DB::table('ppa_entries')->whereIn('staff_id', $ids)->where('performance_period', $period)->where('draft_status', '!=', 1)->pluck('staff_id')->map(fn ($id) => (int) $id)->all();
            $staff = $staff->filter(fn ($s) => in_array((int) $s->staff_id, $hasPpa, true));
            $submitted = DB::table('ppa_entries')->whereIn('staff_id', $ids)->where('performance_period', $period)->where('midterm_draft_status', '!=', 1)->pluck('staff_id')->map(fn ($id) => (int) $id)->all();
        } else {
            $hasPpa = DB::table('ppa_entries')->whereIn('staff_id', $ids)->where('performance_period', $period)->where('draft_status', '!=', 1)->pluck('staff_id')->map(fn ($id) => (int) $id)->all();
            $staff = $staff->filter(fn ($s) => in_array((int) $s->staff_id, $hasPpa, true));
            $submitted = DB::table('ppa_entries')->whereIn('staff_id', $ids)->where('performance_period', $period)->where('endterm_draft_status', '!=', 1)->pluck('staff_id')->map(fn ($id) => (int) $id)->all();
        }

        return array_values($staff->filter(fn ($s) => ! in_array((int) $s->staff_id, $submitted, true))->all());
    }

    /**
     * Current + previous calendar periods (covers in-cycle and close-out reminders).
     *
     * @return list<string>
     */
    public function reminderPeriods(): array
    {
        $y = (int) date('Y');

        return [
            "January-{$y}-to-December-{$y}",
            'January-'.($y - 1).'-to-December-'.($y - 1),
        ];
    }

    public function previousPeriodKey(): string
    {
        $y = (int) date('Y') - 1;

        return "January-{$y}-to-December-{$y}";
    }

    public function endtermPeriodKey(): string
    {
        // Before October: previous year; from October: current year (CI endterm_reminder_period).
        $y = (int) date('n') < 10 ? ((int) date('Y') - 1) : (int) date('Y');

        return "January-{$y}-to-December-{$y}";
    }

    protected function deadlineLabel(PerformancePhase $phase): string
    {
        $status = $this->ppaSettings->submissionWindowStatus($phase);
        if (! empty($status['closes_on'])) {
            return (string) $status['closes_on'];
        }

        return (string) ($status['label'] ?? date('Y-m-d'));
    }

    protected function daysToDeadline(PerformancePhase $phase): ?int
    {
        $status = $this->ppaSettings->submissionWindowStatus($phase);
        if (empty($status['closes_on'])) {
            return null;
        }
        $end = new DateTimeImmutable((string) $status['closes_on']);
        $today = new DateTimeImmutable('today');

        return (int) $today->diff($end)->format('%r%a');
    }

    protected function staffRow(int $staffId): ?object
    {
        return DB::table('staff')->where('staff_id', $staffId)->first();
    }

    protected function displayName(object $staff): string
    {
        return trim(($staff->title ?? '').' '.($staff->fname ?? '').' '.($staff->lname ?? ''));
    }

    public function staffHasAllowedContract(int $staffId, ?string $period = null): bool
    {
        $latestId = DB::table('staff_contracts')->where('staff_id', $staffId)->max('staff_contract_id');
        if (! $latestId) {
            return false;
        }
        $statusId = (int) DB::table('staff_contracts')->where('staff_contract_id', $latestId)->value('status_id');
        if (! in_array($statusId, $this->allowedStatuses, true)) {
            return false;
        }
        if ($period === null || $period === '') {
            return true;
        }
        if (! preg_match('/(\d{4}).*?(\d{4})/', $period, $m) && ! preg_match('/(\d{4})/', $period, $m)) {
            return true;
        }
        $y1 = (int) $m[1];
        $y2 = isset($m[2]) ? (int) $m[2] : $y1;
        $from = sprintf('%04d-01-01', $y1);
        $to = sprintf('%04d-12-31', $y2);

        return DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->whereNotIn('contract_type_id', $this->excludedContractTypes)
            ->where('start_date', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $from)
                    ->orWhere('end_date', '<', '1900-01-01');
            })
            ->exists();
    }
}
