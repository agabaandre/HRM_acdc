<?php

use Illuminate\Support\Facades\Route;
use Modules\Workplan\Http\Controllers\Api\V1\WorkplanApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('workplans', [WorkplanApiController::class, 'index']);
    Route::post('workplans/sync-pra', [WorkplanApiController::class, 'sync']);
    Route::get('workplans/{id}', [WorkplanApiController::class, 'show'])->whereNumber('id');
});
