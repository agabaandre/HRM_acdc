<?php

/**
 * Strip the Apache mount path (e.g. /staff/finance) from REQUEST_URI so Laravel
 * routes like "/" and "/dashboard" match.
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
    $parsed = parse_url($appUrl, PHP_URL_PATH);
    if (is_string($parsed) && $parsed !== '' && $parsed !== '/') {
        $basePath = rtrim($parsed, '/');
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
