<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HelpdeskTicket::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isEndUser = $this->user()?->helpdeskProfile?->role === HelpdeskProfile::ROLE_USER;

        return [
            'category_id' => ['required', 'integer', 'exists:helpdesk_categories,id'],
            'description' => ['nullable', 'string', 'max:65000'],
            'priority' => [
                Rule::prohibitedIf(fn () => $this->user()?->helpdeskProfile?->role === HelpdeskProfile::ROLE_USER),
                'nullable',
                'string',
                'in:low,medium,high,critical',
            ],
            'source' => ['nullable', 'string', 'in:web,whatsapp,teams,email'],
            'requester_staff_id' => [
                Rule::requiredIf(! $isEndUser),
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
