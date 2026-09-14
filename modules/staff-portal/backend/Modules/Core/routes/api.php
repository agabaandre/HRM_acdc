<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\PortalApiController;
use Modules\Core\Http\Controllers\SsoLaunchController;

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('cbp-modules', [PortalApiController::class, 'cbpModules']);
    Route::post('cbp-modules/launch', [SsoLaunchController::class, 'apiLaunch']);
    Route::get('auth/refresh-sso', [SsoLaunchController::class, 'apiRefreshSso']);
});
