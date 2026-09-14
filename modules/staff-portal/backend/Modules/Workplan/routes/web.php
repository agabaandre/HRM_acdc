<?php

use Illuminate\Support\Facades\Route;

$spa = static function (string $path) {
    $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

    return redirect()->away($base.'/'.ltrim($path, '/'));
};

Route::middleware(['web', 'auth'])->group(function () use ($spa): void {
    Route::get('/workplan', fn () => $spa('workplan'))->name('workplan.index');
    Route::get('/workplans', fn () => $spa('workplan'))->name('workplans.index');
});
