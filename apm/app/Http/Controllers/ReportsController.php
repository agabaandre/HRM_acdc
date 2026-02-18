<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\Division;
use App\Models\DocumentCounter;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\RequestARF;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\SpecialMemo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

    /** Apply common filters to activity+matrix query (division, year, quarter, request_type_id, status). Used for memo list (activities only). memo_type = request_type id when numeric. */
    private function applyFilters($query, Request $request, $activitiesTable, $matricesTable)
    {
        $filterDivision = $request->filled('division') ? (int) $request->division : null;
        $filterQuarter = $request->filled('quarter') ? $request->quarter : null;
        $year = $this->defaultYear($request);
        $filterYear = ($year !== '' && $year !== 'all' && is_numeric($year)) ? (int) $year : null;
        $memoTypeParam = $request->get('memo_type');
        $filterRequestType = ($memoTypeParam !== null && $memoTypeParam !== '' && is_numeric($memoTypeParam)) ? (int) $memoTypeParam : null;
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
            ->when($filterRequestType !== null, fn ($q) => $q->where($activitiesTable . '.request_type_id', $filterRequestType))
            ->when($filterStatus !== null, fn ($q) => $q->where($activitiesTable . '.overall_status', $filterStatus));

        return $query;
    }

    /**
     * Get division counts for division counts report. memo_type = document type code (QM, SM, SPM, NT, CR, SR, ARF)
     * or null for all types. Returns collection keyed by division_id with approved_count, pending_count, etc.
     */
    private function getDivisionCountsByMemoType(Request $request, ?string $memoType = null): \Illuminate\Support\Collection
    {
        $filterDivision = $request->filled('division') ? (int) $request->division : null;
        $year = $this->defaultYear($request);
        $filterYear = ($year !== '' && $year !== 'all' && is_numeric($year)) ? (int) $year : null;
        $filterQuarter = $request->filled('quarter') ? $request->quarter : null;

        $typesToQuery = $memoType
            ? [$memoType]
            : [DocumentCounter::TYPE_QUARTERLY_MATRIX, DocumentCounter::TYPE_SINGLE_MEMO, DocumentCounter::TYPE_SPECIAL_MEMO, DocumentCounter::TYPE_NON_TRAVEL_MEMO, DocumentCounter::TYPE_CHANGE_REQUEST, DocumentCounter::TYPE_SERVICE_REQUEST, DocumentCounter::TYPE_ARF];

        $merged = collect();

        foreach ($typesToQuery as $type) {
            $counts = $this->getCountsForDocumentType($type, $filterDivision, $filterYear, $filterQuarter);
            foreach ($counts as $row) {
                $did = $row->division_id;
                if (!$did) {
                    continue;
                }
                $existing = $merged->get($did);
                if (!$existing) {
                    $merged->put($did, (object) [
                        'division_id' => $did,
                        'approved_count' => (int) ($row->approved_count ?? 0),
                        'pending_count' => (int) ($row->pending_count ?? 0),
                        'returned_count' => (int) ($row->returned_count ?? 0),
                        'draft_count' => (int) ($row->draft_count ?? 0),
                        'total_count' => (int) ($row->total_count ?? 0),
                    ]);
                } else {
                    $existing->approved_count += (int) ($row->approved_count ?? 0);
                    $existing->pending_count += (int) ($row->pending_count ?? 0);
                    $existing->returned_count += (int) ($row->returned_count ?? 0);
                    $existing->draft_count += (int) ($row->draft_count ?? 0);
                    $existing->total_count += (int) ($row->total_count ?? 0);
                }
            }
        }

        return $merged;
    }

    /** Run a single division-count query for one document type. */
    private function getCountsForDocumentType(string $documentType, ?int $filterDivision, ?int $filterYear, ?string $filterQuarter): \Illuminate\Support\Collection
    {
        $statusSelect = "division_id, " .
            "SUM(CASE WHEN overall_status = 'approved' THEN 1 ELSE 0 END) as approved_count, " .
            "SUM(CASE WHEN overall_status = 'pending' THEN 1 ELSE 0 END) as pending_count, " .
            "SUM(CASE WHEN overall_status IN ('returned', 'rejected') THEN 1 ELSE 0 END) as returned_count, " .
            "SUM(CASE WHEN overall_status = 'draft' THEN 1 ELSE 0 END) as draft_count, " .
            "COUNT(*) as total_count";

        if ($documentType === DocumentCounter::TYPE_QUARTERLY_MATRIX || $documentType === DocumentCounter::TYPE_SINGLE_MEMO) {
            $activitiesTable = (new Activity())->getTable();
            $matricesTable = (new Matrix())->getTable();
            $divisionIdRaw = 'COALESCE(' . $activitiesTable . '.division_id, ' . $matricesTable . '.division_id)';
            $query = Activity::query()
                ->join($matricesTable, $activitiesTable . '.matrix_id', '=', $matricesTable . '.id')
                ->where($activitiesTable . '.is_single_memo', $documentType === DocumentCounter::TYPE_SINGLE_MEMO ? 1 : 0);
            if ($filterDivision !== null) {
                $query->where(function ($q) use ($activitiesTable, $matricesTable, $filterDivision) {
                    $q->where($activitiesTable . '.division_id', $filterDivision)
                        ->orWhere(function ($q2) use ($activitiesTable, $matricesTable, $filterDivision) {
                            $q2->whereNull($activitiesTable . '.division_id')->where($matricesTable . '.division_id', $filterDivision);
                        });
                });
            }
            if ($filterYear !== null) {
                $query->where($matricesTable . '.year', $filterYear);
            }
            if ($filterQuarter !== null) {
                $query->where($matricesTable . '.quarter', $filterQuarter);
            }
            return $query->selectRaw(
                $divisionIdRaw . ' as division_id, ' .
                'SUM(CASE WHEN ' . $activitiesTable . '.overall_status = ? THEN 1 ELSE 0 END) as approved_count, ' .
                'SUM(CASE WHEN ' . $activitiesTable . '.overall_status = ? THEN 1 ELSE 0 END) as pending_count, ' .
                'SUM(CASE WHEN ' . $activitiesTable . '.overall_status IN (?, ?) THEN 1 ELSE 0 END) as returned_count, ' .
                'SUM(CASE WHEN ' . $activitiesTable . '.overall_status = ? THEN 1 ELSE 0 END) as draft_count, ' .
                'COUNT(*) as total_count',
                ['approved', 'pending', 'returned', 'rejected', 'draft']
            )->groupBy(DB::raw($divisionIdRaw))->get();
        }

        $table = match ($documentType) {
            DocumentCounter::TYPE_SPECIAL_MEMO => (new SpecialMemo())->getTable(),
            DocumentCounter::TYPE_NON_TRAVEL_MEMO => (new NonTravelMemo())->getTable(),
            DocumentCounter::TYPE_CHANGE_REQUEST => (new ChangeRequest())->getTable(),
            DocumentCounter::TYPE_SERVICE_REQUEST => (new ServiceRequest())->getTable(),
            DocumentCounter::TYPE_ARF => (new RequestARF())->getTable(),
            default => null,
        };
        if (!$table) {
            return collect();
        }

        $query = DB::table($table)
            ->selectRaw($statusSelect)
            ->whereNotNull('division_id')
            ->groupBy('division_id');
        if ($filterDivision !== null) {
            $query->where('division_id', $filterDivision);
        }
        if ($filterYear !== null) {
            $query->whereYear('created_at', $filterYear);
        }
        if ($filterQuarter !== null) {
            $quarterNum = (int) str_replace('Q', '', $filterQuarter);
            $query->whereRaw('QUARTER(created_at) = ?', [$quarterNum]);
        }
        return $query->get();
    }

    /** Memo list filters: division, year, quarter, status. Year/quarter for non-Activity use created_at. */
    private function memoListFilters(Request $request): array
    {
        $filterDivision = $request->filled('division') ? (int) $request->division : null;
        $year = $this->defaultYear($request);
        $filterYear = ($year !== '' && $year !== 'all' && is_numeric($year)) ? (int) $year : null;
        $filterQuarter = $request->filled('quarter') ? $request->quarter : null;
        $filterStatus = $request->filled('status') ? $request->status : null;
        return [$filterDivision, $filterYear, $filterQuarter, $filterStatus];
    }

    /**
     * Get memo list rows for one document type (unified format: document_type, id, matrix_id?, document_number, title, division_id, year, quarter, overall_status, date_from, date_to, responsible_person_name, created_at).
     */
    private function getMemoListRowsForType(string $documentType, Request $request): \Illuminate\Support\Collection
    {
        [$filterDivision, $filterYear, $filterQuarter, $filterStatus] = $this->memoListFilters($request);
        $rows = collect();

        if ($documentType === DocumentCounter::TYPE_QUARTERLY_MATRIX || $documentType === DocumentCounter::TYPE_SINGLE_MEMO) {
            $activitiesTable = (new Activity())->getTable();
            $matricesTable = (new Matrix())->getTable();
            $q = Activity::query()
                ->select([
                    $activitiesTable . '.id',
                    $activitiesTable . '.matrix_id',
                    $activitiesTable . '.document_number',
                    $activitiesTable . '.activity_title',
                    $activitiesTable . '.division_id',
                    $activitiesTable . '.overall_status',
                    $activitiesTable . '.date_from',
                    $activitiesTable . '.date_to',
                    $activitiesTable . '.responsible_person_id',
                    $activitiesTable . '.created_at',
                    $matricesTable . '.year as matrix_year',
                    $matricesTable . '.quarter as matrix_quarter',
                    $matricesTable . '.division_id as matrix_division_id',
                    'staff.fname as resp_fname',
                    'staff.lname as resp_lname',
                ])
                ->join($matricesTable, $activitiesTable . '.matrix_id', '=', $matricesTable . '.id')
                ->leftJoin('staff', $activitiesTable . '.responsible_person_id', '=', 'staff.staff_id')
                ->where(function ($q) use ($activitiesTable, $documentType) {
                    if ($documentType === DocumentCounter::TYPE_SINGLE_MEMO) {
                        $q->where($activitiesTable . '.is_single_memo', 1);
                    } else {
                        $q->where(function ($q2) use ($activitiesTable) {
                            $q2->where($activitiesTable . '.is_single_memo', 0)->orWhereNull($activitiesTable . '.is_single_memo');
                        });
                    }
                });
            if ($filterDivision !== null) {
                $q->where(function ($q2) use ($activitiesTable, $matricesTable, $filterDivision) {
                    $q2->where($activitiesTable . '.division_id', $filterDivision)
                        ->orWhere(function ($q3) use ($activitiesTable, $matricesTable, $filterDivision) {
                            $q3->whereNull($activitiesTable . '.division_id')->where($matricesTable . '.division_id', $filterDivision);
                        });
                });
            }
            if ($filterYear !== null) {
                $q->where($matricesTable . '.year', $filterYear);
            }
            if ($filterQuarter !== null) {
                $q->where($matricesTable . '.quarter', $filterQuarter);
            }
            if ($filterStatus !== null) {
                $q->where($activitiesTable . '.overall_status', $filterStatus);
            }
            $list = $q->orderBy($matricesTable . '.year', 'desc')
                ->orderByRaw("FIELD(" . $matricesTable . ".quarter, 'Q4','Q3','Q2','Q1')")
                ->orderBy($activitiesTable . '.created_at', 'desc')
                ->get();
            foreach ($list as $a) {
                $divisionId = $a->division_id ?? Matrix::find($a->matrix_id)?->division_id;
                $respName = trim(($a->resp_fname ?? '') . ' ' . ($a->resp_lname ?? ''));
                if ($respName === '' && $a->responsible_person_id) {
                    $s = \App\Models\Staff::find($a->responsible_person_id);
                    $respName = $s ? trim(($s->fname ?? '') . ' ' . ($s->lname ?? '')) : 'N/A';
                }
                if ($respName === '') {
                    $respName = 'N/A';
                }
                $rows->push((object) [
                    'document_type' => $documentType,
                    'id' => $a->id,
                    'matrix_id' => $a->matrix_id,
                    'document_number' => $a->document_number,
                    'title' => $a->activity_title,
                    'division_id' => $divisionId,
                    'year' => $a->matrix_year,
                    'quarter' => $a->matrix_quarter,
                    'overall_status' => $a->overall_status,
                    'date_from' => $a->date_from,
                    'date_to' => $a->date_to,
                    'responsible_person_name' => $respName,
                    'created_at' => $a->created_at,
                ]);
            }
            return $rows;
        }

        $model = match ($documentType) {
            DocumentCounter::TYPE_SPECIAL_MEMO => SpecialMemo::class,
            DocumentCounter::TYPE_NON_TRAVEL_MEMO => NonTravelMemo::class,
            DocumentCounter::TYPE_CHANGE_REQUEST => ChangeRequest::class,
            DocumentCounter::TYPE_SERVICE_REQUEST => ServiceRequest::class,
            DocumentCounter::TYPE_ARF => RequestARF::class,
            default => null,
        };
        if (!$model) {
            return $rows;
        }

        $with = [];
        if (method_exists($model, 'responsiblePerson')) {
            $with[] = 'responsiblePerson';
        }
        if (method_exists($model, 'staff')) {
            $with[] = 'staff';
        }
        $q = $model::query()->when(!empty($with), fn ($q) => $q->with($with))->orderBy('created_at', 'desc');
        if ($filterDivision !== null) {
            $q->where('division_id', $filterDivision);
        }
        if ($filterYear !== null) {
            $q->whereYear('created_at', $filterYear);
        }
        if ($filterQuarter !== null) {
            $quarterNum = (int) str_replace('Q', '', $filterQuarter);
            $q->whereRaw('QUARTER(created_at) = ?', [$quarterNum]);
        }
        if ($filterStatus !== null) {
            $q->where('overall_status', $filterStatus);
        }

        foreach ($q->get() as $m) {
            // NonTravelMemo uses staff_id (staff relation); others use responsible_person_id (responsiblePerson) when present
            $resp = ($documentType === DocumentCounter::TYPE_NON_TRAVEL_MEMO) ? ($m->staff ?? null) : ($m->responsiblePerson ?? $m->staff ?? null);
            $respName = $resp ? trim(($resp->fname ?? '') . ' ' . ($resp->lname ?? '')) : 'N/A';
            $rows->push((object) [
                'document_type' => $documentType,
                'id' => $m->id,
                'matrix_id' => null,
                'document_number' => $m->document_number ?? null,
                'title' => $m->activity_title ?? '—',
                'division_id' => $m->division_id,
                'year' => $m->created_at ? (int) $m->created_at->format('Y') : null,
                'quarter' => $m->created_at ? 'Q' . $m->created_at->quarter : null,
                'overall_status' => $m->overall_status ?? $m->status ?? null,
                'date_from' => $m->date_from ?? null,
                'date_to' => $m->date_to ?? null,
                'responsible_person_name' => $respName,
                'created_at' => $m->created_at,
            ]);
        }
        return $rows;
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
     * Memo type = document type: Quarterly Matrix, Single Memo, Special Memo, Non Travel, Change Request, Service Request, ARF.
     */
    public function divisionCounts(Request $request)
    {
        $divisions = Division::orderBy('division_name')->get();
        $memoTypes = DocumentCounter::getDocumentTypes();
        $years = Matrix::distinct()->pluck('year')->filter()->sortDesc()->values();
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $currentYear = (string) (int) date('Y');

        return view('reports.division-counts', [
            'divisions' => $divisions,
            'memoTypes' => $memoTypes,
            'years' => $years,
            'quarters' => $quarters,
            'currentYear' => $currentYear,
        ]);
    }

    /**
     * AJAX: division counts table HTML.
     * memo_type = document type code (QM, SM, SPM, NT, CR, SR, ARF) or empty for all.
     */
    public function divisionCountsData(Request $request)
    {
        $memoType = $request->filled('memo_type') ? $request->memo_type : null;
        $counts = $this->getDivisionCountsByMemoType($request, $memoType);
        $divisionIds = $counts->keys()->filter()->unique()->values()->toArray();
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
     * Includes all memo types: QM, SM, SPM, NT, CR, SR, ARF. memo_type filter = document type code.
     * Defaults to "All years" when no year in request so the list shows data on first load.
     */
    public function memoList(Request $request)
    {
        $divisions = Division::orderBy('division_name')->get();
        $memoTypes = DocumentCounter::getDocumentTypes();
        $years = Matrix::distinct()->pluck('year')->filter()->sortDesc()->values();
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $currentYear = (string) (int) date('Y');
        // Default to "all" so first load and "no filters" show all years; otherwise use request year
        $year = $request->filled('year') ? $this->defaultYear($request) : 'all';

        return view('reports.memo-list', [
            'divisions' => $divisions,
            'memoTypes' => $memoTypes,
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

    /** Valid document type codes for memo list. */
    private const MEMO_LIST_DOC_TYPES = [
        DocumentCounter::TYPE_QUARTERLY_MATRIX,
        DocumentCounter::TYPE_SINGLE_MEMO,
        DocumentCounter::TYPE_SPECIAL_MEMO,
        DocumentCounter::TYPE_NON_TRAVEL_MEMO,
        DocumentCounter::TYPE_CHANGE_REQUEST,
        DocumentCounter::TYPE_SERVICE_REQUEST,
        DocumentCounter::TYPE_ARF,
    ];

    /**
     * AJAX: memo list table HTML (body + pagination). Uses unified rows from one or all document types.
     */
    public function memoListData(Request $request)
    {
        $memoType = $request->filled('memo_type') ? $request->memo_type : null;
        $perPage = 20;
        $page = (int) $request->get('page', 1);

        if ($memoType && in_array($memoType, self::MEMO_LIST_DOC_TYPES, true)) {
            $allRows = $this->getMemoListRowsForType($memoType, $request);
        } else {
            $allRows = collect();
            foreach (self::MEMO_LIST_DOC_TYPES as $type) {
                $allRows = $allRows->concat($this->getMemoListRowsForType($type, $request));
            }
            $allRows = $allRows->sortByDesc(function ($r) {
                $y = $r->year ?? 0;
                $q = $r->quarter ?? '';
                $oq = ['Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4][$q] ?? 0;
                $ts = $r->created_at ? (\Carbon\Carbon::parse($r->created_at)->timestamp ?? 0) : 0;
                return sprintf('%04d-%02d-%010d', $y, $oq, $ts);
            })->values();
        }

        $total = $allRows->count();
        $slice = $allRows->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $divisions = Division::orderBy('division_name')->get();
        $memoTypeLabels = DocumentCounter::getDocumentTypes();

        $html = view('reports.partials.memo-list-table', [
            'memoList' => $paginator,
            'divisions' => $divisions,
            'memoTypeLabels' => $memoTypeLabels,
        ])->render();

        return response()->json([
            'html' => $html,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'debug' => $request->has('_debug') ? [
                'received' => ['division' => $request->get('division'), 'year' => $request->get('year'), 'quarter' => $request->get('quarter')],
                'total_rows' => $total,
            ] : null,
        ]);
    }

    /**
     * Export division counts report to Excel (CSV).
     * memo_type = document type code (QM, SM, SPM, NT, CR, SR, ARF) or empty for all.
     */
    public function exportDivisionCountsExcel(Request $request)
    {
        $memoType = $request->filled('memo_type') ? $request->memo_type : null;
        $counts = $this->getDivisionCountsByMemoType($request, $memoType)->values();
        $divisionIds = $counts->pluck('division_id')->filter()->unique()->toArray();
        $divisions = Division::whereIn('id', $divisionIds)->get()->keyBy('id');

        $filename = 'division_memo_counts_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($counts, $divisions) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Division', 'Approved', 'Pending', 'Returned', 'Draft', 'Total']);
            foreach ($counts as $row) {
                $division = $divisions->get($row->division_id);
                $name = $division ? $division->division_name : ('Division #' . $row->division_id);
                fputcsv($file, [
                    $name,
                    (int) ($row->approved_count ?? 0),
                    (int) ($row->pending_count ?? 0),
                    (int) ($row->returned_count ?? 0),
                    (int) ($row->draft_count ?? 0),
                    (int) ($row->total_count ?? 0),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export memo list report to Excel (CSV). Uses unified rows; memo_type filter = document type code or all.
     */
    public function exportMemoListExcel(Request $request)
    {
        $memoType = $request->filled('memo_type') ? $request->memo_type : null;
        if ($memoType && in_array($memoType, self::MEMO_LIST_DOC_TYPES, true)) {
            $rows = $this->getMemoListRowsForType($memoType, $request);
        } else {
            $rows = collect();
            foreach (self::MEMO_LIST_DOC_TYPES as $type) {
                $rows = $rows->concat($this->getMemoListRowsForType($type, $request));
            }
            $rows = $rows->sortByDesc(function ($r) {
                $y = $r->year ?? 0;
                $q = $r->quarter ?? '';
                $oq = ['Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4][$q] ?? 0;
                $ts = $r->created_at ? (\Carbon\Carbon::parse($r->created_at)->timestamp ?? 0) : 0;
                return sprintf('%04d-%02d-%010d', $y, $oq, $ts);
            })->values();
        }

        $divisions = Division::orderBy('division_name')->get();
        $memoTypeLabels = DocumentCounter::getDocumentTypes();

        $filename = 'memo_list_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows, $divisions, $memoTypeLabels) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['#', 'Document #', 'Title', 'Division', 'Type', 'Year', 'Quarter', 'Status', 'Date From', 'Date To', 'Responsible Person']);
            $idx = 0;
            foreach ($rows as $row) {
                $idx++;
                $divisionName = $row->division_id ? ($divisions->firstWhere('id', $row->division_id)->division_name ?? 'N/A') : 'N/A';
                $dateFrom = $row->date_from ? \Carbon\Carbon::parse($row->date_from)->format('Y-m-d') : '';
                $dateTo = $row->date_to ? \Carbon\Carbon::parse($row->date_to)->format('Y-m-d') : '';
                fputcsv($file, [
                    $idx,
                    $row->document_number ?? '',
                    $row->title ?? '',
                    $divisionName,
                    $memoTypeLabels[$row->document_type] ?? $row->document_type,
                    $row->year ?? '',
                    $row->quarter ?? '',
                    $row->overall_status ?? '',
                    $dateFrom,
                    $dateTo,
                    $row->responsible_person_name ?? '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
