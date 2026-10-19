<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\DataClassification;
use App\Enums\DeploymentReadiness;
use App\Enums\NamedProjectRole;
use App\Enums\OperationalStatus;
use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\RagStatus;
use App\Enums\WorkClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'work_class' => [$required, Rule::in(WorkClass::values())],
            'workplan_indicator_id' => [$required, 'integer', 'exists:workplan_indicators,id'],
            'work_request_id' => [
                'nullable',
                'integer',
                'exists:work_requests,id',
                Rule::unique('projects', 'work_request_id')->ignore($this->route('project')),
            ],
            'stage' => ['sometimes', Rule::in(ProjectStage::values())],
            'rag_status' => ['sometimes', Rule::in(RagStatus::values())],
            'rag_reason' => ['nullable', 'string'],
            'priority_category' => ['sometimes', Rule::in(PriorityCategory::values())],
            'status' => ['sometimes', Rule::in(ProjectStatus::values())],
            'operational_status' => ['sometimes', Rule::in(OperationalStatus::values())],
            'start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
            'sprint_goal' => ['nullable', 'string', 'max:255'],
            'budget_status' => ['nullable', 'string', 'max:255'],
            'resource_status' => ['nullable', 'string', 'max:255'],
            'uat_progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'deployment_readiness' => ['sometimes', Rule::in(DeploymentReadiness::values())],
            'data_classification' => ['sometimes', Rule::in(DataClassification::values())],
            'scope' => ['nullable', 'string'],
            'success_measures' => ['nullable', 'string'],
            'modules' => [$required, 'array', 'min:1'],
            'modules.*' => ['required', 'string', 'max:255'],
            'roles' => ['sometimes', 'array'],
            'roles.*.role' => ['required_with:roles', Rule::in(NamedProjectRole::values())],
            'roles.*.staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'roles.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('roles', []) as $index => $role) {
                if (! is_array($role)) {
                    continue;
                }
                if (empty($role['staff_id']) && empty($role['user_id'])) {
                    $validator->errors()->add(
                        "roles.{$index}",
                        'Each named role must include a staff_id (preferred) or user_id.',
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('work_request_id') === '' || $this->input('work_request_id') === 'null') {
            $this->merge(['work_request_id' => null]);
        }

        if ($this->has('modules')) {
            $modules = $this->input('modules');
            if (is_string($modules)) {
                $modules = preg_split('/[\n,]+/', $modules) ?: [];
            }
            if (! is_array($modules)) {
                $modules = [];
            }

            $normalized = [];
            foreach ($modules as $module) {
                if (! is_string($module) && ! is_numeric($module)) {
                    continue;
                }
                $value = trim((string) $module);
                if ($value === '') {
                    continue;
                }
                $key = mb_strtolower($value);
                if (! isset($normalized[$key])) {
                    $normalized[$key] = $value;
                }
            }

            $this->merge(['modules' => array_values($normalized)]);
        }
    }
}
