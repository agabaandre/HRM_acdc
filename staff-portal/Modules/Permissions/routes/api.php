<?php

use Illuminate\Support\Facades\Route;
use Modules\Permissions\Http\Controllers\PermissionsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('permissions', PermissionsController::class)->names('permissions');
});
