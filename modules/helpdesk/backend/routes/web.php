<?php

use App\Http\Controllers\SsoAcceptController;
use Illuminate\Support\Facades\Route;

Route::post('/sso/accept', SsoAcceptController::class)
    ->middleware('throttle:30,1')
    ->name('sso.accept');

Route::get('/sso/accept', function () {
    $configured = rtrim((string) env('BASE_URL', env('HELPDESK_STAFF_PORTAL_URL', '')), '/');
    $path = parse_url($configured, PHP_URL_PATH);
    $home = is_string($path) && $path !== '' && $path !== '/' ? rtrim($path, '/') : '';

    return redirect($home.'/?helpdesk_error=sso&helpdesk_error_reason=invalid');
})->name('sso.accept.get');

Route::get('/', function () {
    return view('welcome');
});
