<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\DocumentCounter;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\RequestARF;
use App\Models\ServiceRequest;
use App\Models\SpecialMemo;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Memo list for the authenticated user's division only (same data shape as reports/memo-list).
 * Endpoints: pending (status=pending), approved (status=approved).
 *
 * Query params (all optional): year, quarter, memo_type (QM|SM|SPM|NT|CR|SR|ARF), title, document_number, per_page, page.
 */
class ApmMemoListController extends Controller
{
    private const DOC_TYPES = [
        DocumentCounter::TYPE_QUARTERLY_MATRIX,
        DocumentCounter::TYPE_SINGLE_MEMO,
        DocumentCounter::TYPE_SPECIAL_MEMO,
        DocumentCounter::TYPE_NON_TRAVEL_MEMO,
        DocumentCounter::TYPE_CHANGE_REQUEST,
        DocumentCounter::TYPE_SERVICE_REQUEST,
        DocumentCounter::TYPE_ARF,
    ];

    /**
     * Pending memos for the current user's division only.
     */
    public function pending(Request $request): JsonResponse
    {
        return $this->memoListByStatus($request, 'pending');
    }

    /**
     * Approved memos for the current user's division only.
     */
    public function approved(Request $request): JsonResponse
    {
        return $this->memoListByStatus($request, 'approved');
    }

    private function memoListByStatus(Request $request, string $status): JsonResponse
    {
        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $primaryDivisionId = (int) ($sessionData['division_id'] ?? 0);
        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $associatedDivisionIds = [];
        if ($staffId) {
            try {
                $staff = Staff::find($staffId);
                if ($staff) {
                    $raw = $staff->associated_divisions ?? null;
                    if (is_array($raw)) {
                        $associatedDivisionIds = array_map('intval', array_filter($raw));
                    } elseif (is_string($raw)) {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $associatedDivisionIds = array_map('intval', array_filter($decoded));
                        }
                    }
                }
            } catch (\Throwable $e) {
                // If staff or associated_divisions fails (e.g. column missing), use primary division only
            }
        }
        $divisionIds = array_values(array_unique(array_filter(array_merge(
            $primaryDivisionId > 0 ? [$primaryDivisionId] : [],
            $associatedDivisionIds
        ))));
        if (empty($divisionIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'memos' => [],
                    'division_id' => $primaryDivisionId ?: null,
                    'division_ids' => [],
                    'status' => $status,
                ],
            ]);
        }

        $year = $request->filled('year') && is_numeric($request->year) ? (int) $request->year : null;
        $quarter = $request->filled('quarter') ? $request->quarter : null;
        $memoType = $request->filled('memo_type') && in_array($request->memo_type, self::DOC_TYPES, true) ? $request->memo_type : null;
        $title = $request->filled('title') ? trim($request->title) : null;
        $documentNumber = $request->filled('document_number') ? trim($request->document_number) : null;
        $perPage = max(1, min(100, (int) $request->get('per_page', 20)));
        $page = max(1, (int) $request->get('page', 1));

        $typesToQuery = $memoType ? [$memoType] : self::DOC_TYPES;
        $allRows = collect();
        foreach ($typesToQuery as $documentType) {
            $allRows = $allRows->concat(
                $this->getMemoListRowsForType($documentType, $divisionIds, $status, $year, $quarter, $title, $documentNumber)
            );
        }

        $allRows = $allRows->sortByDesc(function ($r) {
            $y = (int) ($r['year'] ?? 0);
            $q = (string) ($r['quarter'] ?? '');
            $oq = ['Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4][$q] ?? 0;
            try {
                $ts = isset($r['created_at']) && $r['created_at'] ? \Carbon\Carbon::parse($r['created_at'])->timestamp : 0;
            } catch (\Throwable $e) {
                $ts = 0;
            }
            return sprintf('%04d-%02d-%010d', $y, $oq, $ts);
        })->values();

        $total = $allRows->count();
        $slice = $allRows->slice(($page - 1) * $perPage, $perPage)->values();
        $memoTypeLabels = DocumentCounter::getDocumentTypes();
        $list = $slice->map(function ($r) use ($memoTypeLabels) {
            $r['type_label'] = is_array($memoTypeLabels) ? ($memoTypeLabels[$r['document_type']] ?? $r['document_type']) : $r['document_type'];
            try {
                $r['created_at'] = isset($r['created_at']) && $r['created_at'] ? (\Carbon\Carbon::parse($r['created_at'])->toIso8601String()) : null;
            } catch (\Throwable $e) {
                $r['created_at'] = null;
            }
            try {
                $r['date_from'] = isset($r['date_from']) && $r['date_from'] ? (\Carbon\Carbon::parse($r['date_from'])->format('Y-m-d')) : null;
            } catch (\Throwable $e) {
                $r['date_from'] = null;
            }
            try {
                $r['date_to'] = isset($r['date_to']) && $r['date_to'] ? (\Carbon\Carbon::parse($r['date_to'])->format('Y-m-d')) : null;
            } catch (\Throwable $e) {
                $r['date_to'] = null;
            }
            return $r;
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'memos' => $list,
                'division_id' => $primaryDivisionId ?: null,
                'division_ids' => $divisionIds,
                'status' => $status,
                'filters' => [
                    'year' => $year,
                    'quarter' => $quarter,
                    'memo_type' => $memoType,
                    'title' => $title,
                    'document_number' => $documentNumber,
                ],
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * @param list<int> $divisionIds Primary + associated division IDs the user can see.
     */
    private function getMemoListRowsForType(string $documentType, array $divisionIds, string $status, ?int $year, ?string $quarter, ?string $title = null, ?string $documentNumber = null): Collection
    {
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
                })
                ->where($activitiesTable . '.overall_status', $status)
                ->where(function ($q) use ($activitiesTable, $matricesTable, $divisionIds) {
                    $q->whereIn($activitiesTable . '.division_id', $divisionIds)
                        ->orWhere(function ($q2) use ($activitiesTable, $matricesTable, $divisionIds) {
                            $q2->whereNull($activitiesTable . '.division_id')->whereIn($matricesTable . '.division_id', $divisionIds);
                        });
                });
            if ($year !== null) {
                $q->where($matricesTable . '.year', $year);
            }
            if ($quarter !== null) {
                $q->where($matricesTable . '.quarter', $quarter);
            }
            if ($title !== null && $title !== '') {
                $q->where($activitiesTable . '.activity_title', 'like', '%' . $title . '%');
            }
            if ($documentNumber !== null && $documentNumber !== '') {
                $q->where($activitiesTable . '.document_number', 'like', '%' . $documentNumber . '%');
            }
            $list = $q->orderBy($matricesTable . '.year', 'desc')
                ->orderByRaw("FIELD(" . $matricesTable . ".quarter, 'Q4','Q3','Q2','Q1')")
                ->orderBy($activitiesTable . '.created_at', 'desc')
                ->get();

            foreach ($list as $a) {
                $divisionIdRes = $a->division_id ?? Matrix::find($a->matrix_id)?->division_id;
                $respName = trim(($a->resp_fname ?? '') . ' ' . ($a->resp_lname ?? ''));
                if ($respName === '' && $a->responsible_person_id) {
                    $s = Staff::find($a->responsible_person_id);
                    $respName = $s ? trim(($s->fname ?? '') . ' ' . ($s->lname ?? '')) : 'N/A';
                }
                if ($respName === '') {
                    $respName = 'N/A';
                }
                $rows->push([
                    'document_type' => $documentType,
                    'id' => $a->id,
                    'matrix_id' => $a->matrix_id,
                    'document_number' => $a->document_number,
                    'title' => $a->activity_title,
                    'division_id' => $divisionIdRes,
                    'year' => $a->matrix_year,
                    'quarter' => $a->matrix_quarter,
                    'overall_status' => $a->overall_status,
                    'date_from' => $a->date_from,
                    'date_to' => $a->date_to,
                    'responsible_person_name' => $respName,
                    'created_at' => $a->created_at?->toDateTimeString(),
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
        $q = $model::query()->when(!empty($with), fn ($q) => $q->with($with))
            ->whereIn('division_id', $divisionIds)
            ->where('overall_status', $status)
            ->orderBy('created_at', 'desc');
        if ($year !== null) {
            $q->whereYear('created_at', $year);
        }
        if ($quarter !== null) {
            $quarterNum = (int) str_replace('Q', '', $quarter);
            $q->whereRaw('QUARTER(created_at) = ?', [$quarterNum]);
        }
        if ($title !== null && $title !== '') {
            if ($documentType === DocumentCounter::TYPE_SERVICE_REQUEST) {
                $q->where(function ($q2) use ($title) {
                    $q2->where('title', 'like', '%' . $title . '%')
                        ->orWhere('service_title', 'like', '%' . $title . '%');
                });
            } else {
                $q->where('activity_title', 'like', '%' . $title . '%');
            }
        }
        if ($documentNumber !== null && $documentNumber !== '') {
            $q->where('document_number', 'like', '%' . $documentNumber . '%');
        }

        foreach ($q->get() as $m) {
            $resp = ($documentType === DocumentCounter::TYPE_NON_TRAVEL_MEMO) ? ($m->staff ?? null) : ($m->responsiblePerson ?? $m->staff ?? null);
            $respName = $resp ? trim(($resp->fname ?? '') . ' ' . ($resp->lname ?? '')) : 'N/A';
            $rowTitle = $documentType === DocumentCounter::TYPE_SERVICE_REQUEST
                ? ($m->title ?? $m->service_title ?? '—')
                : ($m->activity_title ?? '—');
            $rows->push([
                'document_type' => $documentType,
                'id' => $m->id,
                'matrix_id' => null,
                'document_number' => $m->document_number ?? null,
                'title' => $rowTitle,
                'division_id' => $m->division_id,
                'year' => $m->created_at ? (int) $m->created_at->format('Y') : null,
                'quarter' => $m->created_at ? 'Q' . $m->created_at->quarter : null,
                'overall_status' => $m->overall_status ?? $m->status ?? null,
                'date_from' => $m->date_from ?? null,
                'date_to' => $m->date_to ?? null,
                'responsible_person_name' => $respName,
                'created_at' => $m->created_at?->toDateTimeString(),
            ]);
        }
        return $rows;
    }
}
