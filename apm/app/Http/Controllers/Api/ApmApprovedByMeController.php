<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApproverDashboardHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApmApprovedByMeController extends Controller
{
    use ApproverDashboardHelper;

    /**
     * List documents approved/rejected by the current user, plus average approval time (same as dashboard).
     */
    public function index(Request $request): JsonResponse
    {
        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $divisionId = $sessionData['division_id'] ?? null;
        $year = $request->filled('year') ? (int) $request->get('year') : null;
        $month = $request->filled('month') ? (int) $request->get('month') : null;

        $avgHours = $this->getAverageApprovalTimeAll($staffId, $divisionId, $year, $month);
        $avgDisplay = $this->formatApprovalTime($avgHours);

        $documents = $this->getApprovedByMeDocuments($staffId, $year, $month, $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'documents' => $documents['items'],
                'total' => $documents['total'],
                'average_approval_time_hours' => $avgHours,
                'average_approval_time_display' => $avgDisplay,
            ],
        ]);
    }

    /**
     * Get average approval time only.
     */
    public function averageTime(Request $request): JsonResponse
    {
        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $divisionId = $sessionData['division_id'] ?? null;
        $year = $request->filled('year') ? (int) $request->get('year') : null;
        $month = $request->filled('month') ? (int) $request->get('month') : null;

        $avgHours = $this->getAverageApprovalTimeAll($staffId, $divisionId, $year, $month);
        $avgDisplay = $this->formatApprovalTime($avgHours);

        return response()->json([
            'success' => true,
            'data' => [
                'average_approval_time_hours' => $avgHours,
                'average_approval_time_display' => $avgDisplay,
            ],
        ]);
    }

    private function getApprovedByMeDocuments(int $staffId, ?int $year, ?int $month, int $perPage): array
    {
        $query = DB::table('approval_trails as at')
            ->where('at.staff_id', $staffId)
            ->whereIn('at.action', ['approved', 'rejected'])
            ->where('at.is_archived', 0)
            ->select('at.model_type', 'at.model_id', 'at.action', 'at.updated_at', 'at.approval_order');

        if ($year || $month) {
            $quarter = $month ? 'Q' . ceil($month / 3) : null;
            $query->where(function ($q) use ($year, $quarter, $month) {
                $q->where(function ($sub) use ($year, $quarter) {
                    $sub->where('at.model_type', 'App\\Models\\Matrix')
                        ->whereExists(function ($ex) use ($year, $quarter) {
                            $ex->select(DB::raw(1))->from('matrices as m')->whereColumn('m.id', 'at.model_id');
                            if ($year) $ex->where('m.year', $year);
                            if ($quarter) $ex->where('m.quarter', $quarter);
                        });
                })
                ->orWhere(function ($sub) use ($year, $quarter) {
                    $sub->where('at.model_type', 'App\\Models\\Activity')
                        ->whereExists(function ($ex) use ($year, $quarter) {
                            $ex->select(DB::raw(1))->from('activities as a')
                                ->join('matrices as m', 'm.id', '=', 'a.matrix_id')
                                ->whereColumn('a.id', 'at.model_id');
                            if ($year) $ex->where('m.year', $year);
                            if ($quarter) $ex->where('m.quarter', $quarter);
                        });
                })
                ->orWhere(function ($sub) use ($year, $month) {
                    $sub->whereNotIn('at.model_type', ['App\\Models\\Matrix', 'App\\Models\\Activity', 'App\\Models\\ChangeRequest']);
                    if ($year) $sub->whereYear('at.created_at', $year);
                    if ($month) $sub->whereMonth('at.created_at', $month);
                });
            });
        }

        $query->orderByDesc('at.updated_at');
        $total = $query->count();
        $rows = (clone $query)->offset(0)->limit($perPage)->get();

        $items = [];
        foreach ($rows as $row) {
            $docType = $this->modelTypeToDocType($row->model_type);
            $title = $this->resolveDocumentTitle($row->model_type, $row->model_id);
            $items[] = [
                'document_type' => $docType,
                'document_id' => (int) $row->model_id,
                'action' => $row->action,
                'acted_at' => $row->updated_at,
                'title' => $title,
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    private function modelTypeToDocType(string $modelType): string
    {
        $map = [
            'App\\Models\\Matrix' => 'matrix',
            'App\\Models\\Activity' => 'activity',
            'App\\Models\\SpecialMemo' => 'special_memo',
            'App\\Models\\NonTravelMemo' => 'non_travel_memo',
            'App\\Models\\ServiceRequest' => 'service_request',
            'App\\Models\\RequestARF' => 'arf',
            'App\\Models\\ChangeRequest' => 'change_request',
        ];
        return $map[$modelType] ?? 'unknown';
    }

    private function resolveDocumentTitle(string $modelType, int $modelId): ?string
    {
        try {
            $class = str_replace('\\\\', '\\', $modelType);
            if (!class_exists($class)) return null;
            $model = $class::find($modelId);
            if (!$model) return null;
            if (isset($model->activity_title)) return $model->activity_title;
            if (isset($model->title)) return $model->title;
            if (isset($model->document_number)) return $model->document_number;
            return (string) $modelId;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
