<?php

namespace App\Support;

/**
 * Same-origin relative paths only — blocks open redirects used in phishing.
 */
final class SafeInternalRedirect
{
    public static function path(?string $candidate): ?string
    {
        if ($candidate === null) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '' || ! str_starts_with($candidate, '/')) {
            return null;
        }

        // Reject protocol-relative and backslash tricks: //evil.com, /\evil.com
        if (str_starts_with($candidate, '//') || str_starts_with($candidate, '/\\')) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
            return null;
        }

        // Allow only path + optional query/hash on this origin.
        if (! preg_match('#^/[A-Za-z0-9._~!$&\'()*+,;=:@%/\-?\#]*$#', $candidate)) {
            return null;
        }

        return $candidate;
    }
}
