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
        $data = $this->normalizePortalUrls($data);

        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array{home: array<string, mixed>, modules: list<array<string, mixed>>}  $data
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    private function normalizePortalUrls(array $data): array
    {
        if (! app()->runningInConsole() && request()->hasHeader('Host')) {
            $host = strtolower((string) request()->getHost());
            if ($host === 'localhost' || $host === '127.0.0.1') {
                $scheme = request()->getScheme();
                $port = request()->getPort();
                $origin = $scheme.'://'.$host.($port && ! in_array($port, [80, 443], true) ? ':'.$port : '');

                if (isset($data['home']['href']) && is_string($data['home']['href'])) {
                    $data['home']['href'] = $this->rewriteStaffOrigin((string) $data['home']['href'], $origin);
                }
                foreach ($data['modules'] as $i => $module) {
                    if (isset($module['href']) && is_string($module['href'])) {
                        $data['modules'][$i]['href'] = $this->rewriteStaffOrigin((string) $module['href'], $origin);
                    }
                    if (isset($module['launch_url']) && is_string($module['launch_url'])) {
                        $data['modules'][$i]['launch_url'] = $this->rewriteStaffOrigin((string) $module['launch_url'], $origin);
                    }
                }
            }
        }

        return $data;
    }

    private function rewriteStaffOrigin(string $url, string $origin): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['path'])) {
            return $url;
        }
        $path = str_starts_with((string) $parts['path'], '/') ? (string) $parts['path'] : '/'.$parts['path'];
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return rtrim($origin, '/').$path.$query;
    }
}
