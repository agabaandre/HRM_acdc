<?php

namespace App\Support;

/**
 * Normalize Staff Share API base URLs for server-side HTTP calls.
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
        if ($hostLower === 'localhost' || $hostLower === '127.0.0.1') {
            return $base;
        }

        if (str_ends_with($hostLower, '.local')) {
            return self::rewriteHost($base, 'localhost', 'http');
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
