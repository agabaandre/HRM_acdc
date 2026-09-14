<?php

namespace App\Support;

/**
 * Resolve public rich-text image URLs back to storage paths for the owning user.
 */
final class RichTextImagePath
{
    public static function pathForUser(string $url, int $userId): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $relative = self::relativePublicPath($path);
        if ($relative === null) {
            return null;
        }

        $prefix = 'helpdesk/rich-text/'.$userId.'/';
        if (! str_starts_with($relative, $prefix)) {
            return null;
        }

        return $relative;
    }

    private static function relativePublicPath(string $path): ?string
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            return substr($path, strlen('storage/'));
        }

        $storagePos = strrpos($path, '/storage/');
        if ($storagePos !== false) {
            return ltrim(substr($path, $storagePos + strlen('/storage/')), '/');
        }

        if (str_starts_with($path, 'helpdesk/rich-text/')) {
            return $path;
        }

        return null;
    }
}
