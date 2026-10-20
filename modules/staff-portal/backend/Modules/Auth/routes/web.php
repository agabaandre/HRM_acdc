<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\SsoController;
use Modules\Auth\Http\Controllers\MicrosoftAuthController;
use Modules\Auth\Http\Controllers\OidcDiscoveryController;
use Modules\Auth\Http\Controllers\SpaBridgeController;
use Modules\Auth\Livewire\LoginForm;

$spa = static function (string $path) {
    $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

    return redirect()->away($base.'/'.ltrim($path, '/'));
};

Route::middleware('web')->group(function () use ($spa): void {
    Route::get('.well-known/openid-configuration', [OidcDiscoveryController::class, 'discovery']);
    Route::get('oauth/.well-known/openid-configuration', [OidcDiscoveryController::class, 'discovery']);
    Route::get('oauth/jwks', [OidcDiscoveryController::class, 'jwks']);

    Route::get('login', LoginForm::class)->name('login')->middleware('guest');

    // Do NOT wrap Microsoft OAuth in `guest`. A leftover web session would bounce
    // authenticated users to the SPA home (no Sanctum token) → instant login flicker.
    Route::prefix('auth/microsoft')->group(function (): void {
        Route::get('/', [MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft.redirect');
        Route::get('callback', [MicrosoftAuthController::class, 'callback'])->name('auth.microsoft.callback');
    });

    Route::match(['get', 'post'], 'logout', function () {
        if (auth()->check()) {
            auth()->logout();
        }
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $spa = rtrim((string) config('staff-portal.spa_url', '/staff/'), '/');
        $login = json_encode($spa.'/login', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return response(
            '<!doctype html><html><head><meta charset="utf-8"><title>Signing out</title></head><body><script>'
            .'try{["staff_portal_api_token","staff_portal_me_cache_v2","risk_register_api_token","helpdesk_api_token","email_server.token","staff_portal.sso_token","helpdesk.staff_sso_token"].forEach(function(k){sessionStorage.removeItem(k);localStorage.removeItem(k);});}catch(e){}'
            .'location.replace('.$login.');'
            .'</script></body></html>',
            200,
            ['Cache-Control' => 'no-store'],
        );
    })->name('auth.logout');

    Route::get('sso/callback', [SsoController::class, 'acceptSsoRedirect'])->name('auth.sso.callback');

    Route::middleware('auth')->group(function () use ($spa): void {
        Route::get('auth/spa-bridge', SpaBridgeController::class)->name('auth.spa-bridge');
        Route::get('auth/users', fn () => $spa('auth/users'))->name('auth.users');
        Route::get('auth/logs', fn () => $spa('auth/audit-logs'))->name('auth.logs');
    });
});
