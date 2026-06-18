<?php

namespace App\Support;

/**
 * Normalize Staff Share API base URLs for server-side HTTP calls.
 *
 * Browsers often resolve mDNS hostnames (e.g. Users-MacBook-Pro.local) while PHP/cURL
 * cannot, which breaks Helpdesk → Staff Share API calls on local Apache setups.
 */
final class StaffApiBaseUrl
{
    public static function resolve(string $configured): string
    {
        $base = rtrim(trim($configured), '/');
        if ($base === '') {
            return 'http://localhost/staff';
        }

        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return $base;
        }

        $hostLower = strtolower($host);
        if (self::isLoopbackHost($hostLower)) {
            return $base;
        }

        if (str_ends_with($hostLower, '.local') || self::matchesMachineHostname($hostLower)) {
            return self::rewriteHost($base, 'localhost', 'http');
        }

        return $base;
    }

    private static function isLoopbackHost(string $hostLower): bool
    {
        return $hostLower === 'localhost' || $hostLower === '127.0.0.1';
    }

    private static function matchesMachineHostname(string $hostLower): bool
    {
        $machine = strtolower(trim((string) (gethostname() ?: '')));
        if ($machine === '') {
            return false;
        }

        return $hostLower === $machine || str_starts_with($hostLower, $machine.'.');
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
