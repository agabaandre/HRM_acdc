<?php

namespace App\Support;

/**
 * Normalize Staff Share API base URLs for server-side HTTP calls.
 * Prefer Laravel Share at /staff/backend (CI /staff host is rewritten).
 */
final class StaffApiBaseUrl
{
    public static function resolve(string $configured): string
    {
        $base = rtrim(trim($configured), '/');
        if ($base === '') {
            return 'http://127.0.0.1/staff/backend';
        }

        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return self::ensureLaravelShareMount($base);
        }

        $hostLower = strtolower($host);
        if ($hostLower === 'localhost' || $hostLower === '127.0.0.1') {
            return self::ensureLaravelShareMount($base);
        }

        if (str_ends_with($hostLower, '.local')) {
            return self::ensureLaravelShareMount(self::rewriteHost($base, 'localhost', 'http'));
        }

        return self::ensureLaravelShareMount($base);
    }

    public static function ensureLaravelShareMount(string $base): string
    {
        $base = rtrim($base, '/');
        $path = (string) (parse_url($base, PHP_URL_PATH) ?? '');
        $path = rtrim($path, '/');

        if ($path === '/staff' || $path === '/demo_staff') {
            return $base.'/backend';
        }

        return $base;
    }

    private static function rewriteHost(string $base, string $newHost, string $newScheme): string
    {
        $parts = parse_url($base);
        if (! is_array($parts)) {
            return $base;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $newScheme.'://'.$newHost.$port.$path.$query.$fragment;
    }
}
