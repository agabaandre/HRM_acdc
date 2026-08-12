<?php

use Illuminate\Support\Facades\Route;
use Modules\Performance\Http\Controllers\Api\V1\PerformanceFormApiController;
use Modules\Performance\Http\Controllers\Api\V1\PerformanceHubApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    Route::get('performance/hub', [PerformanceHubApiController::class, 'hub']);
    Route::get('performance/analytics', [PerformanceFormApiController::class, 'analytics']);
    Route::get('performance/analytics/export.csv', [PerformanceFormApiController::class, 'exportCsv']);
    Route::post('performance/entries', [PerformanceFormApiController::class, 'create']);
    Route::get('performance/entries/{entryId}', [PerformanceFormApiController::class, 'show']);
    Route::put('performance/entries/{entryId}', [PerformanceFormApiController::class, 'update']);
    Route::post('performance/entries/{entryId}/submit', [PerformanceFormApiController::class, 'submit']);
    Route::post('performance/entries/{entryId}/approve', [PerformanceFormApiController::class, 'approve']);
    Route::post('performance/entries/{entryId}/return', [PerformanceFormApiController::class, 'returnEntry']);
    Route::post('performance/entries/{entryId}/consent', [PerformanceFormApiController::class, 'consent']);
    Route::get('performance/entries/{entryId}/print.pdf', [PerformanceFormApiController::class, 'printEntry']);
});
