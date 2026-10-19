<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\WorkItemPriority;
use App\Enums\WorkItemStatus;
use App\Enums\WorkItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkItemRequest extends FormRequest
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
            'enhancement_id' => ['nullable', 'integer', 'exists:enhancements,id'],
            'project_module_id' => ['nullable', 'integer', 'exists:project_modules,id'],
            'sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
            'parent_id' => ['nullable', 'integer', 'exists:work_items,id'],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'acceptance_criteria' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::in(WorkItemType::values())],
            'status' => ['sometimes', Rule::in(WorkItemStatus::values())],
            'priority' => ['sometimes', Rule::in(WorkItemPriority::values())],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'estimate_hours' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'story_points' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'blocked_reason' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
