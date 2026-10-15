<?php

namespace Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Models\PortalUser;
use Modules\Leave\Http\Resources\Api\V1\LeaveTypeResource;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Support\LeaveAccess;

class LeaveBalanceController extends Controller
{
    public function __invoke(LeaveBalanceService $balances): JsonResponse
    {
        $staffId = LeaveAccess::staffId();
        if (! $staffId) {
            return response()->json(['message' => 'Staff profile not linked to your account.'], 403);
        }

        $user = request()->user();
        $userId = $user instanceof PortalUser ? (int) $user->getAuthIdentifier() : 0;
        $year = (int) now()->year;
        $cacheKey = PortalReadCache::key('leave', 'balances', $userId, [
            'staff_id' => $staffId,
            'year' => $year,
        ]);

        $payload = PortalReadCache::remember($cacheKey, function () use ($balances, $staffId, $year): array {
            $rows = $balances->allTypesForStaff($staffId, $year);

            return [
                'data' => array_map(static function (array $row): array {
                    /** @var \Modules\Leave\Models\LeaveType $type */
                    $type = $row['type'];

                    return [
                        'type' => (new LeaveTypeResource($type))->resolve(),
                        'balance' => $row['balance'],
                    ];
                }, $rows),
            ];
        });

        return response()->json($payload);
    }
}
