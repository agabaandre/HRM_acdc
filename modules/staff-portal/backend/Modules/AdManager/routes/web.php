<?php

use Illuminate\Support\Facades\Route;

$spa = static function (string $path) {
    $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

    return redirect()->away($base.'/'.ltrim($path, '/'));
};

Route::middleware(['web', 'auth'])->prefix('admanager')->name('admanager.')->group(function () use ($spa): void {
    Route::get('/', fn () => $spa('admanager'))->name('index');
    Route::get('/expired_accounts', fn () => $spa('admanager/expired'))->name('expired');
    Route::get('/report', fn () => $spa('admanager/disabled'))->name('report');
});
