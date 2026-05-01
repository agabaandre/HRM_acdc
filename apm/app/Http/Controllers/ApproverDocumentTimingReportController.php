<?php

namespace App\Http\Controllers;

use App\Models\ApproverDocumentTimingRecord;
use App\Models\Division;
use App\Models\Staff;
use App\Services\ApproverDocumentTimingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApproverDocumentTimingReportController extends Controller
{
    public function __construct(
        protected ApproverDocumentTimingService $timingService
    ) {}

    protected function ensurePermission88(): void
    {
        $perms = user_session('permissions', []) ?? [];
        abort_unless(in_array(88, $perms, true), 403, 'You do not have access to this report.');
    }

    public function index(Request $request): View
    {
        $this->ensurePermission88();

        $staffId = $request->filled('staff_id') ? (int) $request->staff_id : null;
        $divisionId = $request->filled('division_id') ? (int) $request->division_id : null;
        $documentType = $request->filled('document_type') ? (string) $request->document_type : null;
        $year = $request->filled('year') ? (int) $request->year : null;
        $month = $request->filled('month') ? (int) $request->month : null;
        $search = $request->filled('q') ? trim((string) $request->q) : null;

        $baseQuery = ApproverDocumentTimingRecord::query()
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId))
            ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
            ->when($documentType, fn ($q) => $q->where('document_type_label', $documentType))
            ->when($year, fn ($q) => $q->whereYear('acted_at', $year))
            ->when($month, fn ($q) => $q->whereMonth('acted_at', $month))
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

        return view('reports.approver-document-timing.index', [
            'records' => $records,
            'staffOptions' => $staffOptions,
            'divisions' => $divisions,
            'documentTypes' => $documentTypes,
            'filters' => [
                'staff_id' => $staffId,
                'division_id' => $divisionId,
                'document_type' => $documentType,
                'year' => $year,
                'month' => $month,
                'q' => $search,
            ],
            'summary' => [
                'total_rows' => $totalRows,
                'avg_hours' => $avgHours,
                'total_hours' => $totalHours,
                'avg_display' => $avgHours === null ? '—' : $this->formatHoursForDisplay((float) $avgHours),
            ],
            'timingService' => $this->timingService,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $this->ensurePermission88();

        $staffId = $request->filled('staff_id') ? (int) $request->staff_id : null;
        $divisionId = $request->filled('division_id') ? (int) $request->division_id : null;
        $documentType = $request->filled('document_type') ? (string) $request->document_type : null;
        $year = $request->filled('year') ? (int) $request->year : null;
        $month = $request->filled('month') ? (int) $request->month : null;
        $search = $request->filled('q') ? trim((string) $request->q) : null;

        $query = ApproverDocumentTimingRecord::query()
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId))
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
