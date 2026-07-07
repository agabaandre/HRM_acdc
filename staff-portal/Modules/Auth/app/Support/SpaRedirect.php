<?php

namespace Modules\Auth\Support;

use Illuminate\Http\RedirectResponse;

class SpaRedirect
{
    public static function enabled(): bool
    {
        return (bool) config('staff-portal.spa_enabled', false);
    }

    public static function afterLogin(): RedirectResponse
    {
        if (self::enabled()) {
            return redirect()->route('auth.spa-bridge');
        }

        return redirect()->route('core.home');
    }
}
