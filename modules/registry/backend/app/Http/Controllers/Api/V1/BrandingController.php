<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StaffPortalOrgClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BrandingController extends Controller
{
    public const CACHE_KEY = 'rr.staff_branding.v2';

    public const CACHE_TTL_SECONDS = 3600;

    public function __invoke(StaffPortalOrgClient $client): JsonResponse
    {
        unset($client); // kept for DI compatibility; Share is warmed offline to avoid HTTP loopback deadlocks.
        $defaults = $this->defaults();
        $store = $this->cacheStore();

        try {
            $cached = Cache::store($store)->get(self::CACHE_KEY);
            if (is_array($cached) && $cached !== []) {
                return response()->json(['data' => array_merge($defaults, $cached)]);
            }
        } catch (\Throwable $e) {
            Log::debug('RR branding redis get: '.$e->getMessage());
        }

        // Fast local payload — never block the SPA on Share HTTP (loopback can deadlock).
        $payload = $this->localPayload($defaults);

        try {
            Cache::store($store)->put(self::CACHE_KEY, $payload, self::CACHE_TTL_SECONDS);
        } catch (\Throwable $e) {
            Log::debug('RR branding redis put: '.$e->getMessage());
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function localPayload(array $defaults): array
    {
        if (class_exists(\Modules\Settings\Services\PortalBrandingService::class)) {
            try {
                return array_merge(
                    $defaults,
                    app(\Modules\Settings\Services\PortalBrandingService::class)->publicPayload()
                );
            } catch (\Throwable $e) {
                Log::debug('RR local branding: '.$e->getMessage());
            }
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $staffRoot = rtrim((string) env('BASE_URL', 'http://localhost/staff'), '/');

        return [
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
            'login_welcome_text' => 'Access your Africa CDC Central Business Platform account to manage staff operations and track activities efficiently.',
            'login_background' => '/assets/images/bg_login.jpg',
            'login_background_url' => $staffRoot.'/assets/images/bg_login.jpg',
        ];
    }

    private function cacheStore(): string
    {
        try {
            Cache::store('redis')->get('rr.branding.probe');

            return 'redis';
        } catch (\Throwable) {
            return (string) config('cache.default', 'file');
        }
    }
}
