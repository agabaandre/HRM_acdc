<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Livewire\DashboardIndex;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/dashboard', DashboardIndex::class)->name('dashboard.index');
});
