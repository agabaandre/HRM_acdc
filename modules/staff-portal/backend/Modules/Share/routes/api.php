<?php

use Illuminate\Support\Facades\Route;
use Modules\Share\Http\Controllers\ShareApiController;

Route::prefix('share')->group(function (): void {
    Route::get('/', fn () => response()->json(['message' => 'Africa CDC Staff Portal API']));
    Route::get('validate_session', [ShareApiController::class, 'validateSession']);
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('current_staff', [ShareApiController::class, 'currentStaff']);
    });
});
