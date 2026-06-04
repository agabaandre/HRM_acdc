<?php

use Illuminate\Support\Facades\Route;
use Modules\Share\Http\Controllers\ShareApiController;

/** Legacy CI3-compatible paths (no /api prefix). */
Route::prefix('share')->group(function (): void {
    Route::get('/', fn () => response('Welcome to the Staff Portal API'));
    Route::get('validate_session', [ShareApiController::class, 'validateSession']);
});
