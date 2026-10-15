<?php

namespace Modules\Leave\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Leave\Models\LeaveType;

/** @mixin LeaveType */
class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'leave_id' => (int) $this->leave_id,
            'leave_name' => (string) $this->leave_name,
            'code' => $this->code,
            'leave_days' => (float) $this->leave_days,
            'is_accrued' => (bool) $this->is_accrued,
            'accrual_rate' => (float) $this->accrual_rate,
            'is_active' => (bool) $this->is_active,
            'requires_hr_approval' => (bool) $this->requires_hr_approval,
            'requires_medical_certificate' => (bool) $this->requires_medical_certificate,
            'medical_report_after_days' => $this->medical_report_after_days,
            'max_instances' => $this->max_instances,
            'max_days_per_year' => $this->max_days_per_year !== null ? (float) $this->max_days_per_year : null,
            'min_days_per_year' => $this->min_days_per_year !== null ? (float) $this->min_days_per_year : null,
            'deduct_compensatory_first' => (bool) $this->deduct_compensatory_first,
            'policy_notes' => $this->policy_notes,
            'sort_order' => $this->sort_order,
        ];
    }
}
