<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PrintHelper;
use App\Http\Controllers\Controller;
use App\Models\Matrix;
use App\Models\Activity;
use App\Models\Staff;
use App\Models\ApprovalTrail;
use App\Services\ApprovalService;
use App\Services\PendingApprovalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApmMatrixController extends Controller
{
    /**
     * Create a matrix and optionally its activities.
     * Matrix is created in draft; activities are created as draft and linked to the matrix.
     */
    public function store(Request $request): JsonResponse
    {
        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $staffId = (int) ($sessionData['staff_id'] ?? 0);
        $divisionId = (int) ($sessionData['division_id'] ?? 0);

        $validated = $request->validate([
            'division_id' => 'required|exists:divisions,id',
            'year' => 'required|integer|min:2020|max:2030',
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'key_result_area' => 'required|array|min:1',
            'key_result_area.*.description' => 'required|string',
            'focal_person_id' => 'nullable|exists:staff,staff_id',
            'activities' => 'nullable|array',
            'activities.*.activity_title' => 'required_with:activities|string|max:500',
            'activities.*.responsible_person_id' => 'required_with:activities|integer|exists:staff,staff_id',
            'activities.*.request_type_id' => 'nullable|integer|exists:request_types,id',
            'activities.*.fund_type_id' => 'nullable|integer|exists:fund_types,id',
            'activities.*.date_from' => 'nullable|date',
            'activities.*.date_to' => 'nullable|date|after_or_equal:activities.*.date_from',
            'activities.*.total_participants' => 'nullable|integer|min:0',
            'activities.*.background' => 'nullable|string',
            'activities.*.activity_request_remarks' => 'nullable|string',
            'activities.*.internal_participants' => 'nullable|array',
            'activities.*.location_id' => 'nullable|array',
            'activities.*.location_id.*' => 'integer|exists:locations,id',
            'activities.*.budget_id' => 'nullable|array',
            'activities.*.budget_breakdown' => 'nullable|array',
        ]);

        if (Matrix::existsForDivisionYearQuarter($validated['division_id'], $validated['year'], $validated['quarter'])) {
            return response()->json([
                'success' => false,
                'message' => 'A matrix already exists for this division, year and quarter. Only one matrix per division per quarter is allowed.',
            ], 422);
        }

        $focalPersonId = (int) ($validated['focal_person_id'] ?? $staffId);
        $matrixDivisionId = (int) $validated['division_id'];
        $focalStaff = Staff::find($focalPersonId);
        if (!$focalStaff) {
            return response()->json(['success' => false, 'message' => 'focal_person_id must be a valid staff ID.'], 422);
        }
        if ((int) $focalStaff->division_id !== $matrixDivisionId) {
            return response()->json(['success' => false, 'message' => 'focal_person_id must belong to the same division as division_id.'], 422);
        }

        try {
            DB::beginTransaction();

            $matrix = Matrix::create([
                'division_id' => $validated['division_id'],
                'focal_person_id' => $focalPersonId,
                'year' => $validated['year'],
                'quarter' => $validated['quarter'],
                'key_result_area' => json_encode($validated['key_result_area']),
                'staff_id' => $staffId,
                'forward_workflow_id' => null,
                'overall_status' => 'draft',
            ]);

            $approvalService = app(ApprovalService::class);
            $approvalService->updateApprovalOrderMap($matrix);

            $activitiesPayload = $validated['activities'] ?? [];
            $createdActivities = [];

            foreach ($activitiesPayload as $idx => $act) {
                $dateFrom = isset($act['date_from']) ? $act['date_from'] : now()->toDateString();
                $dateTo = isset($act['date_to']) ? $act['date_to'] : $dateFrom;
                $internalParticipants = $act['internal_participants'] ?? [];
                if (!is_array($internalParticipants)) {
                    $internalParticipants = [];
                }
                $locationId = isset($act['location_id']) && is_array($act['location_id']) ? $act['location_id'] : [];
                $budgetId = isset($act['budget_id']) && is_array($act['budget_id']) ? $act['budget_id'] : [];
                $budgetBreakdown = isset($act['budget_breakdown']) && is_array($act['budget_breakdown']) ? $act['budget_breakdown'] : [];

                $activity = $matrix->activities()->create([
                    'staff_id' => $staffId,
                    'responsible_person_id' => (int) $act['responsible_person_id'],
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'total_participants' => (int) ($act['total_participants'] ?? 1),
                    'total_external_participants' => 0,
                    'key_result_area' => $validated['key_result_area'][0]['description'] ?? '',
                    'request_type_id' => (int) ($act['request_type_id'] ?? 1),
                    'activity_title' => $act['activity_title'],
                    'background' => PrintHelper::trimRichTextInput($act['background'] ?? ''),
                    'activity_request_remarks' => PrintHelper::trimRichTextInput($act['activity_request_remarks'] ?? ''),
                    'forward_workflow_id' => null,
                    'reverse_workflow_id' => 1,
                    'status' => Activity::STATUS_DRAFT,
                    'fund_type_id' => (int) ($act['fund_type_id'] ?? 1),
                    'location_id' => $locationId,
                    'internal_participants' => $internalParticipants,
                    'budget_id' => $budgetId,
                    'budget_breakdown' => $budgetBreakdown,
                    'attachment' => [],
                    'is_single_memo' => 0,
                    'approval_level' => 0,
                    'division_id' => $matrix->division_id,
                    'overall_status' => Activity::STATUS_DRAFT,
                ]);

                $createdActivities[] = [
                    'id' => $activity->id,
                    'activity_title' => $activity->activity_title,
                    'document_number' => $activity->document_number ?? null,
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Matrix created successfully.',
                'data' => [
                    'matrix_id' => $matrix->id,
                    'division_id' => $matrix->division_id,
                    'year' => $matrix->year,
                    'quarter' => $matrix->quarter,
                    'overall_status' => $matrix->overall_status,
                    'activities_count' => count($createdActivities),
                    'activities' => $createdActivities,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create matrix.',
            ], 500);
        }
    }

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
            'matrixApprovalTrails' => function ($q) {
                $q->with(['staff', 'oicStaff', 'workflowDefinition'])
                    ->where('is_archived', 0)
                    ->orderBy('created_at', 'asc');
            },
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
        $trails = $matrix->matrixApprovalTrails ?? collect();
        $matrixArray['approval_trail'] = app(PendingApprovalsService::class)->formatApprovalTrailsForApi($trails);

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
