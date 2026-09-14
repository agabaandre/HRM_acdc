<?php
/**
 * Add wire:navigate to internal links in Blade views (SPA-like navigation).
 * Skips lines that already have wire:navigate.
 */
$viewsPath = dirname(__DIR__) . '/resources/views';
$skipDirs = ['vendor', 'node_modules'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') continue;
    $path = $file->getPathname();
    foreach ($skipDirs as $skip) {
        if (strpos($path, $skip) !== false) continue 2;
    }
    $lines = file($path);
    $changed = false;
    $skipKeywords = ['export', 'download', 'print', 'stream', 'attachment'];
    foreach ($lines as $i => $line) {
        if (stripos($line, 'wire:navigate') !== false) continue;
        if (stripos($line, 'target="_blank"') !== false || stripos($line, "target='_blank'") !== false) continue;
        // <a href="{{ route( or <a href="{{ url(
        if (!preg_match('/<a\s+([^>]*?)href="\{\{\s*(?:route|url)\s*\(/', $line)) continue;
        foreach ($skipKeywords as $kw) {
            if (stripos($line, $kw) !== false) continue 2; // skip this line
        }
        $lines[$i] = preg_replace('/<a\s+/', '<a wire:navigate ', $line, 1);
        $changed = true;
    }
    if ($changed) {
        file_put_contents($path, implode('', $lines));
        $count++;
        echo "Updated: " . str_replace($viewsPath . '/', '', $path) . "\n";
    }
}
echo "Done. Updated {$count} files.\n";
