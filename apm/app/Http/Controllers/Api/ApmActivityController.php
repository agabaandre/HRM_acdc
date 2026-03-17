<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matrix;
use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\ApprovalTrail;
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
     * Activity action (bulk): pass, return, or convert for multiple activities in the matrix.
     * POST /matrices/{matrixId}/activities with body { "activity_ids": [1,2,3], "action": "passed", "comment": "...", "available_budget": 0 }.
     */
    public function updateStatusBulk(Request $request, int $matrixId): JsonResponse
    {
        $request->validate([
            'activity_ids' => 'required|array',
            'activity_ids.*' => 'integer|min:1',
            'action' => 'required|in:passed,returned,convert_to_single_memo',
            'comment' => 'nullable|string|max:1000',
            'available_budget' => 'nullable|numeric|min:0',
        ]);

        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $activityIds = array_values(array_unique(array_filter($request->input('activity_ids', []), fn ($id) => is_numeric($id) && (int) $id > 0)));
        if (empty($activityIds)) {
            return response()->json(['success' => false, 'message' => 'At least one valid activity_id is required.'], 422);
        }

        $matrix = Matrix::find($matrixId);
        if (!$matrix) {
            return response()->json(['success' => false, 'message' => 'Matrix not found.'], 404);
        }

        $processed = [];
        $errors = [];
        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $action = $request->action;
        $comment = $request->comment ?? ($action === 'passed' ? 'Passed' : '');
        $availableBudget = $request->filled('available_budget') ? (float) $request->available_budget : null;

        foreach ($activityIds as $activityId) {
            $activity = Activity::where('matrix_id', $matrixId)->where('is_single_memo', 0)->find($activityId);
            if (!$activity) {
                $errors[] = ['activity_id' => $activityId, 'message' => 'Activity not found or not a matrix activity.'];
                continue;
            }
            try {
                $this->processActivityAction($activity, $action, $comment, $availableBudget, $staffId);
                $processed[] = $activityId;
            } catch (\Throwable $e) {
                $errors[] = ['activity_id' => $activityId, 'message' => $e->getMessage()];
                Log::warning('Activity action failed', ['activity_id' => $activityId, 'error' => $e->getMessage()]);
            }
        }

        $matrix->refresh();
        if ($action !== 'passed' && $action !== 'convert_to_single_memo' && !empty($processed)) {
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Matrix') ?: 1;
            $matrix->forward_workflow_id = $assignedWorkflowId;
            $matrix->overall_status = 'pending';
            $matrix->update();
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($processed) === count($activityIds)
                ? 'All activities updated successfully.'
                : (count($processed) > 0 ? 'Some activities updated; see errors.' : 'No activities were updated.'),
            'data' => [
                'action' => $action,
                'matrix_id' => $matrixId,
                'processed_activity_ids' => $processed,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Activity action (single): pass, return, or convert for one activity.
     * POST /matrices/{matrixId}/activities/{activityId} with body { "action": "passed", "comment": "...", "available_budget": 0 }.
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
        $this->processActivityAction(
            $activity,
            $request->action,
            $request->comment ?? ($request->action === 'passed' ? 'Passed' : ''),
            $request->filled('available_budget') ? (float) $request->available_budget : null,
            $staffId
        );

        $matrix = $activity->matrix;
        if ($request->action !== 'passed' && $request->action !== 'convert_to_single_memo') {
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

    /**
     * Apply one action (passed, returned, convert_to_single_memo) to a single matrix activity.
     */
    private function processActivityAction(Activity $activity, string $action, string $comment, ?float $availableBudget, int $staffId): void
    {
        $trail = new ActivityApprovalTrail();
        $trail->remarks = $comment;
        $trail->action = $action;
        $trail->approval_order = $activity->matrix->approval_level;
        $trail->activity_id = $activity->id;
        $trail->matrix_id = $activity->matrix_id;
        $trail->staff_id = $staffId;
        $trail->is_archived = 0;
        $trail->save();

        if ($action === 'passed' && $availableBudget !== null) {
            $activity->available_budget = $availableBudget;
            $activity->save();
        }

        if ($action === 'convert_to_single_memo') {
            $this->convertActivityToSingleMemo($activity, $comment);
        }
    }

    private function convertActivityToSingleMemo(Activity $activity, ?string $comment): void
    {
        try {
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Activity') ?: 1;

            // Copy activity_approval_trails to approval_trails (single memo uses approval_trails going forward)
            $activityTrails = ActivityApprovalTrail::where('activity_id', $activity->id)
                ->orderBy('id')
                ->get();
            foreach ($activityTrails as $t) {
                ApprovalTrail::create([
                    'model_id' => $activity->id,
                    'model_type' => Activity::class,
                    'matrix_id' => $t->matrix_id,
                    'staff_id' => $t->staff_id,
                    'oic_staff_id' => $t->oic_staff_id,
                    'action' => $t->action,
                    'remarks' => $t->remarks,
                    'approval_order' => $t->approval_order,
                    'forward_workflow_id' => $t->forward_workflow_id,
                    'is_archived' => $t->is_archived ?? 0,
                ]);
            }

            // next_approval_level from mother matrix at time of return
            $matrix = $activity->matrix ?? \App\Models\Matrix::find($activity->matrix_id);
            $nextApprovalLevel = $matrix ? ($matrix->next_approval_level ?? $matrix->approval_level + 1) : 2;

            // Update activity to single memo; approval_level = 1 (HOD); next from matrix; retain existing workflow_id
            $activity->update([
                'is_single_memo' => true,
                'document_number' => null,
                'overall_status' => 'returned', // Returned as single memo
                'approval_level' => 1,
                'next_approval_level' => $nextApprovalLevel,
                // forward_workflow_id and reverse_workflow_id are not changed – activity keeps previous values
                'is_draft' => false,
            ]);
            \App\Jobs\AssignDocumentNumberJob::dispatch($activity);
        } catch (\Exception $e) {
            Log::error('Error converting activity to single memo', ['activity_id' => $activity->id, 'exception' => $e->getMessage()]);
            throw $e;
        }
    }
}
