<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\SsoController;
use Modules\Auth\Http\Controllers\MicrosoftAuthController;
use Modules\Auth\Livewire\AuditLogs;
use Modules\Auth\Livewire\LoginForm;
use Modules\Auth\Livewire\UsersIndex;

Route::middleware('web')->group(function (): void {
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

    Route::middleware('auth')->group(function (): void {
        Route::get('auth/users', UsersIndex::class)->name('auth.users');
        Route::get('auth/logs', AuditLogs::class)->name('auth.logs');
    });
});
