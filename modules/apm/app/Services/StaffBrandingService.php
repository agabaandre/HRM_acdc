<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Org branding from Staff Portal Share API (Settings → Branding).
 */
class StaffBrandingService
{
    public const CACHE_KEY = 'apm.staff_branding';

    /**
     * @return array{
     *   company_name: string,
     *   logo_url: string,
     *   system_logo: string,
     *   footer_copyright_rendered: string,
     *   footer_copyright: string,
     *   print_footer: string,
     *   company_email: string,
     *   company_phone: string,
     *   company_website: string,
     *   company_address: string,
     *   login_welcome_title: string,
     *   login_welcome_text: string,
     *   login_background: string,
     *   login_background_url: string
     * }
     */
    public static function get(): array
    {
        $staffRoot = self::staffPortalRoot();
        $defaults = [
            'company_name' => 'Africa CDC',
            'logo_url' => $staffRoot.'/assets/images/AU_CDC_Logo-800.png',
            'system_logo' => '/assets/images/AU_CDC_Logo-800.png',
            'footer_copyright' => 'Copyright © Africa CDC {year}. All rights reserved.',
            'footer_copyright_rendered' => 'Copyright © Africa CDC '.date('Y').'. All rights reserved.',
            'print_footer' => '',
            'company_email' => 'registry@africacdc.org',
            'company_phone' => '',
            'company_website' => 'https://africacdc.org',
            'company_address' => '',
            'login_welcome_title' => 'Welcome Back',
            'login_welcome_text' => '',
            'login_background' => '/assets/images/bg_login.jpg',
            'login_background_url' => $staffRoot.'/assets/images/bg_login.jpg',
        ];

        try {
            $cached = Cache::remember(self::CACHE_KEY, 300, function () use ($defaults) {
                return self::fetchFromStaff() ?? $defaults;
            });

            return array_merge($defaults, is_array($cached) ? $cached : []);
        } catch (\Throwable $e) {
            Log::debug('StaffBrandingService: '.$e->getMessage());

            return $defaults;
        }
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchFromStaff(): ?array
    {
        try {
            $client = app(StaffPortalShareClient::class);
            if (! $client->isConfigured()) {
                return null;
            }

            $data = $client->fetchBranding();

            return $data !== [] ? $data : null;
        } catch (\Throwable $e) {
            Log::debug('StaffBrandingService share: '.$e->getMessage());

            return null;
        }
    }

    private static function staffPortalRoot(): string
    {
        $base = rtrim((string) env('BASE_URL', 'http://localhost/staff'), '/');
        if ($base === '') {
            return 'http://localhost/staff';
        }

        return $base;
    }
}
