<?php

use App\Http\Controllers\Api\V1\CbpModulesController;
use App\Http\Controllers\Api\V1\CbpModulesLaunchController;
use App\Http\Controllers\Api\V1\DivisionContextController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RiskController;
use App\Http\Controllers\Api\V1\RiskDashboardController;
use App\Http\Controllers\Api\V1\RiskImportController;
use App\Http\Controllers\Api\V1\RiskReportsController;
use App\Http\Controllers\Api\V1\RiskLookupsController;
use App\Http\Controllers\Api\V1\RiskReviewController;
use App\Http\Controllers\Api\V1\AppLogsController;
use App\Http\Controllers\Api\V1\EmailSettingsController;
use App\Http\Controllers\Api\V1\FinanceApmMappingController;
use App\Http\Controllers\Api\V1\StaffApiSettingsController;
use App\Http\Controllers\Api\V1\RiskSettingsController;
use App\Http\Controllers\Api\V1\RiskWorkflowController;
use App\Http\Controllers\Api\V1\AfefCategoryController;
use App\Http\Controllers\Api\V1\FinanceYearSettingsController;
use App\Http\Controllers\Api\V1\GeographyController;
use App\Http\Controllers\Api\V1\GrantController;
use App\Http\Controllers\Api\V1\BudgetExecutionController;
use App\Http\Controllers\Api\V1\ItReferenceController;
use App\Http\Controllers\Api\V1\LicenseAlertSettingsController;
use App\Http\Controllers\Api\V1\Tools\InformationSystemController;
use App\Http\Controllers\Api\V1\Tools\ItAssetController;
use App\Http\Controllers\Api\V1\Tools\LicenseController;
use App\Http\Controllers\Api\V1\Tools\ServerController;
use App\Http\Controllers\Api\V1\PortfolioController;
use App\Http\Controllers\Api\V1\ApmSessionBridgeController;
use App\Http\Controllers\Api\V1\AzureSapStorageSettingsController;
use App\Http\Controllers\Api\V1\SapImportController;
use App\Http\Middleware\AuthenticateRiskSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['ok' => true, 'app' => 'risk-register']));
    // Public: login + shell need logo/copyright without a session.
    Route::get('/branding', \App\Http\Controllers\Api\V1\BrandingController::class);
    // APM mints Finance Bearer tokens into Finance Cache (shared bridge secret).
    Route::post('/internal/apm-session', [ApmSessionBridgeController::class, 'store']);

    Route::middleware([AuthenticateRiskSession::class])->group(function () {
        Route::get('/reference-data', [ItReferenceController::class, 'index']);
        Route::get('/reference-data/staff', [ItReferenceController::class, 'staff']);

        Route::get('/tools/licenses/summary', [LicenseController::class, 'summary']);
        Route::get('/tools/licenses/export', [LicenseController::class, 'export']);
        Route::get('/tools/licenses/export-pdf', [LicenseController::class, 'exportPdf']);
        Route::get('/tools/licenses', [LicenseController::class, 'index']);
        Route::post('/tools/licenses', [LicenseController::class, 'store']);
        Route::put('/tools/licenses/{license}', [LicenseController::class, 'update']);
        Route::delete('/tools/licenses/{license}', [LicenseController::class, 'destroy']);

        Route::get('/tools/it-assets/summary', [ItAssetController::class, 'summary']);
        Route::get('/tools/it-assets/export', [ItAssetController::class, 'export']);
        Route::get('/tools/it-assets/export-pdf', [ItAssetController::class, 'exportPdf']);
        Route::get('/tools/it-assets/categories', [ItAssetController::class, 'categories']);
        Route::post('/tools/it-assets/categories', [ItAssetController::class, 'storeCategory']);
        Route::put('/tools/it-assets/categories/{category}', [ItAssetController::class, 'updateCategory']);
        Route::delete('/tools/it-assets/categories/{category}', [ItAssetController::class, 'destroyCategory']);
        Route::get('/tools/it-assets/brands', [ItAssetController::class, 'brands']);
        Route::post('/tools/it-assets/brands', [ItAssetController::class, 'storeBrand']);
        Route::put('/tools/it-assets/brands/{brand}', [ItAssetController::class, 'updateBrand']);
        Route::delete('/tools/it-assets/brands/{brand}', [ItAssetController::class, 'destroyBrand']);
        Route::get('/tools/it-assets', [ItAssetController::class, 'index']);
        Route::post('/tools/it-assets', [ItAssetController::class, 'store']);
        Route::put('/tools/it-assets/{asset}', [ItAssetController::class, 'update']);
        Route::delete('/tools/it-assets/{asset}', [ItAssetController::class, 'destroy']);

        Route::get('/tools/information-systems/languages', [InformationSystemController::class, 'languages']);
        Route::post('/tools/information-systems/languages', [InformationSystemController::class, 'storeLanguage']);
        Route::get('/tools/information-systems/summary', [InformationSystemController::class, 'summary']);
        Route::get('/tools/information-systems/export', [InformationSystemController::class, 'export']);
        Route::get('/tools/information-systems/export-pdf', [InformationSystemController::class, 'exportPdf']);
        Route::get('/tools/information-systems/reports/trends', [InformationSystemController::class, 'trends']);
        Route::get('/tools/information-systems', [InformationSystemController::class, 'index']);
        Route::post('/tools/information-systems', [InformationSystemController::class, 'store']);
        Route::get('/tools/information-systems/{informationSystem}', [InformationSystemController::class, 'show']);
        Route::put('/tools/information-systems/{informationSystem}', [InformationSystemController::class, 'update']);
        Route::delete('/tools/information-systems/{informationSystem}', [InformationSystemController::class, 'destroy']);
        Route::post('/tools/information-systems/{informationSystem}/modules', [InformationSystemController::class, 'storeModule']);
        Route::put('/tools/information-systems/{informationSystem}/modules/{module}', [InformationSystemController::class, 'updateModule']);
        Route::delete('/tools/information-systems/{informationSystem}/modules/{module}', [InformationSystemController::class, 'destroyModule']);

        Route::get('/settings/license-alerts', [LicenseAlertSettingsController::class, 'show']);
        Route::put('/settings/license-alerts', [LicenseAlertSettingsController::class, 'update']);
        Route::post('/settings/license-alerts/send-now', [LicenseAlertSettingsController::class, 'sendNow']);

        Route::get('/tools/servers/summary', [ServerController::class, 'summary']);
        Route::get('/tools/servers', [ServerController::class, 'index']);
        Route::post('/tools/servers', [ServerController::class, 'store']);
        Route::put('/tools/servers/{server}', [ServerController::class, 'update']);
        Route::delete('/tools/servers/{server}', [ServerController::class, 'destroy']);

        Route::get('/me', MeController::class);
        Route::get('/auth/me', MeController::class);
        Route::post('/division-context', [DivisionContextController::class, 'update']);
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
        Route::get('/settings/app-logs', [AppLogsController::class, 'show']);
        Route::put('/settings/app-logs/telemetry', [AppLogsController::class, 'updateTelemetry']);
        Route::post('/settings/app-logs/critical-alert/test', [AppLogsController::class, 'testCriticalAlert']);
        Route::get('/settings/apm-mapping', [FinanceApmMappingController::class, 'show']);
        Route::post('/settings/apm-mapping/fetch', [FinanceApmMappingController::class, 'fetch']);
        Route::put('/settings/apm-mapping', [FinanceApmMappingController::class, 'update']);

        Route::get('/import/status', [RiskImportController::class, 'status']);
        Route::post('/import/preview', [RiskImportController::class, 'preview']);
        Route::post('/import/{batchId}/commit-matched', [RiskImportController::class, 'commitMatched'])->whereNumber('batchId');
        Route::post('/import/{batchId}/apply-mappings', [RiskImportController::class, 'applyMappings'])->whereNumber('batchId');
        Route::post('/import/remap-unmapped', [RiskImportController::class, 'remapUnmappedRisks']);

        Route::post('/sap/import', [SapImportController::class, 'store']);
        Route::get('/sap/imports/latest', [SapImportController::class, 'latest']);
        Route::get('/sap/imports/history', [SapImportController::class, 'history']);
        Route::get('/sap/dump/files', [SapImportController::class, 'dumpList']);
        Route::get('/sap/dump/download', [SapImportController::class, 'dumpDownload']);
        Route::get('/sap/dump/preview', [SapImportController::class, 'dumpPreview']);
        Route::post('/sap/dump/import', [SapImportController::class, 'dumpImport']);
        Route::post('/sap/dump/fetch', [SapImportController::class, 'dumpFetch']);
        Route::get('/sap/fund-centers/{fundCenter}/expenses', [SapImportController::class, 'fundCenterExpenses'])
            ->where('fundCenter', '[A-Za-z0-9._\-]+');
        Route::post('/sap/imports/{id}/retry-sync', [SapImportController::class, 'retrySync'])->whereNumber('id');

        Route::get('/budget-execution', BudgetExecutionController::class);

        Route::get('/portfolio/summary', [PortfolioController::class, 'summary']);
        Route::get('/portfolio/schemas', [PortfolioController::class, 'schemas']);
        Route::get('/portfolio/sections/{section}', [PortfolioController::class, 'section']);
        Route::put('/portfolio/sections/{section}', [PortfolioController::class, 'replaceSection']);

        Route::get('/grants', [GrantController::class, 'index']);
        Route::post('/grants', [GrantController::class, 'store']);
        Route::put('/grants/{id}', [GrantController::class, 'update'])->whereNumber('id');
        Route::delete('/grants/{id}', [GrantController::class, 'destroy'])->whereNumber('id');

        Route::get('/settings/afef-categories', [AfefCategoryController::class, 'show']);
        Route::put('/settings/afef-categories', [AfefCategoryController::class, 'update']);
        Route::get('/settings/financial-year', [FinanceYearSettingsController::class, 'show']);
        Route::put('/settings/financial-year', [FinanceYearSettingsController::class, 'update']);
        Route::get('/settings/azure-sap-storage', [AzureSapStorageSettingsController::class, 'show']);
        Route::put('/settings/azure-sap-storage', [AzureSapStorageSettingsController::class, 'update']);
        Route::post('/settings/azure-sap-storage/test', [AzureSapStorageSettingsController::class, 'test']);
        Route::post('/settings/azure-sap-storage/fetch-now', [AzureSapStorageSettingsController::class, 'fetchNow']);

        Route::get('/settings/geography', [GeographyController::class, 'show']);
        Route::put('/settings/geography/aliases', [GeographyController::class, 'saveAliases']);
        Route::post('/settings/geography/regions', [GeographyController::class, 'storeRegion']);
        Route::put('/settings/geography/regions/{id}', [GeographyController::class, 'updateRegion'])->whereNumber('id');
        Route::post('/settings/geography/countries', [GeographyController::class, 'storeCountry']);
        Route::put('/settings/geography/countries/{id}', [GeographyController::class, 'updateCountry'])->whereNumber('id');
        Route::post('/settings/geography/locations', [GeographyController::class, 'storeLocation']);
        Route::put('/settings/geography/locations/{id}', [GeographyController::class, 'updateLocation'])->whereNumber('id');

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
