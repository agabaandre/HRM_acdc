<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\SsoLaunchController;
use Modules\Core\Livewire\CbpHome;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/', CbpHome::class)->name('core.home');
});

/*
| CI3-compatible paths used by cbp-sso-launch.js / cbp-session-refresh.js.
| Apache rewrites /staff/home/* and /staff/auth/refresh* → /staff/backend/...
*/
Route::middleware('web')->group(function (): void {
    Route::get('auth/refreshCSRF', [SsoLaunchController::class, 'refreshCsrf'])
        ->name('core.auth.refresh-csrf');
    Route::get('auth/refresh_sso_session', [SsoLaunchController::class, 'refreshSsoSession'])
        ->middleware('auth')
        ->name('core.auth.refresh-sso-session');
    Route::post('home/launch_module', [SsoLaunchController::class, 'launchModule'])
        ->middleware('auth')
        ->name('core.home.launch-module');
});
