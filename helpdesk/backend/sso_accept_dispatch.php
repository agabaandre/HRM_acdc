<?php

use App\Http\Controllers\SsoAcceptController;
use Illuminate\Http\Request;

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

require __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/bootstrap/subdirectory.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Request::capture();
$response = $app->make(SsoAcceptController::class)($request);
$response->send();
