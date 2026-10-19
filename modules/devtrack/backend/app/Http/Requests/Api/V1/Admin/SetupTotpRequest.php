<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetupTotpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();
        $mustSetup = $user !== null && method_exists($user, 'mustSetupTotp') && $user->mustSetupTotp();

        return [
            'password' => [
                Rule::requiredIf(! $mustSetup),
                'nullable',
                'string',
            ],
        ];
    }
}
