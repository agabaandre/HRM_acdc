<?php

use Illuminate\Support\Facades\Route;
use Modules\Tasks\Http\Controllers\Api\V1\TasksApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('tasks/hub', [TasksApiController::class, 'hub']);
    Route::get('tasks/weekly', [TasksApiController::class, 'weekly']);
    Route::post('tasks/weekly', [TasksApiController::class, 'storeWeekly']);
    Route::put('tasks/weekly/{id}', [TasksApiController::class, 'updateWeekly'])->whereNumber('id');
});
