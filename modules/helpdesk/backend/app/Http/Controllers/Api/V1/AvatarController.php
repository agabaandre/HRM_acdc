<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AvatarController extends Controller
{
    public function show(Request $request, User $user): BinaryFileResponse
    {
        $exp = (int) $request->query('exp', 0);
        $sig = (string) $request->query('sig', '');
        if ($exp < time()) {
            abort(403, 'Avatar link expired.');
        }

        $secret = (string) (config('helpdesk.avatar_signing_secret') ?: config('app.key'));
        $expected = hash_hmac('sha256', (string) $user->id.'|'.$exp, $secret);
        if (! hash_equals($expected, $sig)) {
            abort(403, 'Invalid avatar signature.');
        }

        $raw = trim((string) ($user->photo ?? ''));
        $filename = basename(str_replace('\\', '/', $raw));
        if ($filename === '' || $filename === '.' || $filename === '..'
            || ! preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
            abort(404);
        }

        $uploadsRoot = rtrim((string) config('helpdesk.staff_uploads_root', ''), DIRECTORY_SEPARATOR);
        if ($uploadsRoot === '') {
            abort(503, 'Staff uploads path is not configured.');
        }

        $full = $uploadsRoot.DIRECTORY_SEPARATOR.'staff'.DIRECTORY_SEPARATOR.$filename;
        if (! is_file($full) || ! is_readable($full)) {
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
