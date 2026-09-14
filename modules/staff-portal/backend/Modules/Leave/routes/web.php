<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('leave')->name('leave.')->group(function (): void {
    Route::get('/', function () {
        $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

        return redirect()->away($base.'/leave');
    })->name('index');

    Route::get('/apply', function () {
        $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

        return redirect()->away($base.'/leave/apply');
    })->name('apply');
});
