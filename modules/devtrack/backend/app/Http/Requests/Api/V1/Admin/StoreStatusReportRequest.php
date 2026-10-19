<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\RagStatus;
use App\Enums\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatusReportRequest extends FormRequest
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
            'period' => [$required, Rule::in(ReportPeriod::values())],
            'period_start' => [$required, 'date'],
            'period_end' => [$required, 'date', 'after_or_equal:period_start'],
            'title' => [$required, 'string', 'max:255'],
            'rag_status' => ['sometimes', Rule::in(RagStatus::values())],
            'summary' => ['nullable', 'string'],
            'accomplishments' => ['nullable', 'string'],
            'next_period_plan' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'decisions_required' => ['nullable', 'string'],
        ];
    }
}
