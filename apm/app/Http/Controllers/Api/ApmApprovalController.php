<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalTrail;
use App\Models\SpecialMemo;
use App\Models\Matrix;
use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use App\Models\ChangeRequest;
use App\Models\WorkflowModel;
use App\Services\ApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmApprovalController extends Controller
{
    /**
     * Apply action (approve, reject, return, cancel) to a document.
     * Cancel = return when current user is HOD (special memo only); same as web special-memo.
     */
    public function action(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:special_memo,non_travel_memo,single_memo,matrix,service_request,arf,change_request',
            'id' => 'required|integer',
            'action' => 'required|string|in:approved,rejected,returned,cancelled',
            'comment' => 'nullable|string|max:1000',
            'available_budget' => 'nullable|numeric|min:0',
        ]);

        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $modelType = $this->modelTypeFor($request->type);
        $modelId = (int) $request->id;
        $action = $request->action;

        // For special_memo, "cancelled" is allowed (HOD return). For others, map cancel to returned.
        if ($action === 'cancelled' && $request->type !== 'special_memo') {
            $action = 'returned';
        }

        $model = $this->resolveModel($modelType, $modelId);
        if (!$model) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        $approvalService = app(ApprovalService::class);
        if (!$approvalService->canTakeAction($model, $staffId) && !is_with_creator_generic($model)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to perform this action.'], 403);
        }

        $comment = $request->input('comment') ?? $request->input('remarks') ?? '';
        $additionalData = [];
        if ($request->filled('available_budget')) {
            $additionalData['available_budget'] = $request->available_budget;
        }

        $approvalService->processApproval($model, $action, $comment, $staffId, $additionalData);
        send_generic_email_notification($model, $action);

        return response()->json([
            'success' => true,
            'message' => 'Action applied successfully.',
            'data' => ['action' => $action, 'document_type' => $request->type, 'document_id' => $modelId],
        ]);
    }

    private function resolveModel(string $modelType, int $id): ?Model
    {
        $modelClass = "App\\Models\\{$modelType}";
        if (!class_exists($modelClass)) {
            return null;
        }
        return $modelClass::find($id);
    }

    private function modelTypeFor(string $type): string
    {
        $map = [
            'special_memo' => 'SpecialMemo',
            'non_travel_memo' => 'NonTravelMemo',
            'single_memo' => 'Activity',
            'matrix' => 'Matrix',
            'service_request' => 'ServiceRequest',
            'arf' => 'RequestARF',
            'change_request' => 'ChangeRequest',
        ];
        return $map[$type] ?? 'SpecialMemo';
    }

    /**
     * Resubmit a returned document for approval.
     * Works for all document types. Sends the document back to the previous level it came from
     * (the level that returned it), following the same logic as non-travel memo resubmit.
     */
    public function resubmit(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:special_memo,non_travel_memo,single_memo,matrix,service_request,arf,change_request',
            'id' => 'required|integer',
            'comment' => 'nullable|string|max:1000',
        ]);

        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $modelType = $this->modelTypeFor($request->type);
        $modelId = (int) $request->id;

        $model = $this->resolveModel($modelType, $modelId);
        if (!$model) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        if (!in_array($model->overall_status, ['returned', 'pending', 'draft'])) {
            return response()->json(['success' => false, 'message' => 'Only returned, pending or draft documents can be resubmitted.'], 422);
        }

        if (!$this->canUserResubmit($model, $staffId)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to resubmit this document.'], 403);
        }

        $modelClass = get_class($model);
        $workflowModelName = $modelType;

        if ($model->approval_level == 0) {
            // Document was returned to creator/focal person – resubmit to HOD (level 1)
            $workflowId = WorkflowModel::getWorkflowIdForModel($workflowModelName) ?: 1;
            $model->approval_level = 1;
            $model->forward_workflow_id = $workflowId;
            $model->next_approval_level = 2;
        } else {
            // Resubmit to the previous level that returned it
            $lastReturnedTrail = ApprovalTrail::where('model_type', $modelClass)
                ->where('model_id', $model->id)
                ->where('action', 'returned')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastReturnedTrail) {
                return response()->json(['success' => false, 'message' => 'Could not determine the level that returned this document.'], 422);
            }

            $model->approval_level = $lastReturnedTrail->approval_order;
            $model->forward_workflow_id = $lastReturnedTrail->forward_workflow_id;
            $model->next_approval_level = $lastReturnedTrail->approval_order + 1;
        }

        $model->overall_status = 'pending';
        $model->save();

        $resubmitTrail = new ApprovalTrail();
        $resubmitTrail->model_id = $model->id;
        $resubmitTrail->model_type = $modelClass;
        $resubmitTrail->remarks = $request->input('comment') ?? 'Document resubmitted for approval';
        $resubmitTrail->forward_workflow_id = $model->forward_workflow_id;
        $resubmitTrail->action = 'resubmitted';
        $resubmitTrail->approval_order = $model->approval_level;
        $resubmitTrail->staff_id = $staffId;
        $resubmitTrail->is_archived = 0;
        if (method_exists($model, 'matrix_id') && !empty($model->matrix_id)) {
            $resubmitTrail->matrix_id = $model->matrix_id;
        }
        $resubmitTrail->save();

        return response()->json([
            'success' => true,
            'message' => 'Document resubmitted for approval.',
            'data' => [
                'document_type' => $request->type,
                'document_id' => $modelId,
                'approval_level' => $model->approval_level,
            ],
        ]);
    }

    /**
     * Check if the current user can resubmit (division head or creator when at level 0).
     */
    private function canUserResubmit(Model $model, int $staffId): bool
    {
        $division = $this->getDivisionForModel($model);
        if ($division && (int) $division->division_head === $staffId) {
            return true;
        }
        if ($model->approval_level == 0 && (int) $model->staff_id === $staffId) {
            return true;
        }
        return false;
    }

    /**
     * Get the division for any document model (for division head check).
     */
    private function getDivisionForModel(Model $model): ?object
    {
        if (method_exists($model, 'division') && $model->division) {
            return $model->division;
        }
        if (method_exists($model, 'matrix') && $model->matrix && $model->matrix->division) {
            return $model->matrix->division;
        }
        return null;
    }
}
