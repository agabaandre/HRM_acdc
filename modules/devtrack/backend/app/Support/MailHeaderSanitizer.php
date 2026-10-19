<?php

namespace App\Support;

/**
 * Strip control characters that enable SMTP/HTTP header injection or spoofed From lines.
 */
final class MailHeaderSanitizer
{
    public static function line(string $value, int $maxLength = 500): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        if ($maxLength > 0 && mb_strlen($clean) > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength);
        }

        return $clean;
    }

    public static function containsDangerousCharacters(string $value): bool
    {
        return (bool) preg_match('/[\x00-\x1F\x7F]/', $value);
    }
}
