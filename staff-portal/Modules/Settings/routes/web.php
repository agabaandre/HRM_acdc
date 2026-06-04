<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Livewire\LeaveSettings;
use Modules\Settings\Livewire\LookupTableManager;
use Modules\Settings\Livewire\PerformanceSettings;
use Modules\Settings\Livewire\SettingsHub;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/settings', SettingsHub::class)->name('settings.hub');
    Route::get('/settings/leave', LeaveSettings::class)->name('settings.leave');
    Route::get('/settings/performance', PerformanceSettings::class)->name('settings.performance');
    Route::get('/settings/lookup/{table}', LookupTableManager::class)->name('settings.lookup');
});
