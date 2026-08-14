<?php

use App\Http\Controllers\CbpAssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API landing (footer entry) — Swagger UI for the Share API.
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect('/share/docs'))->name('api.home');

/*
|--------------------------------------------------------------------------
| Shared CBP static assets (parent ../assets) — no auth, must run before modules.
|--------------------------------------------------------------------------
*/
Route::get('/cbp-assets/{path}', [CbpAssetController::class, 'serve'])
    ->where('path', '.*')
    ->name('cbp.assets');
