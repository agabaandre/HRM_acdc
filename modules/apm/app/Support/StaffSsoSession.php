<?php

namespace App\Support;

final class StaffSsoSession
{
    /**
     * Bearer token stored in the APM session for Staff portal validation/refresh.
     */
    public static function bearerToken(array $userSession): ?string
    {
        $jwt = trim((string) ($userSession['sso_jwt'] ?? ''));
        if ($jwt !== '') {
            return $jwt;
        }

        $legacy = trim((string) ($userSession['ci_token'] ?? ''));

        return $legacy !== '' ? $legacy : null;
    }

    public static function validateUrl(): string
    {
        return rtrim(RuntimeUrl::staffPortalBaseUrl(), '/').'/share/validate_session';
    }

    public static function refreshUrl(): string
    {
        return rtrim(RuntimeUrl::staffPortalBaseUrl(), '/').'/share/refresh_token';
    }
}
