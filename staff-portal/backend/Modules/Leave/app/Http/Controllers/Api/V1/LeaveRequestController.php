<?php

namespace Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Leave\Http\Resources\Api\V1\LeaveRequestResource;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Services\LeaveRequestService;
use Modules\Leave\Support\LeaveAccess;

class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $staffId = LeaveAccess::staffId();
        $scope = (string) $request->query('scope', 'mine');

        if ($scope === 'all' && ! LeaveAccess::canViewAllStaffRequests()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = StaffLeave::query()
            ->with(['leaveType', 'staff'])
            ->when(
                \Illuminate\Support\Facades\Schema::hasTable('staff_leave_approval_steps'),
                fn ($q) => $q->with('approvalSteps.approver'),
            )
            ->when($scope !== 'all' && $staffId, fn ($q) => $q->where('staff_id', $staffId))
            ->when($request->filled('status'), fn ($q) => $q->where('overall_status', $request->query('status')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('start_date', '>=', $request->query('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('end_date', '<=', $request->query('end_date')))
            ->orderByDesc('start_date')
            ->limit(100);

        return response()->json([
            'data' => LeaveRequestResource::collection($query->get())->resolve(),
        ]);
    }

    public function store(Request $request, LeaveRequestService $requests): JsonResponse
    {
        if (! LeaveAccess::canMakeRequest()) {
            return response()->json(['message' => 'You do not have permission to make leave requests.'], 403);
        }

        $staffId = LeaveAccess::staffId();
        if (! $staffId) {
            return response()->json(['message' => 'Staff profile not linked to your account.'], 403);
        }

        $validated = $request->validate([
            'leave_id' => 'required|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'requested_days' => 'required|integer|min:1',
            'email_leave' => 'required|email',
            'mobile_leave' => 'required|string|max:200',
            'supporting_staff' => 'required|integer|min:1|exists:staff,staff_id',
            'division_head' => 'nullable|integer|min:1|exists:staff,staff_id',
            'remarks' => 'nullable|string|max:2000',
            'document' => 'nullable|file|max:2048|mimes:pdf,doc,docx,png,jpg,jpeg',
        ]);

        $type = \Modules\Leave\Models\LeaveType::query()->find($validated['leave_id']);
        if ($type?->requires_medical_certificate && ! $request->file('document')) {
            return response()->json([
                'message' => 'A medical certificate is required for this leave type.',
                'errors' => ['document' => ['A medical certificate is required for this leave type.']],
            ], 422);
        }

        $workflow = app(\Modules\Leave\Services\LeaveApprovalWorkflowService::class);
        if ($workflow->isEnabled() && empty($validated['division_head'])) {
            return response()->json([
                'message' => 'Select a Head of Division for this leave request.',
                'errors' => ['division_head' => ['Select a Head of Division for this leave request.']],
            ], 422);
        }

        try {
            $leave = $requests->submit([
                'staff_id' => $staffId,
                'leave_id' => $validated['leave_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'requested_days' => $validated['requested_days'],
                'email_leave' => $validated['email_leave'],
                'mobile_leave' => $validated['mobile_leave'],
                'supporting_staff' => (string) $validated['supporting_staff'],
                'division_head' => $validated['division_head'] ?? 0,
                'remarks' => $validated['remarks'] ?? null,
            ], $request->file('document'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $with = ['leaveType', 'staff'];
        if (\Illuminate\Support\Facades\Schema::hasTable('staff_leave_approval_steps')) {
            $with[] = 'approvalSteps.approver';
        }
        $leave->load($with);
        PortalReadCache::bust('leave');

        return response()->json([
            'message' => 'Leave request submitted for approval.',
            'data' => new LeaveRequestResource($leave),
        ], 201);
    }
}
