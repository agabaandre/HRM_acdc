<?php

use App\Http\Controllers\CbpAssetController;
use App\Http\Controllers\SsoAcceptController;
use Illuminate\Support\Facades\Route;

Route::post('/sso/accept', SsoAcceptController::class)
    ->middleware('throttle:30,1')
    ->name('sso.accept');

Route::get('/sso/accept', function () {
    $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

    return redirect()->away($base.'/?risk_error=sso&risk_error_reason=post_required');
})->name('sso.accept.get');

/*
|--------------------------------------------------------------------------
| API landing — prefer SPA shell; Share docs remain under /share/docs.
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect('/up'))->name('api.home');

/*
|--------------------------------------------------------------------------
| Shared CBP static assets (parent ../assets) — no auth, must run before modules.
|--------------------------------------------------------------------------
*/
Route::get('/cbp-assets/{path}', [CbpAssetController::class, 'serve'])
    ->where('path', '.*')
    ->name('cbp.assets');
