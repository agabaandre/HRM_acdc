<?php

use App\Http\Controllers\CbpAssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shared CBP static assets (parent ../assets) — no auth, must run before modules.
|--------------------------------------------------------------------------
*/
Route::get('/cbp-assets/{path}', [CbpAssetController::class, 'serve'])
    ->where('path', '.*')
    ->name('cbp.assets');
