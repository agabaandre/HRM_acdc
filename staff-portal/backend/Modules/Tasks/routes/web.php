<?php

use Illuminate\Support\Facades\Route;

$spa = static function (string $path) {
    $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

    return redirect()->away($base.'/'.ltrim($path, '/'));
};

Route::middleware(['web', 'auth'])->prefix('tasks')->name('tasks.')->group(function () use ($spa): void {
    Route::get('/', fn () => $spa('tasks'))->name('index');
    Route::get('/activity', fn () => $spa('tasks'))->name('activity');
});

Route::middleware(['web', 'auth'])->group(function () use ($spa): void {
    Route::get('/weektasks/tasks', fn () => $spa('tasks/weekly'))->name('weektasks.tasks');
});
