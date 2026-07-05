<?php

namespace App\Support;

final class StaffSsoPolicy
{
    /** Whether legacy GET ?token= hand-off is permitted (default off in production). */
    public static function urlTokenAllowed(): bool
    {
        $flag = env('SSO_ALLOW_URL_TOKEN');
        if ($flag === null || $flag === '') {
            return config('app.env') !== 'production';
        }

        return in_array(strtolower(trim((string) $flag)), ['1', 'true', 'yes', 'on'], true);
    }
}
