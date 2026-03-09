<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\Division;
use App\Models\Matrix;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffQuarterlyTravelReportController extends Controller
{
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

    /**
     * Show the staff quarterly travel report page.
     */
    public function index(): View
    {
        $this->authorizeRole10();

        $divisions = Division::orderBy('division_name')->get(['id', 'division_name']);
        $staffList = Staff::where('active', 1)->orderBy('fname')->orderBy('lname')->get(['staff_id', 'fname', 'lname', 'division_id']);
        $currentYear = (int) date('Y');
        $years = Matrix::select('year')->distinct()->whereNotNull('year')->orderBy('year', 'desc')->pluck('year');
        if ($years->isEmpty()) {
            $years = collect(range($currentYear - 3, $currentYear + 1));
        } else {
            $years = $years->unique()->values();
            if (!$years->contains($currentYear)) {
                $years = $years->push($currentYear)->sortDesc()->values();
            } else {
                $years = $years->sortDesc()->values();
            }
        }
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];

        return view('reports.staff-quarterly-travel.index', compact('divisions', 'staffList', 'years', 'quarters', 'currentYear'));
    }

    /**
     * Get report data (JSON) for the table.
     */
    public function data(Request $request)
    {
        $this->authorizeRole10();

        $request->validate([
            'division_id' => 'nullable|integer|exists:divisions,id',
            'staff_id' => 'nullable|integer|exists:staff,staff_id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'quarter' => 'nullable|string|in:Q1,Q2,Q3,Q4',
        ]);

        $rows = $this->buildReportData(
            $request->get('division_id') ? (int) $request->get('division_id') : null,
            $request->get('staff_id') ? (int) $request->get('staff_id') : null,
            $request->get('year') ? (int) $request->get('year') : null,
            $request->get('quarter') ?: null
        );

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * Export report to CSV.
     */
    public function exportExcel(Request $request)
    {
        $this->authorizeRole10();

        $rows = $this->buildReportData(
            $request->get('division_id') ? (int) $request->get('division_id') : null,
            $request->get('staff_id') ? (int) $request->get('staff_id') : null,
            $request->get('year') ? (int) $request->get('year') : null,
            $request->get('quarter') ?: null
        );

        $filename = 'staff_quarterly_travel_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
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

    /**
     * Export report to PDF using mPDF.
     */
    public function exportPdf(Request $request)
    {
        $this->authorizeRole10();

        $rows = $this->buildReportData(
            $request->get('division_id') ? (int) $request->get('division_id') : null,
            $request->get('staff_id') ? (int) $request->get('staff_id') : null,
            $request->get('year') ? (int) $request->get('year') : null,
            $request->get('quarter') ?: null
        );

        $filters = [];
        if ($request->filled('division_id')) {
            $div = Division::find($request->get('division_id'));
            $filters[] = 'Division: ' . ($div ? $div->division_name : $request->get('division_id'));
        }
        if ($request->filled('staff_id')) {
            $staff = Staff::find($request->get('staff_id'));
            $filters[] = 'Staff: ' . ($staff ? trim($staff->fname . ' ' . $staff->lname) : $request->get('staff_id'));
        }
        if ($request->filled('year')) {
            $filters[] = 'Year: ' . $request->get('year');
        }
        if ($request->filled('quarter')) {
            $filters[] = 'Quarter: ' . $request->get('quarter');
        }
        $filtersSummary = empty($filters) ? 'None' : implode('; ', $filters);

        $htmlData = [
            'rows' => $rows,
            'filters_summary' => $filtersSummary,
        ];

        $mpdf = generate_pdf('reports.staff-quarterly-travel.export-pdf', $htmlData);
        $filename = 'staff_quarterly_travel_' . date('Y-m-d_H-i-s') . '.pdf';
        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Build aggregated report data: staff × year-quarter with activity count and approved travel days.
     * Only activities whose own overall_status = 'approved' are included (single memos and matrix
     * activities use their individual status, not the matrix). Matrices must also be approved.
     * If an activity has an approved change request, use the change request's internal_participants.
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

        // Aggregate: (staff_id => [ 'year_quarter' => [ 'activities' => count, 'days' => sum ] ] )
        $byStaff = [];

        foreach ($activities as $activity) {
            $matrix = $activity->matrix;
            if (!$matrix) {
                continue;
            }
            $yq = $matrix->year . ' ' . $matrix->quarter;

            $participants = $this->getEffectiveInternalParticipants($activity);
            if (empty($participants)) {
                continue;
            }

            foreach ($participants as $pid => $days) {
                $pid = (int) $pid;
                if ($staffId !== null && $pid !== $staffId) {
                    continue;
                }
                if (!isset($byStaff[$pid])) {
                    $byStaff[$pid] = [];
                }
                if (!isset($byStaff[$pid][$yq])) {
                    $byStaff[$pid][$yq] = ['activities' => 0, 'days' => 0];
                }
                $byStaff[$pid][$yq]['activities']++;
                $byStaff[$pid][$yq]['days'] += $days;
            }
        }

        // Flatten to rows and attach staff/division names (only positive staff_ids)
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
            $staffName = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '')) : 'Staff #' . $sid;

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

    /**
     * Activity breakdown for a staff member (for modal). Uses same filters as report; considers
     * most recent approved change request for each activity when present.
     */
    public function activityBreakdown(Request $request, int $staffId)
    {
        $this->authorizeRole10();

        $request->validate([
            'division_id' => 'nullable|integer|exists:divisions,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'quarter' => 'nullable|string|in:Q1,Q2,Q3,Q4',
        ]);

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
            if (!$matrix) {
                continue;
            }
            $participants = $this->getEffectiveInternalParticipants($activity);
            $staffIdStr = (string) $staffId;
            if (!isset($participants[$staffIdStr])) {
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
                'year_quarter' => $matrix->year . ' ' . $matrix->quarter,
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
        $staffName = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '')) : 'Staff #' . $staffId;

        return response()->json([
            'success' => true,
            'staff_name' => $staffName,
            'activities' => $breakdown,
        ]);
    }

    /**
     * Get effective internal_participants for an activity.
     * If there is an approved change request for this activity, use its internal_participants; else use activity's.
     * Returns array keyed by staff_id (int) with participant_days (int) as value.
     * Handles both formats: object keyed by staff_id {"230":{...}} and array of objects [{staff_id:230,...}].
     */
    private function getEffectiveInternalParticipants(Activity $activity): array
    {
        $cr = ChangeRequest::where('activity_id', $activity->id)
            ->where('overall_status', 'approved')
            ->orderBy('id', 'desc')
            ->first();

        $raw = $cr ? $cr->internal_participants : $activity->internal_participants;

        // Ensure we have an array (handle string/double-encoded JSON)
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $isList = array_keys($raw) === range(0, count($raw) - 1);

        if ($isList) {
            // Array of objects: [{"staff_id": 230, "participant_days": "1", ...}, ...]
            foreach ($raw as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $pid = isset($item['staff_id']) ? (int) $item['staff_id'] : (isset($item['id']) ? (int) $item['id'] : null);
                if ($pid === null) {
                    continue;
                }
                $days = isset($item['participant_days']) ? (int) $item['participant_days'] : 0;
                if ($days > 0) {
                    $out[$pid] = ($out[$pid] ?? 0) + $days;
                }
            }
        } else {
            // Object keyed by staff_id: {"230": {"participant_days": "1", ...}, "193": {...}}
            foreach ($raw as $key => $info) {
                if (!is_array($info)) {
                    continue;
                }
                $pid = (int) $key;
                if ($pid <= 0) {
                    continue;
                }
                $days = isset($info['participant_days']) ? (int) $info['participant_days'] : 0;
                if ($days > 0) {
                    $out[$pid] = ($out[$pid] ?? 0) + $days;
                }
            }
        }

        return $out;
    }
}
