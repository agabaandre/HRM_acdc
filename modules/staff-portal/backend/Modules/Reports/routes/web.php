<?php

use Illuminate\Support\Facades\Route;

$spa = static function (string $path) {
    $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

    return redirect()->away($base.'/'.ltrim($path, '/'));
};

Route::middleware(['web', 'auth'])->group(function () use ($spa): void {
    // SPA /reports hub removed — CI3 report builders live under Staff / Dashboard.
    Route::get('/reports', fn () => $spa('staff'))->name('reports.index');
    Route::get('/reports/{any}', fn () => $spa('staff'))->where('any', '.*');
});
