<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('core.home'));
        $middleware->alias([
            'staff.audit' => \Modules\Audit\Http\Middleware\LogStaffPortalAccess::class,
        ]);
        $middleware->appendToGroup('web', [
            \Modules\Auth\Http\Middleware\RefreshPortalSession::class,
            \Modules\Audit\Http\Middleware\LogStaffPortalAccess::class,
        ]);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
