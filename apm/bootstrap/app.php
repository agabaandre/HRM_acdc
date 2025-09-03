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
    ->withMiddleware(function (Middleware $middleware) {
        // Temporarily disable audit middleware to debug the error
        // $middleware->web(append: [
        //     \App\Http\Middleware\AuditLogMiddleware::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Re-enable Flare as it was working before
        if (class_exists(\Spatie\LaravelFlare\Facades\Flare::class)) {
            \Spatie\LaravelFlare\Facades\Flare::handles($exceptions);
        }
    })->create();
