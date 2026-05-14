<?php

namespace App\Services;

use App\Http\Controllers\Api\V1\ReferenceDataController;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Invalidates and repopulates Staff Share reference caches (divisions, directorates, staff)
 * used by {@see ReferenceDataController}.
 */
class ReferenceDataSyncService
{
    public function flushCaches(): void
    {
        Cache::forget('helpdesk_reference_bundle_v1');
        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);
        Cache::forget('helpdesk_reference_staff_v1_'.$limit);
    }

    /**
     * @return array{divisions:int,directorates:int,staff_rows:int}
     */
    public function warmCaches(StaffPortalReferenceClient $client): array
    {
        if (! $client->isConfigured()) {
            throw new RuntimeException('Staff API credentials are not configured.');
        }

        $ttl = max(30, (int) config('helpdesk.reference_data_cache_ttl', 300));
        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);

        $bundle = Cache::remember('helpdesk_reference_bundle_v1', $ttl, function () use ($client) {
            $divisions = array_map([$this, 'normalizeDivision'], $client->fetchDivisions());
            $directorates = array_map([$this, 'normalizeDirectorate'], $client->fetchDirectorates());

            return [
                'divisions' => $divisions,
                'directorates' => $directorates,
            ];
        });

        $rawStaff = Cache::remember('helpdesk_reference_staff_v1_'.$limit, $ttl, function () use ($client, $limit) {
            return $client->fetchStaff($limit, 0);
        });

        return [
            'divisions' => count($bundle['divisions']),
            'directorates' => count($bundle['directorates']),
            'staff_rows' => is_array($rawStaff) ? count($rawStaff) : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{id:int,name:string,short_name:?string,directorate_id:?int}
     */
    private function normalizeDivision(array $r): array
    {
        $id = (int) ($r['division_id'] ?? $r['id'] ?? 0);

        return [
            'id' => $id,
            'name' => (string) ($r['division_name'] ?? $r['name'] ?? ''),
            'short_name' => isset($r['division_short_name']) ? (string) $r['division_short_name'] : (isset($r['short_name']) ? (string) $r['short_name'] : null),
            'directorate_id' => isset($r['directorate_id']) ? (int) $r['directorate_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{id:int,name:string}
     */
    private function normalizeDirectorate(array $r): array
    {
        $id = (int) ($r['directorate_id'] ?? $r['id'] ?? 0);

        return [
            'id' => $id,
            'name' => (string) ($r['name'] ?? $r['directorate_name'] ?? ''),
        ];
    }
}
