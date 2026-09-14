<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesApmPageResponses;
use App\Models\ApproverDocumentTimingRecord;
use App\Models\Division;
use App\Models\Staff;
use App\Services\ApproverDocumentTimingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApproverDocumentTimingReportController extends Controller
{
    use CachesApmPageResponses;

    public function __construct(
        protected ApproverDocumentTimingService $timingService
    ) {}

    public function index(Request $request): View
    {
        approver_timing_report_authorize_web_request($request);

        return $this->apmCachedView(
            'reports',
            $request,
            'reports.approver-document-timing.index',
            ['staff_id', 'division_id', 'document_type', 'year', 'month', 'year_week', 'q', 'page'],
            function () use ($request): array {
                $staffId = approver_timing_report_effective_staff_id($request);
                $divisionId = $request->filled('division_id') ? (int) $request->division_id : null;
                $documentType = $request->filled('document_type') ? (string) $request->document_type : null;
                $year = $request->filled('year') ? (int) $request->year : null;
                $month = $request->filled('month') ? (int) $request->month : null;
                $yearWeek = $request->filled('year_week') ? (int) $request->year_week : null;
                $search = $request->filled('q') ? trim((string) $request->q) : null;

                $baseQuery = ApproverDocumentTimingRecord::query()
                    ->when($staffId !== null && $staffId > 0, fn ($q) => $q->where('staff_id', $staffId))
                    ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
                    ->when($documentType, fn ($q) => $q->where('document_type_label', $documentType))
                    ->when($yearWeek, fn ($q) => $q->whereRaw('YEARWEEK(acted_at, 3) = ?', [$yearWeek]))
                    ->when(! $yearWeek && $year, fn ($q) => $q->whereYear('acted_at', $year))
                    ->when(! $yearWeek && $month, fn ($q) => $q->whereMonth('acted_at', $month))
                    ->when($search, function ($q) use ($search): void {
                        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                        $q->where(function ($q2) use ($like): void {
                            $q2->where('document_title', 'like', $like)
                                ->orWhere('document_number_snapshot', 'like', $like)
                                ->orWhere('staff_name_snapshot', 'like', $like)
                                ->orWhere('workflow_role_snapshot', 'like', $like);
                        });
                    });

                $summaryQuery = clone $baseQuery;

                $records = (clone $baseQuery)
                    ->orderByDesc('acted_at')
                    ->paginate(40)
                    ->withQueryString();

                $totalRows = (clone $summaryQuery)->count();
                $avgHours = null;
                $totalHours = 0.0;
                if ($totalRows > 0) {
                    $avgHours = round((float) (clone $summaryQuery)->avg('hours_elapsed'), 2);
                    $totalHours = round((float) (clone $summaryQuery)->sum('hours_elapsed'), 2);
                }

                $staffIdsWithData = ApproverDocumentTimingRecord::query()->distinct()->orderBy('staff_id')->pluck('staff_id');
                if (! approver_timing_report_can_view_all()) {
                    $ownId = (int) user_session('staff_id');
                    $staffIdsWithData = $staffIdsWithData->filter(fn ($id): bool => (int) $id === $ownId)->values();
                }

                $staffOptions = Staff::query()
                    ->whereIn('staff_id', $staffIdsWithData)
                    ->orderBy('fname')
                    ->orderBy('lname')
                    ->get();

                $documentTypes = ApproverDocumentTimingRecord::query()
                    ->whereNotNull('document_type_label')
                    ->distinct()
                    ->orderBy('document_type_label')
                    ->pluck('document_type_label');

                $divisions = Division::orderBy('division_name')->get();

                $totalElapsedParts = format_approver_timing_elapsed_display($totalHours);

                $recordRows = $records->getCollection()->map(function ($r) {
                    $docUrl = $this->timingService->resolveDocumentUrl($r->model_type, (int) $r->model_id);
                    $elapsedParts = format_approver_timing_elapsed_display($r->hours_elapsed);

                    return [
                        'staff_id' => (int) $r->staff_id,
                        'staff_name' => $r->staff_name_snapshot ?: 'Staff #'.$r->staff_id,
                        'document_type_label' => $r->document_type_label,
                        'document_title' => strip_tags($r->document_title ?? '—'),
                        'document_number' => $r->document_number_snapshot,
                        'division_name' => $r->division_name_snapshot ?? '—',
                        'workflow_name' => $r->workflow_name_snapshot ?? '—',
                        'workflow_role' => $r->workflow_role_snapshot ?? ('Level '.($r->approval_order ?? '—')),
                        'received_at' => $r->received_at?->format('Y-m-d H:i'),
                        'acted_at' => $r->acted_at?->format('Y-m-d H:i'),
                        'elapsed_hours' => $elapsedParts['hours_formatted'],
                        'elapsed_days' => $elapsedParts['days_formatted'],
                        'doc_url' => $docUrl ? url($docUrl) : null,
                    ];
                })->values()->all();

                $yearOpts = range((int) date('Y'), (int) date('Y') - 8);

                return [
                    'pageConfig' => [
                        'reportFullAccess' => approver_timing_report_can_view_all(),
                        'sessionStaffId' => (int) user_session('staff_id'),
                        'filters' => [
                            'staff_id' => $staffId,
                            'division_id' => $divisionId,
                            'document_type' => $documentType,
                            'year' => $year,
                            'month' => $month,
                            'year_week' => $yearWeek,
                            'q' => $search,
                        ],
                        'summary' => [
                            'total_rows' => $totalRows,
                            'avg_hours' => $avgHours,
                            'total_hours' => $totalHours,
                            'avg_display' => $avgHours === null ? '—' : $this->formatHoursForDisplay((float) $avgHours),
                            'total_elapsed_hours' => $totalElapsedParts['hours_formatted'],
                            'total_elapsed_days' => $totalElapsedParts['days_formatted'],
                        ],
                        'staffOptions' => $staffOptions->map(fn ($s) => [
                            'staff_id' => (int) $s->staff_id,
                            'label' => trim(($s->title ? $s->title.' ' : '').$s->fname.' '.$s->lname).' ('.$s->staff_id.')',
                        ])->values()->all(),
                        'divisions' => $divisions->map(fn ($d) => [
                            'id' => (int) $d->id,
                            'name' => $d->division_name,
                        ])->values()->all(),
                        'documentTypes' => $documentTypes->values()->all(),
                        'years' => $yearOpts,
                        'months' => [
                            ['value' => '', 'title' => 'Any month'],
                            ['value' => 1, 'title' => 'January'],
                            ['value' => 2, 'title' => 'February'],
                            ['value' => 3, 'title' => 'March'],
                            ['value' => 4, 'title' => 'April'],
                            ['value' => 5, 'title' => 'May'],
                            ['value' => 6, 'title' => 'June'],
                            ['value' => 7, 'title' => 'July'],
                            ['value' => 8, 'title' => 'August'],
                            ['value' => 9, 'title' => 'September'],
                            ['value' => 10, 'title' => 'October'],
                            ['value' => 11, 'title' => 'November'],
                            ['value' => 12, 'title' => 'December'],
                        ],
                        'records' => $recordRows,
                        'pagination' => [
                            'current_page' => $records->currentPage(),
                            'last_page' => $records->lastPage(),
                            'per_page' => $records->perPage(),
                            'total' => $records->total(),
                            'from' => $records->firstItem(),
                            'to' => $records->lastItem(),
                        ],
                        'routes' => [
                            'index' => route('reports.approver-document-timing.index'),
                            'export' => route('reports.approver-document-timing.export'),
                            'trend' => route('reports.approver-document-timing.trend'),
                            'reportsIndex' => route('reports.index'),
                        ],
                    ],
                ];
            },
            ['report' => 'approver_document_timing']
        );
    }

    public function trend(Request $request): JsonResponse
    {
        approver_timing_report_authorize_web_request($request);

        $staffId = approver_timing_report_effective_staff_id($request);
        $divisionId = $request->filled('division_id') ? (int) $request->division_id : null;
        $documentType = $request->filled('document_type') ? (string) $request->document_type : null;
        $year = $request->filled('year') ? (int) $request->year : null;
        $month = $request->filled('month') ? (int) $request->month : null;
        $search = $request->filled('q') ? trim((string) $request->q) : null;
        $granularity = $request->get('granularity') === 'weekly' ? 'weekly' : 'monthly';

        $filters = array_filter([
            'staff_id' => $staffId,
            'division_id' => $divisionId,
            'document_type' => $documentType,
            'year' => $year,
            'month' => $month,
            'q' => $search,
        ], fn ($v) => $v !== null && $v !== '');

        $points = $this->timingService->averageHoursTrend($granularity, $filters);

        return response()->json([
            'success' => true,
            'granularity' => $granularity,
            'data' => $points,
        ]);
    }

    public function exportCsv(Request $request)
    {
        approver_timing_report_authorize_web_request($request);

        $staffId = approver_timing_report_effective_staff_id($request);
        $divisionId = $request->filled('division_id') ? (int) $request->division_id : null;
        $documentType = $request->filled('document_type') ? (string) $request->document_type : null;
        $year = $request->filled('year') ? (int) $request->year : null;
        $month = $request->filled('month') ? (int) $request->month : null;
        $search = $request->filled('q') ? trim((string) $request->q) : null;

        $query = ApproverDocumentTimingRecord::query()
            ->when($staffId !== null && $staffId > 0, fn ($q) => $q->where('staff_id', $staffId))
            ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
            ->when($documentType, fn ($q) => $q->where('document_type_label', $documentType))
            ->when($year, fn ($q) => $q->whereYear('acted_at', $year))
            ->when($month, fn ($q) => $q->whereMonth('acted_at', $month))
            ->when($search, function ($q) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $q->where(function ($q2) use ($like): void {
                    $q2->where('document_title', 'like', $like)
                        ->orWhere('document_number_snapshot', 'like', $like)
                        ->orWhere('staff_name_snapshot', 'like', $like);
                });
            })
            ->orderBy('id');

        $filename = 'average_time_per_document_'.date('Y-m-d_H-i-s').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($query): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, [
                'Approver staff ID',
                'Approver name (snapshot)',
                'Document type',
                'Document #',
                'Title',
                'Division',
                'Workflow',
                'Role / step',
                'Approval order',
                'Action',
                'Received at (UTC)',
                'Acted at (UTC)',
                'Hours elapsed',
            ]);

            $query->chunkById(500, function ($rows) use ($file): void {
                foreach ($rows as $r) {
                    fputcsv($file, [
                        $r->staff_id,
                        $r->staff_name_snapshot,
                        $r->document_type_label,
                        $r->document_number_snapshot,
                        $r->document_title,
                        $r->division_name_snapshot,
                        $r->workflow_name_snapshot,
                        $r->workflow_role_snapshot,
                        $r->approval_order,
                        $r->action,
                        $r->received_at?->toIso8601String(),
                        $r->acted_at?->toIso8601String(),
                        $r->hours_elapsed,
                    ]);
                }
            }, 'id');
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function formatHoursForDisplay(float $hours): string
    {
        if ($hours <= 0) {
            return '—';
        }
        if ($hours < 1) {
            return max(1, (int) round($hours * 60)).' min';
        }
        if ($hours < 24) {
            return round($hours, 1).' hrs';
        }

        return round($hours / 24, 1).' days';
    }
}
