<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
                Rule::prohibitedIf(fn () => ! $this->user()?->helpdeskProfile?->canReassignTickets()),
                'nullable',
                'string',
                'in:low,medium,high,critical',
            ],
            'status' => ['sometimes', 'string', 'in:open,pending,in_progress,awaiting_requester_confirmation,resolved,closed'],
            'business_unit_id' => ['sometimes', 'nullable', 'integer', 'exists:helpdesk_business_units,id'],
            'category_id' => ['sometimes', 'integer', 'exists:helpdesk_categories,id'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $categoryId = (int) $this->input('category_id');
            $businessUnitId = (int) $this->input('business_unit_id');
            if ($categoryId < 1 || $businessUnitId < 1) {
                return;
            }
            $category = HelpdeskCategory::query()->find($categoryId);
            if ($category && (int) $category->business_unit_id !== $businessUnitId) {
                $v->errors()->add('category_id', 'Choose a category that belongs to the selected business unit.');
            }
        });
    }
}
