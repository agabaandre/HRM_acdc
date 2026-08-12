<?php

use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\Api\V1\StaffApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('staff', [StaffApiController::class, 'index']);
    Route::get('staff/form-lookups', [StaffApiController::class, 'formLookups']);
    Route::post('staff', [StaffApiController::class, 'store']);
    Route::post('staff/{staff}/contracts', [StaffApiController::class, 'storeContract'])->whereNumber('staff');
    Route::put('staff/{staff}/contracts/{contract}', [StaffApiController::class, 'updateContract'])
        ->whereNumber('staff')
        ->whereNumber('contract');
    Route::get('staff/export.csv', [StaffApiController::class, 'exportCsv']);
    Route::get('staff/birthdays', [StaffApiController::class, 'birthdays']);
    Route::get('staff/data-quality', [StaffApiController::class, 'dataQuality']);
    Route::get('staff/{staff}', [StaffApiController::class, 'show'])->whereNumber('staff');
});
