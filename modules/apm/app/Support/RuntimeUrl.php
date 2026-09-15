<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Keep generated URLs aligned with the active HTTP request when the app is
 * mounted under a subdirectory (e.g. /staff/apm).
 */
final class RuntimeUrl
{
    public static function applyFromRequest(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $request = request();
        if (! $request instanceof Request || ! $request->hasHeader('Host')) {
            return;
        }

        $mountPath = self::mountPath();
        if ($mountPath === '') {
            return;
        }

        $root = rtrim($request->getSchemeAndHttpHost().$mountPath, '/');
        URL::forceRootUrl($root);
        URL::forceScheme($request->getScheme());
    }

    /**
     * Application mount path only, e.g. "/staff/apm".
     */
    public static function mountPath(): string
    {
        $configured = rtrim((string) config('app.url', ''), '/');
        $fromConfig = parse_url($configured, PHP_URL_PATH);
        if (is_string($fromConfig) && $fromConfig !== '' && $fromConfig !== '/') {
            return rtrim($fromConfig, '/');
        }

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if (str_ends_with($scriptName, '/public/index.php')) {
            return rtrim(substr($scriptName, 0, -strlen('/public/index.php')), '/');
        }
        if (str_ends_with($scriptName, '/server.php')) {
            return rtrim(substr($scriptName, 0, -strlen('/server.php')), '/');
        }

        foreach (['/staff/apm', '/apm'] as $mount) {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            if (str_starts_with(parse_url($uri, PHP_URL_PATH) ?? '', $mount)) {
                return $mount;
            }
        }

        return '';
    }

    /**
     * Browser-facing Staff Portal SPA / CBP home (never the Laravel API /backend).
     * e.g. https://cbp.africacdc.org/staff  or  http://localhost/staff
     */
    public static function staffPortalBaseUrl(): string
    {
        $candidates = [
            (string) data_get(session('user'), 'base_url', ''),
            (string) config('app.staff_portal_url', ''),
            (string) env('STAFF_PORTAL_SPA_URL', ''),
            (string) env('CI_BASE_URL', ''),
            (string) env('BASE_URL', ''),
        ];

        foreach ($candidates as $raw) {
            $normalized = self::normalizeStaffPortalPublicUrl($raw);
            if ($normalized === '') {
                continue;
            }
            $host = parse_url($normalized, PHP_URL_HOST);
            // Prefer non-local configured URLs when the request is on production.
            if (! self::isLocalHost(is_string($host) ? $host : null) || self::requestIsLocal()) {
                return $normalized;
            }
            // Keep local candidate as last resort below.
            $localFallback = $normalized;
        }

        if (! empty($localFallback)) {
            return $localFallback;
        }

        if (! app()->runningInConsole()) {
            $request = request();
            if ($request instanceof Request && $request->hasHeader('Host')) {
                $webRoot = self::webRootSegment();

                return $request->getSchemeAndHttpHost().'/'.$webRoot;
            }
        }

        return 'http://localhost/'.self::webRootSegment();
    }

    /**
     * SPA login page (Vue), not the Laravel API login path.
     */
    public static function staffPortalLoginUrl(): string
    {
        return rtrim(self::staffPortalBaseUrl(), '/').'/login';
    }

    /**
     * Strip /apm, /backend, /auth/login so browser links hit the SPA mount.
     */
    public static function normalizeStaffPortalPublicUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // Relative path like /staff/ or /staff/backend
        if (! preg_match('#^https?://#i', $url)) {
            $path = '/'.ltrim($url, '/');
            $path = self::stripApiSuffixes($path);
            if ($path === '' || $path === '/') {
                $path = '/'.self::webRootSegment();
            }

            if (! app()->runningInConsole()) {
                $request = request();
                if ($request instanceof Request && $request->hasHeader('Host')) {
                    return rtrim($request->getSchemeAndHttpHost().$path, '/');
                }
            }

            return rtrim('http://localhost'.$path, '/');
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = self::stripApiSuffixes((string) ($parts['path'] ?? ''));
        if ($path === '' || $path === '/') {
            $path = '/'.self::webRootSegment();
        }

        return rtrim($scheme.'://'.$host.$port.$path, '/');
    }

    private static function stripApiSuffixes(string $path): string
    {
        $path = rtrim($path, '/');
        $path = preg_replace('#/apm(?:/.*)?$#', '', $path) ?? $path;
        $path = preg_replace('#/backend(?:/.*)?$#', '', $path) ?? $path;
        $path = preg_replace('#/auth(?:/login)?$#', '', $path) ?? $path;
        $path = rtrim($path, '/');

        return $path === '' ? '' : $path;
    }

    private static function webRootSegment(): string
    {
        foreach ([env('BASE_URL', ''), env('CI_BASE_URL', ''), (string) config('app.url', '')] as $u) {
            $path = (string) (parse_url((string) $u, PHP_URL_PATH) ?? '');
            $path = self::stripApiSuffixes($path);
            $seg = trim($path, '/');
            if ($seg !== '' && ! str_contains($seg, '/')) {
                return $seg;
            }
            if ($seg !== '') {
                return explode('/', $seg)[0];
            }
        }

        return 'staff';
    }

    private static function requestIsLocal(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }
        $request = request();
        if (! $request instanceof Request) {
            return true;
        }

        return self::isLocalHost($request->getHost());
    }

    private static function isLocalHost(?string $host): bool
    {
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || str_ends_with($host, '.local');
    }
}
