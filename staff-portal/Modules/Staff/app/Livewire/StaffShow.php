<?php

namespace Modules\Staff\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Support\LeaveAccess;
use Modules\Staff\Services\StaffContractService;
use Modules\Staff\Services\StaffProfileService;
use Modules\Staff\Support\StaffAccess;

#[Layout('core::layouts.app')]
class StaffShow extends Component
{
    use WithFileUploads;

    public int $staffId;

    public string $tab = 'profile';

    public int $balanceYear;

    /** @var array<int, array{opening_days: float, carried_forward_days: float, compensatory_days: float, notes: string}> */
    public array $openingRows = [];

    /** '', 'edit', or 'renew' */
    public string $contractMode = '';

    public ?int $editingContractId = null;

    /** @var array<string, mixed> */
    public array $contractForm = [];

    /** @var list<object> */
    public array $unitOptions = [];

    public $contractPdf;

    public function mount(int $staff): void
    {
        $this->staffId = $staff;
        if (! StaffAccess::canViewProfile($this->staffId)) {
            abort(403);
        }
        $this->balanceYear = (int) now()->year;
        $this->loadOpeningRows();
    }

    public function updatedBalanceYear(): void
    {
        $this->loadOpeningRows();
    }

    public function updated($property): void
    {
        if ($property === 'contractForm.division_id') {
            $this->loadUnitOptions();
        }
    }

    protected function loadOpeningRows(): void
    {
        $types = LeaveType::query()->where('is_active', true)->orderBy('sort_order')->get();
        $this->openingRows = [];

        foreach ($types as $type) {
            $record = \Modules\Leave\Models\StaffLeaveOpeningBalance::query()
                ->where('staff_id', $this->staffId)
                ->where('leave_id', $type->leave_id)
                ->where('calendar_year', $this->balanceYear)
                ->first();

            $this->openingRows[(int) $type->leave_id] = [
                'opening_days' => (float) ($record?->opening_days ?? 0),
                'carried_forward_days' => (float) ($record?->carried_forward_days ?? 0),
                'compensatory_days' => (float) ($record?->compensatory_days ?? 0),
                'notes' => (string) ($record?->notes ?? ''),
            ];
        }
    }

    public function saveOpeningBalances(LeaveBalanceService $balances): void
    {
        if (! LeaveAccess::isHr()) {
            session()->flash('error', 'Only HR can update opening leave balances.');

            return;
        }

        $balances->saveOpeningBalances($this->staffId, $this->balanceYear, $this->openingRows, auth()->id());
        session()->flash('success', 'Opening leave balances saved for '.$this->balanceYear.'.');
        $this->loadOpeningRows();
    }

    public function startRenewContract(StaffContractService $contracts): void
    {
        if (! StaffAccess::canManageContracts()) {
            return;
        }
        $this->contractMode = 'renew';
        $this->editingContractId = null;
        $this->contractPdf = null;
        $this->contractForm = $contracts->renewDefaults($this->staffId);
        $prev = $contracts->previousContractStatus($this->staffId);
        if ($prev === 4) {
            $this->contractForm['previous_contract_status_id'] = 4;
        }
        $this->loadUnitOptions();
    }

    public function editContract(int $contractId, StaffContractService $contracts): void
    {
        if (! StaffAccess::canManageContracts()) {
            return;
        }
        $row = DB::table('staff_contracts')->where('staff_contract_id', $contractId)->where('staff_id', $this->staffId)->first();
        if (! $row) {
            return;
        }
        $this->contractMode = 'edit';
        $this->editingContractId = $contractId;
        $this->contractPdf = null;
        $this->contractForm = $contracts->rowToForm($row);
        $this->loadUnitOptions();
    }

    public function cancelContractForm(): void
    {
        $this->contractMode = '';
        $this->editingContractId = null;
        $this->contractForm = [];
        $this->contractPdf = null;
        $this->unitOptions = [];
    }

    public function loadUnitOptions(StaffContractService $contracts): void
    {
        $divisionId = (int) ($this->contractForm['division_id'] ?? 0);
        $this->unitOptions = $contracts->unitsForDivision($divisionId);
    }

    public function saveContract(StaffContractService $contracts): void
    {
        if (! StaffAccess::canManageContracts() || $this->contractMode !== 'edit' || ! $this->editingContractId) {
            return;
        }

        $this->validateContractForm(requirePrevious: false);

        $ok = $contracts->update($this->editingContractId, $this->staffId, $this->contractForm, $this->contractPdf);
        if ($ok) {
            session()->flash('success', 'Contract updated.');
            $this->cancelContractForm();
        } else {
            session()->flash('error', 'Could not update contract.');
        }
    }

    public function createContract(StaffContractService $contracts): void
    {
        if (! StaffAccess::canManageContracts() || $this->contractMode !== 'renew') {
            return;
        }

        $prevLocked = $contracts->previousContractStatus($this->staffId) === 4;
        $this->validateContractForm(requirePrevious: ! $prevLocked);

        $newId = $contracts->create($this->staffId, $this->contractForm, $this->contractPdf);
        if (! $newId) {
            session()->flash('error', 'Could not create contract.');

            return;
        }

        $prevStatus = (int) ($this->contractForm['previous_contract_status_id'] ?? 0);
        if ($prevStatus > 0) {
            $contracts->applyPreviousContractStatus($this->staffId, $newId, $prevStatus);
        }

        session()->flash('success', 'New contract created.');
        $this->cancelContractForm();
    }

    private function validateContractForm(bool $requirePrevious): void
    {
        $rules = [
            'contractForm.job_id' => 'required|integer|min:1',
            'contractForm.grade_id' => 'required',
            'contractForm.contracting_institution_id' => 'required|integer|min:1',
            'contractForm.funder_id' => 'required|integer|min:1',
            'contractForm.first_supervisor' => 'required|integer|min:1',
            'contractForm.contract_type_id' => 'required|integer|min:1',
            'contractForm.duty_station_id' => 'required|integer|min:1',
            'contractForm.division_id' => 'required|integer|min:1',
            'contractForm.start_date' => 'required|date',
            'contractForm.end_date' => 'required|date|after_or_equal:contractForm.start_date',
            'contractForm.status_id' => 'required|integer|min:1',
            'contractForm.second_supervisor' => 'nullable|integer',
            'contractForm.job_acting_id' => 'nullable',
            'contractForm.unit_id' => 'nullable|integer',
            'contractForm.other_associated_divisions' => 'nullable|array',
            'contractForm.other_associated_divisions.*' => 'integer',
            'contractPdf' => 'nullable|file|mimes:pdf|max:10240',
        ];

        if ($requirePrevious) {
            $rules['contractForm.previous_contract_status_id'] = 'required|integer|min:1';
        }

        $this->validate($rules);
    }

    public function render(
        StaffProfileService $profiles,
        LeaveBalanceService $balanceService,
        StaffContractService $contracts
    ) {
        $person = $profiles->find($this->staffId);
        if (! $person) {
            abort(404);
        }

        $contractRows = $profiles->contracts($this->staffId);
        foreach ($contractRows as $c) {
            $c->other_divisions_label = $contracts->otherDivisionLabels($c->other_associated_divisions ?? null);
        }

        $balanceRows = $balanceService->allTypesForStaff($this->staffId, $this->balanceYear);
        $leaveTypes = LeaveType::query()->where('is_active', true)->orderBy('sort_order')->get();
        $lookups = StaffAccess::canManageContracts() ? $contracts->formLookups($this->staffId) : [];
        $previousContractStatus = $contracts->previousContractStatus($this->staffId);

        $editStatuses = collect();
        $renewNewStatuses = collect();
        $renewPreviousStatuses = collect();
        if ($this->contractMode === 'edit' && $this->editingContractId) {
            $current = (int) ($this->contractForm['status_id'] ?? 1);
            $editStatuses = $contracts->editableStatuses($current);
        }
        if ($this->contractMode === 'renew') {
            $renewNewStatuses = $contracts->renewNewStatuses();
            $renewPreviousStatuses = $contracts->renewPreviousStatuses($previousContractStatus);
        }

        return view('staff::livewire.staff-show', [
            'person' => $person,
            'contracts' => $contractRows,
            'balanceRows' => $balanceRows,
            'leaveTypes' => $leaveTypes,
            'isHr' => LeaveAccess::isHr(),
            'canManage' => StaffAccess::canManageStaff(),
            'canManageContracts' => StaffAccess::canManageContracts(),
            'lookups' => $lookups,
            'previousContractStatus' => $previousContractStatus,
            'editStatuses' => $editStatuses,
            'renewNewStatuses' => $renewNewStatuses,
            'renewPreviousStatuses' => $renewPreviousStatuses,
        ]);
    }
}
