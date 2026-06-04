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
     * Pending PPA approvals for a supervisor (CI3 get_pending_ppa).
     *
     * @return Collection<int, object>
     */
    public function pendingApprovals(int $supervisorStaffId): Collection
    {
        $sid = (int) $supervisorStaffId;
        $items = collect();

        $rows = DB::table('ppa_entries as p')
            ->join('staff as s', 's.staff_id', '=', 'p.staff_id')
            ->where('p.draft_status', 0)
            ->select('p.*', DB::raw("CONCAT(s.fname, ' ', s.lname) AS staff_name"))
            ->orderByDesc('p.created_at')
            ->limit(500)
            ->get();

        foreach ($rows as $entry) {
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
