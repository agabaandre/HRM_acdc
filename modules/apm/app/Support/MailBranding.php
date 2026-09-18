<?php

namespace App\Support;

use App\Services\StaffBrandingService;

/**
 * HTML email branding — logo from Staff Portal Settings → Branding.
 * Templates keep their own max-width / max-height CSS for aspect ratio.
 */
final class MailBranding
{
    public static function logoUrl(): string
    {
        try {
            $url = trim((string) (StaffBrandingService::get()['logo_url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        } catch (\Throwable) {
            // fall through
        }

        $configured = config('branding.mail_logo_url');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $base = rtrim((string) env('BASE_URL', 'https://cbp.africacdc.org/staff/'), '/');

        return $base.'/assets/images/AU_CDC_Logo-800.png';
    }

    public static function companyName(): string
    {
        try {
            $name = trim((string) (StaffBrandingService::get()['company_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        } catch (\Throwable) {
            // fall through
        }

        return 'Africa CDC';
    }

    /** Inline style that preserves aspect ratio inside email header constraints. */
    public static function logoInlineStyle(string $extra = ''): string
    {
        $base = 'max-height:70px;max-width:200px;width:auto;height:auto;display:block;margin:0 auto;';

        return trim($base.' '.$extra);
    }
}
