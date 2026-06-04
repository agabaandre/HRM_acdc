<?php

namespace Modules\Leave\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveRequestService;
use Modules\Leave\Support\LeaveAccess;

#[Layout('core::layouts.app')]
class LeaveApplication extends Component
{
    use WithFileUploads;

    public int $leave_id = 0;

    public string $start_date = '';

    public string $end_date = '';

    public int $requested_days = 0;

    public string $email_leave = '';

    public string $mobile_leave = '';

    public string $supporting_staff = '';

    public string $remarks = '';

    public $document;

    public function mount(): void
    {
        $user = session('user', []);
        $this->email_leave = (string) ($user['email'] ?? '');
    }

    public function updated($property, LeaveRequestService $requests): void
    {
        if (in_array($property, ['start_date', 'end_date'], true)) {
            $this->recalculateDays($requests);
        }
    }

    public function recalculateDays(LeaveRequestService $requests): void
    {
        if ($this->start_date && $this->end_date) {
            $this->requested_days = $requests->workingDaysBetween($this->start_date, $this->end_date);
        }
    }

    public function submit(LeaveRequestService $requests): void
    {
        $staffId = LeaveAccess::staffId();
        if (! $staffId) {
            session()->flash('error', 'Staff profile not linked to your account.');

            return;
        }

        $this->validate([
            'leave_id' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'requested_days' => 'required|integer|min:1',
            'email_leave' => 'required|email',
            'mobile_leave' => 'required|string|max:200',
            'document' => 'nullable|file|max:2048|mimes:pdf,doc,docx,png,jpg,jpeg',
        ]);

        $type = LeaveType::query()->find($this->leave_id);
        if ($type?->requires_medical_certificate && ! $this->document) {
            $this->addError('document', 'A medical certificate is required for this leave type.');

            return;
        }

        try {
            $requests->submit([
                'staff_id' => $staffId,
                'leave_id' => $this->leave_id,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'requested_days' => $this->requested_days,
                'email_leave' => $this->email_leave,
                'mobile_leave' => $this->mobile_leave,
                'supporting_staff' => $this->supporting_staff,
                'remarks' => $this->remarks,
            ], $this->document);

            session()->flash('success', 'Leave request submitted for approval.');
            $this->redirect(route('leave.index', ['view' => 'requests']), navigate: true);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(LeaveBalanceService $balances)
    {
        $staffId = LeaveAccess::staffId();
        $types = LeaveType::query()->where('is_active', true)->orderBy('sort_order')->get();
        $selectedBalance = ($staffId && $this->leave_id)
            ? $balances->snapshot($staffId, $this->leave_id)
            : null;

        return view('leave::livewire.leave-application', [
            'leaveTypes' => $types,
            'selectedBalance' => $selectedBalance,
        ]);
    }
}
