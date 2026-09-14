<?php

use Illuminate\Support\Facades\Route;
use Modules\Share\Http\Controllers\ShareApiController;
use Modules\Share\Http\Controllers\ShareReferenceApiController;
use Modules\Share\Http\Middleware\AuthenticateShareApi;

/**
 * CI3-compatible Share paths (no /api prefix) for APM / Helpdesk / Finance STAFF_API_* clients.
 */
Route::prefix('share')->group(function (): void {
    Route::get('/', fn () => redirect('/share/docs'));

    Route::get('docs', [ShareReferenceApiController::class, 'docs']);
    Route::get('openapi.yaml', [ShareReferenceApiController::class, 'openapi']);
    Route::get('openapi', [ShareReferenceApiController::class, 'openapi']);
    Route::post('token', [ShareReferenceApiController::class, 'issueToken']);

    Route::get('validate_session', [ShareApiController::class, 'validateSession']);
    Route::get('refresh_token', [ShareApiController::class, 'refreshToken']);
    Route::post('refresh_token', [ShareApiController::class, 'refreshToken']);

    Route::middleware(AuthenticateShareApi::class)->group(function (): void {
        Route::get('get_current_staff/{token?}', [ShareReferenceApiController::class, 'getCurrentStaff'])
            ->where('token', '[^/]+');
        Route::get('divisions/{token?}', [ShareReferenceApiController::class, 'divisions'])
            ->where('token', '[^/]+');
        Route::get('directorates/{token?}', [ShareReferenceApiController::class, 'directorates'])
            ->where('token', '[^/]+');
        Route::get('users/{token?}', [ShareReferenceApiController::class, 'users'])
            ->where('token', '[^/]+');
        Route::get('cbp_modules/{token?}', [ShareReferenceApiController::class, 'cbpModules'])
            ->where('token', '[^/]+');
        Route::get('get_signature/{token?}', [ShareReferenceApiController::class, 'getSignature'])
            ->where('token', '[^/]+');
        Route::get('get_photo/{token?}', [ShareReferenceApiController::class, 'getPhoto'])
            ->where('token', '[^/]+');
        Route::get('helpdesk_agents_in_divisions/{token?}', [ShareReferenceApiController::class, 'helpdeskAgentsInDivisions'])
            ->where('token', '[^/]+');
        Route::post('mark_helpdesk_agents/{token?}', [ShareReferenceApiController::class, 'markHelpdeskAgents'])
            ->where('token', '[^/]+');
    });
});
