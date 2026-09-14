<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
                'href' => rtrim(self::staffWebBaseUrl(), '/').'/',
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
                'finance_cbp_modules_nav_'.$staffId.'_'.$permKey,
                300,
                fn () => $client->fetchCbpModules($staffId, 'finance_management', 'finance_management', $perms)
            );
        } catch (\Throwable $e) {
            Log::debug('CbpModulesNavService: '.$e->getMessage());

            return $defaults;
        }
    }

    public static function staffWebBaseUrl(): string
    {
        $fromSession = rtrim((string) data_get(session('user'), 'base_url', ''), '/');
        $fromSession = rtrim(str_replace(['/finance', '/backend'], '', $fromSession), '/');
        if ($fromSession !== '') {
            return $fromSession;
        }

        // Public SPA base (/staff), not Share API mount (/staff/backend).
        $public = rtrim((string) env('BASE_URL', env('CI_BASE_URL', 'http://localhost/staff')), '/');
        $public = rtrim(preg_replace('#/backend$#', '', $public) ?? $public, '/');
        $public = rtrim(str_replace('/finance', '', $public), '/');

        return $public !== '' ? $public : 'http://localhost/staff';
    }
}
