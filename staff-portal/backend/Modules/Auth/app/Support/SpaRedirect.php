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

    public static function afterLogin(): RedirectResponse
    {
        if (self::enabled()) {
            return redirect()->route('auth.spa-bridge');
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
