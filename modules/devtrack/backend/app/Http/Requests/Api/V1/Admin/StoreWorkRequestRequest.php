<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\DataClassification;
use App\Enums\PriorityCategory;
use App\Enums\UrgencyLevel;
use App\Enums\WorkClass;
use App\Enums\WorkRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'requesting_unit' => [$required, 'string', 'max:255'],
            'directorate' => ['nullable', 'string', 'max:255'],
            'requester_name' => [$required, 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'requester_id' => ['nullable', 'integer', 'exists:users,id'],
            'business_owner' => ['nullable', 'string', 'max:255'],
            'problem_statement' => [$required, 'string'],
            'proposed_solution' => ['nullable', 'string'],
            'rationale' => ['nullable', 'string'],
            'stakeholders' => ['nullable', 'string'],
            'mandate_alignment' => ['nullable', 'string', 'max:255'],
            'desired_timeline' => ['nullable', 'string', 'max:255'],
            'budget_estimate' => ['nullable', 'string', 'max:100'],
            'existing_alternatives' => ['nullable', 'string'],
            'additional_comments' => ['nullable', 'string'],
            'expected_outcomes' => ['nullable', 'string'],
            'success_measures' => ['nullable', 'string'],
            'target_users' => ['nullable', 'string'],
            'urgency' => ['sometimes', Rule::in(UrgencyLevel::values())],
            'deadline' => ['nullable', 'date'],
            'urgency_reason' => ['nullable', 'string'],
            'data_classification' => ['sometimes', Rule::in(DataClassification::values())],
            'existing_systems' => ['nullable', 'string'],
            'integrations_needed' => ['nullable', 'string'],
            'budget_notes' => ['nullable', 'string'],
            'risk_of_not_delivering' => ['nullable', 'string'],
            'work_class' => ['nullable', Rule::in(WorkClass::values())],
            'priority_category' => ['nullable', Rule::in(PriorityCategory::values())],
            'score_strategic_alignment' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score_impact' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score_urgency_risk' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score_user_reach' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score_feasibility' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score_cost_effort' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score_security' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::in(WorkRequestStatus::values())],
            'workplan_indicator_id' => ['nullable', 'integer', 'exists:workplan_indicators,id'],
        ];
    }
}
