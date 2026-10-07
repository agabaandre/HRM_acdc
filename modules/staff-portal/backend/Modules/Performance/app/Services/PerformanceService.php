<?php

namespace Modules\Performance\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PortalTable;
use Modules\Performance\Enums\PerformancePhase;

class PerformanceService
{
    public function __construct(
        protected PerformanceWorkflowService $workflow,
        protected SupervisorResolver $supervisors,
    ) {}

    public function currentPeriodSlug(): string
    {
        return str_replace(' ', '-', $this->currentPeriodLabel());
    }

    public function currentPeriodLabel(): string
    {
        $year = (int) date('Y');

        return "January {$year} to December {$year}";
    }

    /**
     * @return list<string>
     */
    public function periodOptions(): array
    {
        return DB::table('ppa_entries')
            ->distinct()
            ->orderByDesc('performance_period')
            ->pluck('performance_period')
            ->all();
    }

    public function draftStatusLabel(int $status): string
    {
        return match ($status) {
            1 => 'Draft',
            2 => 'Approved',
            default => 'Submitted',
        };
    }

    public function midtermStatusLabel(?int $status): string
    {
        if ($status === null) {
            return '—';
        }

        return match ($status) {
            1 => 'Draft',
            2 => 'Approved',
            default => 'Submitted',
        };
    }

    public function endtermStatusLabel(?int $status): string
    {
        return $this->midtermStatusLabel($status);
    }

    /**
     * @return array{total: int, approved: int, submitted: int, draft: int, without_ppa: int}
     */
    public function dashboardSummary(?int $divisionId, ?string $period, ?int $restrictStaffId = null): array
    {
        $period = $period ?: $this->currentPeriodSlug();

        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        $entries = DB::table('ppa_entries as pe')
            ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId))
            ->where('pe.performance_period', $period);

        $total = (clone $entries)->where('pe.draft_status', '!=', 1)->count();
        $approved = (clone $entries)->where('pe.draft_status', 2)->count();
        $submitted = (clone $entries)->where('pe.draft_status', 0)->count();
        $draft = (clone $entries)->where('pe.draft_status', 1)->count();

        $activeStaff = DB::table('staff as s')
            ->joinSub($latestContractSub, 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->whereIn('sc.status_id', [1, 2, 7])
            ->when($divisionId, fn ($q) => $q->where('sc.division_id', $divisionId))
            ->when($restrictStaffId, fn ($q) => $q->where('s.staff_id', $restrictStaffId))
            ->distinct()
            ->count('s.staff_id');

        $withPpa = DB::table('ppa_entries as pe')
            ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId))
            ->where('pe.performance_period', $period)
            ->where('pe.draft_status', '!=', 1)
            ->distinct()
            ->count('pe.staff_id');

        return [
            'total' => $total,
            'approved' => $approved,
            'submitted' => $submitted,
            'draft' => $draft,
            'without_ppa' => max(0, $activeStaff - $withPpa),
        ];
    }

    public function paginateMyPpas(
        int $staffId,
        ?string $period,
        int $perPage,
        ?int $page
    ): LengthAwarePaginator {
        $q = DB::table('ppa_entries as p')
            ->where('p.staff_id', $staffId)
            ->orderByDesc('p.performance_period');

        if ($period !== null && $period !== '') {
            $q->where('p.performance_period', $period);
        }

        return PortalTable::paginateDistinct($q, 'p.entry_id', $perPage, $page);
    }

    /**
     * One row per phase (PPA / midterm / endterm) for the owner's history list.
     *
     * @return list<array<string, mixed>>
     */
    public function phaseRowsForMyEntry(object $entry): array
    {
        $entryId = (string) ($entry->entry_id ?? '');
        $staffId = (int) ($entry->staff_id ?? 0);
        $period = (string) ($entry->performance_period ?? '');
        $phases = [PerformancePhase::Ppa];

        if (! empty($entry->midterm_created_at)) {
            $phases[] = PerformancePhase::Midterm;
        }
        if (! empty($entry->endterm_created_at)) {
            $phases[] = PerformancePhase::Endterm;
        }

        $rows = [];
        foreach ($phases as $phase) {
            $state = $this->workflow->resolveState($entry, $phase);
            $statusKey = (string) ($state['status_key'] ?? '');
            $pendingWith = null;
            if (in_array($statusKey, ['pending_supervisor_1', 'pending_supervisor_2'], true)) {
                $actorId = (int) ($state['actor_staff_id'] ?? 0);
                if ($actorId > 0) {
                    $pendingWith = $this->supervisors->staffName($actorId);
                }
            }

            $draftCol = $phase->draftStatusColumn();
            $draft = isset($entry->{$draftCol}) ? (int) $entry->{$draftCol} : null;
            $statusLabel = match ($phase) {
                PerformancePhase::Ppa => $this->draftStatusLabel((int) ($draft ?? 1)),
                PerformancePhase::Midterm => $this->midtermStatusLabel($draft),
                PerformancePhase::Endterm => $this->endtermStatusLabel($draft),
            };
            if (str_starts_with($statusKey, 'pending_')) {
                $statusLabel = (string) ($state['label'] ?? $statusLabel);
            }

            $updatedAt = match ($phase) {
                PerformancePhase::Ppa => $entry->updated_at ?? $entry->created_at ?? null,
                PerformancePhase::Midterm => $entry->midterm_updated_at ?? $entry->midterm_created_at ?? null,
                PerformancePhase::Endterm => $entry->endterm_updated_at ?? $entry->endterm_created_at ?? null,
            };

            $rows[] = [
                'entry_id' => $entryId,
                'staff_id' => $staffId,
                'performance_period' => $period,
                'phase' => $phase->value,
                'phase_label' => $phase->label(),
                'status' => $statusLabel,
                'status_key' => $statusKey,
                'pending_with' => $pendingWith,
                'updated_at' => $updatedAt !== null ? (string) $updatedAt : null,
                'form_url' => $entryId !== '' && $staffId > 0
                    ? '/performance/form/'.$phase->value.'/'.$entryId.'/'.$staffId
                    : '',
                'print_url' => $entryId !== ''
                    ? url('/api/v1/performance/entries/'.$entryId.'/print?phase='.$phase->value)
                    : '',
            ];
        }

        return $rows;
    }

    /**
     * Pending PPA approvals for a supervisor (CI3 get_pending_ppa).
     *
     * @return Collection<int, object>
     */
    public function pendingApprovals(int $supervisorStaffId): Collection
    {
        $sid = (int) $supervisorStaffId;
        $items = collect();
        $latestContract = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        // Include entry supervisors and latest-contract supervisors (many older
        // submitted PPAs have null supervisor_id columns).
        $rows = DB::table('ppa_entries as p')
            ->join('staff as s', 's.staff_id', '=', 'p.staff_id')
            ->leftJoinSub($latestContract, 'lc', 'lc.staff_id', '=', 'p.staff_id')
            ->leftJoin('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->where('p.draft_status', 0)
            ->where(function ($q) use ($sid) {
                $q->where('p.supervisor_id', $sid)
                    ->orWhere('p.supervisor2_id', $sid)
                    ->orWhere('sc.first_supervisor', $sid)
                    ->orWhere('sc.second_supervisor', $sid);
            })
            ->select('p.*', DB::raw("CONCAT(s.fname, ' ', s.lname) AS staff_name"))
            ->orderByDesc('p.created_at')
            ->limit(200)
            ->get();

        foreach ($rows as $entry) {
            if (empty($entry->supervisor_id) && empty($entry->supervisor2_id)) {
                $this->workflow->syncSupervisorsFromContract($entry, PerformancePhase::Ppa);
                $fresh = DB::table('ppa_entries')->where('entry_id', $entry->entry_id)->first();
                if ($fresh) {
                    foreach ((array) $fresh as $key => $value) {
                        $entry->{$key} = $value;
                    }
                }
            }

            $state = $this->workflow->resolveState($entry, PerformancePhase::Ppa);
            if ($state['can_act'] && (int) ($state['actor_staff_id'] ?? 0) === $sid) {
                $entry->approval_type = 'ppa';
                $entry->overall_status = $state['label'];
                $items->push($entry);
            }
        }

        return $items;
    }

    public function pendingCount(int $supervisorStaffId): int
    {
        return $this->pendingApprovals($supervisorStaffId)->count();
    }

    public function reviewRoute(PerformancePhase $phase, string $entryId, int $staffId): string
    {
        return match ($phase) {
            PerformancePhase::Ppa => route('performance.ppa.form', ['entryId' => $entryId, 'staffId' => $staffId]),
            PerformancePhase::Midterm => route('performance.midterm.form', ['entryId' => $entryId, 'staffId' => $staffId]),
            PerformancePhase::Endterm => route('performance.endterm.form', ['entryId' => $entryId, 'staffId' => $staffId]),
        };
    }

    public function viewPpaUrl(string $entryId, int $staffId): string
    {
        return route('performance.ppa.form', ['entryId' => $entryId, 'staffId' => $staffId]);
    }

    public function createPpaUrl(?string $period = null): string
    {
        return $period
            ? route('performance.ppa.create', ['period' => $period])
            : route('performance.ppa.create');
    }

}
