<?php

use App\Http\Controllers\Api\V1\CbpModulesController;
use App\Http\Controllers\Api\V1\CbpModulesLaunchController;
use App\Http\Controllers\Api\V1\DivisionContextController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Registry\RegistryController;
use App\Http\Middleware\AuthenticateRiskSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['ok' => true, 'app' => 'registry']));
    Route::get('/branding', \App\Http\Controllers\Api\V1\BrandingController::class);

    Route::middleware([AuthenticateRiskSession::class])->group(function () {
        Route::get('/me', MeController::class);
        Route::get('/auth/me', MeController::class);
        Route::post('/division-context', [DivisionContextController::class, 'update']);
        Route::get('/cbp-modules', CbpModulesController::class);
        Route::post('/cbp-modules/launch', CbpModulesLaunchController::class);
        Route::get('/languages', [\App\Http\Controllers\Api\V1\RiskLocaleController::class, 'catalog']);
        Route::post('/locale', [\App\Http\Controllers\Api\V1\RiskLocaleController::class, 'apply']);

        Route::get('/registry/years', [RegistryController::class, 'years']);
        Route::get('/registry/dashboard', [RegistryController::class, 'dashboard']);
        Route::get('/registry/documents', [RegistryController::class, 'documents']);
        Route::get('/registry/documents/export', [RegistryController::class, 'export']);
        Route::post('/registry/export-table', [RegistryController::class, 'exportTable']);
        Route::post('/registry/documents', [RegistryController::class, 'store']);
        Route::put('/registry/documents/{id}', [RegistryController::class, 'update'])->whereNumber('id');
        Route::get('/registry/settings', [RegistryController::class, 'settings']);
        Route::post('/registry/settings/{table}', [RegistryController::class, 'storeLookup']);
        Route::put('/registry/settings/{table}/{id}', [RegistryController::class, 'updateLookup'])->whereNumber('id');
        Route::delete('/registry/settings/{table}/{id}', [RegistryController::class, 'destroyLookup'])->whereNumber('id');
    });
});
