<?php

use Illuminate\Support\Facades\Route;
use Modules\Share\Http\Controllers\ShareApiController;

/** Session/SSO helpers still available under web stack if needed. */
Route::prefix('share')->group(function (): void {
    Route::get('validate_session', [ShareApiController::class, 'validateSession']);
});
