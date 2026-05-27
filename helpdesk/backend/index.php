<?php

/**
 * Mirrors apm/index.php so requests landing on helpdesk/backend/ (when
 * mod_rewrite isn't available) are routed to Laravel's public/index.php
 * without "/public/" in the URL.
 */

$public_path = __DIR__ . '/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);
$uri = ltrim($uri, '/');

if ($uri === 'index.php' || $uri === '') {
    require $public_path . '/index.php';
    return;
}

// Strip any base prefix up to and including "helpdesk/backend/" so we can
// look files up inside public/ by their relative path.
$relative = $uri;
$marker = 'helpdesk/backend/';
$markerPos = strpos($relative, $marker);
if ($markerPos !== false) {
    $relative = substr($relative, $markerPos + strlen($marker));
}

$staticMimeMap = [
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'json'  => 'application/json',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'png'   => 'image/png',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'webp'  => 'image/webp',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'eot'   => 'application/vnd.ms-fontobject',
    'ico'   => 'image/x-icon',
];

if ($relative !== '') {
    $path = realpath($public_path . '/' . $relative);
    if ($path && is_file($path) && strpos($path, realpath($public_path)) === 0) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (isset($staticMimeMap[$extension])) {
            header('Content-Type: ' . $staticMimeMap[$extension]);
        }
        $lastModified = filemtime($path);
        $etag = md5_file($path);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('Etag: ' . $etag);
        header('Cache-Control: public, max-age=31536000');
        readfile($path);
        return;
    }
}

$_SERVER['SCRIPT_FILENAME'] = $public_path . '/index.php';
require $_SERVER['SCRIPT_FILENAME'];
