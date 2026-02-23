<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matrix;
use App\Models\Staff;
use App\Models\ApprovalTrail;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmMatrixController extends Controller
{
    /**
     * Matrix detail with activities and metadata for current approver (passed vs pending).
     * internal_participants per activity and division_schedule are returned as lists with participant names.
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

        // Collect all staff IDs for participant lookups (division_schedule + activities' internal_participants)
        $staffIds = [];
        $divisionSchedule = $matrix->division_schedule;
        foreach ($divisionSchedule as $row) {
            if (!empty($row->participant_id)) {
                $staffIds[] = (int) $row->participant_id;
            }
        }
        foreach ($matrix->activities as $activity) {
            $raw = is_string($activity->internal_participants) ? json_decode($activity->internal_participants, true) : ($activity->internal_participants ?? []);
            if (is_array($raw)) {
                $staffIds = array_merge($staffIds, array_map('intval', array_keys($raw)));
            }
        }
        $staffIds = array_values(array_unique(array_filter($staffIds)));
        $staffById = $staffIds ? Staff::whereIn('staff_id', $staffIds)->get()->keyBy('staff_id') : collect();

        // Format division_schedule as list with participant name (like activity show)
        $matrixArray = $matrix->toArray();
        $matrixArray['division_schedule'] = $divisionSchedule->map(function ($row) use ($staffById) {
            $staffId = (int) $row->participant_id;
            $staff = $staffById->get($staffId);
            $participantName = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? '')) : null;
            return [
                'staff_id' => $staffId,
                'name' => $participantName,
                'participant_name' => $participantName,
                'participant_days' => isset($row->participant_days) ? (int) $row->participant_days : null,
                'is_home_division' => (bool) ($row->is_home_division ?? false),
                'division_id' => isset($row->division_id) ? (int) $row->division_id : null,
                'quarter' => $row->quarter ?? null,
                'year' => isset($row->year) ? (int) $row->year : null,
            ];
        })->values()->toArray();

        $matrixArray['document_type'] = 'matrix';
        $matrixArray['activities'] = $matrix->activities->map(function ($activity) use ($staffById) {
            $raw = is_string($activity->internal_participants) ? json_decode($activity->internal_participants, true) : ($activity->internal_participants ?? []);
            $internalParticipantsList = [];
            if (is_array($raw)) {
                foreach ($raw as $staffId => $participantData) {
                    $staffId = (int) $staffId;
                    $staff = $staffById->get($staffId);
                    $participantName = $staff ? trim(($staff->title ?? '') . ' ' . ($staff->fname ?? '') . ' ' . ($staff->lname ?? '') . ' ' . ($staff->oname ?? '')) : null;
                    $internalParticipantsList[] = [
                        'staff_id' => $staffId,
                        'name' => $participantName,
                        'participant_name' => $participantName,
                        'participant_start' => $participantData['participant_start'] ?? null,
                        'participant_end' => $participantData['participant_end'] ?? null,
                        'participant_days' => isset($participantData['participant_days']) ? (string) $participantData['participant_days'] : null,
                        'international_travel' => (int) ($participantData['international_travel'] ?? 0),
                    ];
                }
            }
            $activityArray = $activity->toArray();
            $activityArray['internal_participants'] = $internalParticipantsList;
            return array_merge($activityArray, [
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
