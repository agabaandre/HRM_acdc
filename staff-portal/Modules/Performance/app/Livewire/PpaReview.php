<?php

namespace Modules\Performance\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\ChecksPortalPermission;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\PerformanceApprovalService;
use Modules\Performance\Services\PerformanceService;
use Modules\Performance\Services\PerformanceWorkflowService;
use Modules\Performance\Services\SupervisorResolver;
use Modules\Performance\Support\PerformancePeriod;

#[Layout('core::layouts.app')]
class PpaReview extends Component
{
    use ChecksPortalPermission;

    public string $entryId;

    public string $phaseKey = 'ppa';

    public int $staffId;

    public string $action = '';

    public string $comments = '';

    public bool $acceptRating = true;

    public bool $supervisor2Agreement = true;

    public function mount(string $entryId, int $staffId, string $phase = 'ppa'): void
    {
        $this->authorizePortal(74);
        $this->entryId = $entryId;
        $this->staffId = $staffId;
        $this->phaseKey = in_array($phase, ['ppa', 'midterm', 'endterm'], true) ? $phase : 'ppa';
    }

    public function submitApprove(PerformanceApprovalService $approval): void
    {
        $this->validate(['comments' => 'nullable|string|max:5000']);
        try {
            $approval->approve(
                $this->entryId,
                $this->phase(),
                (int) session('user.staff_id'),
                $this->comments,
                $this->supervisor2Agreement,
            );
            session()->flash('success', 'Approval recorded.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitReturn(PerformanceApprovalService $approval): void
    {
        $this->validate(['comments' => 'required|string|max:5000']);
        try {
            $approval->returnForRevision(
                $this->entryId,
                $this->phase(),
                (int) session('user.staff_id'),
                $this->comments,
            );
            session()->flash('success', 'Returned for revision.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitConsent(PerformanceApprovalService $approval): void
    {
        $this->validate(['comments' => 'nullable|string|max:5000']);
        try {
            $approval->recordEmployeeConsent(
                $this->entryId,
                (int) session('user.staff_id'),
                $this->comments,
                $this->acceptRating,
            );
            session()->flash('success', 'Consent recorded.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(
        PerformanceApprovalService $approval,
        PerformanceWorkflowService $workflow,
        PerformanceService $performance,
        SupervisorResolver $supervisors,
    ) {
        $entry = $approval->findEntry($this->entryId);
        if (! $entry || (int) $entry->staff_id !== $this->staffId) {
            abort(404);
        }

        $phase = $this->phase();
        $workflow->syncSupervisorsFromContract($entry, $phase);
        $entry = $approval->findEntry($this->entryId);

        $actorStaffId = (int) (session('user.staff_id') ?? 0);
        $state = $workflow->resolveState($entry, $phase);
        $canAct = $workflow->canActorApprove($entry, $phase, $actorStaffId);
        $sup = $workflow->supervisorIdsForPhase($entry, $phase);
        $contractSup = $supervisors->fromLatestContract($this->staffId);

        return view('performance::livewire.ppa-review', [
            'entry' => $entry,
            'phase' => $phase,
            'state' => $state,
            'timeline' => $workflow->timeline($entry, $phase),
            'trail' => $approval->trail($this->entryId, $phase),
            'canAct' => $canAct,
            'isOwner' => $actorStaffId === (int) $entry->staff_id,
            'supervisors' => $sup,
            'contractSup' => $contractSup,
            'performance' => $performance,
            'periodLabel' => PerformancePeriod::toLabel($entry->performance_period),
        ]);
    }

    protected function phase(): PerformancePhase
    {
        return PerformancePhase::from($this->phaseKey);
    }
}
