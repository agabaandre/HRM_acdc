<?php

use Illuminate\Support\Facades\Route;
use Modules\Performance\Livewire\PerformanceForm;
use Modules\Performance\Livewire\PerformanceHub;

Route::middleware(['web', 'auth'])->prefix('performance')->name('performance.')->group(function (): void {
    Route::get('/', PerformanceHub::class)->name('index');
    Route::get('/ppa_dashboard', PerformanceHub::class)->name('ppa-dashboard');
    Route::get('/my_ppas', PerformanceHub::class)->name('my-ppas');
    Route::get('/pending_approval', PerformanceHub::class)->name('pending');

    Route::get('/create', PerformanceForm::class)->defaults('phase', 'ppa')->name('ppa.create');
    Route::get('/view_ppa/{entryId}/{staffId}', PerformanceForm::class)->defaults('phase', 'ppa')->name('ppa.form');
    Route::get('/midterm/midterm_review/{entryId}/{staffId}', PerformanceForm::class)->defaults('phase', 'midterm')->name('midterm.form');
    Route::get('/endterm/endterm_review/{entryId}/{staffId}', PerformanceForm::class)->defaults('phase', 'endterm')->name('endterm.form');

});
