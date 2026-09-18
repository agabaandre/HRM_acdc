<?php

use Illuminate\Support\Facades\Route;
use Modules\AdManager\Http\Controllers\Api\V1\AdManagerApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('admanager/hub', [AdManagerApiController::class, 'hub']);
    Route::get('admanager/expired', [AdManagerApiController::class, 'expired']);
    Route::get('admanager/disabled', [AdManagerApiController::class, 'disabled']);
    Route::post('admanager/{staff}/disable', [AdManagerApiController::class, 'markDisabled'])
        ->whereNumber('staff');
    Route::post('admanager/{staff}/enable', [AdManagerApiController::class, 'markEnabled'])
        ->whereNumber('staff');
});
