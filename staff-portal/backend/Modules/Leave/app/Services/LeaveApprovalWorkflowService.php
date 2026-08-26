<?php

namespace Modules\Leave\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Leave\Models\LeaveApprovalLevel;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Models\StaffLeaveApprovalStep;
use Modules\Staff\Models\Staff;
use Modules\Staff\Models\StaffContract;

class LeaveApprovalWorkflowService
{
    public function __construct(
        protected LeavePolicyService $policy,
    ) {}

    public function isEnabled(): bool
    {
        $value = $this->policy->get('approval_workflow_enabled', false);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 1 || $value === '1';
    }

    /**
     * @param  list<array{role?: string, staff_id?: int|null, label?: string|null}>  $levels
     * @return array{enabled: bool, levels: list<array<string, mixed>>}
     */
    public function saveDefinition(bool $enabled, array $levels): array
    {
        $hodLabel = 'Head of Division';
        $hrLevels = [];

        foreach ($levels as $level) {
            $role = strtolower(trim((string) ($level['role'] ?? '')));
            $label = trim((string) ($level['label'] ?? ''));
            if ($role === 'hod') {
                if ($label !== '') {
                    $hodLabel = $label;
                }
                continue;
            }
            if ($role !== 'hr') {
                continue;
            }
            $staffId = (int) ($level['staff_id'] ?? 0);
            if ($staffId < 1) {
                throw new \InvalidArgumentException('Each HR approval level needs an assigned staff member.');
            }
            $hrLevels[] = [
                'staff_id' => $staffId,
                'label' => $label !== '' ? $label : 'HR approver',
            ];
        }

        DB::transaction(function () use ($enabled, $hodLabel, $hrLevels): void {
            LeaveApprovalLevel::query()->delete();
            LeaveApprovalLevel::query()->create([
                'sort_order' => 0,
                'role' => 'hod',
                'staff_id' => null,
                'label' => $hodLabel,
            ]);
            foreach ($hrLevels as $index => $hr) {
                LeaveApprovalLevel::query()->create([
                    'sort_order' => $index + 1,
                    'role' => 'hr',
                    'staff_id' => $hr['staff_id'],
                    'label' => $hr['label'],
                ]);
            }
            $this->policy->save(['approval_workflow_enabled' => $enabled]);
        });

        return $this->definition();
    }

    /**
     * @return array{enabled: bool, levels: list<array<string, mixed>>}
     */
    public function definition(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'levels' => $this->levels(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function levels(): array
    {
        if (! Schema::hasTable('leave_approval_levels')) {
            return [$this->virtualHodLevel()];
        }

        $rows = LeaveApprovalLevel::query()
            ->with('approver')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return [$this->virtualHodLevel()];
        }

        $hasHod = $rows->contains(fn (LeaveApprovalLevel $row) => $row->role === 'hod');
        $out = [];
        if (! $hasHod) {
            $out[] = $this->virtualHodLevel();
        }

        foreach ($rows as $row) {
            $out[] = $this->serializeLevel($row);
        }

        return $out;
    }

    /**
     * @return array{staff_id: int, name: string}|null
     */
    public function defaultHodForStaff(int $staffId): ?array
    {
        $contract = StaffContract::query()
            ->where('staff_id', $staffId)
            ->orderByDesc('staff_contract_id')
            ->first();

        $divisionId = (int) ($contract?->division_id ?? 0);
        if ($divisionId < 1 || ! Schema::hasTable('divisions')) {
            return null;
        }

        $headId = (int) (DB::table('divisions')->where('division_id', $divisionId)->value('division_head') ?? 0);
        if ($headId < 1) {
            return null;
        }

        $name = $this->staffName($headId);

        return [
            'staff_id' => $headId,
            'name' => $name ?: ('Staff #'.$headId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewForStaff(int $staffId, ?int $hodStaffId = null): array
    {
        $hod = $hodStaffId && $hodStaffId > 0
            ? ['staff_id' => $hodStaffId, 'name' => $this->staffName($hodStaffId) ?: ('Staff #'.$hodStaffId)]
            : $this->defaultHodForStaff($staffId);

        $preview = [];
        foreach ($this->levels() as $level) {
            if (($level['role'] ?? '') === 'hod') {
                $preview[] = [
                    'role' => 'hod',
                    'label' => $level['label'],
                    'staff_id' => $hod['staff_id'] ?? null,
                    'staff_name' => $hod['name'] ?? null,
                ];
                continue;
            }
            $preview[] = [
                'role' => 'hr',
                'label' => $level['label'],
                'staff_id' => $level['staff_id'],
                'staff_name' => $level['staff_name'],
            ];
        }

        return $preview;
    }

    public function snapshotForRequest(StaffLeave $leave, ?int $hodStaffId = null): void
    {
        if (! $this->isEnabled() || ! Schema::hasTable('staff_leave_approval_steps')) {
            return;
        }

        $hodId = (int) ($hodStaffId ?? $leave->division_head ?? 0);
        if ($hodId < 1) {
            $hodId = (int) ($this->defaultHodForStaff((int) $leave->staff_id)['staff_id'] ?? 0);
        }
        if ($hodId < 1) {
            throw new \InvalidArgumentException('Select a Head of Division for this leave request.');
        }

        if ((int) $leave->division_head !== $hodId) {
            $leave->division_head = $hodId;
            $leave->save();
        }

        StaffLeaveApprovalStep::query()->where('request_id', $leave->request_id)->delete();

        foreach ($this->levels() as $index => $level) {
            $role = (string) $level['role'];
            StaffLeaveApprovalStep::query()->create([
                'request_id' => (int) $leave->request_id,
                'sort_order' => $index,
                'role' => $role,
                'staff_id' => $role === 'hod' ? $hodId : (int) ($level['staff_id'] ?? 0),
                'label' => (string) $level['label'],
                'status' => 'Pending',
            ]);
        }
    }

    public function requestHasSteps(int $requestId): bool
    {
        if (! Schema::hasTable('staff_leave_approval_steps')) {
            return false;
        }

        return StaffLeaveApprovalStep::query()->where('request_id', $requestId)->exists();
    }

    public function decide(int $requestId, int $actorStaffId, string $action, ?string $comments = null): void
    {
        $leave = StaffLeave::query()->findOrFail($requestId);
        if ((string) $leave->overall_status !== 'Pending') {
            throw new \InvalidArgumentException('This leave request is no longer pending.');
        }

        $step = $this->currentPendingStep($requestId);
        if (! $step) {
            throw new \InvalidArgumentException('There is no current approval step for this leave request.');
        }
        if ((int) $step->staff_id !== $actorStaffId) {
            throw new \InvalidArgumentException('You are not the current approver for this leave request.');
        }

        $approved = $action === 'approve';
        $status = $approved ? 'Approved' : 'Rejected';
        $step->status = $status;
        $step->comments = $comments;
        $step->acted_at = now();
        $step->acted_by = $actorStaffId;
        $step->save();

        $this->syncLegacyColumn($leave, $step, $status);

        $wasOverall = (string) $leave->overall_status;
        if (! $approved) {
            $leave->overall_status = 'Rejected';
            $leave->reject_reason = $comments;
        } elseif (! $this->currentPendingStep($requestId)) {
            $leave->overall_status = 'Approved';
        }
        $leave->updated_at = now();
        $leave->save();

        if ($wasOverall !== 'Approved' && $leave->overall_status === 'Approved') {
            $this->consumeCompensatory($leave);
        }
    }

    public function currentPendingStep(int $requestId): ?StaffLeaveApprovalStep
    {
        return StaffLeaveApprovalStep::query()
            ->where('request_id', $requestId)
            ->where('status', 'Pending')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  Collection<int, StaffLeaveApprovalStep>  $steps
     * @return array{enabled: bool, steps: list<array<string, mixed>>}
     */
    public function serializeSteps(Collection $steps, ?int $actorStaffId, string $overallStatus = 'Pending'): array
    {
        $ordered = $steps->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
        $currentId = null;
        if ($overallStatus === 'Pending') {
            $current = $ordered->first(fn (StaffLeaveApprovalStep $step) => $step->status === 'Pending');
            $currentId = $current?->id;
        }

        $payload = [];
        foreach ($ordered as $step) {
            $isCurrent = $currentId !== null && (int) $step->id === (int) $currentId;
            $payload[] = [
                'id' => (int) $step->id,
                'sort_order' => (int) $step->sort_order,
                'role' => (string) $step->role,
                'label' => (string) $step->label,
                'staff_id' => (int) $step->staff_id,
                'staff_name' => $step->approver?->fullName() ?: $this->staffName((int) $step->staff_id),
                'status' => (string) $step->status,
                'comments' => $step->comments,
                'acted_at' => $step->acted_at?->toIso8601String(),
                'is_current' => $isCurrent,
                'can_act' => $isCurrent && $actorStaffId && (int) $step->staff_id === $actorStaffId,
            ];
        }

        return [
            'enabled' => true,
            'steps' => $payload,
        ];
    }

    /**
     * @param  list<int>  $requestIds
     * @return array<int, array{enabled: bool, steps: list<array<string, mixed>>}>
     */
    public function serializeForRequests(array $requestIds, ?int $actorStaffId): array
    {
        if ($requestIds === [] || ! Schema::hasTable('staff_leave_approval_steps')) {
            return [];
        }

        $steps = StaffLeaveApprovalStep::query()
            ->with('approver')
            ->whereIn('request_id', $requestIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('request_id');

        $overall = StaffLeave::query()
            ->whereIn('request_id', $requestIds)
            ->pluck('overall_status', 'request_id');

        $out = [];
        foreach ($steps as $requestId => $group) {
            $out[(int) $requestId] = $this->serializeSteps(
                $group,
                $actorStaffId,
                (string) ($overall[$requestId] ?? 'Pending'),
            );
        }

        return $out;
    }

    /**
     * Request IDs whose current pending step is assigned to this staff member.
     *
     * @return list<int>
     */
    public function pendingRequestIdsForActor(int $staffId): array
    {
        if (! Schema::hasTable('staff_leave_approval_steps')) {
            return [];
        }

        $candidateIds = StaffLeaveApprovalStep::query()
            ->where('staff_id', $staffId)
            ->where('status', 'Pending')
            ->whereIn('request_id', function ($query): void {
                $query->select('request_id')
                    ->from('staff_leave')
                    ->where('overall_status', 'Pending');
            })
            ->pluck('request_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($candidateIds === []) {
            return [];
        }

        return StaffLeaveApprovalStep::query()
            ->whereIn('request_id', $candidateIds)
            ->where('status', 'Pending')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('request_id')
            ->filter(function (Collection $group) use ($staffId): bool {
                $current = $group->sortBy([['sort_order', 'asc'], ['id', 'asc']])->first();

                return $current && (int) $current->staff_id === $staffId;
            })
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Active staff for assigning HR levels in settings.
     *
     * @return list<array{staff_id: int, name: string, work_email: mixed, sap_number: mixed, label: string}>
     */
    public function staffOptions(): array
    {
        if (! Schema::hasTable('staff')) {
            return [];
        }

        $query = DB::table('staff as s')->orderBy('s.lname')->orderBy('s.fname');
        if (Schema::hasTable('staff_contracts')) {
            $activeStaffSub = DB::table('staff_contracts')
                ->select('staff_id')
                ->whereIn('status_id', [1, 2, 7])
                ->groupBy('staff_id');
            $query->joinSub($activeStaffSub, 'active', 'active.staff_id', '=', 's.staff_id');
        }

        return $query
            ->select([
                's.staff_id',
                's.title',
                's.fname',
                's.lname',
                's.oname',
                's.work_email',
                DB::raw('s.SAPNO as sap_number'),
            ])
            ->get()
            ->map(function (object $row): array {
                $name = trim(implode(' ', array_filter([
                    (string) ($row->title ?? ''),
                    (string) ($row->fname ?? ''),
                    (string) ($row->oname ?? ''),
                    (string) ($row->lname ?? ''),
                ])));
                if ($name === '') {
                    $name = 'Staff #'.$row->staff_id;
                }

                return [
                    'staff_id' => (int) $row->staff_id,
                    'name' => $name,
                    'work_email' => $row->work_email,
                    'sap_number' => $row->sap_number,
                    'label' => $name.($row->work_email ? ' — '.$row->work_email : ''),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeLevel(LeaveApprovalLevel $row): array
    {
        $name = $row->approver?->fullName();
        if (! $name && $row->staff_id) {
            $name = $this->staffName((int) $row->staff_id);
        }

        return [
            'id' => (int) $row->id,
            'sort_order' => (int) $row->sort_order,
            'role' => (string) $row->role,
            'staff_id' => $row->role === 'hod' ? null : ($row->staff_id ? (int) $row->staff_id : null),
            'staff_name' => $row->role === 'hod' ? null : $name,
            'label' => (string) $row->label,
            'locked' => $row->role === 'hod',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function virtualHodLevel(): array
    {
        return [
            'id' => 0,
            'sort_order' => 0,
            'role' => 'hod',
            'staff_id' => null,
            'staff_name' => null,
            'label' => 'Head of Division',
            'locked' => true,
        ];
    }

    protected function staffName(int $staffId): ?string
    {
        $staff = Staff::query()->find($staffId);
        if (! $staff) {
            return null;
        }
        $name = $staff->fullName();

        return $name !== '' ? $name : ('Staff #'.$staffId);
    }

    protected function syncLegacyColumn(StaffLeave $leave, StaffLeaveApprovalStep $step, string $status): void
    {
        if ($step->role === 'hod') {
            $leave->approval_status3 = $status;

            return;
        }

        $firstHr = StaffLeaveApprovalStep::query()
            ->where('request_id', $leave->request_id)
            ->where('role', 'hr')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
        if ($firstHr && (int) $firstHr->id === (int) $step->id) {
            $leave->approval_status1 = $status;
        }
    }

    protected function consumeCompensatory(StaffLeave $leave): void
    {
        $type = LeaveType::query()->find($leave->leave_id);
        $kind = $type?->compensatoryKind();
        if ($kind) {
            app(HolidayCompensatoryGrantService::class)->consume(
                (int) $leave->staff_id,
                $kind,
                (float) $leave->requested_days,
            );
        }
    }
}
