<?php

namespace Modules\Settings\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Settings\Models\PortalBrandingSetting;

class PortalBrandingService
{
    public const CACHE_KEY = 'portal_branding.settings_map';

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return [
            'company_name' => 'Africa CDC',
            'system_logo' => '/assets/images/AU_CDC_Logo-800.png',
            'footer_copyright' => 'Copyright © Africa CDC {year}. All rights reserved.',
            'print_footer' => "Africa CDC Headquarters, Ring Road, 16/17,\nHaile Garment Lafto Square, Nifas Silk-Lafto Sub City,\nP.O Box: 200050 Addis Ababa",
            'company_email' => 'registry@africacdc.org',
            'company_phone' => '',
            'company_website' => 'https://africacdc.org',
            'company_address' => "Africa CDC Headquarters, Ring Road, 16/17,\nHaile Garment Lafto Square, Nifas Silk-Lafto Sub City,\nP.O Box: 200050 Addis Ababa",
            'login_welcome_title' => 'Welcome Back',
            'login_welcome_text' => 'Access your Africa CDC Central Business Platform account to manage staff operations and track activities efficiently.',
            'login_background' => '/assets/images/bg_login.jpg',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $out = $this->defaults();
            if (! Schema::hasTable('portal_branding_settings')) {
                return $out;
            }

            $rows = PortalBrandingSetting::query()->get(['setting_key', 'setting_value']);
            foreach ($rows as $row) {
                $key = (string) $row->setting_key;
                if ($key === '' || ! array_key_exists($key, $out)) {
                    continue;
                }
                $out[$key] = (string) ($row->setting_value ?? '');
            }

            return $out;
        });
    }

    public function get(string $key, ?string $default = null): string
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) {
            return (string) $all[$key];
        }

        return (string) ($default ?? '');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function save(array $input): array
    {
        $allowed = array_keys($this->defaults());
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $value = is_string($input[$key]) || is_numeric($input[$key])
                ? (string) $input[$key]
                : '';
            PortalBrandingSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value],
            );
        }

        $this->flushCache();

        return $this->all();
    }

    public function storeLogo(UploadedFile $file): array
    {
        return $this->storeImageSetting($file, 'system_logo');
    }

    public function storeLoginBackground(UploadedFile $file): array
    {
        return $this->storeImageSetting($file, 'login_background');
    }

    /**
     * @return array<string, mixed>
     */
    private function storeImageSetting(UploadedFile $file, string $key): array
    {
        $dir = 'branding';
        Storage::disk('public')->makeDirectory($dir);
        $path = $file->store($dir, 'public');
        $publicPath = '/storage/'.$path;

        PortalBrandingSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $publicPath],
        );
        $this->flushCache();

        return $this->publicPayload();
    }

    /**
     * Payload for SPA settings + Share consumers.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $all = $this->all();
        $logo = (string) ($all['system_logo'] ?? '');
        $loginBg = (string) ($all['login_background'] ?? '');
        $year = (string) date('Y');
        $copyright = str_replace('{year}', $year, (string) ($all['footer_copyright'] ?? ''));

        return [
            'company_name' => (string) ($all['company_name'] ?? 'Africa CDC'),
            'system_logo' => $logo,
            'logo_url' => $this->absoluteUrl($logo),
            'footer_copyright' => (string) ($all['footer_copyright'] ?? ''),
            'footer_copyright_rendered' => $copyright,
            'print_footer' => (string) ($all['print_footer'] ?? ''),
            'company_email' => (string) ($all['company_email'] ?? ''),
            'company_phone' => (string) ($all['company_phone'] ?? ''),
            'company_website' => (string) ($all['company_website'] ?? ''),
            'company_address' => (string) ($all['company_address'] ?? ''),
            'login_welcome_title' => (string) ($all['login_welcome_title'] ?? 'Welcome Back'),
            'login_welcome_text' => (string) ($all['login_welcome_text'] ?? ''),
            'login_background' => $loginBg,
            'login_background_url' => $this->absoluteUrl(
                $loginBg !== '' ? $loginBg : '/assets/images/bg_login.jpg'
            ),
        ];
    }

    /**
     * Public subset for the login page (no auth).
     *
     * @return array<string, mixed>
     */
    public function loginPayload(): array
    {
        $payload = $this->publicPayload();

        return [
            'company_name' => $payload['company_name'],
            'logo_url' => $payload['logo_url'],
            'login_welcome_title' => $payload['login_welcome_title'],
            'login_welcome_text' => $payload['login_welcome_text'],
            'login_background_url' => $payload['login_background_url'],
            'footer_copyright_rendered' => $payload['footer_copyright_rendered'],
        ];
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function absoluteUrl(string $pathOrUrl): string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return $this->absoluteUrl('/assets/images/AU_CDC_Logo-800.png');
        }
        if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
            return $pathOrUrl;
        }

        $base = rtrim((string) config('app.url', ''), '/');
        // Prefer public staff web root when APP_URL points at backend.
        if (str_contains($base, '/backend')) {
            $base = preg_replace('#/backend$#', '', $base) ?? $base;
        }
        if ($base === '') {
            $base = 'http://localhost/staff';
        }

        return $base.'/'.ltrim($pathOrUrl, '/');
    }
}
