<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RiskController;
use App\Http\Controllers\Api\V1\RiskDashboardController;
use App\Http\Controllers\Api\V1\RiskImportController;
use App\Http\Controllers\Api\V1\RiskLookupsController;
use App\Http\Controllers\Api\V1\RiskReviewController;
use App\Http\Controllers\Api\V1\RiskSettingsController;
use App\Http\Controllers\Api\V1\RiskWorkflowController;
use App\Http\Middleware\AuthenticateRiskSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['ok' => true, 'app' => 'risk-register']));

    Route::middleware([AuthenticateRiskSession::class])->group(function () {
        Route::get('/me', MeController::class);
        Route::get('/auth/me', MeController::class);
        Route::get('/lookups', [RiskLookupsController::class, 'index']);
        Route::get('/org', [RiskLookupsController::class, 'org']);
        Route::get('/settings', [RiskSettingsController::class, 'show']);
        Route::put('/settings', [RiskSettingsController::class, 'update']);
        Route::post('/settings/lookups/{table}', [RiskSettingsController::class, 'updateLookup']);
        Route::delete('/settings/lookups/{table}/{id}', [RiskSettingsController::class, 'deleteLookup'])->whereNumber('id');

        Route::get('/import/status', [RiskImportController::class, 'status']);
        Route::post('/import/preview', [RiskImportController::class, 'preview']);
        Route::post('/import/{batchId}/commit-matched', [RiskImportController::class, 'commitMatched'])->whereNumber('batchId');
        Route::post('/import/{batchId}/apply-mappings', [RiskImportController::class, 'applyMappings'])->whereNumber('batchId');

        Route::get('/risks', [RiskController::class, 'index']);
        Route::post('/risks', [RiskController::class, 'store']);
        Route::get('/risks/{id}', [RiskController::class, 'show'])->whereNumber('id');
        Route::put('/risks/{id}', [RiskController::class, 'update'])->whereNumber('id');
        Route::post('/risks/{id}/submit', [RiskWorkflowController::class, 'submit'])->whereNumber('id');
        Route::post('/risks/{id}/reviews', [RiskReviewController::class, 'store'])->whereNumber('id');
        Route::get('/risks/{id}/trends', [RiskReviewController::class, 'trends'])->whereNumber('id');
        Route::get('/dashboard/summary', RiskDashboardController::class);
        Route::get('/my-approvals', [RiskWorkflowController::class, 'myApprovals']);
        Route::post('/approvals/{approvalId}/approve', [RiskWorkflowController::class, 'approve'])->whereNumber('approvalId');
        Route::post('/approvals/{approvalId}/request-feedback', [RiskWorkflowController::class, 'requestFeedback'])->whereNumber('approvalId');
        Route::get('/approvals/{approvalId}/eligible-recipients', [RiskWorkflowController::class, 'eligibleRecipients'])->whereNumber('approvalId');
        Route::get('/workflows', [RiskWorkflowController::class, 'showWorkflow']);
        Route::post('/workflows', [RiskWorkflowController::class, 'upsertWorkflow']);
    });
});
