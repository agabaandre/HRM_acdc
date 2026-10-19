<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ReleaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $release = $this->route('release');

        return [
            'project_id' => [$required, 'integer', 'exists:projects,id'],
            'version' => [
                $required,
                'string',
                'max:50',
                Rule::unique('releases', 'version')
                    ->where(fn ($q) => $q->where('project_id', $this->input('project_id') ?? $release?->project_id))
                    ->ignore($release),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(ReleaseStatus::values())],
            'target_date' => ['nullable', 'date'],
            'released_at' => ['nullable', 'date'],
            'enhancement_ids' => ['sometimes', 'array'],
            'enhancement_ids.*' => ['integer', 'exists:enhancements,id'],
        ];
    }
}
