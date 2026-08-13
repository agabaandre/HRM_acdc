<?php

namespace Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\PortalUser;
use Modules\Leave\Http\Resources\Api\V1\LeaveRequestResource;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Services\LeaveRequestService;
use Modules\Leave\Support\LeaveAccess;

class LeaveApprovalController extends Controller
{
    public function index(): JsonResponse
    {
        $staffId = LeaveAccess::staffId();
        if (! $staffId) {
            return response()->json(['data' => [], 'meta' => ['is_hr' => LeaveAccess::isHr()]]);
        }

        $isHr = LeaveAccess::isHr();
        $user = auth()->user();
        $userId = $user instanceof PortalUser ? (int) $user->user_id : (int) $staffId;

        $cacheKey = PortalReadCache::key('leave', 'approvals', $userId, [
            'staff_id' => $staffId,
            'hr' => $isHr ? 1 : 0,
        ]);

        $payload = PortalReadCache::remember($cacheKey, function () use ($staffId, $isHr): array {
            return [
                'data' => $this->pendingApprovals($staffId, $isHr),
                'meta' => [
                    'is_hr' => $isHr,
                ],
            ];
        });

        return response()->json($payload);
    }

    public function decide(Request $request, int $id, LeaveRequestService $requests): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:supporting_staff,hr,supervisor,hod',
            'action' => 'required|string|in:approve,reject',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validated['role'] === 'hr' && ! LeaveAccess::isHr() && ! LeaveAccess::canManageSettings()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $message = $validated['message']
            ?? ($validated['action'] === 'approve' ? 'Approved' : 'Rejected');

        $ok = $requests->approve($id, $validated['role'], $message);
        if (! $ok) {
            return response()->json(['message' => 'Could not update leave request.'], 422);
        }

        PortalReadCache::bust('leave');

        $leave = StaffLeave::query()
            ->with([
                'leaveType:leave_id,leave_name',
                'staff:staff_id,fname,lname,SAPNO',
            ])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Leave request '.$message.'.',
            'data' => new LeaveRequestResource($leave),
        ]);
    }

    /**
     * Lean join query — avoid select * / full model hydration on the approvals list.
     *
     * @return list<array<string, mixed>>
     */
    protected function pendingApprovals(int $staffId, bool $isHr): array
    {
        $query = DB::table('staff_leave as sl')
            ->leftJoin('staff as s', 's.staff_id', '=', 'sl.staff_id')
            ->leftJoin('leave_types as lt', 'lt.leave_id', '=', 'sl.leave_id')
            ->where('sl.overall_status', 'Pending')
            ->orderByDesc('sl.created_at')
            ->limit(50)
            ->select([
                'sl.request_id',
                'sl.staff_id',
                'sl.leave_id',
                'sl.start_date',
                'sl.end_date',
                'sl.requested_days',
                'sl.overall_status',
                'sl.email_leave',
                'sl.mobile_leave',
                'sl.remarks',
                'sl.supporting_documentation',
                'sl.created_at',
                'lt.leave_name',
                DB::raw("TRIM(CONCAT(COALESCE(s.fname, ''), ' ', COALESCE(s.lname, ''))) as staff_name"),
                's.SAPNO as sap_number',
            ]);

        if (! $isHr) {
            $query->where(function ($q) use ($staffId): void {
                $q->where('sl.supervisor_id', $staffId)
                    ->orWhere('sl.supervisor2_id', $staffId)
                    ->orWhere('sl.division_head', $staffId)
                    // supporting_staff is stored as string in legacy rows.
                    ->orWhere('sl.supporting_staff', (string) $staffId)
                    ->orWhere('sl.supporting_staff', $staffId);
            });
        }

        return $query->get()->map(function (object $row): array {
            $sap = $row->sap_number !== null && $row->sap_number !== ''
                ? (string) $row->sap_number
                : null;
            $name = trim((string) ($row->staff_name ?? ''));

            return [
                'request_id' => (int) $row->request_id,
                'staff_id' => (int) $row->staff_id,
                'staff_name' => $name !== '' ? $name : null,
                'sap_number' => $sap,
                'leave_id' => (int) $row->leave_id,
                'leave_name' => $row->leave_name,
                'start_date' => $row->start_date ? substr((string) $row->start_date, 0, 10) : null,
                'end_date' => $row->end_date ? substr((string) $row->end_date, 0, 10) : null,
                'requested_days' => (int) $row->requested_days,
                'overall_status' => (string) $row->overall_status,
                'email_leave' => $row->email_leave,
                'mobile_leave' => $row->mobile_leave,
                'remarks' => $row->remarks,
                'supporting_documentation' => $row->supporting_documentation,
                'created_at' => $row->created_at,
            ];
        })->values()->all();
    }
}
