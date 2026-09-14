<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollDashboardController;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollLoanController;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollPayslipController;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollPeriodController;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollRunController;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollSettingsController;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollStaffPayController;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollTaxRuleController;
use Modules\Payroll\Http\Controllers\Api\V1\PayrollWageTypeController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('payroll/dashboard', PayrollDashboardController::class);

    Route::get('payroll/settings', [PayrollSettingsController::class, 'show']);
    Route::put('payroll/settings', [PayrollSettingsController::class, 'update']);

    Route::get('payroll/wage-types', [PayrollWageTypeController::class, 'index']);
    Route::post('payroll/wage-types', [PayrollWageTypeController::class, 'store']);
    Route::put('payroll/wage-types/{id}', [PayrollWageTypeController::class, 'update'])->whereNumber('id');
    Route::delete('payroll/wage-types/{id}', [PayrollWageTypeController::class, 'destroy'])->whereNumber('id');

    Route::get('payroll/tax-rules', [PayrollTaxRuleController::class, 'index']);
    Route::post('payroll/tax-rules', [PayrollTaxRuleController::class, 'store']);
    Route::put('payroll/tax-rules/{id}', [PayrollTaxRuleController::class, 'update'])->whereNumber('id');

    Route::get('payroll/staff-pay', [PayrollStaffPayController::class, 'directory']);
    Route::get('payroll/staff/{staffId}/pay', [PayrollStaffPayController::class, 'show'])->whereNumber('staffId');
    Route::put('payroll/staff/{staffId}/pay', [PayrollStaffPayController::class, 'upsert'])->whereNumber('staffId');
    Route::post('payroll/staff/{staffId}/wage-items', [PayrollStaffPayController::class, 'storeWageItem'])->whereNumber('staffId');
    Route::put('payroll/staff/{staffId}/wage-items/{id}', [PayrollStaffPayController::class, 'updateWageItem'])->whereNumber('staffId')->whereNumber('id');
    Route::delete('payroll/staff/{staffId}/wage-items/{id}', [PayrollStaffPayController::class, 'destroyWageItem'])->whereNumber('staffId')->whereNumber('id');

    Route::get('payroll/periods', [PayrollPeriodController::class, 'index']);
    Route::post('payroll/periods', [PayrollPeriodController::class, 'store']);
    Route::put('payroll/periods/{id}/close', [PayrollPeriodController::class, 'close'])->whereNumber('id');
    Route::put('payroll/periods/{id}/fx', [PayrollPeriodController::class, 'upsertFx'])->whereNumber('id');

    Route::get('payroll/runs', [PayrollRunController::class, 'index']);
    Route::post('payroll/runs', [PayrollRunController::class, 'store']);
    Route::get('payroll/runs/{id}', [PayrollRunController::class, 'show'])->whereNumber('id');
    Route::get('payroll/runs/{id}/lines', [PayrollRunController::class, 'lines'])->whereNumber('id');
    Route::post('payroll/runs/{id}/simulate', [PayrollRunController::class, 'simulate'])->whereNumber('id');
    Route::post('payroll/runs/{id}/post', [PayrollRunController::class, 'post'])->whereNumber('id');

    Route::get('payroll/payslips', [PayrollPayslipController::class, 'index']);
    Route::get('payroll/payslips/{id}/pdf', [PayrollPayslipController::class, 'pdf'])->whereNumber('id');

    Route::get('payroll/loans', [PayrollLoanController::class, 'index']);
    Route::post('payroll/loans', [PayrollLoanController::class, 'store']);
    Route::post('payroll/loans/{id}/decide', [PayrollLoanController::class, 'decide'])->whereNumber('id');
    Route::post('payroll/loans/{id}/disburse', [PayrollLoanController::class, 'disburse'])->whereNumber('id');
    Route::post('payroll/loans/{id}/schedules/{scheduleId}/waive', [PayrollLoanController::class, 'waive'])
        ->whereNumber('id')
        ->whereNumber('scheduleId');
});
