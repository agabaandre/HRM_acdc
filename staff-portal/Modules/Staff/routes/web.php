<?php

use App\Http\Controllers\StaffUploadController;
use Illuminate\Support\Facades\Route;
use Modules\Staff\Livewire\StaffBirthdays;
use Modules\Staff\Livewire\StaffDataQuality;
use Modules\Staff\Livewire\StaffIndex;
use Modules\Staff\Livewire\StaffShow;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/staff-media/photo/{filename}', [StaffUploadController::class, 'photo'])
        ->where('filename', '.*')
        ->name('staff.media.photo');
    Route::get('/staff-media/contract/{filename}', [StaffUploadController::class, 'contract'])
        ->where('filename', '.*')
        ->name('staff.media.contract');
});

Route::middleware(['web', 'auth'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/', StaffIndex::class)->name('index');
    Route::get('/search', StaffIndex::class)->name('search');
    Route::get('/all_staff', fn () => redirect()->route('staff.index', ['preset' => 'all']))->name('all');
    Route::get('/contract_status/{preset}', StaffIndex::class)->name('contract-status');
    Route::get('/staff_birthday', StaffBirthdays::class)->name('birthdays');
    Route::get('/staff_data_quality_report', StaffDataQuality::class)->name('data-quality');
    Route::get('/{staff}', StaffShow::class)->name('show')->whereNumber('staff');
});
