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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $fullPath = $this->resolveAttachmentFilePath($path);
        if ($fullPath === null) {
            Log::warning('Attachment file not found', ['type' => $type, 'id' => $id, 'index' => $index, 'path' => $path]);
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

        $fullPath = $this->resolveAttachmentFilePath($path);
        if ($fullPath === null) {
            Log::warning('Attachment file not found (stream)', ['type' => $type, 'id' => $id, 'index' => $index, 'path' => $path]);
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
     * Resolve stored attachment path to a real file path. Path may be stored relative to
     * storage/app/public (e.g. "uploads/special-memos/file.pdf") or with "storage/" prefix.
     * Returns full filesystem path or null if file does not exist.
     */
    private function resolveAttachmentFilePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        // Normalize: strip leading "storage/" or "/" so path is relative to public disk root
        $path = preg_replace('#^/+#', '', $path);
        $path = preg_replace('#^storage/+#', '', $path);

        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            return $disk->path($path);
        }
        // Fallback: path might be stored as absolute under storage/app/public
        $basePath = realpath(storage_path('app/public')) ?: storage_path('app/public');
        $fullPath = realpath($basePath . '/' . $path);
        if ($fullPath !== false && str_starts_with($fullPath, $basePath) && is_file($fullPath)) {
            return $fullPath;
        }
        return null;
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
        $data = $this->mergeCurrentApprovalIntoDocument($data, $memo, 'special_memo');
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
        $data = $this->mergeCurrentApprovalIntoDocument($data, $matrix, 'matrix');
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
        $data = $this->mergeCurrentApprovalIntoDocument($data, $activity, $data['document_type']);
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
        $data = $this->mergeCurrentApprovalIntoDocument($data, $memo, 'non_travel_memo');
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function serviceRequest(int $id): JsonResponse
    {
        $doc = ServiceRequest::with([
            'staff', 'division', 'fundType', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
        ])->find($id);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $this->buildServiceRequestDocumentData($doc, $id)]);
    }

    /**
     * Build service request document data (same shape for show and list endpoints).
     */
    private function buildServiceRequestDocumentData(ServiceRequest $doc, int $id): array
    {
        $data = $doc->toArray();
        unset($data['activity'], $data['budget_breakdown']);
        $data['document_type'] = 'service_request';
        $data['internal_participants'] = $this->formatServiceRequestParticipantCostList($doc->internal_participants_cost ?? [], true);
        $data['external_participants'] = $this->formatServiceRequestParticipantCostList($doc->external_participants_cost ?? [], false);
        $data['internal_participants_cost'] = $this->formatServiceRequestParticipantCostList($doc->internal_participants_cost ?? [], true);
        $data['external_participants_cost'] = $this->ensureList($doc->external_participants_cost ?? []);
        $data['other_costs'] = $this->ensureList($doc->other_costs ?? []);
        $data['budget_breakdown'] = $this->processServiceRequestBudgetBreakdown($doc->budget_breakdown ?? null);
        $data['approval_trails'] = $this->formatApprovalTrails($doc->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($doc, 'service_request', $id);
        $data['print_url'] = $this->getPrintUrl('service_request', $doc);
        return $this->mergeCurrentApprovalIntoDocument($data, $doc, 'service_request');
    }

    private function arf(int $id): JsonResponse
    {
        $doc = RequestARF::with([
            'staff', 'division', 'fundType', 'funder', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
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
        $data = $this->mergeCurrentApprovalIntoDocument($data, $doc, 'arf');
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function changeRequest(int $id): JsonResponse
    {
        $doc = ChangeRequest::with([
            'staff', 'division', 'fundType', 'requestType', 'nonTravelMemoCategory', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'approvalTrails.workflowDefinition', 'forwardWorkflow',
        ])->find($id);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $this->buildChangeRequestDocumentData($doc, $id)]);
    }

    /**
     * Build change request document data (same shape for show and list endpoints).
     */
    private function buildChangeRequestDocumentData(ChangeRequest $doc, int $id): array
    {
        $parentIsActivity = in_array($doc->parent_memo_model ?? '', ['App\\Models\\Activity', 'Activity'], true);
        if (!$parentIsActivity && !$doc->relationLoaded('matrix')) {
            $doc->load('matrix');
        }
        $data = $this->normalizeDocumentJsonFields($doc->toArray());
        if ($parentIsActivity) {
            unset($data['matrix']);
        }
        $data['document_type'] = 'change_request';
        $data['source_id'] = $doc->parent_memo_id;
        $data['source_type'] = $this->changeRequestParentModelToSourceType($doc->parent_memo_model);
        // Use raw DB value: column can be double-encoded JSON (string of JSON string), so decode until we get an array
        $rawIp = $doc->getRawOriginal('internal_participants');
        $data['internal_participants'] = $this->formatInternalParticipantsFromRaw($this->decodeJsonFieldUntilArray($rawIp));
        $data['approval_trails'] = $this->formatApprovalTrails($doc->approvalTrails ?? collect());
        $data['attachments'] = $this->buildAttachmentsWithUrls($doc, 'change_request', $id);
        $data['print_url'] = $this->getPrintUrl('change_request', $doc);
        $data['budget_information'] = $this->buildBudgetInformation($doc, 'change_request');
        $data = array_merge($data, $this->enrichDocumentRelations($doc, 'change_request'));
        return $this->mergeCurrentApprovalIntoDocument($data, $doc, 'change_request');
    }

    /**
     * Map change request parent_memo_model (full class) to short source_type name, e.g. "activity", "special_memo".
     */
    private function changeRequestParentModelToSourceType(?string $parentMemoModel): ?string
    {
        if ($parentMemoModel === null || $parentMemoModel === '') {
            return null;
        }
        $basename = class_basename(str_replace('\\\\', '\\', $parentMemoModel));
        return strtolower(Str::snake($basename));
    }

    /**
     * Normalize document array for API: decode any JSON string fields that are still raw strings.
     * Used by matrix, activity, and other document types.
     */
    private function normalizeMemoJsonFields(array $data): array
    {
        return $this->normalizeDocumentJsonFields($data);
    }

    /**
     * Normalize document array for API: decode any JSON string fields that are still raw strings.
     * Shared implementation so both normalizeMemoJsonFields and direct callers work.
     */
    private function normalizeDocumentJsonFields(array $data): array
    {
        $jsonKeys = [
            'budget_breakdown', 'budget_id', 'location_id', 'attachment', 'attachments',
            'internal_participants', 'division_staff_request', 'key_result_area',
            'approval_order_map',
        ];
        foreach ($jsonKeys as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $decoded = json_decode($data[$key], true);
                $data[$key] = is_array($decoded) ? $decoded : $data[$key];
            }
        }
        return $data;
    }

    /**
     * Decode a JSON field that may be double-encoded (e.g. DB stores a JSON string whose value is another JSON string).
     * Keeps decoding until an array is obtained or no longer a string. Returns [] if invalid or not array.
     */
    private function decodeJsonFieldUntilArray($value): array
    {
        $maxDecodes = 5;
        while ($maxDecodes-- > 0) {
            if ($value === null || !is_string($value)) {
                return is_array($value) ? $value : [];
            }
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (!is_string($decoded)) {
                return [];
            }
            $value = $decoded;
        }
        return [];
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
                'staff', 'division', 'fundType', 'nonTravelMemoCategory', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
            ])->where('overall_status', $status),
            'service_request' => ServiceRequest::with([
                'staff', 'division', 'fundType', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
            ])->where('overall_status', $status),
            'arf' => RequestARF::with([
                'staff', 'division', 'fundType', 'funder', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'forwardWorkflow',
            ])->where('overall_status', $status),
            'change_request' => ChangeRequest::with([
                'staff', 'division', 'fundType', 'requestType', 'nonTravelMemoCategory', 'matrix', 'approvalTrails.staff', 'approvalTrails.oicStaff', 'approvalTrails.workflowDefinition', 'forwardWorkflow',
            ])->where('overall_status', $status),
            default => null,
        };
    }

    /**
     * Ensure value is a JSON list (0-indexed array). Decode if string, then array_values.
     */
    private function ensureList($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }
        return array_values($value);
    }

    /**
     * Process service request budget_breakdown: decode, normalize "Unknown Cost (ID: X)" keys, return as list structure.
     */
    private function processServiceRequestBudgetBreakdown($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        $data = $this->normalizeBudgetBreakdownCostKeysRecursive($raw);
        return $this->budgetBreakdownAsList($data);
    }

    /**
     * Recursively normalize "Unknown Cost (ID: X)" keys in costs arrays within budget_breakdown.
     */
    private function normalizeBudgetBreakdownCostKeysRecursive(array $data): array
    {
        $prefix = 'Unknown Cost (ID: ';
        $len = strlen($prefix);
        foreach ($data as $key => $value) {
            if ($key === 'costs' && is_array($value)) {
                $newCosts = [];
                foreach ($value as $costKey => $costVal) {
                    $k = (string) $costKey;
                    if (strpos($k, $prefix) === 0 && substr($k, -1) === ')') {
                        $costKey = substr($k, $len, -1);
                    }
                    $newCosts[$costKey] = $costVal;
                }
                $data['costs'] = $newCosts;
            } elseif (is_array($value)) {
                $data[$key] = $this->normalizeBudgetBreakdownCostKeysRecursive($value);
            }
        }
        return $data;
    }

    /**
     * Convert budget_breakdown to list form: sections that are arrays become 0-indexed lists.
     */
    private function budgetBreakdownAsList(array $data): array
    {
        $listKeys = ['internal_participants', 'external_participants', 'other_costs', 'by_fund_code', 'items'];
        foreach ($listKeys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = array_values($data[$key]);
            }
        }
        if (isset($data['by_fund_code']) && is_array($data['by_fund_code'])) {
            foreach ($data['by_fund_code'] as $i => $group) {
                if (is_array($group) && isset($group['items']) && is_array($group['items'])) {
                    $data['by_fund_code'][$i]['items'] = array_values($group['items']);
                }
            }
        }
        return $data;
    }

    /**
     * Normalize participant cost keys: "Unknown Cost (ID: Name)" -> "Name" so the API returns proper cost labels.
     */
    private function normalizeParticipantCostsKeys(array $row): array
    {
        if (!isset($row['costs']) || !is_array($row['costs'])) {
            return $row;
        }
        $prefix = 'Unknown Cost (ID: ';
        $len = strlen($prefix);
        $newCosts = [];
        foreach ($row['costs'] as $costKey => $value) {
            $key = (string) $costKey;
            if (strpos($key, $prefix) === 0 && substr($key, -1) === ')') {
                $costKey = substr($key, $len, -1);
            }
            $newCosts[$costKey] = $value;
        }
        $row['costs'] = $newCosts;
        return $row;
    }

    /**
     * Format service request participant cost array as a list with staff names when staff_id present (internal).
     */
    private function formatServiceRequestParticipantCostList($raw, bool $resolveStaff): array
    {
        $arr = $this->ensureList($raw);
        $arr = array_map(fn ($row) => $this->normalizeParticipantCostsKeys(is_array($row) ? $row : []), $arr);
        if (!$resolveStaff || empty($arr)) {
            return $arr;
        }
        $staffIds = array_values(array_unique(array_filter(array_map(function ($row) {
            $id = $row['staff_id'] ?? null;
            return is_numeric($id) ? (int) $id : null;
        }, $arr))));
        $staffById = $staffIds ? Staff::with('division')->whereIn('staff_id', $staffIds)->get()->keyBy('staff_id') : collect();
        return array_map(function ($row) use ($staffById) {
            $row = is_array($row) ? $row : [];
            $staffId = isset($row['staff_id']) ? (int) $row['staff_id'] : null;
            $staff = $staffId ? $staffById->get($staffId) : null;
            $name = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? '')) : null;
            $row['name'] = $name;
            $row['participant_name'] = $name;
            $row['division_name'] = ($staff && $staff->relationLoaded('division') && $staff->division) ? ($staff->division->division_name ?? null) : null;
            return $row;
        }, $arr);
    }

    /**
     * Format raw internal_participants (object keyed by staff_id) as a list with staff names/division resolved from Staff.
     * Returns every participant from the raw data; only skips staff_id < 1 (invalid placeholders).
     * Name/division are null when Staff record is not found.
     */
    private function formatInternalParticipantsFromRaw($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw) || empty($raw)) {
            return [];
        }
        $staffIds = array_values(array_unique(array_filter(array_map('intval', array_keys($raw)), fn ($id) => $id > 0)));
        $staffById = $staffIds ? Staff::with('division')->whereIn('staff_id', $staffIds)->get()->keyBy('staff_id') : collect();
        $list = [];
        foreach ($raw as $staffId => $participantData) {
            if (!is_array($participantData)) {
                continue;
            }
            $staffId = (int) $staffId;
            if ($staffId < 1) {
                continue;
            }
            $staff = $staffById->get($staffId);
            $participantName = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? '')) : null;
            if ($participantName !== null && $participantName === '') {
                $participantName = null;
            }
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
     * Format activity internal_participants (raw object keyed by staff_id) as a list with staff names,
     * matching the shape used by the matrix endpoint for activities.
     */
    private function formatActivityInternalParticipants(Activity $activity): array
    {
        return $this->formatInternalParticipantsFromRaw($activity->internal_participants ?? []);
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
     * Convert a loaded model to API document data (same shape as show() for each type).
     */
    private function modelToDocumentData(object $model, string $type, int $id): array
    {
        if ($type === 'service_request' && $model instanceof ServiceRequest) {
            return $this->buildServiceRequestDocumentData($model, $id);
        }
        if ($type === 'change_request' && $model instanceof ChangeRequest) {
            return $this->buildChangeRequestDocumentData($model, $id);
        }

        $data = $this->normalizeMemoJsonFields($model->toArray());
        $docType = $type === 'activity' && isset($model->is_single_memo) && $model->is_single_memo ? 'single_memo' : $type;
        $data['document_type'] = $docType;

        if ($type === 'activity' || $type === 'single_memo') {
            foreach (['matrix', 'division_schedule', 'division_staff', 'workflow_definition', 'current_actor', 'has_intramural', 'has_extramural', 'intramural_budget', 'extramural_budget'] as $key) {
                unset($data[$key]);
            }
            $data['internal_participants'] = $this->formatActivityInternalParticipants($model);
        }

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

        if (in_array($type, ['special_memo', 'non_travel_memo', 'arf', 'activity', 'single_memo'], true)) {
            $data['budget_information'] = $this->buildBudgetInformation($model, $docType);
            $data = array_merge($data, $this->enrichDocumentRelations($model, $docType));
        }

        return $this->mergeCurrentApprovalIntoDocument($data, $model, $docType);
    }

    /**
     * Current workflow step and role for document API payloads.
     * Matrix line items (activity, is_single_memo = 0): matrix approval_level + matrix workflow definition.
     * Single memo: activity approval_level + activity workflow definition.
     * Other types: that model's approval_level + its workflow_definition accessor.
     *
     * @param  string  $documentType  API document_type value (activity, single_memo, matrix, …).
     */
    private function currentApprovalApiFields(object $model, string $documentType): array
    {
        $approvalOrder = null;
        $def = null;

        if ($documentType === 'matrix' && $model instanceof Matrix) {
            $model->loadMissing('division');
            $approvalOrder = $model->approval_level;
            $def = $model->workflow_definition;
        } elseif (($documentType === 'activity' || $documentType === 'single_memo') && $model instanceof Activity) {
            if (!$model->is_single_memo) {
                $model->loadMissing('matrix.division');
                $matrix = $model->matrix;
                if ($matrix) {
                    $approvalOrder = $matrix->approval_level;
                    $def = $matrix->workflow_definition;
                }
            } else {
                $model->loadMissing('division');
                $approvalOrder = $model->approval_level;
                $def = $model->workflow_definition;
            }
        } elseif ($documentType === 'special_memo' && $model instanceof SpecialMemo) {
            $model->loadMissing('division');
            $approvalOrder = $model->approval_level;
            $def = $model->workflow_definition;
        } elseif ($documentType === 'non_travel_memo' && $model instanceof NonTravelMemo) {
            $model->loadMissing('division');
            $approvalOrder = $model->approval_level;
            $def = $model->workflow_definition;
        } elseif ($documentType === 'service_request' && $model instanceof ServiceRequest) {
            $model->loadMissing('division');
            $approvalOrder = $model->approval_level;
            $def = $model->workflow_definition;
        } elseif ($documentType === 'arf' && $model instanceof RequestARF) {
            $model->loadMissing('division');
            $approvalOrder = $model->approval_level;
            $def = $model->workflow_definition;
        } elseif ($documentType === 'change_request' && $model instanceof ChangeRequest) {
            $model->loadMissing('division');
            $approvalOrder = $model->approval_level;
            $def = $model->workflow_definition;
        }

        return [
            'approval_order' => $approvalOrder !== null ? (int) $approvalOrder : null,
            'approval_role' => $def?->role,
            'workflow_definition_id' => $def?->id !== null ? (int) $def->id : null,
        ];
    }

    private function mergeCurrentApprovalIntoDocument(array $data, object $model, string $documentType): array
    {
        return array_merge($data, $this->currentApprovalApiFields($model, $documentType));
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

    /**
     * Collect fund_code primary keys from budget_breakdown keys, model budget_id, and activity activity_budget rows.
     *
     * @param  array<int|string>  $breakdownFundCodeIds
     * @return array<int>
     */
    private function mergeFundCodeIdsForBudget(object $model, array $breakdownFundCodeIds): array
    {
        $ids = [];
        foreach ($breakdownFundCodeIds as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }
        $budgetId = $model->budget_id ?? null;
        if (is_string($budgetId)) {
            $budgetId = json_decode($budgetId, true);
        }
        if (is_array($budgetId)) {
            foreach ($budgetId as $bid) {
                if (is_numeric($bid) && (int) $bid > 0) {
                    $ids[] = (int) $bid;
                }
            }
        }
        if (method_exists($model, 'activity_budget')) {
            $rows = $model->relationLoaded('activity_budget')
                ? $model->activity_budget
                : null;
            if ($rows) {
                foreach ($rows as $row) {
                    $fc = $row->fund_code ?? null;
                    if (is_numeric($fc) && (int) $fc > 0) {
                        $ids[] = (int) $fc;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    /** @return array{id: int, code: mixed, activity: mixed, fund_type_name: mixed, funder_name: mixed, partner_name: mixed}|null */
    private function fundCodeToBudgetApiArray(?FundCode $fc): ?array
    {
        if (!$fc) {
            return null;
        }

        return [
            'id' => (int) $fc->id,
            'code' => $fc->code,
            'activity' => $fc->activity ?? null,
            'fund_type_name' => $fc->fundType->name ?? null,
            'funder_name' => $fc->funder->name ?? null,
            'partner_name' => $fc->partner->name ?? null,
        ];
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
            $fundCodeId = is_numeric($key) ? (int) $key : 0;
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
                'fund_code_id' => $fundCodeId > 0 ? $fundCodeId : null,
                'items' => $items,
                'group_total' => round($groupTotal, 2),
            ];
        }
        if ($total == 0 && !empty($byFundCode)) {
            $total = array_sum(array_column($byFundCode, 'group_total'));
        }
        $breakdownIds = array_filter(array_column($byFundCode, 'fund_code_id'), fn ($id) => $id !== null && (int) $id > 0);
        $allFundCodeIds = $this->mergeFundCodeIdsForBudget($model, $breakdownIds);
        $fundCodesByIntId = collect();
        if (!empty($allFundCodeIds)) {
            $fundCodesByIntId = FundCode::with(['fundType', 'funder', 'partner'])
                ->whereIn('id', $allFundCodeIds)
                ->get()
                ->keyBy(fn (FundCode $fc) => (int) $fc->id);
        }
        $fundCodesList = [];
        foreach ($byFundCode as &$group) {
            $fid = isset($group['fund_code_id']) && is_numeric($group['fund_code_id']) ? (int) $group['fund_code_id'] : 0;
            $fc = $fid > 0 ? $fundCodesByIntId->get($fid) : null;
            if (!$fc && $fid > 0) {
                $fc = FundCode::with(['fundType', 'funder', 'partner'])->find($fid);
                if ($fc) {
                    $fundCodesByIntId->put($fid, $fc);
                }
            }
            $group['fund_code'] = $this->fundCodeToBudgetApiArray($fc);
            if ($fc) {
                $fundCodesList[(int) $fc->id] = $this->fundCodeToBudgetApiArray($fc);
            }
        }
        unset($group);

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
        $breakdownKeyIds = [];
        foreach (array_keys($raw) as $key) {
            if (is_numeric($key) && (int) $key > 0) {
                $breakdownKeyIds[] = (int) $key;
            }
        }
        $allCodeIds = $this->mergeFundCodeIdsForBudget($model, $breakdownKeyIds);
        $fundCodesByIntId = collect();
        if (!empty($allCodeIds)) {
            $fundCodesByIntId = FundCode::with(['fundType', 'funder', 'partner'])
                ->whereIn('id', $allCodeIds)
                ->get()
                ->keyBy(fn (FundCode $fc) => (int) $fc->id);
        }
        $byFundCode = [];
        $total = 0;
        foreach ($raw as $key => $items) {
            if (!is_array($items)) {
                continue;
            }
            $fundCodeId = is_numeric($key) ? (int) $key : 0;
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
            $fid = $fundCodeId > 0 ? $fundCodeId : 0;
            $fc = $fid > 0 ? $fundCodesByIntId->get($fid) : null;
            if (!$fc && $fid > 0) {
                $fc = FundCode::with(['fundType', 'funder', 'partner'])->find($fid);
                if ($fc) {
                    $fundCodesByIntId->put($fid, $fc);
                }
            }
            $byFundCode[] = [
                'fund_code_id' => $fid > 0 ? $fid : null,
                'fund_code' => $this->fundCodeToBudgetApiArray($fc),
                'items' => $rows,
                'group_total' => round($groupTotal, 2),
            ];
        }
        $fundCodesList = [];
        foreach ($fundCodesByIntId as $fc) {
            $fundCodesList[] = $this->fundCodeToBudgetApiArray($fc);
        }
        return [
            'total' => round($total, 2),
            'by_fund_code' => array_values($byFundCode),
            'fund_codes' => array_values($fundCodesList),
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
