<?php

$__staffRootEnv = dirname(__DIR__, 4) . '/shared/load-staff-root-env.php';
if (is_file($__staffRootEnv)) {
    require_once $__staffRootEnv;
}

use App\Support\RrSettingsBag;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Audit\Http\Middleware\LogStaffPortalAccess;
use Modules\Auth\Http\Middleware\RefreshPortalSession;
use Modules\Share\Http\Middleware\AuthenticateShareApi;
use Staff\Shared\ModuleCriticalErrorAlerts;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Shared across CBP modules (different APP_KEYs) — must stay unencrypted.
        $middleware->encryptCookies(except: [
            'staff_portal_locale',
        ]);
        $middleware->redirectGuestsTo(function () {
            $base = rtrim((string) config('app.url', ''), '/');

            return $base !== '' ? $base.'/login' : route('login');
        });
        $middleware->redirectUsersTo(function () {
            if ((bool) config('staff-portal.spa_enabled', false)) {
                return rtrim((string) config('staff-portal.spa_url', '/'), '/').'/';
            }

            $base = rtrim((string) config('app.url', ''), '/');

            return $base !== '' ? $base.'/' : route('core.home');
        });
        $middleware->alias([
            'staff.audit' => LogStaffPortalAccess::class,
            'share.auth' => AuthenticateShareApi::class,
        ]);
        $middleware->appendToGroup('web', [
            RefreshPortalSession::class,
            LogStaffPortalAccess::class,
        ]);
        // SPA uses Sanctum bearer tokens. Stateful CSRF still runs because the
        // browser sends same-origin session cookies; skip it for the API.
        $middleware->validateCsrfTokens(except: [
            'api/*',
            '*/api/*',
            'sso/accept',
            // Legacy CBP launch posts africacdc_csrf_token (not Laravel _token).
            // Session auth + same-site cookie still required via auth middleware.
            'home/launch_module',
        ]);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ModuleCriticalErrorAlerts::register(
            $exceptions,
            'Finance',
            static fn () => new RrSettingsBag,
        );
    })->create();
