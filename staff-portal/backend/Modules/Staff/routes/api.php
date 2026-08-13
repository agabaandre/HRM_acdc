<?php

use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\Api\V1\NextOfKinApiController;
use Modules\Staff\Http\Controllers\Api\V1\SignatureManagerApiController;
use Modules\Staff\Http\Controllers\Api\V1\StaffApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('staff', [StaffApiController::class, 'index']);
    Route::get('staff/filter-options', [StaffApiController::class, 'filterOptions']);
    Route::get('staff/form-lookups', [StaffApiController::class, 'formLookups']);
    Route::post('staff', [StaffApiController::class, 'store']);
    Route::post('staff/{staff}/contracts', [StaffApiController::class, 'storeContract'])->whereNumber('staff');
    Route::put('staff/{staff}/contracts/{contract}', [StaffApiController::class, 'updateContract'])
        ->whereNumber('staff')
        ->whereNumber('contract');
    // POST alias so multipart PDF uploads work (PHP does not parse files on PUT).
    Route::post('staff/{staff}/contracts/{contract}', [StaffApiController::class, 'updateContract'])
        ->whereNumber('staff')
        ->whereNumber('contract');
    // Avoid .csv/.pdf extensions — Apache may treat them as static files (404).
    Route::get('staff/export/csv', [StaffApiController::class, 'exportCsv']);
    Route::get('staff/export/pdf', [StaffApiController::class, 'exportPdf']);
    Route::get('staff/export.csv', [StaffApiController::class, 'exportCsv']);
    Route::get('staff/export.pdf', [StaffApiController::class, 'exportPdf']);
    Route::get('staff/birthdays', [StaffApiController::class, 'birthdays']);
    Route::get('staff/data-quality', [StaffApiController::class, 'dataQuality']);

    Route::get('staff/signatures', [SignatureManagerApiController::class, 'index']);
    Route::post('staff/signatures/refresh-approvers', [SignatureManagerApiController::class, 'refreshApprovers']);
    Route::post('staff/signatures/bulk', [SignatureManagerApiController::class, 'bulkSave']);
    Route::post('staff/signatures/upload', [SignatureManagerApiController::class, 'upload']);
    Route::get('staff/signatures/export/csv', [SignatureManagerApiController::class, 'exportCsv']);
    Route::get('staff/signatures/export/pdf', [SignatureManagerApiController::class, 'exportPdf']);

    Route::get('staff/next-of-kin', [NextOfKinApiController::class, 'index']);
    Route::get('staff/next-of-kin/export/csv', [NextOfKinApiController::class, 'exportCsv']);
    Route::get('staff/next-of-kin/export/pdf', [NextOfKinApiController::class, 'exportPdf']);

    Route::get('staff/{staff}', [StaffApiController::class, 'show'])->whereNumber('staff');
    Route::get('staff/{staff}/audit-trail', [StaffApiController::class, 'auditTrail'])->whereNumber('staff');
    Route::put('staff/{staff}', [StaffApiController::class, 'updateBiodata'])->whereNumber('staff');
});
