<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('payroll')->name('payroll.')->group(function (): void {
    Route::get('/', function () {
        $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

        return redirect()->away($base.'/payroll');
    })->name('index');
});
