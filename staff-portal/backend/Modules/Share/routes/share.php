<?php

use Illuminate\Support\Facades\Route;
use Modules\Share\Http\Controllers\ShareApiController;
use Modules\Share\Http\Controllers\ShareReferenceApiController;
use Modules\Share\Http\Middleware\AuthenticateShareApi;

/**
 * CI3-compatible Share paths (no /api prefix) for APM STAFF_API_* clients.
 *
 * Examples:
 *   GET /share/get_current_staff
 *   GET /share/get_current_staff/{STAFF_API_TOKEN}
 *   GET /share/divisions
 *   GET /share/directorates
 *   POST /share/token
 *   GET /share/docs
 */
Route::prefix('share')->group(function (): void {
    Route::get('/', fn () => response()->json([
        'message' => 'Africa CDC Staff Share API',
        'docs' => url('/share/docs'),
        'openapi' => url('/share/openapi.yaml'),
    ]));

    Route::get('docs', [ShareReferenceApiController::class, 'docs']);
    Route::get('openapi.yaml', [ShareReferenceApiController::class, 'openapi']);
    Route::get('openapi', [ShareReferenceApiController::class, 'openapi']);
    Route::post('token', [ShareReferenceApiController::class, 'issueToken']);

    Route::get('validate_session', [ShareApiController::class, 'validateSession']);

    Route::middleware(AuthenticateShareApi::class)->group(function (): void {
        Route::get('get_current_staff/{token?}', [ShareReferenceApiController::class, 'getCurrentStaff'])
            ->where('token', '[^/]+');
        Route::get('divisions/{token?}', [ShareReferenceApiController::class, 'divisions'])
            ->where('token', '[^/]+');
        Route::get('directorates/{token?}', [ShareReferenceApiController::class, 'directorates'])
            ->where('token', '[^/]+');
    });
});
