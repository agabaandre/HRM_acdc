<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\PortalApiController;

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('cbp-modules', [PortalApiController::class, 'cbpModules']);
});
