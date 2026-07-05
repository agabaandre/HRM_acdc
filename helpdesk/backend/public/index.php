<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$staffHelper = dirname(__DIR__, 2).'/application/helpers/sso_launch_helper.php';
if (is_file($staffHelper)) {
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($staffHelper, true);
    }
    require_once $staffHelper;
}

require_once __DIR__.'/../bootstrap/subdirectory.php';

$request = Request::capture();
if ($request->isMethod('POST') && trim($request->path(), '/') === 'sso/accept') {
    require __DIR__.'/../sso_accept_dispatch.php';
    exit;
}

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest($request);
