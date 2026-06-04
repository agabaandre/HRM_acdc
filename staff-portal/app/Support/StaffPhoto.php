<?php

namespace App\Support;

final class StaffPhoto
{
    /** @var list<string> */
    private const COLORS = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];

    public static function uploadsPath(string $filename): string
    {
        $safe = basename(str_replace('\\', '/', $filename));

        return base_path('../uploads/staff/'.$safe);
    }

    public static function exists(?string $filename): bool
    {
        if ($filename === null || trim($filename) === '') {
            return false;
        }
        $path = self::uploadsPath($filename);

        return is_file($path) && @getimagesize($path) !== false;
    }

    public static function url(?string $filename): ?string
    {
        if (! self::exists($filename)) {
            return null;
        }

        return route('staff.media.photo', ['filename' => basename($filename)]);
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
