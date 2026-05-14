<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'min:1'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'in:user,agent,supervisor,admin,auditor'],
            'directorate_id' => ['nullable', 'integer', 'min:0'],
            'division_id' => ['nullable', 'integer', 'min:0'],
            'sap_no' => ['nullable', 'string', 'max:64'],
            'photo' => ['nullable', 'string', 'max:255'],
            'ts' => ['required', 'integer'],
            'sig' => ['required', 'string', 'size:64'],
        ];
    }
}
