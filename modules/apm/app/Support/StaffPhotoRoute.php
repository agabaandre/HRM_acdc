<?php

namespace App\Support;

/**
 * Browser-safe URL for legacy staff portrait files (uploads/staff/{filename} on disk).
 * Direct /uploads/staff/* is blocked in CI3; APM serves these via an authenticated route.
 */
class StaffPhotoRoute
{
    public static function url(?string $photo): string
    {
        $photo = trim((string) $photo);
        if ($photo === '') {
            return '';
        }

        $filename = basename(str_replace('\\', '/', $photo));
        if ($filename === '' || $filename === '.' || $filename === '..'
            || !preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
            return '';
        }

        return route('staff-uploads.photo') . '?f=' . rawurlencode($filename);
    }
}
