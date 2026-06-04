<?php

use Illuminate\Support\Facades\Route;
use Modules\AdManager\Livewire\AdManagerIndex;
use Modules\AdManager\Livewire\DisabledAccountsReport;
use Modules\AdManager\Livewire\ExpiredAccounts;

Route::middleware(['auth'])->prefix('admanager')->name('admanager.')->group(function (): void {
    Route::get('/', AdManagerIndex::class)->name('index');
    Route::get('/expired_accounts', ExpiredAccounts::class)->name('expired');
    Route::get('/report', DisabledAccountsReport::class)->name('report');
});
