<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\Http\Controllers\Api\V1\LeaveAdminBalanceController;
use Modules\Leave\Http\Controllers\Api\V1\LeaveApprovalController;
use Modules\Leave\Http\Controllers\Api\V1\LeaveBalanceController;
use Modules\Leave\Http\Controllers\Api\V1\LeaveHolidaySettingsController;
use Modules\Leave\Http\Controllers\Api\V1\LeaveMetaController;
use Modules\Leave\Http\Controllers\Api\V1\LeavePlanController;
use Modules\Leave\Http\Controllers\Api\V1\LeaveRequestController;
use Modules\Leave\Http\Controllers\Api\V1\LeaveSettingsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('leave/balances', LeaveBalanceController::class);
    Route::get('leave/requests', [LeaveRequestController::class, 'index']);
    Route::post('leave/requests', [LeaveRequestController::class, 'store']);
    Route::get('leave/approvals', [LeaveApprovalController::class, 'index']);
    Route::post('leave/requests/{id}/decide', [LeaveApprovalController::class, 'decide'])
        ->whereNumber('id');
    Route::get('leave/types', [LeaveMetaController::class, 'types']);
    Route::get('leave/apply-rules', [LeaveMetaController::class, 'applyRules']);
    Route::post('leave/working-days', [LeaveMetaController::class, 'workingDays']);
    Route::get('leave/balance-for-type', [LeaveMetaController::class, 'balanceForType']);
    Route::get('leave/supporting-officers', [LeaveMetaController::class, 'supportingOfficers']);

    Route::get('leave/plans', [LeavePlanController::class, 'show']);
    Route::put('leave/plans/{id}', [LeavePlanController::class, 'update'])->whereNumber('id');
    Route::post('leave/plans/{id}/submit', [LeavePlanController::class, 'submit'])->whereNumber('id');

    Route::get('leave/admin/balances', [LeaveAdminBalanceController::class, 'directory']);
    Route::post('leave/admin/balances/bulk-fill', [LeaveAdminBalanceController::class, 'bulkFill']);
    Route::get('leave/admin/balances/{staffId}', [LeaveAdminBalanceController::class, 'show'])
        ->whereNumber('staffId');
    Route::put('leave/admin/balances/{staffId}', [LeaveAdminBalanceController::class, 'update'])
        ->whereNumber('staffId');

    Route::get('leave/settings/policy', [LeaveSettingsController::class, 'showPolicy']);
    Route::put('leave/settings/policy', [LeaveSettingsController::class, 'updatePolicy']);
    Route::get('leave/settings/approval-workflow', [LeaveSettingsController::class, 'showApprovalWorkflow']);
    Route::put('leave/settings/approval-workflow', [LeaveSettingsController::class, 'updateApprovalWorkflow']);
    Route::get('leave/settings/types', [LeaveSettingsController::class, 'types']);
    Route::post('leave/settings/types', [LeaveSettingsController::class, 'storeType']);
    Route::put('leave/settings/types/{leaveId}', [LeaveSettingsController::class, 'updateType'])
        ->whereNumber('leaveId');

    Route::get('leave/settings/holidays', [LeaveHolidaySettingsController::class, 'index']);
    Route::post('leave/settings/holidays', [LeaveHolidaySettingsController::class, 'store']);
    Route::put('leave/settings/holidays/{id}', [LeaveHolidaySettingsController::class, 'update'])
        ->whereNumber('id');
    Route::delete('leave/settings/holidays/{id}', [LeaveHolidaySettingsController::class, 'destroy'])
        ->whereNumber('id');
    Route::get('leave/settings/holidays/preview', [LeaveHolidaySettingsController::class, 'preview']);
    Route::get('leave/settings/holidays/openholidays/countries', [LeaveHolidaySettingsController::class, 'openHolidaysCountries']);
    Route::get('leave/settings/holidays/openholidays/preview', [LeaveHolidaySettingsController::class, 'openHolidaysPreview']);
    Route::post('leave/settings/holidays/openholidays/import', [LeaveHolidaySettingsController::class, 'openHolidaysImport']);
    Route::get('leave/settings/holidays/independence', [LeaveHolidaySettingsController::class, 'independenceIndex']);
    Route::put('leave/settings/holidays/independence', [LeaveHolidaySettingsController::class, 'independenceUpdate']);
    Route::get('leave/settings/holidays/duty-stations', [LeaveHolidaySettingsController::class, 'dutyStationsIndex']);
    Route::put('leave/settings/holidays/duty-stations', [LeaveHolidaySettingsController::class, 'dutyStationsUpdate']);
});
