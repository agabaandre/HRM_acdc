<?php

/**
 * Apache front controller (mirrors apm/server.php).
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Strip subdirectory mount (e.g. /staff/finance) before static file lookup.
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

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
