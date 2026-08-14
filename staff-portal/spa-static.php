<?php
/**
 * Staff Portal static/SPA front controller.
 * Used when Apache would otherwise hand /staff/staff-portal/* to CodeIgniter
 * (which returns HTTP 500 + text/html for missing Vite assets).
 */
declare(strict_types=1);

$asset = isset($_GET['f']) ? (string) $_GET['f'] : '';
$asset = str_replace(['\\', "\0"], '', $asset);
$asset = ltrim($asset, '/');

$dir = __DIR__;

$mime = static function (string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($ext) {
        'js', 'mjs' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'ico' => 'image/x-icon',
        'html', 'htm' => 'text/html; charset=utf-8',
        default => 'application/octet-stream',
    };
};

$send = static function (string $path) use ($mime): void {
    header('Content-Type: '.$mime($path));
    header('X-Content-Type-Options: nosniff');
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['js', 'mjs', 'css', 'woff', 'woff2', 'ttf'], true)) {
        header('Cache-Control: public, max-age=31536000, immutable');
    } else {
        header('Cache-Control: no-cache, must-revalidate');
    }
    header('Content-Length: '.(string) filesize($path));
    readfile($path);
    exit;
};

// /staff/staff-portal/assets/<file>  (via ?f=)
if ($asset !== '') {
    if (str_contains($asset, '..') || ! preg_match('/^[A-Za-z0-9._-]+$/', $asset)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Invalid asset name\n";
        exit;
    }
    foreach ([
        $dir.'/assets/'.$asset,
        $dir.'/public-spa/assets/'.$asset,
        $dir.'/frontend/dist-build/assets/'.$asset,
    ] as $candidate) {
        if (is_file($candidate)) {
            $send($candidate);
        }
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Asset not found: {$asset}\n";
    echo "Run: cd staff-portal && npm --prefix frontend run build && ./scripts/publish-spa.sh\n";
    exit;
}

// SPA index for client routes
foreach ([
    $dir.'/index.html',
    $dir.'/public-spa/index.html',
    $dir.'/frontend/dist-build/index.html',
] as $index) {
    if (is_file($index)) {
        $send($index);
    }
}

http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
echo "Staff Portal SPA is not published.\n";
echo "Run: cd staff-portal/frontend && npm run build && cd .. && ./scripts/publish-spa.sh\n";
