<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\PortalSpaAuthController;
use Modules\Auth\Http\Controllers\Api\SsoController;
use Modules\Auth\Http\Controllers\Api\V1\AuthAdminApiController;

Route::prefix('v1')->group(function (): void {
    Route::post('sso/validate', [SsoController::class, 'validateSsoToken']);
    Route::post('auth/login', [PortalSpaAuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('session', [SsoController::class, 'session']);
    Route::post('token/issue', [SsoController::class, 'issueToken']);
    Route::get('me', [PortalSpaAuthController::class, 'me']);
    Route::post('auth/bootstrap', [PortalSpaAuthController::class, 'bootstrapFromSession']);
    Route::get('auth/users', [AuthAdminApiController::class, 'users']);
    Route::get('auth/audit-logs', [AuthAdminApiController::class, 'auditLogs']);
    Route::post('auth/audit-logs/{id}/revert', [AuthAdminApiController::class, 'revertAuditLog']);
});

Route::middleware('auth:api')->prefix('v1')->group(function (): void {
    Route::get('oauth/user', [PortalSpaAuthController::class, 'me']);
});
