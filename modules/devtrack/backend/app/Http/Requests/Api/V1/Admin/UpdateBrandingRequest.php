<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:500'],
            'primary_color' => ['sometimes', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'logo' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'logo_dark' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'admin_logo_inverse' => ['sometimes', 'boolean'],
            'admin_logo_size_percent' => ['sometimes', 'integer', 'min:50', 'max:200'],
            'favicon' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,ico', 'max:512'],
        ];
    }
}
