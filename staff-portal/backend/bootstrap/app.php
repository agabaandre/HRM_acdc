<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Audit\Http\Middleware\LogStaffPortalAccess;
use Modules\Auth\Http\Middleware\RefreshPortalSession;
use Modules\Share\Http\Middleware\AuthenticateShareApi;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function () {
            if ((bool) config('staff-portal.spa_enabled', false)) {
                return rtrim((string) config('staff-portal.spa_url', '/'), '/').'/';
            }

            return route('core.home');
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
        ]);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
