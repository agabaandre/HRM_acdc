<?php

/**
 * Laravel front controller for Apache (no /public/ in URLs).
 * Strips /{webRoot}/backend via bootstrap/subdirectory.php, then boots public/index.php.
 */

require_once __DIR__.'/bootstrap/subdirectory.php';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');

if ($uri !== '/' && $uri !== '' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
