<?php

namespace Modules\Jobs\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\PpaSettingsService;

class PerformanceReminderService
{
    /** @var list<int> */
    private array $allowedStatuses = [1, 2, 3, 7];

    /** @var list<int> */
    private array $excludedContractTypes = [1, 3, 5, 7];

    public function __construct(
        private EmailNotificationService $mail,
        private PpaSettingsService $ppaSettings,
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
        $period = $this->previousPeriodKey();
        $deadline = $this->deadlineLabel(PerformancePhase::Ppa);
        $queued = 0;

        $supervisors = DB::select("
            SELECT DISTINCT s.staff_id AS supervisor_id, s.title, s.fname, s.lname, s.work_email
            FROM staff s
            JOIN ppa_entries p ON s.staff_id = p.supervisor_id OR s.staff_id = p.supervisor2_id
            WHERE p.performance_period = ?
              AND p.draft_status = 0
              AND p.entry_id NOT IN (SELECT entry_id FROM ppa_approval_trail WHERE action = 'Approved')
            ORDER BY s.fname ASC
        ", [$period]);

        foreach ($supervisors as $supervisor) {
            if (! $this->staffHasAllowedContract((int) $supervisor->supervisor_id)) {
                continue;
            }
            $pending = $this->pendingPpasForSupervisor((int) $supervisor->supervisor_id);
            $pending = $this->filterPendingByContract($pending, $period);
            if ($pending === []) {
                continue;
            }
            $name = trim(($supervisor->title ?? '').' '.($supervisor->fname ?? '').' '.($supervisor->lname ?? ''));
            $body = $this->mail->render('supervisor_reminder', [
                'supervisor_name' => $name,
                'period' => $period,
                'deadline' => $deadline,
                'pending_list' => $pending,
            ]);
            $to = $this->mail->appendSystemInbox((string) $supervisor->work_email);
            $entryId = md5($supervisor->supervisor_id.'-SUPPPAREM-'.date('Y-m-d'));
            if ($this->mail->queue('Staff Portal System', $to, $body, "Reminder: Pending PPA Approvals for {$period}", (int) $supervisor->supervisor_id, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        $queued += $this->notifyUnsubmittedPpas($period, $deadline);
        $this->mail->purgeTestRecipients();

        return $queued;
    }

    public function notifySupervisorsPendingMidterms(): int
    {
        $period = $this->previousPeriodKey();
        $deadline = $this->deadlineLabel(PerformancePhase::Midterm);
        $queued = 0;

        $supervisors = DB::select("
            SELECT DISTINCT s.staff_id AS supervisor_id, s.title, s.fname, s.lname, s.work_email
            FROM staff s
            JOIN ppa_entries p ON s.staff_id = p.midterm_supervisor_1 OR s.staff_id = p.midterm_supervisor_2
            WHERE p.performance_period = ?
              AND p.midterm_draft_status = 0
              AND p.midterm_sign_off = 1
              AND p.entry_id NOT IN (SELECT entry_id FROM ppa_approval_trail_midterm WHERE action = 'Approved')
            ORDER BY s.fname ASC
        ", [$period]);

        foreach ($supervisors as $supervisor) {
            if (! $this->staffHasAllowedContract((int) $supervisor->supervisor_id)) {
                continue;
            }
            $pending = $this->pendingMidtermsForSupervisor((int) $supervisor->supervisor_id);
            $pending = $this->filterPendingByContract($pending, $period);
            if ($pending === []) {
                continue;
            }
            $name = trim(($supervisor->title ?? '').' '.($supervisor->fname ?? '').' '.($supervisor->lname ?? ''));
            $entryId = md5($supervisor->supervisor_id.'-SUPMIDREM-'.$period.'-'.date('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                continue;
            }
            $body = $this->mail->render('supervisor_reminder_midterm', [
                'supervisor_name' => $name,
                'period' => $period,
                'deadline' => $deadline,
                'pending_list' => $pending,
            ]);
            $to = $this->mail->appendSystemInbox((string) $supervisor->work_email);
            if ($this->mail->queue('Staff Portal System', $to, $body, "Reminder: Pending Midterm Approvals for {$period}", (int) $supervisor->supervisor_id, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        $queued += $this->notifyUnsubmittedMidterms($period, $deadline);
        $this->mail->purgeTestRecipients();

        return $queued;
    }

    public function notifySupervisorsPendingEndterms(): int
    {
        $period = $this->endtermPeriodKey();
        $deadline = $this->deadlineLabel(PerformancePhase::Endterm);
        $queued = 0;
        $queued += $this->notifyFirstSupervisorsPendingEndterms($period, $deadline);
        $queued += $this->notifyStaffConsentPendingEndterms($period, $deadline);
        $queued += $this->notifySecondSupervisorsPendingEndterms($period, $deadline);
        $queued += $this->notifyUnsubmittedEndterms($period, $deadline);

        return $queued;
    }

    public function notifySupervisorsPendingPerformanceApproval(): int
    {
        $queued = 0;
        $supervisors = DB::select("
            SELECT DISTINCT s.staff_id AS supervisor_id, s.title, s.fname, s.lname, s.work_email
            FROM staff s
            JOIN ppa_entries p ON s.staff_id IN (
                p.supervisor_id, p.supervisor2_id,
                p.midterm_supervisor_1, p.midterm_supervisor_2,
                p.endterm_supervisor_1, p.endterm_supervisor_2
            )
            WHERE TRIM(COALESCE(s.work_email, '')) != ''
        ");

        foreach ($supervisors as $supervisor) {
            $supervisorId = (int) $supervisor->supervisor_id;
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
            $typeCounts = ['ppa' => 0, 'midterm' => 0, 'endterm' => 0];
            foreach ($pending as $row) {
                $key = strtolower((string) ($row['approval_type'] ?? 'ppa'));
                if (isset($typeCounts[$key])) {
                    $typeCounts[$key]++;
                }
            }
            $name = trim(($supervisor->title ?? '').' '.($supervisor->fname ?? '').' '.($supervisor->lname ?? ''));
            $portal = (string) config('jobs.schedule.portal_base_url');
            $body = $this->mail->render('supervisor_reminder_performance_approval', [
                'supervisor_name' => $name,
                'generated_on' => date('d M Y H:i'),
                'type_counts' => $typeCounts,
                'pending_list' => $pending,
                'pending_url' => $portal.'performance',
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
            $name = trim(($staff->title ?? '').' '.($staff->fname ?? '').' '.($staff->lname ?? ''));
            $body = $this->mail->render('staff_reminder', [
                'name' => $name,
                'period' => $period,
                'deadline' => $deadline,
            ]);
            $to = $this->mail->appendSystemInbox((string) $staff->work_email);
            $entryId = md5($staff->staff_id.'-PPAREM-'.date('Y-m-d'));
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
            $entryId = md5($staff->staff_id.'-empMIDTERMREM-'.date('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                continue;
            }
            $name = trim(($staff->title ?? '').' '.($staff->fname ?? '').' '.($staff->lname ?? ''));
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
            $name = trim(($staff->title ?? '').' '.($staff->fname ?? '').' '.($staff->lname ?? ''));
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

    protected function notifyFirstSupervisorsPendingEndterms(string $period, string $deadline): int
    {
        $rows = DB::select("
            SELECT p.endterm_supervisor_1 AS supervisor_id, s.title, s.fname, s.lname, s.work_email,
                   p.entry_id, p.staff_id,
                   CONCAT(st.title, ' ', st.fname, ' ', st.lname) AS staff_name
            FROM ppa_entries p
            JOIN staff s ON s.staff_id = p.endterm_supervisor_1
            JOIN staff st ON st.staff_id = p.staff_id
            WHERE p.performance_period = ?
              AND p.endterm_draft_status = 0
              AND p.endterm_sign_off = 1
              AND p.endterm_supervisor_1 IS NOT NULL
              AND NOT EXISTS (
                SELECT 1 FROM ppa_approval_trail_end_term t
                WHERE t.entry_id = p.entry_id AND t.staff_id = p.endterm_supervisor_1 AND t.action = 'Approved'
              )
        ", [$period]);

        return $this->queueEndtermSupervisorBundles($rows, $period, $deadline, 'first', 'supervisor_reminder_endterm_first', 'SUP1ENDREM');
    }

    protected function notifySecondSupervisorsPendingEndterms(string $period, string $deadline): int
    {
        $rows = DB::select("
            SELECT p.endterm_supervisor_2 AS supervisor_id, s.title, s.fname, s.lname, s.work_email,
                   p.entry_id, p.staff_id,
                   CONCAT(st.title, ' ', st.fname, ' ', st.lname) AS staff_name
            FROM ppa_entries p
            JOIN staff s ON s.staff_id = p.endterm_supervisor_2
            JOIN staff st ON st.staff_id = p.staff_id
            WHERE p.performance_period = ?
              AND p.endterm_draft_status = 0
              AND p.endterm_sign_off = 1
              AND p.endterm_staff_consent_at IS NOT NULL
              AND p.endterm_supervisor_2 IS NOT NULL
              AND EXISTS (
                SELECT 1 FROM ppa_approval_trail_end_term t
                WHERE t.entry_id = p.entry_id AND t.staff_id = p.endterm_supervisor_1 AND t.action = 'Approved'
              )
              AND NOT EXISTS (
                SELECT 1 FROM ppa_approval_trail_end_term t2
                WHERE t2.entry_id = p.entry_id AND t2.staff_id = p.endterm_supervisor_2 AND t2.action = 'Approved'
              )
        ", [$period]);

        return $this->queueEndtermSupervisorBundles($rows, $period, $deadline, 'second', 'supervisor_reminder_endterm_second', 'SUP2ENDREM');
    }

    protected function notifyStaffConsentPendingEndterms(string $period, string $deadline): int
    {
        if (! $this->ppaSettings->endtermRequiresEmployeeConsent()) {
            return 0;
        }
        $rows = DB::select("
            SELECT p.entry_id, p.staff_id, s.title, s.fname, s.lname, s.work_email
            FROM ppa_entries p
            JOIN staff s ON s.staff_id = p.staff_id
            WHERE p.performance_period = ?
              AND p.endterm_draft_status = 0
              AND p.endterm_sign_off = 1
              AND p.endterm_staff_consent_at IS NULL
              AND EXISTS (
                SELECT 1 FROM ppa_approval_trail_end_term t
                WHERE t.entry_id = p.entry_id AND t.staff_id = p.endterm_supervisor_1 AND t.action = 'Approved'
              )
        ", [$period]);

        $queued = 0;
        foreach ($rows as $row) {
            if (! $this->staffHasAllowedContract((int) $row->staff_id, $period)) {
                continue;
            }
            $entryId = md5($row->staff_id.'-STAFFCONSENTENDREM-'.$row->entry_id.'-'.date('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                continue;
            }
            $name = trim(($row->title ?? '').' '.($row->fname ?? '').' '.($row->lname ?? ''));
            $body = $this->mail->render('staff_consent_reminder_endterm', [
                'name' => $name,
                'period' => $period,
                'deadline' => $deadline,
                'entry_id' => $row->entry_id,
                'staff_id' => $row->staff_id,
            ]);
            $to = $this->mail->appendSystemInbox((string) $row->work_email);
            if ($this->mail->queue('Staff Portal System', $to, $body, "Endterm Consent Reminder ($period)", (int) $row->staff_id, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        return $queued;
    }

    /**
     * @param  list<object>  $rows
     */
    protected function queueEndtermSupervisorBundles(array $rows, string $period, string $deadline, string $which, string $view, string $keyPrefix): int
    {
        $bySupervisor = [];
        foreach ($rows as $row) {
            if (! $this->staffHasAllowedContract((int) $row->staff_id, $period)) {
                continue;
            }
            if (! $this->staffHasAllowedContract((int) $row->supervisor_id)) {
                continue;
            }
            $bySupervisor[(int) $row->supervisor_id][] = $row;
        }

        $queued = 0;
        foreach ($bySupervisor as $supervisorId => $list) {
            $entryId = md5($supervisorId.'-'.$keyPrefix.'-'.$period.'-'.date('Y-m-d'));
            if ($this->mail->entryExists($entryId)) {
                continue;
            }
            $first = $list[0];
            $name = trim(($first->title ?? '').' '.($first->fname ?? '').' '.($first->lname ?? ''));
            $pending = array_map(fn ($r) => [
                'entry_id' => $r->entry_id,
                'staff_id' => $r->staff_id,
                'staff_name' => $r->staff_name,
            ], $list);
            $body = $this->mail->render($view, [
                'supervisor_name' => $name,
                'period' => $period,
                'deadline' => $deadline,
                'pending_list' => $pending,
            ]);
            $to = $this->mail->appendSystemInbox((string) $first->work_email);
            $subject = $which === 'second'
                ? "Reminder: Pending Endterm Second Approvals for {$period}"
                : "Reminder: Pending Endterm Approvals for {$period}";
            if ($this->mail->queue('Staff Portal System', $to, $body, $subject, $supervisorId, date('Y-m-d'), date('Y-m-d'), $entryId)) {
                $queued++;
            }
        }

        return $queued;
    }

    /** @return list<object> */
    protected function pendingPpasForSupervisor(int $supervisorId): array
    {
        return DB::select("
            SELECT p.entry_id, p.staff_id,
                   CONCAT(s.title, ' ', s.fname, ' ', s.lname) AS staff_name,
                   p.performance_period, p.created_at
            FROM ppa_entries p
            LEFT JOIN staff s ON s.staff_id = p.staff_id
            WHERE (p.supervisor_id = ? OR p.supervisor2_id = ?)
              AND p.draft_status = 0
              AND p.entry_id NOT IN (SELECT entry_id FROM ppa_approval_trail WHERE action = 'Approved')
            ORDER BY p.created_at DESC
        ", [$supervisorId, $supervisorId]);
    }

    /** @return list<object> */
    protected function pendingMidtermsForSupervisor(int $supervisorId): array
    {
        return DB::select("
            SELECT p.entry_id, p.staff_id,
                   CONCAT(s.title, ' ', s.fname, ' ', s.lname) AS staff_name,
                   p.performance_period, p.midterm_updated_at AS created_at
            FROM ppa_entries p
            LEFT JOIN staff s ON s.staff_id = p.staff_id
            WHERE (p.midterm_supervisor_1 = ? OR p.midterm_supervisor_2 = ?)
              AND p.midterm_draft_status = 0
              AND p.midterm_sign_off = 1
              AND p.entry_id NOT IN (SELECT entry_id FROM ppa_approval_trail_midterm WHERE action = 'Approved')
            ORDER BY p.midterm_updated_at DESC
        ", [$supervisorId, $supervisorId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function allPendingApprovalsForSupervisor(int $supervisorId): array
    {
        $portal = (string) config('jobs.schedule.portal_base_url');
        $out = [];

        foreach ($this->pendingPpasForSupervisor($supervisorId) as $row) {
            if (! $this->staffHasAllowedContract((int) $row->staff_id, (string) $row->performance_period)) {
                continue;
            }
            $out[] = [
                'staff_name' => $row->staff_name,
                'approval_type' => 'ppa',
                'period' => $row->performance_period,
                'status' => 'Pending First Supervisor',
                'submitted_at' => $row->created_at,
                'review_url' => $portal.'performance/form/ppa/'.$row->entry_id.'/'.$row->staff_id,
            ];
        }
        foreach ($this->pendingMidtermsForSupervisor($supervisorId) as $row) {
            if (! $this->staffHasAllowedContract((int) $row->staff_id, (string) $row->performance_period)) {
                continue;
            }
            $out[] = [
                'staff_name' => $row->staff_name,
                'approval_type' => 'midterm',
                'period' => $row->performance_period,
                'status' => 'Pending First Supervisor',
                'submitted_at' => $row->created_at,
                'review_url' => $portal.'performance/form/midterm/'.$row->entry_id.'/'.$row->staff_id,
            ];
        }

        return $out;
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
            ->whereIn('sc.status_id', [1, 2])
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
     * @param  list<object>  $pending
     * @return list<object>
     */
    protected function filterPendingByContract(array $pending, string $period): array
    {
        return array_values(array_filter($pending, fn ($row) => $this->staffHasAllowedContract((int) $row->staff_id, $period)));
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
