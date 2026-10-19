<?php

namespace App\Support;

/**
 * Prevent password-reset / auth emails from linking to an attacker-controlled host
 * when FRONTEND_URL is misconfigured or tampered with.
 */
final class TrustedFrontendUrl
{
    public static function base(): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $app = rtrim((string) config('app.url'), '/');

        if ($frontend === '' || ! self::isTrustedFrontend($frontend, $app)) {
            return $app !== '' ? $app : 'http://localhost';
        }

        return $frontend;
    }

    public static function isTrustedFrontend(string $frontend, ?string $appUrl = null): bool
    {
        $appUrl ??= (string) config('app.url');
        $frontendHost = self::host($frontend);
        $appHost = self::host($appUrl);

        if ($frontendHost === null || $appHost === null) {
            return false;
        }

        // Same host (ports may differ: local Vite :3006 vs API :8089).
        return $frontendHost === $appHost;
    }

    private static function host(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return strtolower((string) $parts['host']);
    }
}
