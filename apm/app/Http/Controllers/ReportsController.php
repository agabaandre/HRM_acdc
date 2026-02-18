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
    /**
     * Reports index: Division memo counts + List of memos.
     * Filters: division, quarter, year, memo type (request type), status (for list).
     */
    public function index(Request $request)
    {
        $divisions = Division::orderBy('division_name')->get();
        $requestTypes = RequestType::orderBy('name')->get();
        $years = Matrix::distinct()->pluck('year')->filter()->sortDesc()->values();
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];

        $filterDivision = $request->filled('division') ? (int) $request->division : null;
        $filterQuarter = $request->filled('quarter') ? $request->quarter : null;
        $filterYear = $request->filled('year') ? (int) $request->year : null;
        $filterMemoType = $request->filled('memo_type') ? (int) $request->memo_type : null;
        $filterStatus = $request->filled('status') ? $request->status : null;

        $activitiesTable = (new Activity())->getTable();
        $matricesTable = (new Matrix())->getTable();
        $divisionsTable = (new Division())->getTable();

        $baseQuery = Activity::query()
            ->select([$activitiesTable . '.*', $matricesTable . '.year as matrix_year', $matricesTable . '.quarter as matrix_quarter'])
            ->join($matricesTable, $activitiesTable . '.matrix_id', '=', $matricesTable . '.id')
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

        $divisionIdRaw = 'COALESCE(' . $activitiesTable . '.division_id, ' . $matricesTable . '.division_id)';

        $countsQuery = Activity::query()
            ->join($matricesTable, $activitiesTable . '.matrix_id', '=', $matricesTable . '.id')
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
            ->when($filterStatus !== null, fn ($q) => $q->where($activitiesTable . '.overall_status', $filterStatus))
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

        $memoList = (clone $baseQuery)
            ->with(['requestType', 'responsiblePerson', 'matrix'])
            ->orderBy('matrix_year', 'desc')
            ->orderByRaw("FIELD(matrix_quarter, 'Q1','Q2','Q3','Q4')")
            ->orderBy($activitiesTable . '.created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('reports.index', [
            'divisions' => $divisions,
            'requestTypes' => $requestTypes,
            'years' => $years,
            'quarters' => $quarters,
            'filterDivision' => $filterDivision,
            'filterQuarter' => $filterQuarter,
            'filterYear' => $filterYear,
            'filterMemoType' => $filterMemoType,
            'filterStatus' => $filterStatus,
            'counts' => $counts,
            'divisionsForCounts' => $divisionsForCounts,
            'memoList' => $memoList,
        ]);
    }
}
