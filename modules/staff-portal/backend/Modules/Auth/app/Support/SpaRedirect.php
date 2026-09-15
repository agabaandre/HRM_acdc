<?php

namespace Modules\Auth\Support;

use Illuminate\Http\RedirectResponse;

class SpaRedirect
{
    public static function enabled(): bool
    {
        return (bool) config('staff-portal.spa_enabled', false);
    }

    public static function spaBase(): string
    {
        return rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');
    }

    /**
     * Absolute Laravel URL for the SPA token hand-off (must include /{webRoot}/backend).
     */
    public static function spaBridgeUrl(): string
    {
        $base = rtrim((string) (config('staff-portal.base_url') ?: config('app.url')), '/');

        // Guard against mis-set APP_URL=https://host (no /{webRoot}/backend) which
        // produces https://host/auth/spa-bridge and Apache 404.
        if ($base === '' || ! str_ends_with($base, '/backend')) {
            $webRoot = trim((string) env('WEB_ROOT', 'staff'), '/');
            if ($webRoot === '') {
                $webRoot = 'staff';
            }
            $host = '';
            if (app()->bound('request')) {
                try {
                    $host = request()->getSchemeAndHttpHost();
                } catch (\Throwable) {
                    $host = '';
                }
            }
            if ($host === '') {
                $fromApp = (string) config('app.url');
                $parts = parse_url($fromApp);
                if (is_array($parts) && ! empty($parts['host'])) {
                    $scheme = $parts['scheme'] ?? 'https';
                    $host = $scheme.'://'.$parts['host'];
                    if (! empty($parts['port'])) {
                        $host .= ':'.$parts['port'];
                    }
                }
            }
            if ($host === '') {
                $host = 'http://localhost';
            }
            $base = $host.'/'.$webRoot.'/backend';
        }

        return $base.'/auth/spa-bridge';
    }

    public static function afterLogin(): RedirectResponse
    {
        if (self::enabled()) {
            // Use APP_URL explicitly — redirect()->route() can drop the subdirectory
            // when Request::root() is wrong under Apache rewrites.
            return redirect()->away(self::spaBridgeUrl());
        }

        return redirect()->route('core.home');
    }

    /**
     * Send the browser back to the SPA (or Livewire) login with a visible error.
     */
    public static function toLoginWithError(string $message, ?string $code = null): RedirectResponse
    {
        $message = trim(strip_tags($message));
        if (mb_strlen($message) > 400) {
            $message = mb_substr($message, 0, 397).'…';
        }
        if ($message === '') {
            $message = 'Sign-in failed. Please try again.';
        }

        if (self::enabled()) {
            $query = ['error' => $message];
            if ($code !== null && $code !== '') {
                $query['error_code'] = $code;
            }

            return redirect()->away(self::spaBase().'/login?'.http_build_query($query));
        }

        return redirect()
            ->route('login')
            ->with('error', $message);
    }
}
