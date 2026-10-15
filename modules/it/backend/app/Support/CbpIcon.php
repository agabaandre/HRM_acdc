<?php

namespace App\Support;

/**
 * Normalizes cbp_modules.icon_class values for Font Awesome 6 (fa-solid fa-*).
 */
final class CbpIcon
{
    public static function classes(?string $iconClass, string $extra = ''): string
    {
        $raw = trim((string) $iconClass);
        if ($raw === '') {
            $raw = 'fa-th';
        }

        // Already a full FA6 class list (e.g. fa-solid fa-users, fab fa-github).
        if (preg_match('/\b(fa-solid|fa-regular|fa-brands|fas|far|fab)\b/i', $raw)) {
            $normalized = preg_replace('/\b(fas)\b/', 'fa-solid', $raw);
            $normalized = preg_replace('/\b(far)\b/', 'fa-regular', $normalized);
            $normalized = preg_replace('/\b(fab)\b/', 'fa-brands', $normalized);

            return trim($normalized.' '.$extra);
        }

        // Legacy: "fa-users" or "fa fa-users"
        $name = preg_replace('/^(fa\s+)+/i', '', $raw);
        $name = ltrim($name, '-');
        if (! str_starts_with($name, 'fa-')) {
            $name = 'fa-'.$name;
        }

        return trim('fa-solid '.$name.' '.$extra);
    }
}
