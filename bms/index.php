<?php

/**
 * Redirect all requests to the public folder
 *
 * This file allows you to access your Laravel application
 * without having to include "public" in the URL.
 */

// Path to the public folder
$public_path = __DIR__ . '/public';

// Get the requested URI
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Remove any base URI from the requested path
$uri = ltrim($uri, '/');

// If this file was accessed directly without any path
if ($uri == 'index.php' || empty($uri)) {
    // Redirect to public/index.php
    $path = $public_path . '/index.php';
    require $path;
    return;
}

// Check if the request is for an asset in the assets folder
if (strpos($uri, 'assets/') === 0) {
    $path = realpath($public_path . '/' . $uri);
    if ($path && is_file($path)) {
        // Set the correct mime type for the file
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'css':
                header('Content-Type: text/css');
                break;
            case 'js':
                header('Content-Type: application/javascript');
                break;
            case 'json':
                header('Content-Type: application/json');
                break;
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'gif':
                header('Content-Type: image/gif');
                break;
            case 'svg':
                header('Content-Type: image/svg+xml');
                break;
            case 'woff':
                header('Content-Type: font/woff');
                break;
            case 'woff2':
                header('Content-Type: font/woff2');
                break;
            case 'ttf':
                header('Content-Type: font/ttf');
                break;
            case 'eot':
                header('Content-Type: application/vnd.ms-fontobject');
                break;
            case 'ico':
                header('Content-Type: image/x-icon');
                break;
            default:
                // For unknown types, let the browser figure it out
                break;
        }

        // Output the file directly with proper caching headers
        $lastModified = filemtime($path);
        $etagFile = md5_file($path);
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
        header("Etag: $etagFile");
        header('Cache-Control: public, max-age=31536000'); // Cache for a year
        readfile($path);
        exit;
    }
}

// Check if the file exists in public directory (for other direct files)
$path = realpath($public_path . '/' . $uri);
if ($path && is_file($path)) {
    // Set the correct mime type for the file
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    switch ($extension) {
        case 'css':
            header('Content-Type: text/css');
            break;
        case 'js':
            header('Content-Type: application/javascript');
            break;
        case 'json':
            header('Content-Type: application/json');
            break;
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'gif':
            header('Content-Type: image/gif');
            break;
        case 'svg':
            header('Content-Type: image/svg+xml');
            break;
        case 'woff':
            header('Content-Type: font/woff');
            break;
        case 'woff2':
            header('Content-Type: font/woff2');
            break;
    }

    // Output the file directly
    readfile($path);
    return;
}

// If the file doesn't exist or is not a direct asset, pass to Laravel's index.php
$_SERVER['SCRIPT_FILENAME'] = $public_path . '/index.php';
require $_SERVER['SCRIPT_FILENAME'];
