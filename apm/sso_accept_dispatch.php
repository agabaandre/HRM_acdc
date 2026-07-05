<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

require __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/bootstrap/subdirectory.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Request::capture();

$handler = static function (Request $req) use ($app) {
    return $app->make(AuthController::class)->ssoAccept($req);
};

$handler = static function (Request $req) use ($app, $handler) {
    return $app->make(Illuminate\Session\Middleware\StartSession::class)->handle($req, $handler);
};

$handler = static function (Request $req) use ($app, $handler) {
    return $app->make(Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class)
        ->handle($req, $handler);
};

$response = $app->make(Illuminate\Cookie\Middleware\EncryptCookies::class)
    ->handle($request, $handler);

$response->send();
$app->make(Illuminate\Contracts\Http\Kernel::class)->terminate($request, $response);
