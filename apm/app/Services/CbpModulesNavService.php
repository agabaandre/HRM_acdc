<?php

namespace App\Services;

use App\Support\RuntimeUrl;
use App\Support\StaffApiBaseUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Top-bar CBP Modules dropdown (Staff Share API — same payload as Helpdesk).
 * Fallback modules are rendered inside the dropdown, not as separate header links.
 */
class CbpModulesNavService
{
    /**
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public static function headerNav(): array
    {
        $defaults = self::defaultPayload();

        $staffId = (int) data_get(session('user'), 'staff_id', 0);
        if ($staffId < 1) {
            return $defaults;
        }

        $client = app(StaffPortalShareClient::class);
        if (! $client->isConfigured()) {
            return self::fallbackPayload($defaults);
        }

        try {
            $perms = array_map('strval', (array) session('permissions', []));
            $permKey = $perms !== [] ? md5(implode(',', $perms)) : 'db';

            return Cache::remember(
                'apm_cbp_modules_nav_'.$staffId.'_'.$permKey,
                300,
                fn () => $client->fetchCbpModules($staffId, 'approvals_management', 'approvals_management', $perms)
            );
        } catch (\Throwable $e) {
            Log::warning('CbpModulesNavService: '.$e->getMessage());

            return self::fallbackPayload($defaults);
        }
    }

    public static function staffWebBaseUrl(): string
    {
        return RuntimeUrl::staffPortalBaseUrl();
    }

    /**
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    private static function defaultPayload(): array
    {
        return [
            'home' => [
                'id' => 'cbp_home',
                'label' => 'CBP Home',
                'description' => '',
                'href' => self::staffWebBaseUrl().'/home/index',
                'is_active' => false,
            ],
            'modules' => [],
        ];
    }

    /**
     * @param  array{home: array<string, mixed>, modules: list<array<string, mixed>>}  $defaults
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    private static function fallbackPayload(array $defaults): array
    {
        $modules = [];
        foreach (CbpPlatformMenuService::primaryNavItems() as $item) {
            $modules[] = [
                'id' => md5((string) (($item['url'] ?? '').($item['title'] ?? ''))),
                'label' => (string) ($item['title'] ?? 'Module'),
                'description' => (string) ($item['description'] ?? ''),
                'href' => (string) ($item['url'] ?? '#'),
                'icon' => (string) ($item['icon'] ?? 'fa fa-th'),
                'opens_in_new_tab' => true,
                'is_active' => false,
            ];
        }

        if ($modules !== []) {
            $defaults['modules'] = $modules;

            return $defaults;
        }

        $staffBase = self::staffWebBaseUrl();

        $defaults['modules'] = [
            [
                'id' => 'staff_portal',
                'label' => 'Staff Portal',
                'description' => '',
                'href' => $staffBase.'/home/index',
                'icon' => 'fa fa-users',
                'opens_in_new_tab' => false,
                'is_active' => false,
            ],
            [
                'id' => 'finance_management',
                'label' => 'Finance Management',
                'description' => '',
                'href' => $staffBase.'/finance',
                'icon' => 'fa fa-wallet',
                'opens_in_new_tab' => false,
                'is_active' => false,
            ],
        ];

        return $defaults;
    }

    private static function isLocalDevUrl(string $url): bool
    {
        return str_contains($url, 'localhost')
            || str_contains($url, '127.0.0.1')
            || str_contains($url, '.local');
    }

    private static function isLocalDevHost(string $host): bool
    {
        return str_contains($host, 'localhost')
            || str_contains($host, '127.0.0.1')
            || str_ends_with(strtolower($host), '.local');
    }
}
