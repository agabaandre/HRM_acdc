<?php

namespace App\Support;

/**
 * Finance is mounted under a subdirectory (e.g. /staff/finance). Helpers here
 * build paths and URLs that include that prefix for Inertia and redirects.
 */
class AppBasePath
{
    /**
     * Mount path only, e.g. "/staff/finance" (no trailing slash).
     */
    public static function path(): string
    {
        $parsed = parse_url((string) config('app.url', ''), PHP_URL_PATH);
        if (! is_string($parsed) || $parsed === '' || $parsed === '/') {
            return '';
        }

        return rtrim($parsed, '/');
    }

    /**
     * Absolute URL under the app mount, e.g. http://localhost/staff/finance/dashboard
     */
    public static function url(string $path = '/'): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return $base.'/';
        }

        return $base.$path;
    }
}
