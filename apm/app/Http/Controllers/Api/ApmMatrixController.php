<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matrix;
use App\Models\ApprovalTrail;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmMatrixController extends Controller
{
    /**
     * Matrix detail with activities and metadata for current approver (passed vs pending).
     */
    public function show(Request $request, int $matrixId): JsonResponse
    {
        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $matrix = Matrix::with([
            'division', 'staff', 'focalPerson', 'forwardWorkflow',
            'activities' => function ($q) {
                $q->where('is_single_memo', 0)
                    ->with(['requestType', 'fundType', 'responsiblePerson', 'activity_budget.fundcode.funder']);
            },
        ])->find($matrixId);

        if (!$matrix) {
            return response()->json(['success' => false, 'message' => 'Matrix not found.'], 404);
        }

        $matrixArray = $matrix->toArray();
        $matrixArray['document_type'] = 'matrix';
        $matrixArray['activities'] = $matrix->activities->map(function ($activity) {
            return array_merge($activity->toArray(), [
                'has_passed_at_current_level' => $activity->has_passed_at_current_level ?? false,
                'my_last_action' => $activity->my_last_action ?? null,
                'my_current_level_action' => $activity->my_current_level_action ?? null,
            ]);
        })->values()->toArray();

        return response()->json(['success' => true, 'data' => $matrixArray]);
    }

    /**
     * Matrix approve or return (same as web matrices/24 update_status).
     */
    public function updateStatus(Request $request, int $matrixId): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approved,returned',
            'comment' => 'nullable|string|max:1000',
        ]);

        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $matrix = Matrix::find($matrixId);
        if (!$matrix) {
            return response()->json(['success' => false, 'message' => 'Matrix not found.'], 404);
        }

        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $approvalService = app(ApprovalService::class);

        if (!$approvalService->canTakeAction($matrix, $staffId)) {
            return response()->json(['success' => false, 'message' => 'You are not the current approver for this level.'], 403);
        }

        $action = $request->action;
        $recentDuplicate = ApprovalTrail::where('model_id', $matrix->id)
            ->where('model_type', Matrix::class)
            ->where('staff_id', $staffId)
            ->where('approval_order', $matrix->approval_level)
            ->where('action', $action)
            ->where('is_archived', 0)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($recentDuplicate) {
            return response()->json([
                'success' => true,
                'message' => 'Action was already recorded.',
                'data' => ['action' => $action, 'matrix_id' => $matrixId],
            ]);
        }

        $approvalService->processApproval(
            $matrix,
            $action,
            $request->input('comment'),
            $staffId,
            null
        );

        $approvalService->updateApprovalOrderMap($matrix);
        send_generic_email_notification($matrix, $action === 'approved' ? 'approved' : 'returned');

        return response()->json([
            'success' => true,
            'message' => 'Matrix updated successfully.',
            'data' => ['action' => $action, 'matrix_id' => $matrixId],
        ]);
    }
}
