<?php

use Illuminate\Support\Facades\Route;
use Modules\Workflows\Http\Controllers\WorkflowsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('workflows', WorkflowsController::class)->names('workflows');
});
