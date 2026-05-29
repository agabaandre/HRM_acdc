<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Top-bar CBP Modules dropdown (Staff Share API — same payload as Helpdesk).
 */
class CbpModulesNavService
{
    /**
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public static function headerNav(): array
    {
        $defaults = [
            'home' => [
                'id' => 'cbp_home',
                'label' => 'CBP Home',
                'description' => '',
                'href' => self::staffWebBaseUrl().'/home/index',
                'is_active' => false,
            ],
            'modules' => [],
        ];

        $staffId = (int) data_get(session('user'), 'staff_id', 0);
        if ($staffId < 1) {
            return $defaults;
        }

        $client = app(StaffPortalShareClient::class);
        if (! $client->isConfigured()) {
            return $defaults;
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
            Log::debug('CbpModulesNavService: '.$e->getMessage());

            return $defaults;
        }
    }

    public static function staffWebBaseUrl(): string
    {
        $u = rtrim((string) session('user.base_url', config('services.staff_api.base_url', 'http://localhost/staff/')), '/');

        return rtrim(str_replace('/apm', '', $u), '/');
    }
}
