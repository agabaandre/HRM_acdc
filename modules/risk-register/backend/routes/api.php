<?php

use App\Http\Controllers\Api\V1\CbpModulesController;
use App\Http\Controllers\Api\V1\CbpModulesLaunchController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RiskController;
use App\Http\Controllers\Api\V1\RiskDashboardController;
use App\Http\Controllers\Api\V1\RiskImportController;
use App\Http\Controllers\Api\V1\RiskReportsController;
use App\Http\Controllers\Api\V1\RiskLookupsController;
use App\Http\Controllers\Api\V1\RiskReviewController;
use App\Http\Controllers\Api\V1\EmailSettingsController;
use App\Http\Controllers\Api\V1\StaffApiSettingsController;
use App\Http\Controllers\Api\V1\RiskSettingsController;
use App\Http\Controllers\Api\V1\RiskWorkflowController;
use App\Http\Middleware\AuthenticateRiskSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['ok' => true, 'app' => 'risk-register']));
    // Public: login + shell need logo/copyright without a session.
    Route::get('/branding', \App\Http\Controllers\Api\V1\BrandingController::class);

    Route::middleware([AuthenticateRiskSession::class])->group(function () {
        Route::get('/me', MeController::class);
        Route::get('/auth/me', MeController::class);
        Route::get('/cbp-modules', CbpModulesController::class);
        Route::post('/cbp-modules/launch', CbpModulesLaunchController::class);
        Route::get('/languages', [\App\Http\Controllers\Api\V1\RiskLocaleController::class, 'catalog']);
        Route::post('/locale', [\App\Http\Controllers\Api\V1\RiskLocaleController::class, 'apply']);
        Route::get('/lookups', [RiskLookupsController::class, 'index']);
        Route::get('/org', [RiskLookupsController::class, 'org']);
        Route::get('/org/staff', [RiskLookupsController::class, 'staff']);
        Route::get('/settings', [RiskSettingsController::class, 'show']);
        Route::put('/settings', [RiskSettingsController::class, 'update']);
        Route::post('/settings/lookups/{table}', [RiskSettingsController::class, 'updateLookup']);
        Route::delete('/settings/lookups/{table}/{id}', [RiskSettingsController::class, 'deleteLookup'])->whereNumber('id');
        Route::post('/settings/rating-bands/publish', [RiskSettingsController::class, 'publishRatingBands']);
        Route::post('/settings/rating-bands/activate', [RiskSettingsController::class, 'activateRatingVersion']);
        Route::get('/settings/email', [EmailSettingsController::class, 'show']);
        Route::put('/settings/email', [EmailSettingsController::class, 'update']);
        Route::post('/settings/email/test', [EmailSettingsController::class, 'test']);
        Route::get('/settings/staff-api', [StaffApiSettingsController::class, 'show']);
        Route::put('/settings/staff-api', [StaffApiSettingsController::class, 'update']);
        Route::post('/settings/staff-api/test', [StaffApiSettingsController::class, 'test']);

        Route::get('/import/status', [RiskImportController::class, 'status']);
        Route::post('/import/preview', [RiskImportController::class, 'preview']);
        Route::post('/import/{batchId}/commit-matched', [RiskImportController::class, 'commitMatched'])->whereNumber('batchId');
        Route::post('/import/{batchId}/apply-mappings', [RiskImportController::class, 'applyMappings'])->whereNumber('batchId');
        Route::post('/import/remap-unmapped', [RiskImportController::class, 'remapUnmappedRisks']);

        Route::get('/risks', [RiskController::class, 'index']);
        Route::post('/risks', [RiskController::class, 'store']);
        Route::get('/risks/{id}', [RiskController::class, 'show'])->whereNumber('id');
        Route::put('/risks/{id}', [RiskController::class, 'update'])->whereNumber('id');
        Route::post('/risks/{id}/submit', [RiskWorkflowController::class, 'submit'])->whereNumber('id');
        Route::get('/risks/{id}/approvals', [RiskWorkflowController::class, 'riskApprovals'])->whereNumber('id');
        Route::post('/risks/{id}/reviews', [RiskReviewController::class, 'store'])->whereNumber('id');
        Route::get('/risks/{id}/trends', [RiskReviewController::class, 'trends'])->whereNumber('id');
        Route::get('/dashboard/summary', RiskDashboardController::class);
        Route::get('/reports', [RiskReportsController::class, 'catalog']);
        Route::get('/reports/enterprise-themes', [RiskReportsController::class, 'enterpriseThemes']);
        Route::get('/reports/enterprise-themes/{id}', [RiskReportsController::class, 'enterpriseThemeShow'])->whereNumber('id');
        Route::get('/reports/heat-map', [RiskReportsController::class, 'heatMap']);
        Route::get('/my-approvals', [RiskWorkflowController::class, 'myApprovals']);
        Route::post('/approvals/{approvalId}/approve', [RiskWorkflowController::class, 'approve'])->whereNumber('approvalId');
        Route::post('/approvals/{approvalId}/request-feedback', [RiskWorkflowController::class, 'requestFeedback'])->whereNumber('approvalId');
        Route::get('/approvals/{approvalId}/eligible-recipients', [RiskWorkflowController::class, 'eligibleRecipients'])->whereNumber('approvalId');
        Route::get('/workflows', [RiskWorkflowController::class, 'showWorkflow']);
        Route::get('/workflows/by-division', [RiskWorkflowController::class, 'byDivision']);
        Route::post('/workflows', [RiskWorkflowController::class, 'upsertWorkflow']);
        Route::put('/workflows/{id}', [RiskWorkflowController::class, 'updateWorkflow'])->whereNumber('id');
    });
});
