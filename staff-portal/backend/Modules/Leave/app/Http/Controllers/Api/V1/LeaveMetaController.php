<?php

namespace Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReferenceCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveRequestService;
use Modules\Leave\Support\LeaveAccess;

class LeaveMetaController extends Controller
{
    public function types(): JsonResponse
    {
        $types = PortalReferenceCache::remember(
            PortalReferenceCache::leaveTypesKey(),
            fn () => PortalReferenceCache::buildLeaveTypes()
        );

        return response()->json([
            'data' => $types,
        ]);
    }

    public function workingDays(Request $request, LeaveRequestService $requests): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json([
            'data' => [
                'requested_days' => $requests->workingDaysBetween(
                    $validated['start_date'],
                    $validated['end_date']
                ),
            ],
        ]);
    }

    public function balanceForType(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        $staffId = LeaveAccess::staffId();
        if (! $staffId) {
            return response()->json(['message' => 'Staff profile not linked to your account.'], 403);
        }

        $validated = $request->validate([
            'leave_id' => 'required|integer|min:1',
        ]);

        return response()->json([
            'data' => $balances->snapshot($staffId, (int) $validated['leave_id']),
        ]);
    }

    /**
     * Active colleagues for Supporting officer / OIC selection (CI3 lists/supervisor parity).
     */
    public function supportingOfficers(): JsonResponse
    {
        if (! LeaveAccess::canMakeRequest()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $excludeStaffId = LeaveAccess::staffId() ?? 0;

        $activeStaffSub = DB::table('staff_contracts')
            ->select('staff_id')
            ->whereIn('status_id', [1, 2, 7])
            ->groupBy('staff_id');

        $rows = DB::table('staff as s')
            ->joinSub($activeStaffSub, 'active', 'active.staff_id', '=', 's.staff_id')
            ->when($excludeStaffId > 0, fn ($q) => $q->where('s.staff_id', '!=', $excludeStaffId))
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->select([
                's.staff_id',
                's.title',
                's.fname',
                's.lname',
                's.oname',
                's.work_email',
                DB::raw('s.SAPNO as sap_number'),
            ])
            ->get()
            ->map(static function (object $row): array {
                $name = trim(implode(' ', array_filter([
                    (string) ($row->title ?? ''),
                    (string) ($row->fname ?? ''),
                    (string) ($row->oname ?? ''),
                    (string) ($row->lname ?? ''),
                ])));

                return [
                    'staff_id' => (int) $row->staff_id,
                    'name' => $name !== '' ? $name : ('Staff #'.$row->staff_id),
                    'work_email' => $row->work_email,
                    'sap_number' => $row->sap_number,
                    'label' => $name !== ''
                        ? $name.($row->work_email ? ' — '.$row->work_email : '')
                        : ('Staff #'.$row->staff_id),
                ];
            })
            ->all();

        return response()->json(['data' => $rows]);
    }
}
