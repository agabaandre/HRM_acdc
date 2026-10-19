<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\SafeMailHeader;
use App\Services\EmailAttachmentService;
use Illuminate\Foundation\Http\FormRequest;

class IntegrationSendMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var EmailAttachmentService $attachments */
        $attachments = app(EmailAttachmentService::class);

        return array_merge([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:500', new SafeMailHeader],
            'body' => ['required', 'string'],
            'is_html' => ['sometimes', 'boolean'],
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:email_providers,id'],
            'cc' => ['sometimes', 'nullable', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['sometimes', 'nullable', 'array'],
            'bcc.*' => ['email'],
        ], $attachments->validationRules());
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanField('is_html');
        $this->normalizeOptionalProviderId();
        $this->normalizeRecipientList('cc');
        $this->normalizeRecipientList('bcc');
    }

    private function normalizeBooleanField(string $field): void
    {
        if (! $this->has($field)) {
            return;
        }

        $value = $this->input($field);

        if (is_bool($value)) {
            return;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                $this->merge([$field => $normalized]);

                return;
            }
        }

        if (in_array($value, [1, 0, '1', '0'], true)) {
            $this->merge([$field => (bool) $value]);
        }
    }

    private function normalizeOptionalProviderId(): void
    {
        if (! $this->has('provider_id')) {
            return;
        }

        $providerId = $this->input('provider_id');

        if ($providerId === null || $providerId === '' || $providerId === '0' || $providerId === 0) {
            $this->merge(['provider_id' => null]);
        }
    }

    private function normalizeRecipientList(string $field): void
    {
        if (! $this->has($field)) {
            return;
        }

        $value = $this->input($field);

        if ($value === null || $value === '' || $value === []) {
            $this->merge([$field => null]);

            return;
        }

        if (! is_string($value)) {
            return;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || in_array(strtolower($trimmed), [$field, 'string'], true)) {
            $this->merge([$field => null]);

            return;
        }

        $emails = array_values(array_filter(array_map('trim', preg_split('/[,\n]/', $trimmed))));

        $this->merge([$field => $emails === [] ? null : $emails]);
    }
}
