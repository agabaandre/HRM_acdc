<?php

use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\ContractsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('contracts', ContractsController::class)->names('contracts');
});
