<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\Http\Controllers\LeaveController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('leaves', LeaveController::class)->names('leave');
});
