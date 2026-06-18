<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CBP Modules dropdown payload for Helpdesk top nav (Staff Share API).
 */
class CbpModulesNavService
{
    /**
     * @return array{data: array{home: array<string, mixed>, modules: list<array<string, mixed>>}, meta: array<string, mixed>}
     */
    public function resolveForUser(User $user, StaffPortalReferenceClient $client): array
    {
        $profile = $user->helpdeskProfile;
        if (! $profile instanceof HelpdeskProfile || ! $profile->staff_id) {
            abort(403, 'A Staff-linked helpdesk profile is required to load CBP modules.');
        }

        if (! $client->isConfigured()) {
            return $this->wrap($this->fallbackPayload($profile), [
                'source' => 'fallback',
                'degraded' => true,
                'reason' => 'staff_api_not_configured',
            ]);
        }

        $staffId = (int) $profile->staff_id;
        $permissionIds = is_array($profile->staff_portal_permissions)
            ? array_map('strval', $profile->staff_portal_permissions)
            : [];
        $ttl = max(60, (int) config('helpdesk.reference_data_cache_ttl', 300));
        $permKey = $permissionIds !== [] ? md5(implode(',', $permissionIds)) : 'db';
        $cacheKey = 'helpdesk_cbp_modules_v4_'.$staffId.'_'.$permKey;

        try {
            $data = Cache::remember(
                $cacheKey,
                $ttl,
                fn () => $client->fetchCbpModules($staffId, 'helpdesk_itsm', 'helpdesk_itsm', $permissionIds)
            );

            return $this->wrap($data, [
                'source' => 'staff_share_api',
                'degraded' => false,
                'cache_ttl_seconds' => $ttl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Helpdesk CBP modules: Staff Share API unavailable', [
                'staff_id' => $staffId,
                'message' => $e->getMessage(),
            ]);

            return $this->wrap($this->fallbackPayload($profile), [
                'source' => 'fallback',
                'degraded' => true,
                'reason' => 'staff_share_api_error',
            ]);
        }
    }

    /**
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public function fallbackPayload(HelpdeskProfile $profile): array
    {
        $staffBase = rtrim((string) config('helpdesk.staff_portal_url', 'http://localhost/staff'), '/');
        $apmBase = rtrim((string) config('helpdesk.apm_base_url', $staffBase.'/apm'), '/');
        $financeBase = rtrim($staffBase, '/').'/finance';

        return [
            'home' => [
                'id' => 'cbp_home',
                'label' => 'CBP Home',
                'description' => '',
                'href' => $staffBase.'/home/index',
                'is_active' => false,
            ],
            'modules' => [
                [
                    'id' => 'approvals_management',
                    'label' => 'Approvals Management (APM)',
                    'description' => '',
                    'href' => $apmBase,
                    'icon' => 'fa fa-sitemap',
                    'opens_in_new_tab' => false,
                    'is_active' => false,
                ],
                [
                    'id' => 'finance_management',
                    'label' => 'Finance Management',
                    'description' => '',
                    'href' => $financeBase,
                    'icon' => 'fa fa-wallet',
                    'opens_in_new_tab' => false,
                    'is_active' => false,
                ],
            ],
        ];
    }

    /**
     * @param  array{home: array<string, mixed>, modules: list<array<string, mixed>>}  $data
     * @param  array<string, mixed>  $meta
     * @return array{data: array{home: array<string, mixed>, modules: list<array<string, mixed>>}, meta: array<string, mixed>}
     */
    private function wrap(array $data, array $meta): array
    {
        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }
}
