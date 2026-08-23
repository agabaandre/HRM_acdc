<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    $spa = static function (string $path) {
        $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

        return redirect()->away($base.'/'.ltrim($path, '/'));
    };

    Route::get('/settings', fn () => $spa('settings'))->name('settings.hub');
    Route::get('/settings/languages', fn () => $spa('settings/languages'))->name('settings.languages');
    Route::get('/settings/ai-providers', fn () => $spa('settings/ai-providers'))->name('settings.ai-providers');
    Route::get('/settings/leave', fn () => $spa('settings/leave'))->name('settings.leave');
    Route::get('/settings/performance', fn () => $spa('settings/performance'))->name('settings.performance');
    Route::get('/settings/lookup/{table}', fn (string $table) => $spa('settings/lookup/'.$table))
        ->where('table', '[a-z_]+')
        ->name('settings.lookup');
});
