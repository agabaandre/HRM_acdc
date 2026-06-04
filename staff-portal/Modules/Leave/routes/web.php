<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\Livewire\LeaveApplication;
use Modules\Leave\Livewire\LeaveDashboard;

Route::middleware(['web', 'auth'])->prefix('leave')->name('leave.')->group(function (): void {
    Route::get('/', LeaveDashboard::class)->name('index');
    Route::get('/apply', LeaveApplication::class)->name('apply');
});
