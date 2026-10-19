<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\EmailDriver;
use App\Rules\SafeMailHeader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:email_providers,slug'],
            'driver' => ['required', Rule::in(EmailDriver::values())],
            'config' => ['nullable', 'array'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255', new SafeMailHeader],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string'],
        ];
    }
}
