<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\EmailDriver;
use App\Rules\SafeMailHeader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $providerId = $this->route('email_provider')?->id ?? $this->route('email_provider');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('email_providers', 'slug')->ignore($providerId)],
            'driver' => ['sometimes', Rule::in(EmailDriver::values())],
            'config' => ['sometimes', 'array'],
            'from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255', new SafeMailHeader],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
