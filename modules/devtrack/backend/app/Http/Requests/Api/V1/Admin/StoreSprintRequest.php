<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\SprintStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => [$required, 'string', 'max:255'],
            'goal' => ['nullable', 'string'],
            'start_date' => [$required, 'date'],
            'end_date' => [$required, 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(SprintStatus::values())],
            'committed_points' => ['nullable', 'integer', 'min:0'],
            'completed_points' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
