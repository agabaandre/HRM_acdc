<?php

/**
 * Laravel front controller for Apache / PHP built-in server.
 * Mirrors helpdesk/backend/server.php so /public/ never appears in URLs.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
if ($appUrl === '' && is_readable(__DIR__.'/.env')) {
    $lines = file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), 'APP_URL=')) {
            $appUrl = trim(substr($line, strlen('APP_URL=')), " \t\"'");
            break;
        }
    }
}
$basePath = parse_url((string) $appUrl, PHP_URL_PATH);
if (is_string($basePath) && $basePath !== '' && $basePath !== '/') {
    $basePath = rtrim($basePath, '/');
    if (str_starts_with($uri, $basePath)) {
        $uri = substr($uri, strlen($basePath)) ?: '/';
    }
}

foreach (['/staff/staff-portal/backend', '/staff-portal/backend'] as $mount) {
    if (str_starts_with($uri, $mount)) {
        $uri = substr($uri, strlen($mount)) ?: '/';
        break;
    }
}

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
