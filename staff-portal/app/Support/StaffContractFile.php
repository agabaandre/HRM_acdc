<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class StaffContractFile
{
    public static function uploadsPath(string $filename): string
    {
        $safe = basename(str_replace('\\', '/', $filename));

        return base_path('../uploads/staff/contracts/'.$safe);
    }

    public static function exists(?string $filename): bool
    {
        if ($filename === null || trim($filename) === '') {
            return false;
        }

        return is_file(self::uploadsPath($filename));
    }

    public static function url(?string $filename): ?string
    {
        if (! self::exists($filename)) {
            return null;
        }

        return route('staff.media.contract', ['filename' => basename($filename)]);
    }

    public static function ensureDirectory(): void
    {
        $dir = base_path('../uploads/staff/contracts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public static function download(string $filename): BinaryFileResponse
    {
        $path = self::uploadsPath($filename);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($filename).'"',
        ]);
    }
}
