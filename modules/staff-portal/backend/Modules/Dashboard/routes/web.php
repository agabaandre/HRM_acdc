<?php

use Illuminate\Support\Facades\Route;

$spa = static function (string $path) {
    $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

    return redirect()->away($base.'/'.ltrim($path, '/'));
};

Route::middleware(['web', 'auth'])->group(function () use ($spa): void {
    Route::get('/dashboard', fn () => $spa('dashboard'))->name('dashboard.index');
});
