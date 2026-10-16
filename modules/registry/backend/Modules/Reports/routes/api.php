<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\Api\V1\ReportsApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    Route::get('reports/hub', [ReportsApiController::class, 'hub']);
});
