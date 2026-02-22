<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpecialMemo;
use App\Models\Matrix;
use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use App\Models\ChangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApmDocumentController extends Controller
{
    /**
     * Stream a document attachment by type, id, and index. Requires auth.
     */
    public function attachment(Request $request, string $type, int $id, int $index): JsonResponse|StreamedResponse
    {
        if (!$request->attributes->get('api_user_session')) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $model = $this->resolveDocument($type, $id);
        if (!$model) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $attachments = $this->getAttachmentList($model, $type);
        if (!isset($attachments[$index])) {
            return response()->json(['success' => false, 'message' => 'Attachment not found.'], 404);
        }

        $item = $attachments[$index];
        $path = $item['path'] ?? null;
        if (!$path || !is_string($path)) {
            return response()->json(['success' => false, 'message' => 'Invalid attachment path.'], 400);
        }

        $path = str_replace('\\', '/', $path);
        $basePath = realpath(storage_path('app/public')) ?: storage_path('app/public');
        $fullPath = realpath($basePath . '/' . $path);
        if ($fullPath === false || !str_starts_with($fullPath, $basePath) || !is_file($fullPath)) {
            return response()->json(['success' => false, 'message' => 'File not found.'], 404);
        }

        $filename = $item['original_name'] ?? $item['filename'] ?? basename($path);
        $mimeType = $item['mime_type'] ?? 'application/octet-stream';

        return response()->streamDownload(function () use ($fullPath) {
            $stream = fopen($fullPath, 'rb');
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $mimeType,
        ], 'inline');
    }

    /**
     * List documents by type and status (from mother models). Optional query param id returns single document.
     * GET /documents/{type}/{status} or /documents/{type}/{status}?id=32
     */
    public function listByTypeAndStatus(Request $request, string $type, string $status): JsonResponse
    {
        if (!$request->attributes->get('api_user_session')) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $idParam = $request->query('id');
        if ($idParam !== null && $idParam !== '') {
            $id = (int) $idParam;
            if ($id <= 0) {
                return response()->json(['success' => false, 'message' => 'Invalid id parameter.'], 400);
            }
            $query = $this->queryByTypeAndStatus($type, $status);
            if ($query === null) {
                return response()->json(['success' => false, 'message' => 'Unknown document type.'], 400);
            }
            $model = $query->where('id', $id)->first();
            if (!$model) {
                return response()->json(['success' => false, 'message' => 'Document not found or does not have the requested status.'], 404);
            }
            $data = $this->modelToDocumentData($model, $type, $id);
            return response()->json(['success' => true, 'data' => $data]);
        }

        $query = $this->queryByTypeAndStatus($type, $status);
        if ($query === null) {
            return response()->json(['success' => false, 'message' => 'Unknown document type.'], 400);
        }

        $this->applyDocumentFilters($query, $type, $request);
        $this->applyDocumentOrder($query, $type);

        $perPage = max(1, min(100, (int) $request->get('per_page', 20)));
        $page = max(1, (int) $request->get('page', 1));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $data = $paginator->getCollection()->map(fn ($model) => $this->modelToDocumentData($model, $type, (int) $model->id))->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => count($data),
            'total' => $paginator->total(),
            'per_page' => $perPage,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'filters' => [
                'year' => $request->get('year'),
                'quarter' => $request->get('quarter'),
                'title' => $request->get('title'),
                'document_number' => $request->get('document_number'),
            ],
        ]);
    }

    /**
     * Get document data by type and id (API-shaped for approver app).
     */
    public function show(Request $request, string $type, int $id): JsonResponse
    {
        if (!$request->attributes->get('api_user_session')) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        switch ($type) {
            case 'special_memo':
                return $this->specialMemo($id);
            case 'matrix':
                return $this->matrix($id);
            case 'activity':
                return $this->activity($id);
            case 'non_travel_memo':
                return $this->nonTravelMemo($id);
            case 'service_request':
                return $this->serviceRequest($id);
            case 'arf':
                return $this->arf($id);
            case 'change_request':
                return $this->changeRequest($id);
            default:
                return response()->json(['success' => false, 'message' => 'Unknown document type.'], 400);
        }
    }

    private function specialMemo(int $id): JsonResponse
    {
        $memo = SpecialMemo::with([
            'staff', 'division', 'requestType', 'fundType', 'responsiblePerson',
            'approvalTrails.staff', 'approvalTrails.oicStaff', 'approvalTrails.workflowDefinition', 'forwardWorkflow',
        ])->find($id);
        if (!$memo) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $memo->toArray();
        $data['document_type'] = 'special_memo';
        $data['approval_trails'] = $this->formatApprovalTrails($memo->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($memo, 'special_memo', $id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function matrix(int $id): JsonResponse
    {
        $matrix = Matrix::with([
            'division', 'staff', 'focalPerson', 'forwardWorkflow',
            'matrixApprovalTrails.staff', 'matrixApprovalTrails.oicStaff',
            'activities' => fn ($q) => $q->where('is_single_memo', 0)->with(['requestType', 'fundType', 'responsiblePerson']),
        ])->find($id);
        if (!$matrix) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $matrix->toArray();
        $data['document_type'] = 'matrix';
        $data['approval_trails'] = $this->formatApprovalTrails($matrix->matrixApprovalTrails ?? collect());
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function activity(int $id): JsonResponse
    {
        $activity = Activity::with([
            'staff', 'requestType', 'fundType', 'responsiblePerson', 'matrix.division',
            'activity_budget.fundcode.funder',
            'activityApprovalTrails.staff', 'activityApprovalTrails.oicStaff',
            'approvalTrails.staff', 'approvalTrails.oicStaff',
        ])->find($id);
        if (!$activity) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $activity->toArray();
        $data['document_type'] = $activity->is_single_memo ? 'single_memo' : 'activity';
        $trails = $activity->is_single_memo
            ? ($activity->approvalTrails ?? collect())
            : ($activity->activityApprovalTrails ?? collect());
        $data['approval_trails'] = $this->formatApprovalTrails($trails);
        $data['attachments'] = $this->buildAttachmentsWithUrls($activity, 'activity', $id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function nonTravelMemo(int $id): JsonResponse
    {
        $memo = NonTravelMemo::with([
            'staff', 'division', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
        ])->find($id);
        if (!$memo) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $memo->toArray();
        $data['document_type'] = 'non_travel_memo';
        $data['approval_trails'] = $this->formatApprovalTrails($memo->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($memo, 'non_travel_memo', $id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function serviceRequest(int $id): JsonResponse
    {
        $doc = ServiceRequest::with([
            'staff', 'division', 'activity', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
        ])->find($id);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $doc->toArray();
        $data['document_type'] = 'service_request';
        $data['approval_trails'] = $this->formatApprovalTrails($doc->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($doc, 'service_request', $id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function arf(int $id): JsonResponse
    {
        $doc = RequestARF::with([
            'staff', 'division', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
        ])->find($id);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $doc->toArray();
        $data['document_type'] = 'arf';
        $data['approval_trails'] = $this->formatApprovalTrails($doc->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($doc, 'arf', $id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function changeRequest(int $id): JsonResponse
    {
        $doc = ChangeRequest::with([
            'staff', 'division', 'matrix', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'approvalTrails.workflowDefinition', 'forwardWorkflow',
        ])->find($id);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $doc->toArray();
        $data['document_type'] = 'change_request';
        $data['approval_trails'] = $this->formatApprovalTrails($doc->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($doc, 'change_request', $id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Build Eloquent query for document type filtered by overall_status.
     */
    private function queryByTypeAndStatus(string $type, string $status): \Illuminate\Database\Eloquent\Builder|null
    {
        $status = strtolower($status);
        return match ($type) {
            'special_memo' => SpecialMemo::with([
                'staff', 'division', 'requestType', 'fundType', 'responsiblePerson',
                'approvalTrails.staff', 'approvalTrails.oicStaff', 'approvalTrails.workflowDefinition', 'forwardWorkflow',
            ])->where('overall_status', $status),
            'matrix' => Matrix::with([
                'division', 'staff', 'focalPerson', 'forwardWorkflow',
                'matrixApprovalTrails.staff', 'matrixApprovalTrails.oicStaff',
                'activities' => fn ($q) => $q->where('is_single_memo', 0)->with(['requestType', 'fundType', 'responsiblePerson']),
            ])->where('overall_status', $status),
            'activity', 'single_memo' => Activity::with([
                'staff', 'requestType', 'fundType', 'responsiblePerson', 'matrix.division',
                'activity_budget.fundcode.funder',
                'activityApprovalTrails.staff', 'activityApprovalTrails.oicStaff',
                'approvalTrails.staff', 'approvalTrails.oicStaff',
            ])->where('overall_status', $status),
            'non_travel_memo' => NonTravelMemo::with([
                'staff', 'division', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
            ])->where('overall_status', $status),
            'service_request' => ServiceRequest::with([
                'staff', 'division', 'activity', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
            ])->where('overall_status', $status),
            'arf' => RequestARF::with([
                'staff', 'division', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
            ])->where('overall_status', $status),
            'change_request' => ChangeRequest::with([
                'staff', 'division', 'matrix', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'approvalTrails.workflowDefinition', 'forwardWorkflow',
            ])->where('overall_status', $status),
            default => null,
        };
    }

    /**
     * Apply memo-list style filters to the documents query (year, quarter, title, document_number).
     */
    private function applyDocumentFilters(\Illuminate\Database\Eloquent\Builder $query, string $type, Request $request): void
    {
        $year = $request->filled('year') ? (string) $request->get('year') : null;
        $quarter = $request->filled('quarter') ? $request->get('quarter') : null;
        $title = $request->filled('title') ? trim((string) $request->get('title')) : null;
        $documentNumber = $request->filled('document_number') ? trim((string) $request->get('document_number')) : null;

        if ($type === 'matrix') {
            if ($year !== null) {
                $query->where('year', $year);
            }
            if ($quarter !== null && in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
                $query->where('quarter', $quarter);
            }
            // Matrix has no activity_title / document_number in list view; skip title/document_number
            return;
        }

        if ($type === 'activity' || $type === 'single_memo') {
            if ($year !== null || $quarter !== null) {
                $query->whereHas('matrix', function ($q) use ($year, $quarter) {
                    if ($year !== null) {
                        $q->where('year', $year);
                    }
                    if ($quarter !== null && in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
                        $q->where('quarter', $quarter);
                    }
                });
            }
            if ($title !== null) {
                $query->where('activity_title', 'like', '%' . addcslashes($title, '%_\\') . '%');
            }
            if ($documentNumber !== null) {
                $query->where('document_number', 'like', '%' . addcslashes($documentNumber, '%_\\') . '%');
            }
            return;
        }

        // special_memo, non_travel_memo, service_request, arf, change_request: year/quarter from created_at
        if ($year !== null) {
            $query->whereYear('created_at', $year);
        }
        if ($quarter !== null && in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
            $qNum = (int) substr($quarter, 1);
            $query->whereRaw('QUARTER(created_at) = ?', [$qNum]);
        }

        if ($type === 'service_request') {
            if ($title !== null) {
                $esc = addcslashes($title, '%_\\');
                $query->where(function ($q) use ($esc) {
                    $q->where('title', 'like', '%' . $esc . '%')
                        ->orWhere('service_title', 'like', '%' . $esc . '%');
                });
            }
            if ($documentNumber !== null) {
                $query->where('document_number', 'like', '%' . addcslashes($documentNumber, '%_\\') . '%');
            }
        } else {
            // special_memo, non_travel_memo, arf, change_request: activity_title, document_number
            if ($title !== null) {
                $query->where('activity_title', 'like', '%' . addcslashes($title, '%_\\') . '%');
            }
            if ($documentNumber !== null) {
                $query->where('document_number', 'like', '%' . addcslashes($documentNumber, '%_\\') . '%');
            }
        }
    }

    /**
     * Order documents by latest first (memo-list style: year desc, quarter desc, created_at desc).
     */
    private function applyDocumentOrder(\Illuminate\Database\Eloquent\Builder $query, string $type): void
    {
        if ($type === 'matrix') {
            $query->orderBy('year', 'desc')
                ->orderByRaw("FIELD(quarter, 'Q4','Q3','Q2','Q1')")
                ->orderBy('created_at', 'desc');
            return;
        }
        if ($type === 'activity' || $type === 'single_memo') {
            $query->leftJoin('matrices', 'activities.matrix_id', '=', 'matrices.id')
                ->select('activities.*')
                ->orderBy('matrices.year', 'desc')
                ->orderByRaw("FIELD(matrices.quarter, 'Q4','Q3','Q2','Q1') DESC")
                ->orderBy('activities.created_at', 'desc');
            return;
        }
        $query->orderBy('created_at', 'desc');
    }

    /**
     * Convert a loaded model to API document data (document_type, approval_trails, attachments).
     */
    private function modelToDocumentData(object $model, string $type, int $id): array
    {
        $data = $model->toArray();
        $data['document_type'] = $type === 'activity' && isset($model->is_single_memo) && $model->is_single_memo ? 'single_memo' : $type;
        if ($type === 'matrix') {
            $data['approval_trails'] = $this->formatApprovalTrails($model->matrixApprovalTrails ?? collect());
        } elseif ($type === 'activity' || $type === 'single_memo') {
            $trails = isset($model->is_single_memo) && $model->is_single_memo
                ? ($model->approvalTrails ?? collect())
                : ($model->activityApprovalTrails ?? collect());
            $data['approval_trails'] = $this->formatApprovalTrails($trails);
        } else {
            $data['approval_trails'] = $this->formatApprovalTrails($model->approvalTrails ?? collect());
        }
        $data['attachments'] = $this->buildAttachmentsWithUrls($model, $type, $id);
        return $data;
    }

    /**
     * Resolve document model by type and id.
     */
    private function resolveDocument(string $type, int $id): SpecialMemo|Matrix|Activity|NonTravelMemo|ServiceRequest|RequestARF|ChangeRequest|null
    {
        return match ($type) {
            'special_memo' => SpecialMemo::find($id),
            'matrix' => Matrix::find($id),
            'activity', 'single_memo' => Activity::find($id),
            'non_travel_memo' => NonTravelMemo::find($id),
            'service_request' => ServiceRequest::find($id),
            'arf' => RequestARF::find($id),
            'change_request' => ChangeRequest::find($id),
            default => null,
        };
    }

    /**
     * Get attachment list from model (handles both 'attachment' and 'attachments' keys).
     */
    private function getAttachmentList(object $model, string $type): array
    {
        $key = $type === 'service_request' ? 'attachments' : 'attachment';
        $raw = $model->{$key} ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($raw) ? $raw : [];
    }

    /**
     * Build attachments array with url for each (API-accessible download link).
     * Each item gets url: GET /api/apm/v1/documents/attachments/{type}/{id}/{index} (requires Bearer token).
     */
    private function buildAttachmentsWithUrls(object $model, string $type, int $id): array
    {
        $list = $this->getAttachmentList($model, $type);
        $base = rtrim(url('/api/apm/v1/documents/attachments/' . $type . '/' . $id), '/');
        return array_values(array_map(function ($item, $index) use ($base) {
            $item = is_array($item) ? $item : [];
            $item['url'] = $base . '/' . $index;
            return $item;
        }, $list, array_keys($list)));
    }

    /**
     * Format approval trail collection for API (works with ApprovalTrail or ActivityApprovalTrail).
     * Returns array of { id, action, remarks, approval_order, staff_id, staff_name, oic_staff_id, oic_staff_name, role, created_at, is_archived }.
     */
    private function formatApprovalTrails(Collection $trails): array
    {
        if ($trails->isEmpty()) {
            return [];
        }
        return $trails->map(function ($t) {
            $staff = $t->relationLoaded('staff') ? $t->staff : null;
            $oic = $t->relationLoaded('oicStaff') ? $t->oicStaff : null;
            $staffName = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? '')) : null;
            $oicName = $oic ? trim(($oic->title ?? '') . ' ' . ($oic->fname ?? '') . ' ' . ($oic->lname ?? '') . ' ' . ($oic->oname ?? '')) : null;
            $role = null;
            if ($t->relationLoaded('workflowDefinition') && $t->workflowDefinition) {
                $role = $t->workflowDefinition->role ?? null;
            } elseif (isset($t->approver_role)) {
                $role = $t->approver_role;
            } elseif (method_exists($t, 'getApproverRoleNameAttribute')) {
                $role = $t->approver_role_name ?? null;
            }
            return [
                'id' => $t->id,
                'action' => $t->action ?? null,
                'remarks' => $t->remarks ?? null,
                'approval_order' => $t->approval_order ?? null,
                'staff_id' => $t->staff_id ?? null,
                'staff_name' => $staffName,
                'oic_staff_id' => $t->oic_staff_id ?? null,
                'oic_staff_name' => $oicName,
                'role' => $role,
                'created_at' => $t->created_at ? (\Carbon\Carbon::parse($t->created_at)->toIso8601String()) : null,
                'is_archived' => (bool) ($t->is_archived ?? false),
            ];
        })->values()->toArray();
    }
}
