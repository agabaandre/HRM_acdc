<?php

use Illuminate\Support\Facades\Route;
use Modules\Workplan\Http\Controllers\WorkplanController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('workplans', WorkplanController::class)->names('workplan');
});
