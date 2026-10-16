<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\PortalApiController;
use Modules\Core\Http\Controllers\SsoLaunchController;

// Risk Register SPA uses AuthenticateRiskSession routes in app/routes/api.php
// for cbp-modules (+ launch). Sanctum duplicates here would 401 SSO Bearer tokens.
Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('auth/refresh-sso', [SsoLaunchController::class, 'apiRefreshSso']);
});
