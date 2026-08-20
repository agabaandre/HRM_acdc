<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Api\V1\CbpModulesSettingsController;
use Modules\Settings\Http\Controllers\Api\V1\EmailProvidersController;
use Modules\Settings\Http\Controllers\Api\V1\OrgStructureController;
use Modules\Settings\Http\Controllers\Api\V1\OrgUnitsSettingsController;
use Modules\Settings\Http\Controllers\Api\V1\SharedStorageController;
use Modules\Settings\Http\Controllers\Api\V1\SettingsApiController;
use Modules\Settings\Http\Controllers\Api\V1\StaffJobsSettingsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('settings/hub', [SettingsApiController::class, 'hub']);
    Route::get('settings/staff-jobs', [StaffJobsSettingsController::class, 'show']);
    Route::put('settings/staff-jobs', [StaffJobsSettingsController::class, 'update']);
    Route::post('settings/staff-jobs/run', [StaffJobsSettingsController::class, 'run']);
    Route::get('settings/shared-storage', [SharedStorageController::class, 'show']);
    Route::post('settings/shared-storage/migrate', [SharedStorageController::class, 'migrate']);
    Route::post('settings/shared-storage/enable-host', [SharedStorageController::class, 'enableHost']);
    Route::post('settings/shared-storage/purge-ci', [SharedStorageController::class, 'purgeCi']);
    Route::get('settings/portal-modules', [SettingsApiController::class, 'showPortalModules']);
    Route::put('settings/portal-modules', [SettingsApiController::class, 'updatePortalModules']);
    Route::get('settings/email-servers/drivers', [EmailProvidersController::class, 'drivers']);
    Route::get('settings/email-servers', [EmailProvidersController::class, 'index']);
    Route::post('settings/email-servers', [EmailProvidersController::class, 'store']);
    Route::put('settings/email-servers/{uuid}', [EmailProvidersController::class, 'update']);
    Route::delete('settings/email-servers/{uuid}', [EmailProvidersController::class, 'destroy']);
    Route::post('settings/email-servers/{uuid}/default', [EmailProvidersController::class, 'setDefault']);
    Route::post('settings/email-servers/{uuid}/test', [EmailProvidersController::class, 'test']);
    Route::get('settings/org-structure', [OrgStructureController::class, 'show']);
    Route::post('settings/org-structure/generate', [OrgStructureController::class, 'generate']);
    Route::put('settings/org-structure/nodes/{id}', [OrgStructureController::class, 'updateNode'])
        ->whereNumber('id');
    Route::get('settings/staff-options', [OrgUnitsSettingsController::class, 'staffOptions']);
    Route::get('settings/divisions', [OrgUnitsSettingsController::class, 'divisionsIndex']);
    Route::post('settings/divisions', [OrgUnitsSettingsController::class, 'divisionsStore']);
    Route::put('settings/divisions/{id}', [OrgUnitsSettingsController::class, 'divisionsUpdate'])->whereNumber('id');
    Route::delete('settings/divisions/{id}', [OrgUnitsSettingsController::class, 'divisionsDestroy'])->whereNumber('id');
    Route::get('settings/directorates', [OrgUnitsSettingsController::class, 'directoratesIndex']);
    Route::post('settings/directorates', [OrgUnitsSettingsController::class, 'directoratesStore']);
    Route::put('settings/directorates/{id}', [OrgUnitsSettingsController::class, 'directoratesUpdate'])->whereNumber('id');
    Route::delete('settings/directorates/{id}', [OrgUnitsSettingsController::class, 'directoratesDestroy'])->whereNumber('id');
    Route::get('settings/cbp-modules', [CbpModulesSettingsController::class, 'index']);
    Route::post('settings/cbp-modules', [CbpModulesSettingsController::class, 'store']);
    Route::put('settings/cbp-modules/{id}', [CbpModulesSettingsController::class, 'update'])->whereNumber('id');
    Route::get('settings/lookup-tables', [SettingsApiController::class, 'lookupCatalog']);
    Route::get('settings/lookups/{table}', [SettingsApiController::class, 'lookupIndex'])
        ->where('table', '[a-z_]+');
    Route::post('settings/lookups/{table}', [SettingsApiController::class, 'lookupStore'])
        ->where('table', '[a-z_]+');
    Route::put('settings/lookups/{table}/{id}', [SettingsApiController::class, 'lookupUpdate'])
        ->where('table', '[a-z_]+');
    Route::delete('settings/lookups/{table}/{id}', [SettingsApiController::class, 'lookupDestroy'])
        ->where('table', '[a-z_]+');
    Route::get('settings/performance', [SettingsApiController::class, 'showPerformance']);
    Route::put('settings/performance', [SettingsApiController::class, 'updatePerformance']);
    Route::get('settings/performance/entries/{entryId}/workflow-correction', [SettingsApiController::class, 'previewPerformanceWorkflowCorrection']);
    Route::post('settings/performance/entries/{entryId}/workflow-correction', [SettingsApiController::class, 'applyPerformanceWorkflowCorrection']);
});
