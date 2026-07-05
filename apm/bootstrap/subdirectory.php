<?php

/**
 * Strip the Apache mount path (e.g. /staff/apm) from REQUEST_URI so Laravel
 * routes like "/sso/accept" match.
 */
$basePath = '';

$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
if ($scriptName !== '') {
    if (str_ends_with($scriptName, '/server.php')) {
        $basePath = substr($scriptName, 0, -strlen('/server.php'));
    } elseif (str_ends_with($scriptName, '/public/index.php')) {
        $basePath = substr($scriptName, 0, -strlen('/public/index.php'));
    }
}

if ($basePath === '') {
    $appUrl = (string) (function_exists('env') ? env('APP_URL', '') : '');
    if ($appUrl === '' && is_readable(__DIR__.'/../.env')) {
        foreach (file(__DIR__.'/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), 'APP_URL=')) {
                $appUrl = trim(substr(trim($line), strlen('APP_URL=')), " \t\"'");
                break;
            }
        }
    }
    $parsed = parse_url($appUrl, PHP_URL_PATH);
    if (is_string($parsed) && $parsed !== '' && $parsed !== '/') {
        $basePath = rtrim($parsed, '/');
    }
}

foreach (['/staff/apm', '/apm'] as $mount) {
    if ($basePath === '' && isset($_SERVER['REQUEST_URI']) && str_starts_with((string) $_SERVER['REQUEST_URI'], $mount)) {
        $basePath = $mount;
        break;
    }
}

if ($basePath === '' || $basePath === '/') {
    return;
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$query = parse_url($requestUri, PHP_URL_QUERY);

if (! str_starts_with($path, $basePath)) {
    return;
}

$path = substr($path, strlen($basePath)) ?: '/';
if ($path !== '/' && ! str_starts_with($path, '/')) {
    $path = '/'.$path;
}

$_SERVER['REQUEST_URI'] = $path.($query !== null && $query !== '' ? '?'.$query : '');

if (isset($_SERVER['PATH_INFO']) && str_starts_with((string) $_SERVER['PATH_INFO'], $basePath)) {
    $_SERVER['PATH_INFO'] = substr((string) $_SERVER['PATH_INFO'], strlen($basePath)) ?: '/';
}
