<?php

/**
 * PHP built-in server router (php artisan serve).
 * Serves files from /public before bootstrapping Laravel.
 */
$publicPath = __DIR__.'/public';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

if ($uri !== '/' && $uri !== '') {
    $file = $publicPath.$uri;
    if (is_file($file)) {
        return false;
    }
}

require_once $publicPath.'/index.php';
