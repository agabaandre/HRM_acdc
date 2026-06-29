<?php

/**
 * Deep diagnostic for duplicate App\Models\Activity (run on production).
 *
 *   cd /path/to/staff/apm && php ../scripts/lib/diagnose-apm-activity.php
 */

declare(strict_types=1);

$apmRoot = getenv('APM_ROOT') ?: dirname(__DIR__, 2).'/apm';
$canonical = $apmRoot.'/app/Models/Activity.php';

echo "APM root: {$apmRoot}\n";
echo "Canonical: {$canonical}\n\n";

if (! is_file($canonical)) {
    fwrite(STDERR, "error: missing canonical Activity.php\n");
    exit(1);
}

echo "==> Files under apm/app containing 'class Activity'\n";
$declarations = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($apmRoot.'/app', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $contents = @file_get_contents($path);
    if ($contents === false) {
        continue;
    }
    if (preg_match('/^class Activity\b/m', $contents)) {
        $declarations[] = $path;
        $count = preg_match_all('/^class Activity\b/m', $contents);
        echo sprintf("  %s (%d declaration(s))\n", $path, $count);
    }
}

echo "\n==> Models directory listing (case check)\n";
foreach (scandir($apmRoot.'/app/Models') ?: [] as $name) {
    if (stripos($name, 'activity') !== false) {
        $full = $apmRoot.'/app/Models/'.$name;
        echo sprintf("  %s  (%s bytes)\n", $name, is_file($full) ? filesize($full) : 0);
    }
}

echo "\n==> Canonical file\n";
echo '  md5: '.md5_file($canonical)."\n";
echo '  class Activity count: '.preg_match_all('/^class Activity\b/m', file_get_contents($canonical) ?: '')."\n";

echo "\n==> Autoload load test\n";
require $apmRoot.'/vendor/autoload.php';
echo '  class_exists before: '.(class_exists('App\\Models\\Activity', false) ? 'yes' : 'no')."\n";
$loader = require $apmRoot.'/vendor/autoload.php';
$loader->loadClass('App\\Models\\Activity');
echo '  class_exists after: '.(class_exists('App\\Models\\Activity', false) ? 'yes' : 'no')."\n";

if (function_exists('opcache_get_status')) {
    echo "\n==> OPcache file status (canonical)\n";
    $status = opcache_get_status(false);
    echo '  opcache enabled: '.(! empty($status['opcache_enabled']) ? 'yes' : 'no')."\n";
    if (! empty($status['scripts'][$canonical])) {
        print_r($status['scripts'][$canonical]);
    } else {
        echo "  (canonical file not in OPcache yet — normal for CLI)\n";
    }
}

echo "\nDone.\n";
