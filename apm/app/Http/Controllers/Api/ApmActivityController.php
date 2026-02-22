<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matrix;
use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\WorkflowModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApmActivityController extends Controller
{
    /**
     * Activity detail (matrix activity or single memo) - same data as matrices/24/activities/401.
     */
    public function show(Request $request, int $matrixId, int $activityId): JsonResponse
    {
        if (!$request->attributes->get('api_user_session')) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $activity = Activity::with([
            'staff', 'requestType', 'fundType', 'responsiblePerson', 'matrix.division',
            'activity_budget.fundcode.funder', 'activityApprovalTrails.staff', 'approvalTrails',
        ])->where('matrix_id', $matrixId)->find($activityId);

        if (!$activity) {
            return response()->json(['success' => false, 'message' => 'Activity not found.'], 404);
        }

        $data = $activity->toArray();
        $data['document_type'] = $activity->is_single_memo ? 'single_memo' : 'activity';
        $data['matrix_id'] = $activity->matrix_id;
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Activity action: passed, returned, convert_to_single_memo (matrix activity only).
     */
    public function updateStatus(Request $request, int $matrixId, int $activityId): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:passed,returned,convert_to_single_memo',
            'comment' => 'nullable|string|max:1000',
            'available_budget' => 'nullable|numeric|min:0',
        ]);

        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $activity = Activity::where('matrix_id', $matrixId)->where('is_single_memo', 0)->find($activityId);
        if (!$activity) {
            return response()->json(['success' => false, 'message' => 'Activity not found or not a matrix activity.'], 404);
        }

        $staffId = (int) ($sessionData['staff_id'] ?? 0);

        $trail = new ActivityApprovalTrail();
        $trail->remarks = $request->comment ?? 'Passed';
        $trail->action = $request->action;
        $trail->approval_order = $activity->matrix->approval_level;
        $trail->activity_id = $activity->id;
        $trail->matrix_id = $activity->matrix_id;
        $trail->staff_id = $staffId;
        $trail->is_archived = 0;
        $trail->save();

        if ($request->action === 'passed' && $request->filled('available_budget')) {
            $activity->available_budget = $request->available_budget;
            $activity->save();
        }

        $matrix = $activity->matrix;

        if ($request->action === 'convert_to_single_memo') {
            $this->convertActivityToSingleMemo($activity, $request->comment);
        } elseif ($request->action !== 'passed') {
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Matrix') ?: 1;
            $matrix->forward_workflow_id = $assignedWorkflowId;
            $matrix->overall_status = 'pending';
            $matrix->update();
        }

        return response()->json([
            'success' => true,
            'message' => 'Activity updated successfully.',
            'data' => ['action' => $request->action, 'matrix_id' => $matrixId, 'activity_id' => $activityId],
        ]);
    }

    private function convertActivityToSingleMemo(Activity $activity, ?string $comment): void
    {
        try {
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Activity') ?: 1;
            $activity->update([
                'is_single_memo' => true,
                'document_number' => null,
                'overall_status' => 'draft',
                'approval_level' => 1,
                'next_approval_level' => 2,
                'forward_workflow_id' => null,
                'reverse_workflow_id' => $assignedWorkflowId,
                'is_draft' => true,
            ]);
            \App\Jobs\AssignDocumentNumberJob::dispatch($activity);
        } catch (\Exception $e) {
            Log::error('Error converting activity to single memo', ['activity_id' => $activity->id, 'exception' => $e->getMessage()]);
            throw $e;
        }
    }
}
