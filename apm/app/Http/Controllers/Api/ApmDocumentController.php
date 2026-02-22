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

class ApmDocumentController extends Controller
{
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
        return response()->json(['success' => true, 'data' => $data]);
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
