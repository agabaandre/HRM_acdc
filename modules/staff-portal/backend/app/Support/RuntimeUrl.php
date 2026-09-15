<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Keep generated URLs under the Apache mount (e.g. /staff/backend, /cbp/backend).
 * Without this, redirect()->route() can emit /auth/spa-bridge at the host root.
 */
final class RuntimeUrl
{
    public static function applyFromRequest(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $mountPath = self::mountPath();
        if ($mountPath === '') {
            return;
        }

        $request = request();
        $schemeHost = $request instanceof Request && $request->hasHeader('Host')
            ? $request->getSchemeAndHttpHost()
            : null;

        if ($schemeHost === null) {
            $configured = rtrim((string) config('app.url', ''), '/');
            if ($configured === '') {
                return;
            }
            URL::forceRootUrl($configured);

            return;
        }

        URL::forceRootUrl(rtrim($schemeHost.$mountPath, '/'));
        URL::forceScheme($request->getScheme());
    }

    /**
     * Application mount path only, e.g. "/staff/backend" or "/cbp/backend".
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
            return rtrim(substr($scriptName, 0, -strlen('/public/index.php')), '/') ?: '';
        }
        if (str_ends_with($scriptName, '/server.php')) {
            return rtrim(substr($scriptName, 0, -strlen('/server.php')), '/') ?: '';
        }
        if (str_ends_with($scriptName, '/index.php')) {
            return rtrim(substr($scriptName, 0, -strlen('/index.php')), '/') ?: '';
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH) ?? '';
        if (preg_match('#^(/[^/]+/backend)(?:/|$)#', $path, $m) === 1) {
            return $m[1];
        }

        return '';
    }
}
