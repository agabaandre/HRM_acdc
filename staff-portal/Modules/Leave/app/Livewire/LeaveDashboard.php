<?php

namespace Modules\Leave\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveRequestService;
use Modules\Leave\Support\LeaveAccess;

#[Layout('core::layouts.app')]
class LeaveDashboard extends Component
{
    #[Url]
    public string $view = 'balances';

    public string $statusFilter = '';

    public string $startDate = '';

    public string $endDate = '';

    public function approve(int $requestId, string $role, string $action, LeaveRequestService $requests): void
    {
        $message = $action === 'approve' ? 'Approved' : 'Rejected';
        $requests->approve($requestId, $role, $message);
        session()->flash('success', 'Leave request '.$message.'.');
    }

    public function render(LeaveBalanceService $balances)
    {
        $staffId = LeaveAccess::staffId();
        $balanceRows = $staffId ? $balances->allTypesForStaff($staffId) : [];

        $requests = StaffLeave::query()
            ->with(['leaveType', 'staff'])
            ->when($this->view !== 'all' && $staffId && ! LeaveAccess::isHr(), fn ($q) => $q->where('staff_id', $staffId))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('overall_status', $this->statusFilter))
            ->when($this->startDate !== '', fn ($q) => $q->whereDate('start_date', '>=', $this->startDate))
            ->when($this->endDate !== '', fn ($q) => $q->whereDate('end_date', '<=', $this->endDate))
            ->orderByDesc('start_date')
            ->limit(100)
            ->get();

        $pendingApprovals = $staffId
            ? StaffLeave::query()
                ->with(['leaveType', 'staff'])
                ->where('overall_status', 'Pending')
                ->when(! LeaveAccess::isHr(), function ($q) use ($staffId): void {
                    $q->where(function ($q2) use ($staffId): void {
                        $q2->where('supervisor_id', $staffId)
                            ->orWhere('supervisor2_id', $staffId)
                            ->orWhere('division_head', $staffId)
                            ->orWhere('supporting_staff', (string) $staffId);
                    });
                })
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
            : collect();

        if ($this->view === 'all' && ! portal_can(77)) {
            abort(403);
        }

        return view('leave::livewire.leave-dashboard', [
            'balanceRows' => $balanceRows,
            'requests' => $requests,
            'pendingApprovals' => $pendingApprovals,
            'isHr' => LeaveAccess::isHr(),
            'showAllStaff' => $this->view === 'all',
        ]);
    }
}
