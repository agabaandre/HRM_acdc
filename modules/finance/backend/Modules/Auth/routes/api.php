<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\PortalSpaAuthController;
use Modules\Auth\Http\Controllers\Api\SsoController;
use Modules\Auth\Http\Controllers\Api\V1\AuthAdminApiController;
use Modules\Auth\Http\Controllers\Api\V1\OAuthClientApiController;
use Modules\Auth\Http\Controllers\Api\V1\SelfServiceProfileController;

Route::prefix('v1')->group(function (): void {
    Route::post('sso/validate', [SsoController::class, 'validateSsoToken']);
    Route::get('auth/login-options', [PortalSpaAuthController::class, 'loginOptions']);
    Route::post('auth/login', [PortalSpaAuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('session', [SsoController::class, 'session']);
    Route::post('token/issue', [SsoController::class, 'issueToken']);
    Route::get('me', [PortalSpaAuthController::class, 'me']);
    Route::get('me/profile', [SelfServiceProfileController::class, 'show']);
    Route::put('me/profile', [SelfServiceProfileController::class, 'update']);
    Route::post('me/profile/photo', [SelfServiceProfileController::class, 'uploadPhoto']);
    Route::post('me/profile/passport', [SelfServiceProfileController::class, 'uploadPassport']);
    Route::post('me/profile/signature', [SelfServiceProfileController::class, 'uploadSignature']);
    Route::put('me/password', [SelfServiceProfileController::class, 'changePassword']);
    Route::post('auth/bootstrap', [PortalSpaAuthController::class, 'bootstrapFromSession']);
    Route::get('auth/users', [AuthAdminApiController::class, 'users']);
    Route::get('auth/user-groups', [AuthAdminApiController::class, 'userGroups']);
    Route::put('auth/users/{id}', [AuthAdminApiController::class, 'updateUser'])->whereNumber('id');
    Route::post('auth/users/{id}/block', [AuthAdminApiController::class, 'blockUser'])->whereNumber('id');
    Route::post('auth/users/{id}/unblock', [AuthAdminApiController::class, 'unblockUser'])->whereNumber('id');
    Route::post('auth/users/{id}/reset-password', [AuthAdminApiController::class, 'resetPassword'])->whereNumber('id');
    Route::post('auth/users/{id}/allow-email-login', [AuthAdminApiController::class, 'setAllowEmailLogin'])->whereNumber('id');
    Route::post('auth/users/{id}/impersonate', [AuthAdminApiController::class, 'impersonate'])->whereNumber('id');
    Route::post('auth/impersonation/revert', [AuthAdminApiController::class, 'revertImpersonation']);
    Route::post('auth/users/bulk-create', [AuthAdminApiController::class, 'bulkCreateUsers']);
    Route::get('auth/audit-logs', [AuthAdminApiController::class, 'auditLogs']);
    Route::post('auth/audit-logs/{id}/revert', [AuthAdminApiController::class, 'revertAuditLog']);
    Route::get('auth/oauth-clients', [OAuthClientApiController::class, 'index']);
    Route::post('auth/oauth-clients', [OAuthClientApiController::class, 'store']);
    Route::put('auth/oauth-clients/{id}', [OAuthClientApiController::class, 'update']);
    Route::delete('auth/oauth-clients/{id}', [OAuthClientApiController::class, 'destroy']);
});

Route::middleware('auth:api')->prefix('v1')->group(function (): void {
    Route::get('oauth/user', [PortalSpaAuthController::class, 'me']);
});
