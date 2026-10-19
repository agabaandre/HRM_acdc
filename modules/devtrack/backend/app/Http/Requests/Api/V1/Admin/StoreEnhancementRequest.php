<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\EnhancementStatus;
use App\Enums\EnhancementWorkType;
use App\Enums\WorkItemPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnhancementRequest extends FormRequest
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
            'project_module_id' => ['nullable', 'integer', 'exists:project_modules,id'],
            'name' => [$required, 'string', 'max:255'],
            'work_type' => [$required, Rule::in(EnhancementWorkType::values())],
            'description' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'requesting_division' => ['nullable', 'string', 'max:255'],
            'business_owner_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'responsible_developer_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'priority' => ['sometimes', Rule::in(WorkItemPriority::values())],
            'planned_start' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(EnhancementStatus::values())],
            'acceptance_criteria' => ['nullable', 'string'],
            'release_ids' => ['sometimes', 'array'],
            'release_ids.*' => ['integer', 'exists:releases,id'],
        ];
    }
}
