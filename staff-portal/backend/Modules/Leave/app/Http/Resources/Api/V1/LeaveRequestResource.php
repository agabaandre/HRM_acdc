<?php

namespace Modules\Leave\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;
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
            'division_head' => (int) $this->division_head,
            'supporting_documentation' => $this->supporting_documentation,
            'created_at' => $this->created_at?->toIso8601String(),
            'workflow' => $this->workflowPayload(),
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
}
