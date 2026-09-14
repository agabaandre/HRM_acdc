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
     * Staff portal (CodeIgniter) base URL without trailing slash, e.g. https://cbp.africacdc.org/staff
     */
    public static function staffPortalBaseUrl(): string
    {
        $fromSession = rtrim((string) data_get(session('user'), 'base_url', ''), '/');
        $fromSession = rtrim(str_replace('/apm', '', $fromSession), '/');
        if ($fromSession !== '' && ! self::isLocalHost(parse_url($fromSession, PHP_URL_HOST))) {
            return $fromSession;
        }

        $fromConfig = rtrim((string) config('services.staff_api.base_url', ''), '/');
        $fromConfig = rtrim(str_replace('/apm', '', $fromConfig), '/');
        $fromConfig = preg_replace('#/auth(?:/login)?$#', '', $fromConfig) ?? $fromConfig;
        if ($fromConfig !== '' && ! self::isLocalHost(parse_url($fromConfig, PHP_URL_HOST))) {
            return $fromConfig;
        }

        if (! app()->runningInConsole()) {
            $request = request();
            if ($request instanceof Request && $request->hasHeader('Host') && ! self::isLocalHost($request->getHost())) {
                return $request->getSchemeAndHttpHost().'/staff';
            }
        }

        return $fromConfig !== '' ? $fromConfig : 'http://localhost/staff';
    }

    public static function staffPortalLoginUrl(): string
    {
        return rtrim(self::staffPortalBaseUrl(), '/').'/auth/login';
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
