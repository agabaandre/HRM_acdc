<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\Api\V1\DashboardApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    Route::get('dashboard', DashboardApiController::class);
    Route::get('dashboard/filter-jobs', [DashboardApiController::class, 'filterJobs']);
    Route::get('dashboard/export/pdf', [DashboardApiController::class, 'exportPdf']);
    Route::get('dashboard/export/csv', [DashboardApiController::class, 'exportCsv']);
    Route::get('dashboard/export.pdf', [DashboardApiController::class, 'exportPdf']);
    Route::get('dashboard/export.csv', [DashboardApiController::class, 'exportCsv']);
});
