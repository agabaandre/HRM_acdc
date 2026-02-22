<?php

use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\ApmAuthController;
use App\Http\Controllers\Api\ApmPendingController;
use App\Http\Controllers\Api\ApmDocumentController;
use App\Http\Controllers\Api\ApmApprovalController;
use App\Http\Controllers\Api\ApmApprovedByMeController;
use App\Http\Controllers\Api\ApmMatrixController;
use App\Http\Controllers\Api\ApmActivityController;
use App\Http\Controllers\Api\ApmMemoListController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Session Management API Routes
Route::get('/validate-session', [SessionController::class, 'validateSession'])->name('api.validate-session');
Route::post('/extend-session', [SessionController::class, 'extendSession'])->name('api.extend-session');
Route::get('/session-status', [SessionController::class, 'getSessionStatus'])->name('api.session-status');
Route::get('/session-debug', [SessionController::class, 'getSessionDebug'])->name('api.session-debug');

// Logout API route (called from CodeIgniter logout)
// This route needs session access, so we use web middleware
Route::post('/logout', [AuthController::class, 'apiLogout'])
    ->middleware('web')
    ->name('api.logout');

// APM API v1 (JWT auth; does not affect CodeIgniter/web auth)
Route::prefix('apm/v1')->group(function () {
    Route::post('auth/login', [ApmAuthController::class, 'login']);
    // Logout is outside auth middleware so it can be called with or without token (always returns 200)
    Route::post('auth/logout', [ApmAuthController::class, 'logout']);

    Route::middleware(['auth:api', 'apm.api.context'])->group(function () {
        Route::post('auth/refresh', [ApmAuthController::class, 'refresh']);
        Route::get('auth/me', [ApmAuthController::class, 'me']);

        Route::get('pending-approvals', [ApmPendingController::class, 'index']);
        Route::get('pending-approvals/summary', [ApmPendingController::class, 'summary']);

        Route::get('documents/{type}/{status}', [ApmDocumentController::class, 'listByTypeAndStatus'])->where('type', 'special_memo|matrix|activity|non_travel_memo|service_request|arf|change_request')->where('status', 'pending|approved|draft|rejected|returned');
        Route::get('documents/{type}/{id}', [ApmDocumentController::class, 'show'])->where('type', 'special_memo|matrix|activity|non_travel_memo|service_request|arf|change_request')->where('id', '[0-9]+');
        Route::get('documents/attachments/{type}/{id}/{index}', [ApmDocumentController::class, 'attachment'])->where('type', 'special_memo|matrix|activity|non_travel_memo|service_request|arf|change_request')->where('index', '[0-9]+');

        Route::post('actions', [ApmApprovalController::class, 'action']);

        Route::get('approved-by-me', [ApmApprovedByMeController::class, 'index']);
        Route::get('approved-by-me/average-time', [ApmApprovedByMeController::class, 'averageTime']);

        Route::get('matrices/{matrixId}', [ApmMatrixController::class, 'show']);
        Route::post('matrices/{matrixId}', [ApmMatrixController::class, 'updateStatus']);

        Route::get('matrices/{matrixId}/activities/{activityId}', [ApmActivityController::class, 'show']);
        Route::post('matrices/{matrixId}/activities/{activityId}', [ApmActivityController::class, 'updateStatus']);

        Route::get('memo-list/pending', [ApmMemoListController::class, 'pending']);
        Route::get('memo-list/approved', [ApmMemoListController::class, 'approved']);
    });
});
