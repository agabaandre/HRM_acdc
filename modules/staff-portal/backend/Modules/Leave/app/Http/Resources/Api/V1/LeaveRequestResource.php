<?php

namespace Modules\Leave\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Services\LeaveApprovalWorkflowService;
use Modules\Leave\Support\LeaveAccess;

/** @mixin StaffLeave */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $staff = $this->staff;
        $workflow = $this->workflowPayload();
        $supportingName = $this->relationLoaded('supportingOfficer')
            ? $this->supportingOfficer?->fullName()
            : null;
        $hodName = $this->relationLoaded('divisionHeadStaff')
            ? $this->divisionHeadStaff?->fullName()
            : null;

        return [
            'request_id' => (int) $this->request_id,
            'staff_id' => (int) $this->staff_id,
            'staff_name' => $staff
                ? trim(($staff->fname ?? '').' '.($staff->lname ?? ''))
                : null,
            'sap_number' => $staff?->SAPNO !== null && $staff?->SAPNO !== ''
                ? (string) $staff->SAPNO
                : null,
            'leave_id' => (int) $this->leave_id,
            'leave_name' => $this->leaveType?->leave_name,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'requested_days' => (int) $this->requested_days,
            'overall_status' => (string) $this->overall_status,
            'email_leave' => $this->email_leave,
            'mobile_leave' => $this->mobile_leave,
            'remarks' => $this->remarks,
            'supporting_staff' => $this->supporting_staff,
            'supporting_staff_name' => $supportingName !== '' ? $supportingName : null,
            'division_head' => (int) $this->division_head,
            'division_head_name' => $hodName !== '' ? $hodName : null,
            'supporting_documentation' => $this->supporting_documentation,
            'document_url' => $this->documentUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            'pending_with' => $this->pendingWith($workflow),
            'workflow' => $workflow,
        ];
    }

    /**
     * @return array{enabled: bool, steps: list<array<string, mixed>>}|null
     */
    protected function workflowPayload(): ?array
    {
        if (! Schema::hasTable('staff_leave_approval_steps')) {
            return null;
        }

        $steps = $this->relationLoaded('approvalSteps')
            ? $this->approvalSteps
            : $this->approvalSteps()->with('approver')->get();

        if ($steps->isEmpty()) {
            return null;
        }

        return app(LeaveApprovalWorkflowService::class)->serializeSteps(
            $steps,
            LeaveAccess::staffId(),
            (string) $this->overall_status,
        );
    }

    /**
     * @param  array{pending_with?: array<string, mixed>|null}|null  $workflow
     * @return array{staff_id: int, staff_name: ?string, label: string}|null
     */
    protected function pendingWith(?array $workflow): ?array
    {
        $status = (string) $this->overall_status;
        if ($status === 'Returned') {
            $name = $this->staff?->fullName();

            return [
                'staff_id' => (int) $this->staff_id,
                'staff_name' => $name !== '' ? $name : null,
                'label' => 'Employee',
            ];
        }
        if ($status !== 'Pending') {
            return null;
        }
        if (isset($workflow['pending_with']) && is_array($workflow['pending_with'])) {
            return $workflow['pending_with'];
        }

        return $this->classicPendingWith();
    }

    /**
     * @return array{staff_id: int, staff_name: ?string, label: string}|null
     */
    protected function classicPendingWith(): ?array
    {
        $candidates = [
            ['status' => $this->approval_status, 'id' => (int) $this->supporting_staff, 'label' => 'Supporting officer / OIC', 'name' => $this->supportingOfficer?->fullName()],
            ['status' => $this->approval_status1, 'id' => 0, 'label' => 'HR', 'name' => 'HR'],
            ['status' => $this->approval_status2, 'id' => (int) $this->supervisor_id, 'label' => 'Supervisor', 'name' => null],
            ['status' => $this->approval_status3, 'id' => (int) $this->division_head, 'label' => 'Head of Division', 'name' => $this->divisionHeadStaff?->fullName()],
        ];
        foreach ($candidates as $row) {
            if (($row['status'] ?? '') !== 'Pending') {
                continue;
            }
            $name = is_string($row['name']) && $row['name'] !== ''
                ? $row['name']
                : ($row['id'] > 0 ? 'Staff #'.$row['id'] : $row['label']);

            return [
                'staff_id' => (int) $row['id'],
                'staff_name' => $name,
                'label' => $row['label'],
            ];
        }

        return null;
    }

    protected function documentUrl(): ?string
    {
        return self::publicDocumentUrl($this->supporting_documentation);
    }

    public static function publicDocumentUrl(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
