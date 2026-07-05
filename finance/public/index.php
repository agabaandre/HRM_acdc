<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

$staffHelper = dirname(__DIR__, 2).'/application/helpers/sso_launch_helper.php';
if (is_file($staffHelper)) {
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($staffHelper, true);
    }
    require_once $staffHelper;
}

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

require_once __DIR__.'/../bootstrap/subdirectory.php';

$request = Request::capture();
if ($request->isMethod('POST') && trim($request->path(), '/') === 'sso/accept') {
    require __DIR__.'/../sso_accept_dispatch.php';
    exit;
}

$app->handleRequest($request);
