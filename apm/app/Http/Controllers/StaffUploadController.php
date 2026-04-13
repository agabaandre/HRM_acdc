<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;

/**
 * Stream staff uploads from the shared CI3 uploads tree with APM session (or JWT) auth.
 * Mirrors CI3 Secure_upload validation: filename registered on a staff row, file on disk.
 */
class StaffUploadController extends Controller
{
    public function photo(Request $request)
    {
        $raw = (string) $request->query('f', '');
        $filename = basename(str_replace('\\', '/', rawurldecode($raw)));
        if ($filename === '' || $filename === '.' || $filename === '..'
            || !preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
            abort(404);
        }

        if (!Staff::query()->where('photo', $filename)->exists()) {
            abort(403);
        }

        $uploadsRoot = (string) config('staff_portal.uploads_root', dirname(base_path()) . DIRECTORY_SEPARATOR . 'uploads');
        $full = rtrim($uploadsRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'staff'
            . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($full) || !is_readable($full)) {
            abort(404);
        }

        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($full);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        } elseif (function_exists('finfo_open')) {
            $f = @finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $detected = @finfo_file($f, $full);
                finfo_close($f);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
        }

        return response()->file($full, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
