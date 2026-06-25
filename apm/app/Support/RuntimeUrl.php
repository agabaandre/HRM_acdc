<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Keep generated URLs aligned with the active HTTP request when cached config
 * still points at localhost (common after config:cache on production).
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

        $configured = rtrim((string) config('app.url'), '/');
        $configuredHost = parse_url($configured, PHP_URL_HOST);
        $requestHost = $request->getHost();

        if (! self::isLocalHost($configuredHost) || self::isLocalHost($requestHost)) {
            return;
        }

        $root = rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');
        URL::forceRootUrl($root);
        URL::forceScheme($request->getScheme());
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
