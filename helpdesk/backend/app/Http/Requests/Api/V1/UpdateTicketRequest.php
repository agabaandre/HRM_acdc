<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof HelpdeskTicket
            && $this->user()?->can('update', $ticket);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => [
                Rule::prohibitedIf(fn () => $this->user()?->helpdeskProfile?->role === HelpdeskProfile::ROLE_USER),
                'nullable',
                'string',
                'in:low,medium,high,critical',
            ],
            'status' => ['sometimes', 'string', 'in:open,pending,in_progress,awaiting_requester_confirmation,resolved,closed'],
            'category_id' => ['sometimes', 'integer', 'exists:helpdesk_categories,id'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
