<?php

namespace Modules\Settings\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeavePolicyService;

#[Layout('core::layouts.app')]
class LeaveSettings extends Component
{
    public string $tab = 'policy';

    /** @var array<string, mixed> */
    public array $policy = [];

    public ?int $editingTypeId = null;

    public string $leave_name = '';

    public string $code = '';

    public float $leave_days = 0;

    public bool $is_accrued = false;

    public float $accrual_rate = 0;

    public bool $requires_hr_approval = false;

    public bool $requires_medical_certificate = false;

    public ?int $medical_report_after_days = null;

    public ?int $max_instances = null;

    public ?float $max_days_per_year = null;

    public ?float $min_days_per_year = null;

    public bool $deduct_compensatory_first = false;

    public string $policy_notes = '';

    public function mount(LeavePolicyService $policyService): void
    {
        $this->policy = $policyService->all();
    }

    public function savePolicy(LeavePolicyService $policyService): void
    {
        $policyService->save($this->policy);
        session()->flash('success', 'Leave policy and accumulation rules saved.');
    }

    public function editType(int $leaveId): void
    {
        $type = LeaveType::query()->findOrFail($leaveId);
        $this->editingTypeId = $leaveId;
        $this->leave_name = (string) $type->leave_name;
        $this->code = (string) ($type->code ?? '');
        $this->leave_days = (float) $type->leave_days;
        $this->is_accrued = (bool) $type->is_accrued;
        $this->accrual_rate = (float) $type->accrual_rate;
        $this->requires_hr_approval = (bool) $type->requires_hr_approval;
        $this->requires_medical_certificate = (bool) $type->requires_medical_certificate;
        $this->medical_report_after_days = $type->medical_report_after_days;
        $this->max_instances = $type->max_instances;
        $this->max_days_per_year = $type->max_days_per_year !== null ? (float) $type->max_days_per_year : null;
        $this->min_days_per_year = $type->min_days_per_year !== null ? (float) $type->min_days_per_year : null;
        $this->deduct_compensatory_first = (bool) $type->deduct_compensatory_first;
        $this->policy_notes = (string) ($type->policy_notes ?? '');
    }

    public function resetTypeForm(): void
    {
        $this->editingTypeId = null;
        $this->leave_name = '';
        $this->code = '';
        $this->leave_days = 0;
        $this->is_accrued = false;
        $this->accrual_rate = 2.33;
        $this->requires_hr_approval = false;
        $this->requires_medical_certificate = false;
        $this->medical_report_after_days = null;
        $this->max_instances = null;
        $this->max_days_per_year = null;
        $this->min_days_per_year = null;
        $this->deduct_compensatory_first = false;
        $this->policy_notes = '';
    }

    public function saveType(): void
    {
        $this->validate([
            'leave_name' => 'required|string|max:100',
            'code' => 'nullable|string|max:40',
            'leave_days' => 'numeric|min:0',
            'accrual_rate' => 'numeric|min:0',
        ]);

        $data = [
            'leave_name' => $this->leave_name,
            'code' => $this->code ?: null,
            'leave_days' => (int) $this->leave_days,
            'is_accrued' => $this->is_accrued ? 1 : 0,
            'accrual_rate' => $this->accrual_rate,
            'is_active' => true,
            'requires_hr_approval' => $this->requires_hr_approval,
            'requires_medical_certificate' => $this->requires_medical_certificate,
            'medical_report_after_days' => $this->medical_report_after_days,
            'max_instances' => $this->max_instances,
            'max_days_per_year' => $this->max_days_per_year,
            'min_days_per_year' => $this->min_days_per_year,
            'deduct_compensatory_first' => $this->deduct_compensatory_first,
            'policy_notes' => $this->policy_notes ?: null,
        ];

        if ($this->editingTypeId) {
            LeaveType::query()->where('leave_id', $this->editingTypeId)->update($data);
            session()->flash('success', 'Leave type updated.');
        } else {
            LeaveType::query()->create($data);
            session()->flash('success', 'Leave type created.');
        }

        $this->resetTypeForm();
    }

    public function render()
    {
        return view('settings::livewire.leave-settings', [
            'leaveTypes' => LeaveType::query()->orderBy('sort_order')->orderBy('leave_name')->get(),
        ]);
    }
}
