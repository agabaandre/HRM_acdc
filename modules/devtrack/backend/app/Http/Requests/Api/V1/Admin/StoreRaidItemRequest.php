<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\RaidStatus;
use App\Enums\RaidType;
use App\Enums\UrgencyLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRaidItemRequest extends FormRequest
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
            'type' => [$required, Rule::in(RaidType::values())],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'severity' => ['sometimes', Rule::in(UrgencyLevel::values())],
            'status' => ['sometimes', Rule::in(RaidStatus::values())],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'mitigation' => ['nullable', 'string'],
        ];
    }
}
