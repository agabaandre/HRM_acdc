<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\AvatarController;
use App\Models\User;

/**
 * Browser-safe portrait URL for the Helpdesk SPA.
 *
 * {@see AvatarController} streams the file from the shared
 * Staff uploads tree (same files as APM staff-uploads/photo). URLs are signed so image
 * tags work without Authorization headers (unlike calling APM directly).
 */
final class StaffPhotoUrl
{
    public static function forUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $base = basename(str_replace('\\', '/', trim((string) ($user->photo ?? ''))));
        if ($base === '' || $base === '.' || $base === '..'
            || ! preg_match('/^[a-zA-Z0-9_.-]+$/', $base)) {
            return null;
        }

        $ttl = (int) config('helpdesk.avatar_signed_ttl_seconds', 604800);
        $exp = time() + max(120, $ttl);
        $secret = (string) (config('helpdesk.avatar_signing_secret') ?: config('app.key'));
        $sig = hash_hmac('sha256', (string) $user->id.'|'.$exp, $secret);

        $path = '/api/v1/avatar/'.$user->id.'?exp='.$exp.'&sig='.$sig;
        $public = trim((string) config('helpdesk.api_public_url', ''));
        if ($public !== '') {
            return rtrim($public, '/').$path;
        }

        return $path;
    }
}
