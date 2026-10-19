<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PromoteApiDocsTokenFromCookie;
use App\Support\ApiDocsAuthCookie;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Docs must NOT use the "web" middleware group (EncryptCookies/StartSession).
            // Those require APP_KEY and caused production 500s while /api/v1/health still worked.
            if (! config('app.api_docs_enabled')) {
                return;
            }

            Route::middleware([
                PromoteApiDocsTokenFromCookie::class,
                'auth:sanctum',
                EnsureUserIsActive::class,
            ])->group(function (): void {
                Route::get('/api/documentation', [ApiDocumentationController::class, 'ui']);
                Route::get('/docs', fn () => redirect('/api/documentation'));
                Route::get('/api/docs.json', [ApiDocumentationController::class, 'spec']);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Admin UI uses Bearer tokens (sessionStorage), not cookie/CSRF SPA auth.
        // Never call statefulApi() — it makes SANCTUM_STATEFUL_DOMAINS (e.g.
        // notifications.africacdc.org) require CSRF and breaks browser login
        // while curl http://127.0.0.1:8089 still succeeds.
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'sso/accept',
        ]);
        $middleware->encryptCookies(except: [
            ApiDocsAuthCookie::NAME,
        ]);
        // Laravel defaults to route('login') which does not exist — that throws 500
        // on unauthenticated API calls that omit Accept: application/json.
        $middleware->redirectGuestsTo(fn () => rtrim(request()->getBasePath(), '/').'/login');
        $middleware->throttleApi('api');
        $middleware->trustProxies(
            // Only trust private/docker peers — never the public internet.
            // Host Nginx / compose network set X-Forwarded-* ; direct :8089 clients cannot spoof.
            at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\BlockListedIps::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            function (Request $request): bool {
                // Swagger HTML page should redirect guests to /login (not JSON 401),
                // but only when the docs routes are enabled.
                if (
                    config('app.api_docs_enabled')
                    && $request->is('api/documentation', 'docs')
                    && ! $request->expectsJson()
                ) {
                    return false;
                }

                // Use str_starts_with: $request->is('api/*') does not match nested paths
                // like api/v1/admin/users (* does not cross "/").
                return str_starts_with($request->path(), 'api/') || $request->expectsJson();
            },
        );
    })->create();
