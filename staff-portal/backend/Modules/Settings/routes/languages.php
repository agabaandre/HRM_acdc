<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Api\V1\PortalLanguagesController;

/*
| Staff Portal locale catalog + Settings → Languages admin.
| Loaded from api.php and also registered after a stale route cache (see RouteServiceProvider).
*/
Route::get('languages', [PortalLanguagesController::class, 'catalog']);
Route::post('locale', [PortalLanguagesController::class, 'apply']);
Route::get('settings/languages/translations', [PortalLanguagesController::class, 'translations']);
Route::put('settings/languages/translations', [PortalLanguagesController::class, 'saveTranslations']);
Route::post('settings/languages/translations/ai', [PortalLanguagesController::class, 'fillWithAi']);
Route::get('settings/languages', [PortalLanguagesController::class, 'index']);
Route::post('settings/languages', [PortalLanguagesController::class, 'store']);
Route::put('settings/languages/{id}', [PortalLanguagesController::class, 'update'])->whereNumber('id');
Route::delete('settings/languages/{id}', [PortalLanguagesController::class, 'destroy'])->whereNumber('id');
