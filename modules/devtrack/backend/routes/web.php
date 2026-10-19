<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\SpaController;
use App\Http\Controllers\SsoAcceptController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/api/health', HealthController::class);

Route::post('/sso/accept', SsoAcceptController::class)->withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
]);

// CBP staff-portal sign-in uses this app's registered Entra redirect, then returns home.
Route::get('/login/redirect', function (Request $request) {
    $state = (string) $request->query('state', '');
    if (str_starts_with($state, 'cbp.')) {
        $target = rtrim((string) env('CBP_MICROSOFT_CALLBACK_URL', ''), '/');
        if ($target === '') {
            $appUrl = rtrim((string) config('app.url', ''), '/');
            $parts = parse_url($appUrl) ?: [];
            $origin = ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? 'localhost');
            if (! empty($parts['port'])) {
                $origin .= ':'.$parts['port'];
            }
            $root = preg_replace('#/devtrack/?$#', '', (string) ($parts['path'] ?? '')) ?: '';
            $target = $origin.rtrim($root, '/').'/backend/auth/microsoft/callback';
        }
        $query = $request->getQueryString();

        return redirect()->away($target.($query ? '?'.$query : ''));
    }

    return app(SpaController::class)($request);
})->withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
]);

// The shell is a static file. Cookie encryption and sessions need APP_KEY;
// when that key is empty they throw, and the error page throws a second time.
Route::get('/{any?}', SpaController::class)
    ->where('any', '^(?!api(?:/|$)|up$).*')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    ]);
