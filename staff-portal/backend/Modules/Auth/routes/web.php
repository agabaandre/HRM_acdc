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

    Route::middleware('guest')->prefix('auth/microsoft')->group(function (): void {
        Route::get('/', [MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft.redirect');
        Route::get('callback', [MicrosoftAuthController::class, 'callback'])->name('auth.microsoft.callback');
    });

    Route::post('logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('auth.logout')->middleware('auth');

    Route::get('sso/callback', [SsoController::class, 'acceptSsoRedirect'])->name('auth.sso.callback');

    Route::middleware('auth')->group(function () use ($spa): void {
        Route::get('auth/spa-bridge', SpaBridgeController::class)->name('auth.spa-bridge');
        Route::get('auth/users', fn () => $spa('auth/users'))->name('auth.users');
        Route::get('auth/logs', fn () => $spa('auth/audit-logs'))->name('auth.logs');
    });
});
