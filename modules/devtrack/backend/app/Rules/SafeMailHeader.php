<?php

namespace App\Rules;

use App\Support\MailHeaderSanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeMailHeader implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (MailHeaderSanitizer::containsDangerousCharacters($value)) {
            $fail('The :attribute must not contain line breaks or control characters.');
        }
    }
}
