<?php

use App\Http\Controllers\StaffUploadController;
use Illuminate\Support\Facades\Route;

$spa = static function (string $path) {
    $base = rtrim((string) config('staff-portal.spa_url', '/staff/staff-portal/'), '/');

    return redirect()->away($base.'/'.ltrim($path, '/'));
};

/*
 * Profile/staff media for SPA <img> tags (Bearer auth cannot be sent on image
 * requests). Filenames are opaque; uploads dir remains blocked by .htaccess.
 * Contract files stay session-authenticated.
 */
Route::middleware(['web'])->group(function (): void {
    Route::get('/staff-media/photo/{filename}', [StaffUploadController::class, 'photo'])
        ->where('filename', '[^/]+')
        ->name('staff.media.photo');
    Route::get('/staff-media/signature/{filename}', [StaffUploadController::class, 'signature'])
        ->where('filename', '[^/]+')
        ->name('staff.media.signature');
    Route::get('/staff-media/passport-biodata/{filename}', [StaffUploadController::class, 'passport'])
        ->where('filename', '[^/]+')
        ->name('staff.media.passport');
});

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/staff-media/contract/{filename}', [StaffUploadController::class, 'contract'])
        ->where('filename', '[^/]+')
        ->name('staff.media.contract');
});

Route::middleware(['web', 'auth'])->prefix('staff')->name('staff.')->group(function () use ($spa): void {
    Route::get('/', fn () => $spa('staff'))->name('index');
    Route::get('/search', fn () => $spa('staff'))->name('search');
    Route::get('/all_staff', fn () => $spa('staff?preset=all'))->name('all');
    Route::get('/contract_status/{preset}', fn (string $preset) => $spa('staff?preset='.$preset))->name('contract-status');
    Route::get('/staff_history', fn () => $spa('staff/history'))->name('history');
    Route::get('/staff_birthday', fn () => $spa('staff/birthdays'))->name('birthdays');
    Route::get('/staff_data_quality_report', fn () => $spa('staff/data-quality'))->name('data-quality');
    Route::get('/signature_manager', fn () => $spa('staff/signatures'))->name('signature-manager');
    Route::get('/staff_next_of_kin', fn () => $spa('staff/next-of-kin'))->name('next-of-kin');
    Route::get('/{staff}', fn (int $staff) => $spa('staff/'.$staff))->name('show')->whereNumber('staff');
});
