<?php

namespace App\Support;

use Staff\Shared\StaffStorage;

final class StaffPhoto
{
    /** @var list<string> */
    private const COLORS = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];

    public static function uploadsRoot(): string
    {
        return (string) config('staff-portal.uploads_root', StaffStorage::ciUploadsRoot(dirname(base_path(), 2)));
    }

    public static function uploadsPath(string $filename): string
    {
        $safe = basename(str_replace('\\', '/', $filename));

        return StaffStorage::ciPath('staff/'.$safe);
    }

    /**
     * Absolute path to a readable staff photo, checking host CI storage then
     * the legacy repo uploads/ tree (newer photos may not be migrated yet).
     */
    public static function resolveExistingPath(?string $filename): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }

        $safe = basename(str_replace('\\', '/', trim($filename)));
        if ($safe === '' || $safe === '.' || $safe === '..') {
            return null;
        }

        foreach (self::candidatePaths($safe) as $path) {
            if (is_file($path) && @getimagesize($path) !== false) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function candidatePaths(string $safe): array
    {
        $paths = [self::uploadsPath($safe)];

        // Legacy CI tree still used by Staff Portal when photos land under repo uploads/.
        $repoRoot = dirname(base_path(), 3); // …/modules/risk-register/backend → staff repo
        if (is_dir($repoRoot.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'staff')) {
            $paths[] = $repoRoot.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'staff'.DIRECTORY_SEPARATOR.$safe;
        }

        $configRoot = rtrim((string) config('staff-portal.uploads_root', ''), '/\\');
        if ($configRoot !== '') {
            $paths[] = $configRoot.DIRECTORY_SEPARATOR.'staff'.DIRECTORY_SEPARATOR.$safe;
        }

        return array_values(array_unique($paths));
    }

    public static function exists(?string $filename): bool
    {
        return self::resolveExistingPath($filename) !== null;
    }

    /**
     * Public photo URL. By default skips disk/getimagesize checks so list endpoints stay fast;
     * pass $verifyReadable=true when a missing file must not be linked.
     */
    public static function url(?string $filename, bool $verifyReadable = false): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }

        $safe = basename(str_replace('\\', '/', $filename));
        if ($safe === '' || $safe === '.' || $safe === '..') {
            return null;
        }

        if ($verifyReadable && ! self::exists($filename)) {
            return null;
        }

        return route('staff.media.photo', ['filename' => $safe]);
    }

    public static function initials(string $fname, string $lname): string
    {
        $s = $lname !== '' ? strtoupper(substr($lname, 0, 1)) : '';
        $f = $fname !== '' ? strtoupper(substr($fname, 0, 1)) : '';

        return $s.$f ?: '?';
    }

    public static function backgroundColor(string $fname): string
    {
        $first = $fname !== '' ? strtoupper($fname[0]) : 'A';
        $index = (ord($first) - 65) % count(self::COLORS);

        return self::COLORS[max(0, $index)];
    }

    public static function age(?string $dateOfBirth): string
    {
        if ($dateOfBirth === null || $dateOfBirth === '') {
            return 'N/A';
        }
        try {
            return (string) \Carbon\Carbon::parse($dateOfBirth)->age;
        } catch (\Throwable) {
            return 'N/A';
        }
    }

    public static function yearsOfTenure(?string $initiationDate): string
    {
        if ($initiationDate === null || $initiationDate === '') {
            return 'N/A';
        }
        try {
            $years = \Carbon\Carbon::parse($initiationDate)->diffInYears(now());

            return $years.' '.($years === 1 ? 'year' : 'years');
        } catch (\Throwable) {
            return 'N/A';
        }
    }
}
