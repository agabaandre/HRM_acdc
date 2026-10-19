<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkplanIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $param = $this->route('workplan_indicator');
        $id = $param instanceof \App\Models\WorkplanIndicator ? $param->id : $param;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('workplan_indicators', 'code')->ignore($id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'directorate' => ['nullable', 'string', 'max:255'],
            'programme' => ['nullable', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'target' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'baseline' => ['nullable', 'string', 'max:100'],
            'q1' => ['nullable', 'string', 'max:100'],
            'q2' => ['nullable', 'string', 'max:100'],
            'q3' => ['nullable', 'string', 'max:100'],
            'q4' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
