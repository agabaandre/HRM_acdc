<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Division;
use App\Models\Matrix;
use App\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    /** Default year for filters: current year. Use "all" for all years. */
    private function defaultYear(Request $request)
    {
        $y = $request->get('year');
        if ($y === null || $y === '') {
            return (string) (int) date('Y');
        }
        return (string) $y;
    }

    /** Apply common filters to activity+matrix query (division, year, quarter, memo_type, status). */
    private function applyFilters($query, Request $request, $activitiesTable, $matricesTable)
    {
        $filterDivision = $request->filled('division') ? (int) $request->division : null;
        $filterQuarter = $request->filled('quarter') ? $request->quarter : null;
        $year = $this->defaultYear($request);
        $filterYear = ($year !== '' && $year !== 'all' && is_numeric($year)) ? (int) $year : null;
        $filterMemoType = $request->filled('memo_type') ? (int) $request->memo_type : null;
        $filterStatus = $request->filled('status') ? $request->status : null;

        $query
            ->when($filterDivision !== null, function ($q) use ($activitiesTable, $matricesTable, $filterDivision) {
                $q->where(function ($q2) use ($activitiesTable, $matricesTable, $filterDivision) {
                    $q2->where($activitiesTable . '.division_id', $filterDivision)
                        ->orWhere(function ($q3) use ($activitiesTable, $matricesTable, $filterDivision) {
                            $q3->whereNull($activitiesTable . '.division_id')
                                ->where($matricesTable . '.division_id', $filterDivision);
                        });
                });
            })
            ->when($filterYear !== null, fn ($q) => $q->where($matricesTable . '.year', $filterYear))
            ->when($filterQuarter !== null, fn ($q) => $q->where($matricesTable . '.quarter', $filterQuarter))
            ->when($filterMemoType !== null, fn ($q) => $q->where($activitiesTable . '.request_type_id', $filterMemoType))
            ->when($filterStatus !== null, fn ($q) => $q->where($activitiesTable . '.overall_status', $filterStatus));

        return $query;
    }

    /**
     * Reports index: list all reports (links to division counts, memo list, etc.).
     */
    public function index()
    {
        return view('reports.index');
    }

    /**
     * Division memo counts report page (data loaded via AJAX).
     */
    public function divisionCounts(Request $request)
    {
        $divisions = Division::orderBy('division_name')->get();
        $requestTypes = RequestType::orderBy('name')->get();
        $years = Matrix::distinct()->pluck('year')->filter()->sortDesc()->values();
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $currentYear = (string) (int) date('Y');

        return view('reports.division-counts', [
            'divisions' => $divisions,
            'requestTypes' => $requestTypes,
            'years' => $years,
            'quarters' => $quarters,
            'currentYear' => $currentYear,
        ]);
    }

    /**
     * AJAX: division counts table HTML.
     */
    public function divisionCountsData(Request $request)
    {
        $activitiesTable = (new Activity())->getTable();
        $matricesTable = (new Matrix())->getTable();
        $divisionIdRaw = 'COALESCE(' . $activitiesTable . '.division_id, ' . $matricesTable . '.division_id)';

        $countsQuery = Activity::query()
            ->join($matricesTable, $activitiesTable . '.matrix_id', '=', $matricesTable . '.id');
        $this->applyFilters($countsQuery, $request, $activitiesTable, $matricesTable);
        $countsQuery
            ->selectRaw(
                $divisionIdRaw . ' as division_id, ' .
                'SUM(CASE WHEN ' . $activitiesTable . '.overall_status = ? THEN 1 ELSE 0 END) as approved_count, ' .
                'SUM(CASE WHEN ' . $activitiesTable . '.overall_status = ? THEN 1 ELSE 0 END) as pending_count, ' .
                'SUM(CASE WHEN ' . $activitiesTable . '.overall_status IN (?, ?) THEN 1 ELSE 0 END) as returned_count, ' .
                'COUNT(*) as total_count',
                ['approved', 'pending', 'returned', 'rejected']
            )
            ->groupBy(DB::raw($divisionIdRaw));

        $counts = $countsQuery->get()->keyBy('division_id');
        $divisionIds = $counts->pluck('division_id')->filter()->unique()->toArray();
        $divisionsForCounts = Division::whereIn('id', $divisionIds)->get()->keyBy('id');

        $detailsUrl = route('reports.memo-list');
        $html = view('reports.partials.counts-table', [
            'counts' => $counts,
            'divisionsForCounts' => $divisionsForCounts,
            'detailsUrl' => $detailsUrl,
            'request' => $request,
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * Memo list (details) report page (data loaded via AJAX with pagination).
     * Query params (division, year, quarter, memo_type, status) pre-fill filters when linked from counts report.
     */
    public function memoList(Request $request)
    {
        $divisions = Division::orderBy('division_name')->get();
        $requestTypes = RequestType::orderBy('name')->get();
        $years = Matrix::distinct()->pluck('year')->filter()->sortDesc()->values();
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $currentYear = (string) (int) date('Y');
        $year = $this->defaultYear($request);

        return view('reports.memo-list', [
            'divisions' => $divisions,
            'requestTypes' => $requestTypes,
            'years' => $years,
            'quarters' => $quarters,
            'currentYear' => $currentYear,
            'filterDivision' => $request->get('division'),
            'filterYear' => $year,
            'filterQuarter' => $request->get('quarter'),
            'filterMemoType' => $request->get('memo_type'),
            'filterStatus' => $request->get('status'),
        ]);
    }

    /**
     * AJAX: memo list table HTML (body + pagination).
     */
    public function memoListData(Request $request)
    {
        $activitiesTable = (new Activity())->getTable();
        $matricesTable = (new Matrix())->getTable();

        $baseQuery = Activity::query()
            ->select([$activitiesTable . '.*', $matricesTable . '.year as matrix_year', $matricesTable . '.quarter as matrix_quarter'])
            ->join($matricesTable, $activitiesTable . '.matrix_id', '=', $matricesTable . '.id');
        $this->applyFilters($baseQuery, $request, $activitiesTable, $matricesTable);

        $memoList = $baseQuery
            ->with(['requestType', 'responsiblePerson', 'matrix'])
            ->orderBy('matrix_year', 'desc')
            ->orderByRaw("FIELD(matrix_quarter, 'Q1','Q2','Q3','Q4')")
            ->orderBy($activitiesTable . '.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $divisions = Division::orderBy('division_name')->get();

        $html = view('reports.partials.memo-list-table', [
            'memoList' => $memoList,
            'divisions' => $divisions,
        ])->render();

        return response()->json([
            'html' => $html,
            'current_page' => $memoList->currentPage(),
            'last_page' => $memoList->lastPage(),
            'total' => $memoList->total(),
        ]);
    }
}
