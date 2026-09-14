<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Api\V1\PortalAiProvidersController;

/*
| Settings → AI providers (OpenAI-compatible chat models).
| Also registered after a stale route cache (see RouteServiceProvider).
*/
Route::get('settings/ai-providers/drivers', [PortalAiProvidersController::class, 'drivers']);
Route::post('settings/ai-providers/test', [PortalAiProvidersController::class, 'test']);
Route::get('settings/ai-providers', [PortalAiProvidersController::class, 'index']);
Route::post('settings/ai-providers', [PortalAiProvidersController::class, 'store']);
Route::put('settings/ai-providers/{uuid}', [PortalAiProvidersController::class, 'update']);
Route::delete('settings/ai-providers/{uuid}', [PortalAiProvidersController::class, 'destroy']);
Route::post('settings/ai-providers/{uuid}/default', [PortalAiProvidersController::class, 'setDefault']);
Route::post('settings/ai-providers/{uuid}/test', [PortalAiProvidersController::class, 'test']);
