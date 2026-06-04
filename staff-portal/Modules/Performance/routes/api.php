<?php

use Illuminate\Support\Facades\Route;
use Modules\Performance\Http\Controllers\PerformanceController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('performances', PerformanceController::class)->names('performance');
});
