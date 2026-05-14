<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StaffPortalReferenceClient;
use App\Support\StaffShareNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Proxies Staff Share API for divisions / directorates / staff with the same shapes as
 * the APM reference-data lists (directorates include director_id and director summary), and similar fetch cadence via short-lived server cache (default 300s, configurable).
 */
class ReferenceDataController extends Controller
{
    /** Any authenticated user with a Staff-linked profile may load directory data (ticket create: self or on-behalf). */
    private function ensureStaffDirectoryAccess(Request $request): void
    {
        $p = $request->user()?->helpdeskProfile;
        abort_unless(
            $p && $p->staff_id,
            403,
            'A Staff-linked helpdesk profile is required to load directory reference data.'
        );
    }

    public function index(Request $request, StaffPortalReferenceClient $client): JsonResponse
    {
        $this->ensureStaffDirectoryAccess($request);

        if (! $client->isConfigured()) {
            return response()->json([
                'message' => 'Staff API credentials are not configured. Set HELPDESK_STAFF_API_USERNAME and HELPDESK_STAFF_API_PASSWORD (same as APM STAFF_API_*).',
            ], 503);
        }

        $ttl = max(30, (int) config('helpdesk.reference_data_cache_ttl', 300));

        $bundle = Cache::remember('helpdesk_reference_bundle_v1', $ttl, function () use ($client) {
            $divisions = array_map(fn (array $r) => StaffShareNormalizer::division($r), $client->fetchDivisions());
            $directorates = array_map(fn (array $r) => StaffShareNormalizer::directorate($r), $client->fetchDirectorates());

            return [
                'divisions' => $divisions,
                'directorates' => $directorates,
            ];
        });

        return response()->json([
            'data' => $bundle,
            'meta' => [
                'cache_ttl_seconds' => $ttl,
                'source' => 'staff_share_api',
            ],
        ]);
    }

    public function staff(Request $request, StaffPortalReferenceClient $client): JsonResponse
    {
        $this->ensureStaffDirectoryAccess($request);

        if (! $client->isConfigured()) {
            return response()->json([
                'message' => 'Staff API credentials are not configured.',
            ], 503);
        }

        $ttl = max(30, (int) config('helpdesk.reference_data_cache_ttl', 300));
        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);

        $staffRows = Cache::remember('helpdesk_reference_staff_v1_'.$limit, $ttl, function () use ($client, $limit) {
            return $client->fetchStaff($limit, 0);
        });

        $bundle = Cache::get('helpdesk_reference_bundle_v1');
        if (! is_array($bundle) || empty($bundle['divisions'])) {
            $bundle = [
                'divisions' => array_map(fn (array $r) => StaffShareNormalizer::division($r), $client->fetchDivisions()),
                'directorates' => array_map(fn (array $r) => StaffShareNormalizer::directorate($r), $client->fetchDirectorates()),
            ];
        }

        /** @var Collection<int, array<string, mixed>> $divisionById */
        $divisionById = collect($bundle['divisions'])->keyBy('id');

        $normalized = [];
        foreach ($staffRows as $raw) {
            $row = StaffShareNormalizer::staff(is_array($raw) ? $raw : (array) $raw);
            $div = $divisionById->get((int) ($row['division_id'] ?? 0));
            $row['directorate_id'] = $div['directorate_id'] ?? null;
            $normalized[] = $row;
        }

        $directorateId = $request->integer('directorate_id');
        $divisionId = $request->integer('division_id');
        $q = strtolower(trim((string) $request->query('q', '')));

        $filtered = collect($normalized)->filter(function (array $row) use ($directorateId, $divisionId, $q) {
            if ($divisionId > 0 && (int) ($row['division_id'] ?? 0) !== $divisionId) {
                return false;
            }
            if ($directorateId > 0 && (int) ($row['directorate_id'] ?? 0) !== $directorateId) {
                return false;
            }
            if ($q !== '') {
                $hay = strtolower(
                    ($row['name'] ?? '')
                    .' '.($row['work_email'] ?? '')
                    .' '.($row['duty_station_name'] ?? '')
                    .' '.(string) ($row['id'] ?? '')
                );

                return str_contains($hay, $q);
            }

            return true;
        })->values()->all();

        return response()->json([
            'data' => [
                'staff' => $filtered,
            ],
            'meta' => [
                'cache_ttl_seconds' => $ttl,
                'total_cached' => count($normalized),
                'returned' => count($filtered),
                'source' => 'staff_share_api',
            ],
        ]);
    }
}
