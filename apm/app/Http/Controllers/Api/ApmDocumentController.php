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
use App\Models\FundCode;
use App\Models\Location;
use App\Models\NonTravelMemoCategory;
use App\Models\RequestType;
use App\Models\Staff;
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
     * Stream a document attachment for in-browser viewing (web session auth).
     * Use this URL when opening attachments in the browser so the user is authorized via session.
     */
    public function streamAttachment(Request $request, string $type, int $id, int $index): JsonResponse|StreamedResponse
    {
        if (!session()->has('user')) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
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
        $data['print_url'] = $this->getPrintUrl('special_memo', $memo);
        $data['budget_information'] = $this->buildBudgetInformation($memo, 'special_memo');
        $data = array_merge($data, $this->enrichDocumentRelations($memo, 'special_memo'));
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
        $data = $this->normalizeMemoJsonFields($matrix->toArray());
        $data['document_type'] = 'matrix';
        $data['approval_trails'] = $this->formatApprovalTrails($matrix->matrixApprovalTrails ?? collect());
        $data['print_url'] = $this->getPrintUrl('matrix', $matrix);
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function activity(int $id): JsonResponse
    {
        // Do not load matrix relation for activity document — only include matrix when document type is matrix.
        $activity = Activity::with([
            'staff', 'requestType', 'fundType', 'responsiblePerson',
            'activity_budget.fundcode.funder',
            'activityApprovalTrails.staff', 'activityApprovalTrails.oicStaff',
            'approvalTrails.staff', 'approvalTrails.oicStaff',
        ])->find($id);
        if (!$activity) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $activity->toArray();
        // Remove matrix-related payload for activity/single_memo document (type is not matrix).
        foreach (['matrix', 'division_schedule', 'division_staff', 'workflow_definition', 'current_actor', 'has_intramural', 'has_extramural', 'intramural_budget', 'extramural_budget'] as $key) {
            unset($data[$key]);
        }
        $data['internal_participants'] = $this->formatActivityInternalParticipants($activity);
        $data['document_type'] = $activity->is_single_memo ? 'single_memo' : 'activity';
        $trails = $activity->is_single_memo
            ? ($activity->approvalTrails ?? collect())
            : ($activity->activityApprovalTrails ?? collect());
        $data['approval_trails'] = $this->formatApprovalTrails($trails);
        $data['attachments'] = $this->buildAttachmentsWithUrls($activity, 'activity', $id);
        $data['print_url'] = $this->getPrintUrl($activity->is_single_memo ? 'single_memo' : 'activity', $activity);
        $data['budget_information'] = $this->buildBudgetInformation($activity, $activity->is_single_memo ? 'single_memo' : 'activity');
        $data = array_merge($data, $this->enrichDocumentRelations($activity, $data['document_type']));
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function nonTravelMemo(int $id): JsonResponse
    {
        $memo = NonTravelMemo::with([
            'staff', 'division', 'fundType', 'nonTravelMemoCategory', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
        ])->find($id);
        if (!$memo) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $memo->toArray();
        $data['document_type'] = 'non_travel_memo';
        $data['approval_trails'] = $this->formatApprovalTrails($memo->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($memo, 'non_travel_memo', $id);
        $data['print_url'] = $this->getPrintUrl('non_travel_memo', $memo);
        $data['budget_information'] = $this->buildBudgetInformation($memo, 'non_travel_memo');
        $data = array_merge($data, $this->enrichDocumentRelations($memo, 'non_travel_memo'));
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function serviceRequest(int $id): JsonResponse
    {
        $doc = ServiceRequest::with([
            'staff', 'division', 'fundType', 'activity', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
        ])->find($id);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $doc->toArray();
        $data['document_type'] = 'service_request';
        $data['approval_trails'] = $this->formatApprovalTrails($doc->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($doc, 'service_request', $id);
        $data['print_url'] = $this->getPrintUrl('service_request', $doc);
        $data['budget_information'] = $this->buildBudgetInformation($doc, 'service_request');
        $data = array_merge($data, $this->enrichDocumentRelations($doc, 'service_request'));
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function arf(int $id): JsonResponse
    {
        $doc = RequestARF::with([
            'staff', 'division', 'fundType', 'funder', 'partner', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
        ])->find($id);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $doc->toArray();
        $data['document_type'] = 'arf';
        $data['approval_trails'] = $this->formatApprovalTrails($doc->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($doc, 'arf', $id);
        $data['print_url'] = $this->getPrintUrl('arf', $doc);
        $data['budget_information'] = $this->buildBudgetInformation($doc, 'arf');
        $data = array_merge($data, $this->enrichDocumentRelations($doc, 'arf'));
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function changeRequest(int $id): JsonResponse
    {
        $doc = ChangeRequest::with([
            'staff', 'division', 'matrix', 'fundType', 'requestType', 'nonTravelMemoCategory', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'approvalTrails.workflowDefinition', 'forwardWorkflow',
        ])->find($id);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        $data = $this->normalizeMemoJsonFields($doc->toArray());
        $data['document_type'] = 'change_request';
        $data['approval_trails'] = $this->formatApprovalTrails($doc->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($doc, 'change_request', $id);
        $data['print_url'] = $this->getPrintUrl('change_request', $doc);
        $data['budget_information'] = $this->buildBudgetInformation($doc, 'change_request');
        $data = array_merge($data, $this->enrichDocumentRelations($doc, 'change_request'));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Return the web print/PDF URL for an approved document, or null if not approved.
     * Included in document detail payload when overall_status is approved.
     */
    private function getPrintUrl(string $documentType, object $model): ?string
    {
        $status = $model->overall_status ?? $model->status ?? null;
        if (strtolower((string) $status) !== 'approved') {
            return null;
        }

        try {
            return match ($documentType) {
                'special_memo' => route('special-memo.print', ['specialMemo' => $model->id]),
                'matrix' => route('matrices.export.pdf', ['matrix' => $model->id]),
                'activity', 'single_memo' => $model->is_single_memo
                    ? route('activities.single-memos.print', ['activity' => $model->id])
                    : (($matrixId = $model->matrix_id ?? $model->matrix?->id) ? route('matrices.export.pdf', ['matrix' => $matrixId]) : null),
                'non_travel_memo' => route('non-travel.print', ['nonTravel' => $model->id]),
                'service_request' => route('service-requests.print', ['serviceRequest' => $model->id]),
                'arf' => route('request-arf.print', ['requestARF' => $model->id]),
                'change_request' => route('change-requests.print', ['changeRequest' => $model->id]),
                default => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
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
                'staff', 'requestType', 'fundType', 'responsiblePerson',
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
     * Format activity internal_participants (raw object keyed by staff_id) as a list with staff names,
     * matching the shape used by the matrix endpoint for activities.
     */
    private function formatActivityInternalParticipants(Activity $activity): array
    {
        $raw = $activity->internal_participants ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw) || empty($raw)) {
            return [];
        }
        $staffIds = array_values(array_unique(array_filter(array_map('intval', array_keys($raw)))));
        $staffById = $staffIds ? Staff::with('division')->whereIn('staff_id', $staffIds)->get()->keyBy('staff_id') : collect();
        $list = [];
        foreach ($raw as $staffId => $participantData) {
            $staffId = (int) $staffId;
            $staff = $staffById->get($staffId);
            $participantName = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? '')) : null;
            $division = $staff && $staff->relationLoaded('division') ? $staff->division : null;
            $list[] = [
                'staff_id' => $staffId,
                'name' => $participantName,
                'participant_name' => $participantName,
                'division_id' => $staff ? (int) $staff->division_id : null,
                'division_name' => $division ? ($division->division_name ?? null) : null,
                'participant_start' => $participantData['participant_start'] ?? null,
                'participant_end' => $participantData['participant_end'] ?? null,
                'participant_days' => isset($participantData['participant_days']) ? (int) $participantData['participant_days'] : null,
                'international_travel' => (int) ($participantData['international_travel'] ?? 0),
            ];
        }
        return $list;
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
        $data = $this->normalizeMemoJsonFields($model->toArray());
        $docType = $type === 'activity' && isset($model->is_single_memo) && $model->is_single_memo ? 'single_memo' : $type;
        $data['document_type'] = $docType;
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
        $data['print_url'] = $this->getPrintUrl($docType, $model);
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
     * Add resolved relation payloads for location_id, non_travel_memo_category_id, budget_id, request_type_id
     * so the API returns text/object values (locations, non_travel_memo_category, fund_codes, request_type) where they exist.
     */
    private function enrichDocumentRelations(object $model, string $documentType): array
    {
        $out = [];

        // request_type_id is valid only for special_memo, activity, single_memo, change_request
        $hasRequestType = in_array($documentType, ['special_memo', 'activity', 'single_memo', 'change_request'], true);
        if ($hasRequestType && isset($model->request_type_id) && $model->request_type_id) {
            $rt = $model->relationLoaded('requestType') ? $model->requestType : RequestType::find($model->request_type_id);
            if ($rt) {
                $out['request_type'] = ['id' => $rt->id, 'name' => $rt->name];
            }
        }

        // non_travel_memo_category_id is valid only for non_travel_memo and change_request
        $hasNonTravelCategory = in_array($documentType, ['non_travel_memo', 'change_request'], true);
        if ($hasNonTravelCategory && isset($model->non_travel_memo_category_id) && $model->non_travel_memo_category_id) {
            $cat = $model->relationLoaded('nonTravelMemoCategory') ? $model->nonTravelMemoCategory : NonTravelMemoCategory::find($model->non_travel_memo_category_id);
            if ($cat) {
                $out['non_travel_memo_category'] = ['id' => $cat->id, 'name' => $cat->name];
            }
        }

        // location_id (JSON array or array of ids) -> locations [ { id, name }, ... ]
        $locationIds = $model->location_id ?? null;
        if ($locationIds !== null) {
            if (is_string($locationIds)) {
                $locationIds = json_decode($locationIds, true);
            }
            if (is_array($locationIds) && !empty($locationIds)) {
                $ids = array_values(array_map('intval', array_filter($locationIds, fn ($v) => is_numeric($v))));
                if (!empty($ids)) {
                    $locations = Location::whereIn('id', $ids)->get();
                    $out['locations'] = $locations->map(fn ($loc) => ['id' => $loc->id, 'name' => $loc->name])->values()->toArray();
                }
            }
        }

        // budget_id (JSON array or array of ids) -> fund_codes [ { id, code, activity, fund_type_name, funder_name, partner_name }, ... ]
        $budgetIds = $model->budget_id ?? null;
        if ($budgetIds !== null) {
            if (is_string($budgetIds)) {
                $budgetIds = json_decode($budgetIds, true);
            }
            if (is_array($budgetIds) && !empty($budgetIds)) {
                $ids = array_values(array_filter(array_map('intval', $budgetIds), fn ($id) => $id > 0));
                if (!empty($ids)) {
                    $codes = FundCode::with(['fundType', 'funder', 'partner'])->whereIn('id', $ids)->get();
                    $out['fund_codes'] = $codes->map(fn ($fc) => [
                        'id' => $fc->id,
                        'code' => $fc->code,
                        'activity' => $fc->activity ?? null,
                        'fund_type_name' => $fc->fundType->name ?? null,
                        'funder_name' => $fc->funder->name ?? null,
                        'partner_name' => $fc->partner->name ?? null,
                    ])->values()->toArray();
                }
            }
        }

        return $out;
    }

    /**
     * Build processed budget information for API, following how each document type displays budget on show pages.
     * Travel-style (special_memo, activity, single_memo): by_fund_code with items (cost, unit_cost, units, days, total, description).
     * Non-travel style (non_travel_memo, service_request when source is non_travel): by_fund_code with items (description, quantity, unit_cost, total, notes).
     * ARF: total_amount, fund_type, funder, partner, and parsed budget_breakdown.
     */
    private function buildBudgetInformation(object $model, string $documentType): array
    {
        return match ($documentType) {
            'special_memo' => $this->buildBudgetTravelStyle($model, $model->fundType ?? null),
            'activity', 'single_memo' => $this->buildBudgetTravelStyle($model, $model->fundType ?? null),
            'non_travel_memo' => $this->buildBudgetNonTravelStyle($model, $model->fundType ?? null),
            'service_request' => $this->buildBudgetServiceRequest($model),
            'arf' => $this->buildBudgetArf($model),
            'change_request' => $this->buildBudgetChangeRequest($model),
            default => ['total' => 0, 'by_fund_code' => [], 'fund_codes' => [], 'fund_type' => null],
        };
    }

    /** Travel-style: budget_breakdown keyed by fund_code_id, items have cost, unit_cost, units, days, description. */
    private function buildBudgetTravelStyle(object $model, $fundType): array
    {
        $raw = $model->budget_breakdown ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        $byFundCode = [];
        $total = 0;
        foreach ($raw as $key => $item) {
            if ($key === 'grand_total') {
                $total = (float) $item;
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            $fundCodeId = is_numeric($key) ? (int) $key : $key;
            $items = [];
            $groupTotal = 0;
            foreach ($item as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $unitCost = (float) ($row['unit_cost'] ?? 0);
                $units = (float) ($row['units'] ?? 0);
                $days = (float) ($row['days'] ?? 1);
                $lineTotal = $days > 1 ? $unitCost * $units * $days : $unitCost * $units;
                $groupTotal += $lineTotal;
                $items[] = [
                    'cost' => $row['cost'] ?? 'N/A',
                    'unit_cost' => round($unitCost, 2),
                    'units' => $units,
                    'days' => $days,
                    'total' => round($lineTotal, 2),
                    'description' => $row['description'] ?? '',
                ];
            }
            $total += $groupTotal;
            $byFundCode[] = [
                'fund_code_id' => $fundCodeId,
                'items' => $items,
                'group_total' => round($groupTotal, 2),
            ];
        }
        if ($total == 0 && !empty($byFundCode)) {
            $total = array_sum(array_column($byFundCode, 'group_total'));
        }
        $fundCodeIds = array_unique(array_column($byFundCode, 'fund_code_id'));
        $fundCodes = FundCode::with(['fundType', 'funder', 'partner'])->whereIn('id', $fundCodeIds)->get()->keyBy('id');
        $fundCodesList = [];
        foreach ($byFundCode as &$group) {
            $fc = $fundCodes->get($group['fund_code_id']);
            $group['fund_code'] = $fc ? [
                'id' => $fc->id,
                'code' => $fc->code,
                'activity' => $fc->activity ?? null,
                'fund_type_name' => $fc->fundType->name ?? null,
                'funder_name' => $fc->funder->name ?? null,
                'partner_name' => $fc->partner->name ?? null,
            ] : null;
            if ($fc && !isset($fundCodesList[$fc->id])) {
                $fundCodesList[$fc->id] = [
                    'id' => $fc->id,
                    'code' => $fc->code,
                    'activity' => $fc->activity ?? null,
                    'fund_type_name' => $fc->fundType->name ?? null,
                    'funder_name' => $fc->funder->name ?? null,
                    'partner_name' => $fc->partner->name ?? null,
                ];
            }
        }
        return [
            'total' => round($total, 2),
            'by_fund_code' => array_values($byFundCode),
            'fund_codes' => array_values($fundCodesList),
            'fund_type' => $fundType ? ['id' => $fundType->id, 'name' => $fundType->name] : null,
        ];
    }

    /** Non-travel style: budget_breakdown keyed by fund_code_id, items have description, quantity, unit_cost, notes. */
    private function buildBudgetNonTravelStyle(object $model, $fundType): array
    {
        $raw = $model->budget_breakdown ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        unset($raw['grand_total']);
        $budgetIds = $model->budget_id ?? [];
        if (is_string($budgetIds)) {
            $budgetIds = json_decode($budgetIds, true) ?? [];
        }
        $allCodeIds = array_unique(array_merge($budgetIds, array_map('intval', array_keys($raw))));
        $allCodeIds = array_filter($allCodeIds, fn ($id) => $id > 0);
        $fundCodes = FundCode::with(['fundType', 'funder', 'partner'])->whereIn('id', $allCodeIds)->get()->keyBy('id');
        $byFundCode = [];
        $total = 0;
        foreach ($raw as $key => $items) {
            if (!is_array($items)) {
                continue;
            }
            $fundCodeId = is_numeric($key) ? (int) $key : $key;
            $rows = [];
            $groupTotal = 0;
            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $qty = (float) ($row['quantity'] ?? 1);
                $unitCost = (float) ($row['unit_cost'] ?? 0);
                $lineTotal = $qty * $unitCost;
                $groupTotal += $lineTotal;
                $rows[] = [
                    'description' => $row['description'] ?? 'N/A',
                    'quantity' => $qty,
                    'unit_cost' => round($unitCost, 2),
                    'total' => round($lineTotal, 2),
                    'notes' => $row['notes'] ?? null,
                ];
            }
            $total += $groupTotal;
            $fc = $fundCodes->get($fundCodeId);
            $byFundCode[] = [
                'fund_code_id' => $fundCodeId,
                'fund_code' => $fc ? [
                    'id' => $fc->id,
                    'code' => $fc->code,
                    'activity' => $fc->activity ?? null,
                    'fund_type_name' => $fc->fundType->name ?? null,
                    'funder_name' => $fc->funder->name ?? null,
                    'partner_name' => $fc->partner->name ?? null,
                ] : null,
                'items' => $rows,
                'group_total' => round($groupTotal, 2),
            ];
        }
        $fundCodesList = $fundCodes->map(fn ($fc) => [
            'id' => $fc->id,
            'code' => $fc->code,
            'activity' => $fc->activity ?? null,
            'fund_type_name' => $fc->fundType->name ?? null,
            'funder_name' => $fc->funder->name ?? null,
            'partner_name' => $fc->partner->name ?? null,
        ])->values()->toArray();
        return [
            'total' => round($total, 2),
            'by_fund_code' => array_values($byFundCode),
            'fund_codes' => $fundCodesList,
            'fund_type' => $fundType ? ['id' => $fundType->id, 'name' => $fundType->name] : null,
        ];
    }

    /** Service request: same as non-travel when source is non_travel_memo; else travel-style. Plus original/new totals. */
    private function buildBudgetServiceRequest(object $model): array
    {
        $base = $model->source_type === 'App\\Models\\NonTravelMemo' || $model->source_type === 'non_travel_memo'
            ? $this->buildBudgetNonTravelStyle($model, $model->fundType ?? null)
            : $this->buildBudgetTravelStyle($model, $model->fundType ?? null);
        $base['original_total_budget'] = $model->original_total_budget !== null ? (float) $model->original_total_budget : null;
        $base['new_total_budget'] = $model->new_total_budget !== null ? (float) $model->new_total_budget : null;
        return $base;
    }

    /** ARF: total_amount, fund_type, funder, partner; budget_breakdown parsed (travel or non-travel by shape). */
    private function buildBudgetArf(object $model): array
    {
        $raw = $model->budget_breakdown ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $fundType = $model->relationLoaded('fundType') ? $model->fundType : null;
        $funder = $model->relationLoaded('funder') ? $model->funder : null;
        $partner = $model->relationLoaded('partner') ? $model->partner : null;
        $base = [
            'total_amount' => $model->total_amount !== null ? (float) $model->total_amount : null,
            'fund_type' => $fundType ? ['id' => $fundType->id, 'name' => $fundType->name] : null,
            'funder' => $funder ? ['id' => $funder->id, 'name' => $funder->name] : null,
            'partner' => $partner ? ['id' => $partner->id, 'name' => $partner->name] : null,
            'extramural_code' => $model->extramural_code ?? null,
            'by_fund_code' => [],
            'fund_codes' => [],
        ];
        if (!is_array($raw) || empty($raw)) {
            return $base;
        }
        $firstKey = array_key_first($raw);
        $firstVal = $raw[$firstKey];
        $isNonTravel = is_array($firstVal) && isset($firstVal[0]) && is_array($firstVal[0])
            && array_key_exists('quantity', $firstVal[0]) && array_key_exists('description', $firstVal[0])
            && !array_key_exists('units', $firstVal[0]);
        $base = array_merge($base, $isNonTravel
            ? $this->buildBudgetNonTravelStyle($model, $fundType)
            : $this->buildBudgetTravelStyle($model, $fundType));
        $base['total_amount'] = $model->total_amount !== null ? (float) $model->total_amount : ($base['total'] ?? null);
        return $base;
    }

    /** Change request: budget shape follows parent_memo_model (SpecialMemo/Activity -> travel, NonTravelMemo -> non-travel). */
    private function buildBudgetChangeRequest(object $model): array
    {
        $parent = $model->parent_memo_model ?? '';
        $isNonTravel = $parent === 'App\\Models\\NonTravelMemo' || $parent === 'NonTravelMemo';
        $fundType = $model->relationLoaded('fundType') ? $model->fundType : null;
        return $isNonTravel
            ? $this->buildBudgetNonTravelStyle($model, $fundType)
            : $this->buildBudgetTravelStyle($model, $fundType);
    }

    /**
     * Build attachments array with url for each (API-accessible) and web_view_url (for in-browser viewing with session).
     * - url: GET /api/apm/v1/documents/attachments/{type}/{id}/{index} (requires Bearer token).
     * - web_view_url: GET /documents/attachments/{type}/{id}/{index} (requires web session; use to open in browser).
     */
    private function buildAttachmentsWithUrls(object $model, string $type, int $id): array
    {
        $list = $this->getAttachmentList($model, $type);
        $apiBase = rtrim(url('/api/apm/v1/documents/attachments/' . $type . '/' . $id), '/');
        return array_values(array_map(function ($item, $index) use ($apiBase, $type, $id) {
            $item = is_array($item) ? $item : [];
            $item['url'] = $apiBase . '/' . $index;
            try {
                $item['web_view_url'] = route('documents.attachments.stream', ['type' => $type, 'id' => $id, 'index' => $index]);
            } catch (\Throwable $e) {
                $item['web_view_url'] = url('/documents/attachments/' . $type . '/' . $id . '/' . $index);
            }
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
