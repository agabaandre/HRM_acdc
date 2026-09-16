<?php

use App\Http\Controllers\CbpAssetController;
use App\Http\Controllers\SsoAcceptController;
use Illuminate\Support\Facades\Route;

Route::post('/sso/accept', SsoAcceptController::class)
    ->middleware('throttle:30,1')
    ->name('sso.accept');

Route::get('/sso/accept', function () {
    $spaPath = trim((string) env('RISK_REGISTER_SPA_PATH', 'staff/risk-register'), '/');
    $host = request()->getHost();
    $scheme = request()->getScheme() ?: 'http';
    $url = $scheme.'://'.$host.'/'.$spaPath.'/access-error?reason=post_required';

    return redirect()->away($url);
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
