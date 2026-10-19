<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\SafeMailHeader;
use Illuminate\Foundation\Http\FormRequest;

class StoreExternalIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new SafeMailHeader],
            'slug' => ['nullable', 'string', 'max:64', 'alpha_dash', 'unique:external_integrations,slug'],
            'client_id' => ['nullable', 'string', 'max:64', 'alpha_dash', 'unique:external_integrations,slug'],
            'client_secret' => ['required_without:generate_secret', 'nullable', 'string', 'min:16', 'max:255'],
            'generate_secret' => ['sometimes', 'boolean'],
            'email_provider_id' => ['nullable', 'integer', 'exists:email_providers,id'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['string', 'max:45', 'ip'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('client_id') && ! $this->filled('slug')) {
            $this->merge(['slug' => $this->input('client_id')]);
        }
    }
}
