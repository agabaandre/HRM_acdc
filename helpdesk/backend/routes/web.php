<?php

use App\Http\Controllers\SsoAcceptController;
use Illuminate\Support\Facades\Route;

Route::post('/sso/accept', SsoAcceptController::class)
    ->middleware('throttle:30,1')
    ->name('sso.accept');

Route::get('/sso/accept', function () {
    $host = request()->getHost();
    $scheme = request()->getScheme();
    if ($host !== '' && (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))) {
        return redirect($scheme.'://'.$host.'/staff/home/index?helpdesk_error=sso&helpdesk_error_reason=invalid');
    }
    $base = rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/');

    return redirect()->away($base.'/home/index?helpdesk_error=sso&helpdesk_error_reason=invalid');
})->name('sso.accept.get');

Route::get('/', function () {
    return view('welcome');
});
