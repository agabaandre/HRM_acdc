<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\DataClassification;
use App\Enums\DeploymentReadiness;
use App\Enums\ModuleStatus;
use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\RagStatus;
use App\Enums\WorkClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'project_id' => [$required, 'integer', 'exists:projects,id'],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'work_class' => ['nullable', Rule::in(WorkClass::values())],
            'stage' => ['sometimes', Rule::in(ProjectStage::values())],
            'rag_status' => ['sometimes', Rule::in(RagStatus::values())],
            'rag_reason' => ['nullable', 'string'],
            'priority_category' => ['nullable', Rule::in(PriorityCategory::values())],
            'status' => ['sometimes', Rule::in(ModuleStatus::values())],
            'start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
            'sprint_goal' => ['nullable', 'string', 'max:255'],
            'budget_status' => ['nullable', 'string', 'max:255'],
            'resource_status' => ['nullable', 'string', 'max:255'],
            'uat_progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'deployment_readiness' => ['nullable', Rule::in(DeploymentReadiness::values())],
            'data_classification' => ['nullable', Rule::in(DataClassification::values())],
            'scope' => ['nullable', 'string'],
            'success_measures' => ['nullable', 'string'],
            'requesting_division' => ['nullable', 'string', 'max:255'],
            'business_owner_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'responsible_developer_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
        ];
    }
}
