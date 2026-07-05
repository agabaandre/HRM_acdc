<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 *
 * Mirrors apm/server.php so the helpdesk Laravel API can be served by Apache
 * (or the built-in PHP web server) without exposing `/public/` in the URL.
 * Apache reaches this script via helpdesk/backend/.htaccess.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Strip subdirectory mount (e.g. /staff/helpdesk/backend) before static file lookup.
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

// When APP_URL points at another host, still strip the known local mount prefix.
foreach (['/staff/helpdesk/backend', '/helpdesk/backend'] as $mount) {
    if (str_starts_with($uri, $mount)) {
        $uri = substr($uri, strlen($mount)) ?: '/';
        break;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && preg_match('#/sso/accept/?$#', $uri)) {
    require __DIR__.'/sso_accept_dispatch.php';
    exit;
}

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
