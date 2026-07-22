<?php

namespace App\Http\Requests\Api\V1;

use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Services\HtmlSanitizer;
use App\Services\RichTextDataUriExternalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $showCategory = HelpdeskSetting::showIssueCategoryOnRequestForm();

        return [
            'business_unit_id' => ['required', 'integer', 'exists:helpdesk_business_units,id'],
            'category_id' => [
                Rule::requiredIf($showCategory),
                'nullable',
                'integer',
                'exists:helpdesk_categories,id',
            ],
            'description' => ['required', 'string', 'max:65000'],
            'priority' => ['prohibited'],
            'source' => ['nullable', 'string', 'in:web,whatsapp,teams,email'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'requester_staff_id' => [
                Rule::requiredIf(! $isEndUser && ! $this->boolean('is_anonymous')),
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('description') || ! is_string($this->input('description'))) {
            return;
        }

        $this->merge([
            'description' => RichTextDataUriExternalizer::externalize(
                $this->input('description'),
                ticket: null,
                user: $this->user(),
            ),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->has('description')) {
                return;
            }
            if (HtmlSanitizer::sanitize($this->input('description')) === null) {
                $v->errors()->add(
                    'description',
                    'A description is required. Add text or images in the editor.',
                );
            }

            $businessUnitId = (int) $this->input('business_unit_id');
            $unit = $businessUnitId > 0
                ? HelpdeskBusinessUnit::query()->find($businessUnitId)
                : null;

            if ($this->boolean('is_anonymous')) {
                if (! $unit || ! $unit->allows_anonymous) {
                    $v->errors()->add('is_anonymous', 'Anonymous reporting is only available for Internal Oversight.');
                }
            }

            $categoryId = (int) $this->input('category_id');
            if ($categoryId > 0 && $businessUnitId > 0) {
                $category = HelpdeskCategory::query()->find($categoryId);
                if (! $category || (int) $category->business_unit_id !== $businessUnitId) {
                    $v->errors()->add('category_id', 'Choose a category that belongs to the selected business unit.');
                }
                if ($category && ! $category->is_active) {
                    $v->errors()->add('category_id', 'That category is not active.');
                }
            }

            if ($unit && ! $unit->is_active) {
                $v->errors()->add('business_unit_id', 'That business unit is not active.');
            }
        });
    }
}
