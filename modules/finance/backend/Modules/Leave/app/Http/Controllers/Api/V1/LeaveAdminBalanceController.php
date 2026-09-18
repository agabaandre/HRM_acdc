<?php

namespace Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\PortalUser;
use Modules\Leave\Http\Resources\Api\V1\LeaveTypeResource;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Support\LeaveAccess;
use Modules\Staff\Models\Staff;

class LeaveAdminBalanceController extends Controller
{
    public function directory(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        LeaveAccess::authorizeBalancesAdmin();

        $result = $balances->adminDirectory(
            search: trim((string) $request->query('q', '')),
            year: $request->filled('year') ? (int) $request->query('year') : null,
            page: max(1, (int) $request->query('page', 1)),
            perPage: max(1, min(100, (int) $request->query('per_page', 25))),
        );

        return response()->json($result);
    }

    public function show(Request $request, int $staffId, LeaveBalanceService $balances): JsonResponse
    {
        LeaveAccess::authorizeBalancesAdmin();

        $staff = Staff::query()->findOrFail($staffId);
        $year = $request->filled('year') ? (int) $request->query('year') : (int) now()->year;

        $rows = $balances->allTypesForStaff($staffId, $year);

        return response()->json([
            'data' => [
                'staff' => [
                    'staff_id' => (int) $staff->staff_id,
                    'name' => trim(implode(' ', array_filter([
                        (string) $staff->fname,
                        (string) ($staff->oname ?? ''),
                        (string) $staff->lname,
                    ]))),
                    'work_email' => $staff->work_email,
                ],
                'year' => $year,
                'balances' => array_map(static function (array $row): array {
                    /** @var \Modules\Leave\Models\LeaveType $type */
                    $type = $row['type'];

                    return [
                        'type' => (new LeaveTypeResource($type))->resolve(),
                        'balance' => $row['balance'],
                        'opening_days' => $row['balance']['opening'],
                        'carried_forward_days' => $row['balance']['carried_forward'],
                        'compensatory_days' => $row['balance']['compensatory'],
                    ];
                }, $rows),
            ],
        ]);
    }

    public function update(Request $request, int $staffId, LeaveBalanceService $balances): JsonResponse
    {
        LeaveAccess::authorizeBalancesAdmin();
        Staff::query()->findOrFail($staffId);

        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'rows' => 'required|array|min:1',
            'rows.*.leave_id' => 'required|integer|min:1',
            'rows.*.opening_days' => 'nullable|numeric|min:0',
            'rows.*.carried_forward_days' => 'nullable|numeric|min:0',
            'rows.*.compensatory_days' => 'nullable|numeric|min:0',
            'rows.*.notes' => 'nullable|string|max:2000',
        ]);

        $mapped = [];
        foreach ($validated['rows'] as $row) {
            $mapped[(int) $row['leave_id']] = [
                'opening_days' => (float) ($row['opening_days'] ?? 0),
                'carried_forward_days' => (float) ($row['carried_forward_days'] ?? 0),
                'compensatory_days' => (float) ($row['compensatory_days'] ?? 0),
                'notes' => $row['notes'] ?? null,
            ];
        }

        $user = auth()->user();
        $userId = $user instanceof PortalUser ? (int) $user->getAuthIdentifier() : null;

        $balances->saveOpeningBalances($staffId, (int) $validated['year'], $mapped, $userId);
        PortalReadCache::bust('leave');

        return response()->json([
            'message' => 'Leave balances saved.',
            'data' => [
                'staff_id' => $staffId,
                'year' => (int) $validated['year'],
            ],
        ]);
    }

    public function bulkFill(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        LeaveAccess::authorizeBalancesAdmin();

        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
            'overwrite' => 'nullable|boolean',
            'leave_ids' => 'nullable|array',
            'leave_ids.*' => 'integer|min:1',
        ]);

        $user = auth()->user();
        $userId = $user instanceof PortalUser ? (int) $user->getAuthIdentifier() : null;

        $result = $balances->bulkFillOpeningBalances(
            year: isset($validated['year']) ? (int) $validated['year'] : null,
            overwrite: (bool) ($validated['overwrite'] ?? false),
            userId: $userId,
            leaveTypeIds: $validated['leave_ids'] ?? null,
        );
        PortalReadCache::bust('leave');

        return response()->json([
            'message' => sprintf(
                'Filled leave balances for %d staff (%d created, %d updated, %d skipped).',
                $result['staff_processed'],
                $result['rows_created'],
                $result['rows_updated'],
                $result['rows_skipped'],
            ),
            'data' => $result,
        ]);
    }
}
