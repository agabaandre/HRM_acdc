<?php

namespace Modules\Performance\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Core\Livewire\Concerns\InteractsWithPortalTable;
use Modules\Performance\Services\PerformanceApprovalService;
use Modules\Performance\Services\PerformanceService;
use Modules\Performance\Services\PpaSettingsService;

#[Layout('core::layouts.app')]
class PerformanceHub extends Component
{
    use ChecksPortalPermission;
    use InteractsWithPortalTable;

    /** dashboard | my | pending */
    #[Url]
    public string $tab = 'dashboard';

    #[Url]
    public ?string $period = null;

    #[Url]
    public ?int $division = null;

    public function mount(): void
    {
        $this->authorizePortal(74);

        if ($this->period === null || $this->period === '') {
            $this->period = app(PerformanceService::class)->currentPeriodSlug();
        }

        if (request()->routeIs('performance.my-ppas')) {
            $this->tab = 'my';
        } elseif (request()->routeIs('performance.pending')) {
            $this->tab = 'pending';
        } elseif (request()->routeIs('performance.ppa-dashboard', 'performance.index')) {
            $this->tab = 'dashboard';
        }

        $allowed = ['dashboard', 'my', 'pending'];
        if (! in_array($this->tab, $allowed, true)) {
            $this->tab = 'dashboard';
        }
    }

    public function updatedTab(): void
    {
        $this->resetTablePage();
    }

    public function updatedPeriod(): void
    {
        $this->resetTablePage();
    }

    public function render(PerformanceService $performance, PerformanceApprovalService $approval, PpaSettingsService $ppaSettings)
    {
        $staffId = (int) (session('user.staff_id') ?? 0);
        $roleId = (int) (session('user.role_id') ?? session('user.role') ?? 0);
        $restrictStaff = $roleId === 17 ? $staffId : null;

        $pending = $staffId > 0 ? $approval->pendingActionsFor($staffId) : collect();
        $pendingCount = $pending->count();

        $divisions = \Illuminate\Support\Facades\DB::table('divisions')
            ->orderBy('division_name')
            ->get(['division_id', 'division_name']);

        $summary = $performance->dashboardSummary($this->division, $this->period, $restrictStaff);

        $myPaginator = null;
        $myRange = ['from' => 0, 'to' => 0, 'total' => 0];
        if ($this->tab === 'my' && $staffId > 0) {
            $myPaginator = $performance->paginateMyPpas($staffId, $this->period, $this->perPage, $this->getPage());
            $myRange = $this->tableRange($myPaginator);
        }

        return view('performance::livewire.performance-hub', [
            'summary' => $summary,
            'divisions' => $divisions,
            'periods' => $performance->periodOptions(),
            'pending' => $pending,
            'pendingCount' => $pendingCount,
            'myPpas' => $myPaginator,
            'myFrom' => $myRange['from'],
            'myTo' => $myRange['to'],
            'myTotal' => $myRange['total'],
            'performance' => $performance,
            'workflowSummary' => [
                'ppa' => $ppaSettings->workflowSummaryLine(\Modules\Performance\Enums\PerformancePhase::Ppa),
                'midterm' => $ppaSettings->workflowSummaryLine(\Modules\Performance\Enums\PerformancePhase::Midterm),
                'endterm' => $ppaSettings->workflowSummaryLine(\Modules\Performance\Enums\PerformancePhase::Endterm),
            ],
            'submissionWindows' => $ppaSettings->allSubmissionWindowStatuses(),
            'ppaSubmissionOpen' => $ppaSettings->isSubmissionOpen(\Modules\Performance\Enums\PerformancePhase::Ppa),
        ]);
    }
}
