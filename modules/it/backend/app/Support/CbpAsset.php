<?php

namespace App\Support;

final class CbpAsset
{
    /**
     * URL for shared CBP theme assets (served via /cbp-assets/…).
     */
    public static function url(string $path): string
    {
        $configured = env('STAFF_CBP_ASSETS_URL');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/').'/'.ltrim($path, '/');
        }

        return url('cbp-assets/'.ltrim($path, '/'));
    }
}
