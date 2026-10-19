<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlockedAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

        if (! $this->filled('scope')) {
            if ($this->filled('ip_address') && $this->filled('email')) {
                $this->merge(['scope' => 'both']);
            } elseif ($this->filled('email')) {
                $this->merge(['scope' => 'email']);
            } else {
                $this->merge(['scope' => 'ip']);
            }
        }
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['ip', 'email', 'both'])],
            'ip_address' => ['required_if:scope,ip', 'required_if:scope,both', 'nullable', 'string', 'ip', 'max:45'],
            'email' => ['required_if:scope,email', 'required_if:scope,both', 'nullable', 'string', 'email:rfc', 'max:255'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'audit_log_id' => ['sometimes', 'nullable', 'integer', 'exists:audit_logs,id'],
        ];
    }
}
