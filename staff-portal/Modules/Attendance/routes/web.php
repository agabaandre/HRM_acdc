<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Livewire\AttendanceIndex;

Route::middleware(['web', 'auth'])->prefix('attendance')->name('attendance.')->group(function (): void {
    Route::get('/', AttendanceIndex::class)->name('index');
    Route::get('/upload', AttendanceIndex::class)->name('upload');
    Route::get('/person', AttendanceIndex::class)->name('person');
    Route::get('/status', AttendanceIndex::class)->name('status');
    Route::get('/time_sheet', AttendanceIndex::class)->name('timesheet');
});
