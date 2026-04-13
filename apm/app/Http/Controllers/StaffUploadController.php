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

        if (!self::authorizedForStaffPortrait($filename)) {
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

    /**
     * Any staff row may reference this filename (CI3 secure_upload behaviour), or the logged-in user
     * must match the file (session/JWT photo or staff.photo) so nav avatars work when DB/session edge cases differ.
     */
    private static function authorizedForStaffPortrait(string $filename): bool
    {
        if (Staff::query()->where('photo', $filename)->exists()) {
            return true;
        }

        $user = session('user');
        if ($user === null) {
            return false;
        }

        $staffId = is_array($user)
            ? (int) ($user['staff_id'] ?? 0)
            : (int) ($user->staff_id ?? 0);

        if ($staffId > 0) {
            $dbPhoto = Staff::query()->where('staff_id', $staffId)->value('photo');
            if (is_string($dbPhoto) && $dbPhoto !== '') {
                $base = basename(str_replace('\\', '/', $dbPhoto));
                if ($base === $filename) {
                    return true;
                }
            }
        }

        $sessionPhoto = is_array($user)
            ? trim((string) ($user['photo'] ?? ''))
            : trim((string) ($user->photo ?? ''));
        if ($sessionPhoto !== '') {
            $base = basename(str_replace('\\', '/', $sessionPhoto));
            if ($base === $filename) {
                return true;
            }
        }

        return false;
    }
}
