<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\SsoController;

Route::prefix('v1')->group(function (): void {
    Route::post('sso/validate', [SsoController::class, 'validateSsoToken']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::get('session', [SsoController::class, 'session']);
    Route::post('token/issue', [SsoController::class, 'issueToken']);
});
