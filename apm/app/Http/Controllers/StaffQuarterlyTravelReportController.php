<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesApmPageResponses;
use App\Models\Activity;
use App\Models\Division;
use App\Models\Matrix;
use App\Models\Staff;
use App\Services\ApmPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffQuarterlyTravelReportController extends Controller
{
    use CachesApmPageResponses;

    /**
     * Only users with session role 10 (admin) may access this report.
     */
    private function authorizeRole10(): void
    {
        $user = session('user', []);
        $role = $user['role'] ?? $user['user_role'] ?? null;
        if ((int) $role !== 10) {
            abort(403, 'This report is only available to administrators.');
        }
    }

    private function currentQuarterLabel(): string
    {
        return 'Q'.(int) ceil((int) date('n') / 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPageConfig(): array
    {
        $currentYear = (int) date('Y');
        $currentQuarter = $this->currentQuarterLabel();

        $divisions = ApmPageCache::rememberLookups('staff_quarterly_travel_divisions', function () {
            return Division::orderBy('division_name')->get(['id', 'division_name']);
        });

        $staffOptions = ApmPageCache::rememberLookups('staff_quarterly_travel_staff', function () {
            return Staff::where('active', 1)
                ->orderBy('fname')
                ->orderBy('lname')
                ->get(['staff_id', 'fname', 'lname', 'division_id'])
                ->map(fn (Staff $s) => [
                    'staff_id' => (int) $s->staff_id,
                    'label' => trim(($s->fname ?? '').' '.($s->lname ?? '')),
                ])
                ->values()
                ->all();
        });

        $years = ApmPageCache::rememberLookups('staff_quarterly_travel_years', function () use ($currentYear) {
            $fromDb = Matrix::select('year')->distinct()->whereNotNull('year')->orderBy('year', 'desc')->pluck('year');
            if ($fromDb->isEmpty()) {
                return collect(range($currentYear - 3, $currentYear + 1))->values()->all();
            }
            $years = $fromDb->unique()->values();
            if (! $years->contains($currentYear)) {
                $years = $years->push($currentYear)->sortDesc()->values();
            } else {
                $years = $years->sortDesc()->values();
            }

            return $years->all();
        });

        return [
            'defaults' => [
                'year' => $currentYear,
                'quarter' => $currentQuarter,
            ],
            'currentYear' => $currentYear,
            'currentQuarter' => $currentQuarter,
            'quarters' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'divisions' => $divisions,
            'staffOptions' => $staffOptions,
            'years' => $years,
            'routes' => [
                'data' => route('reports.staff-quarterly-travel.data'),
                'exportExcel' => route('reports.staff-quarterly-travel.export.excel'),
                'exportPdf' => route('reports.staff-quarterly-travel.export.pdf'),
                'breakdown' => route('reports.staff-quarterly-travel.breakdown', ['staffId' => '__STAFF_ID__']),
                'reportsIndex' => route('reports.index'),
            ],
        ];
    }

    public function index(): View
    {
        $this->authorizeRole10();

        return view('reports.staff-quarterly-travel.index', [
            'pageConfig' => $this->buildPageConfig(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeRole10();
        $this->validateReportFilters($request);

        $keyParts = $this->apmCacheKeyFromRequest($request, [
            'division_id', 'staff_id', 'year', 'quarter', 'sort_column', 'sort_dir',
        ], ['report' => 'staff_quarterly_travel']);

        return $this->apmCachedJson('reports', $request, $keyParts, function () use ($request): array {
            $rows = $this->sortReportRows(
                $this->cachedReportRows($request),
                $request->get('sort_column', 'division_name'),
                $request->get('sort_dir', 'asc')
            );

            return [
                'success' => true,
                'data' => $rows,
                'summary' => $this->summarizeRows($rows),
            ];
        });
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorizeRole10();
        $this->validateReportFilters($request);

        $rows = $this->sortReportRows(
            $this->cachedReportRows($request),
            $request->get('sort_column', 'division_name'),
            $request->get('sort_dir', 'asc')
        );

        $filename = 'staff_quarterly_travel_'.date('Y-m-d_H-i-s').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($rows): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['#', 'Staff Name', 'Division', 'Year & Quarter', 'Number of QM Activities', 'Approved Travel Days']);
            foreach ($rows as $index => $row) {
                fputcsv($file, [
                    $index + 1,
                    $row['staff_name'] ?? '',
                    $row['division_name'] ?? '',
                    $row['year_quarter'] ?? '',
                    $row['activity_count'] ?? 0,
                    $row['approved_travel_days'] ?? 0,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeRole10();
        $this->validateReportFilters($request);

        $rows = $this->sortReportRows(
            $this->cachedReportRows($request),
            $request->get('sort_column', 'division_name'),
            $request->get('sort_dir', 'asc')
        );

        $filters = [];
        if ($request->filled('division_id')) {
            $div = Division::find($request->get('division_id'));
            $filters[] = 'Division: '.($div ? $div->division_name : $request->get('division_id'));
        }
        if ($request->filled('staff_id')) {
            $staff = Staff::find($request->get('staff_id'));
            $filters[] = 'Staff: '.($staff ? trim($staff->fname.' '.$staff->lname) : $request->get('staff_id'));
        }
        if ($request->filled('year')) {
            $filters[] = 'Year: '.$request->get('year');
        }
        if ($request->filled('quarter')) {
            $filters[] = 'Quarter: '.$request->get('quarter');
        } else {
            $filters[] = 'Quarter: All quarters';
        }

        $htmlData = [
            'rows' => $rows,
            'filters_summary' => empty($filters) ? 'None' : implode('; ', $filters),
        ];

        $mpdf = generate_pdf('reports.staff-quarterly-travel.export-pdf', $htmlData);
        $filename = 'staff_quarterly_travel_'.date('Y-m-d_H-i-s').'.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function activityBreakdown(Request $request, int $staffId): JsonResponse
    {
        $this->authorizeRole10();

        $request->validate([
            'division_id' => 'nullable|integer|exists:divisions,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'quarter' => 'nullable|string|in:Q1,Q2,Q3,Q4',
        ]);

        $keyParts = $this->apmCacheKeyFromRequest($request, [
            'division_id', 'year', 'quarter',
        ], [
            'report' => 'staff_quarterly_travel_breakdown',
            'staff_id' => $staffId,
        ]);

        return $this->apmCachedJson('reports', $request, $keyParts, function () use ($request, $staffId): array {
            $divisionId = $request->get('division_id') ? (int) $request->get('division_id') : null;
            $year = $request->get('year') ? (int) $request->get('year') : null;
            $quarter = $request->get('quarter') ?: null;

            $activities = Activity::with('matrix')
                ->where('overall_status', 'approved')
                ->whereHas('matrix', function ($q) use ($divisionId, $year, $quarter) {
                    $q->where('overall_status', 'approved');
                    if ($divisionId !== null) {
                        $q->where('division_id', $divisionId);
                    }
                    if ($year !== null) {
                        $q->where('year', $year);
                    }
                    if ($quarter !== null) {
                        $q->where('quarter', $quarter);
                    }
                })
                ->get();

            $breakdown = [];
            foreach ($activities as $activity) {
                $matrix = $activity->matrix;
                if (! $matrix) {
                    continue;
                }
                $participants = $activity->getEffectiveInternalParticipants(true);
                $staffIdStr = (string) $staffId;
                if (! isset($participants[$staffIdStr])) {
                    continue;
                }
                $days = (int) $participants[$staffIdStr];
                if ($divisionId !== null && $matrix->division_id != $divisionId) {
                    continue;
                }
                $showUrl = $activity->is_single_memo
                    ? route('activities.single-memos.show', $activity->id)
                    : route('matrices.activities.show', [$matrix->id, $activity->id]);

                $breakdown[] = [
                    'activity_id' => $activity->id,
                    'activity_title' => $activity->activity_title ?? '—',
                    'year_quarter' => $matrix->year.' '.$matrix->quarter,
                    'matrix_id' => $matrix->id,
                    'travel_days' => $days,
                    'show_url' => $showUrl,
                ];
            }

            usort($breakdown, function ($a, $b) {
                $c = strcmp($a['year_quarter'], $b['year_quarter']);

                return $c !== 0 ? $c : strcmp($a['activity_title'], $b['activity_title']);
            });

            $staff = Staff::find($staffId);
            $staffName = $staff ? trim(($staff->title ?? '').' '.($staff->fname ?? '').' '.($staff->lname ?? '')) : 'Staff #'.$staffId;

            return [
                'success' => true,
                'staff_name' => $staffName,
                'activities' => $breakdown,
            ];
        });
    }

    private function validateReportFilters(Request $request): void
    {
        $request->validate([
            'division_id' => 'nullable|integer|exists:divisions,id',
            'staff_id' => 'nullable|integer|exists:staff,staff_id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'quarter' => 'nullable|string|in:Q1,Q2,Q3,Q4',
            'sort_column' => 'nullable|string|in:staff_name,division_name,year_quarter,activity_count,approved_travel_days',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cachedReportRows(Request $request): array
    {
        $keyParts = $this->apmCacheKeyFromRequest($request, [
            'division_id', 'staff_id', 'year', 'quarter',
        ], ['report' => 'staff_quarterly_travel_rows']);

        return ApmPageCache::remember('reports', $keyParts, function () use ($request): array {
            return $this->buildReportData(
                $request->get('division_id') ? (int) $request->get('division_id') : null,
                $request->get('staff_id') ? (int) $request->get('staff_id') : null,
                $request->get('year') ? (int) $request->get('year') : null,
                $request->get('quarter') ?: null
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortReportRows(array $rows, ?string $sortColumn, ?string $sortDir): array
    {
        $sortColumn = $sortColumn ?: 'division_name';
        $sortDir = strtolower((string) $sortDir) === 'desc' ? 'desc' : 'asc';

        usort($rows, function ($a, $b) use ($sortColumn, $sortDir) {
            $va = $a[$sortColumn] ?? '';
            $vb = $b[$sortColumn] ?? '';
            if (in_array($sortColumn, ['activity_count', 'approved_travel_days'], true)) {
                $cmp = ((int) $va) <=> ((int) $vb);
            } else {
                $cmp = strcmp((string) $va, (string) $vb);
            }

            return $sortDir === 'desc' ? -$cmp : $cmp;
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summarizeRows(array $rows): array
    {
        $staffIds = [];
        $totalDays = 0;
        $totalActivities = 0;
        foreach ($rows as $row) {
            $staffIds[(int) ($row['staff_id'] ?? 0)] = true;
            $totalDays += (int) ($row['approved_travel_days'] ?? 0);
            $totalActivities += (int) ($row['activity_count'] ?? 0);
        }
        unset($staffIds[0]);

        return [
            'total_rows' => count($rows),
            'staff_count' => count($staffIds),
            'total_travel_days' => $totalDays,
            'total_activities' => $totalActivities,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildReportData(?int $divisionId, ?int $staffId, ?int $year, ?string $quarter): array
    {
        $activities = Activity::with('matrix')
            ->where('overall_status', 'approved')
            ->whereHas('matrix', function ($q) use ($divisionId, $year, $quarter) {
                $q->where('overall_status', 'approved');
                if ($divisionId !== null) {
                    $q->where('division_id', $divisionId);
                }
                if ($year !== null) {
                    $q->where('year', $year);
                }
                if ($quarter !== null) {
                    $q->where('quarter', $quarter);
                }
            })
            ->get();

        $byStaff = [];

        foreach ($activities as $activity) {
            $matrix = $activity->matrix;
            if (! $matrix) {
                continue;
            }
            $yq = $matrix->year.' '.$matrix->quarter;

            $participants = $activity->getEffectiveInternalParticipants(true);
            if (empty($participants)) {
                continue;
            }

            foreach ($participants as $pid => $days) {
                $pid = (int) $pid;
                if ($staffId !== null && $pid !== $staffId) {
                    continue;
                }
                if (! isset($byStaff[$pid])) {
                    $byStaff[$pid] = [];
                }
                if (! isset($byStaff[$pid][$yq])) {
                    $byStaff[$pid][$yq] = ['activities' => 0, 'days' => 0];
                }
                $byStaff[$pid][$yq]['activities']++;
                $byStaff[$pid][$yq]['days'] += $days;
            }
        }

        $staffIds = array_values(array_unique(array_filter(array_map('intval', array_keys($byStaff)), fn ($id) => $id > 0)));
        $staffById = $staffIds ? Staff::with('division')->whereIn('staff_id', $staffIds)->get()->keyBy('staff_id') : collect();
        $rows = [];

        foreach ($byStaff as $sid => $yqData) {
            $staff = $staffIds ? $staffById->get($sid) : null;
            $divisionName = $staff && $staff->relationLoaded('division') && $staff->division
                ? $staff->division->division_name
                : ($staff ? ($staff->division_name ?? 'N/A') : 'N/A');
            if ($divisionId !== null && $staff && (int) $staff->division_id !== $divisionId) {
                continue;
            }
            $staffName = $staff ? trim(($staff->title ?? '').' '.($staff->fname ?? '').' '.($staff->lname ?? '')) : 'Staff #'.$sid;

            foreach ($yqData as $yq => $agg) {
                $rows[] = [
                    'staff_id' => $sid,
                    'staff_name' => $staffName,
                    'division_name' => $divisionName,
                    'year_quarter' => $yq,
                    'activity_count' => $agg['activities'],
                    'approved_travel_days' => (int) $agg['days'],
                ];
            }
        }

        usort($rows, function ($a, $b) {
            $c = strcmp($a['division_name'], $b['division_name']);
            if ($c !== 0) {
                return $c;
            }
            $c = strcmp($a['staff_name'], $b['staff_name']);
            if ($c !== 0) {
                return $c;
            }

            return strcmp($a['year_quarter'], $b['year_quarter']);
        });

        return $rows;
    }
}
