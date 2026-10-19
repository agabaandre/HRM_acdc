<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'min:32'],
            'method' => ['required', 'string', 'in:email,totp'],
            'code' => ['required', 'string', 'min:4', 'max:32'],
        ];
    }
}
