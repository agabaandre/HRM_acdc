<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlockSuspiciousAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('scope')) {
            $this->merge(['scope' => 'ip']);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'scope' => ['required', Rule::in(['ip', 'email', 'both'])],
        ];
    }
}
