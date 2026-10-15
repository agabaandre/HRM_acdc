<?php

namespace Modules\Dashboard\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use App\Support\PortalReferenceCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Services\CsvExportService;
use Modules\Core\Services\PdfService;
use Modules\Core\Support\PortalPermission;
use Modules\Dashboard\Services\DashboardService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardApiController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        PortalPermission::authorize(76);

        $divisionId = $request->filled('division_id') ? (int) $request->query('division_id') : null;
        $dutyStationId = $request->filled('duty_station_id') ? (int) $request->query('duty_station_id') : null;
        $funderId = $request->filled('funder_id') ? (int) $request->query('funder_id') : null;
        $jobId = $request->filled('job_id') ? (int) $request->query('job_id') : null;

        $user = $request->user();
        $userId = $user instanceof PortalUser ? (int) $user->getAuthIdentifier() : 0;
            $cacheKey = PortalReadCache::key('dashboard', 'snapshot-maps', $userId, [
            'division_id' => $divisionId,
            'duty_station_id' => $dutyStationId,
            'funder_id' => $funderId,
            'job_id' => $jobId,
        ]);

        // Cache chart/KPI payload separately from filter dropdowns so a large jobs
        // list is not rebuilt/serialized inside every dashboard snapshot miss.
        $data = PortalReadCache::remember($cacheKey, function () use (
            $dashboard,
            $divisionId,
            $dutyStationId,
            $funderId,
            $jobId,
        ): array {
            $payload = $dashboard->getDashboardData($divisionId, $dutyStationId, $funderId, $jobId);
            $payload['birthdays'] = $dashboard->birthdayEvents($divisionId, $dutyStationId, $funderId, $jobId);

            return $payload;
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'filters' => $this->filterOptions(),
                'exports' => [
                    'pdf' => url('/api/v1/dashboard/export/pdf'),
                    'csv' => url('/api/v1/dashboard/export/csv'),
                ],
            ],
        ]);
    }

    public function exportPdf(Request $request, DashboardService $dashboard, PdfService $pdf): Response
    {
        PortalPermission::authorize(76);

        $data = $dashboard->getDashboardData(
            $request->filled('division_id') ? (int) $request->query('division_id') : null,
            $request->filled('duty_station_id') ? (int) $request->query('duty_station_id') : null,
            $request->filled('funder_id') ? (int) $request->query('funder_id') : null,
            $request->filled('job_id') ? (int) $request->query('job_id') : null,
        );

        $html = view('dashboard::pdf.snapshot', ['data' => $data, 'generatedAt' => now()->toDateTimeString()])->render();

        return $pdf->inline($html, 'staff-dashboard.pdf', [
            'title' => 'Staff Dashboard',
            'document_url' => url('/staff/staff-portal/dashboard'),
            'landscape' => true,
        ]);
    }

    public function exportCsv(Request $request, DashboardService $dashboard, CsvExportService $csv): StreamedResponse
    {
        PortalPermission::authorize(76);

        $data = $dashboard->getDashboardData(
            $request->filled('division_id') ? (int) $request->query('division_id') : null,
            $request->filled('duty_station_id') ? (int) $request->query('duty_station_id') : null,
            $request->filled('funder_id') ? (int) $request->query('funder_id') : null,
            $request->filled('job_id') ? (int) $request->query('job_id') : null,
        );

        $rows = [
            ['Metric', 'Value'],
            ['Active staff', $data['staff']],
            ['Contracts due (2 months)', $data['two_months']],
            ['Under renewal', $data['staff_renewal']],
            ['Expired', $data['expired']],
        ];
        foreach ($data['staff_by_division']['division'] ?? [] as $i => $label) {
            $rows[] = ['Division: '.$label, $data['staff_by_division']['value'][$i] ?? 0];
        }
        foreach ($data['staff_by_duty_station_map']['points'] ?? [] as $point) {
            $rows[] = ['Duty station country: '.($point['name'] ?? $point['iso2'] ?? ''), $point['value'] ?? 0];
        }
        foreach ($data['staff_by_nationality_map']['points'] ?? [] as $point) {
            $rows[] = ['Nationality: '.($point['name'] ?? $point['iso2'] ?? ''), $point['value'] ?? 0];
        }

        return $csv->stream('staff-dashboard.csv', $rows);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterOptions(): array
    {
        return PortalReferenceCache::remember('dashboard:filter-options:v2', function (): array {
            return [
                'divisions' => DB::table('divisions')->orderBy('division_name')->get(['division_id', 'division_name']),
                'duty_stations' => DB::table('duty_stations')->orderBy('duty_station_name')->get(['duty_station_id', 'duty_station_name']),
                'funders' => DB::table('funders')->orderBy('funder')->get(['funder_id', 'funder']),
                // Jobs are loaded lazily by the SPA (large list; was ~70% of payload).
                'jobs' => [],
            ];
        });
    }

    public function filterJobs(): JsonResponse
    {
        PortalPermission::authorize(76);

        $jobs = PortalReferenceCache::remember('dashboard:filter-jobs:v1', function () {
            return DB::table('jobs')->orderBy('job_name')->limit(500)->get(['job_id', 'job_name']);
        });

        return response()->json(['data' => $jobs]);
    }
}
