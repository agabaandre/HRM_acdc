<?php

namespace Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\PortalUser;
use Modules\Leave\Models\StaffLeavePlan;
use Modules\Leave\Services\LeavePlanService;
use Modules\Leave\Support\LeaveAccess;
use RuntimeException;

class LeavePlanController extends Controller
{
    public function show(Request $request, LeavePlanService $plans): JsonResponse
    {
        if (! LeaveAccess::canMakeRequest()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $staffId = LeaveAccess::staffId();
        if (! $staffId) {
            return response()->json(['message' => 'Staff profile not linked to your account.'], 403);
        }

        if (! $plans->tablesReady()) {
            return response()->json([
                'message' => 'Leave plan is not available yet. Run database migrations.',
            ], 503);
        }

        $year = $request->filled('year')
            ? (int) $request->query('year')
            : (int) now()->year;

        if ($year < 2000 || $year > 2100) {
            return response()->json(['message' => 'Invalid plan year.'], 422);
        }

        $user = $request->user();
        $userId = $user instanceof PortalUser ? (int) $user->getAuthIdentifier() : 0;
        $cacheKey = PortalReadCache::key('leave', 'plan', $userId, [
            'staff_id' => $staffId,
            'year' => $year,
        ]);

        $yearOptions = $this->yearOptions();

        try {
            $payload = PortalReadCache::remember($cacheKey, function () use ($plans, $staffId, $year, $yearOptions): array {
                $plan = $plans->getOrCreateForStaff($staffId, $year);

                return [
                    'data' => $plans->present($plan, $staffId),
                    'meta' => [
                        'year' => $year,
                        'year_options' => $yearOptions,
                    ],
                ];
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($payload);
    }

    public function update(Request $request, int $id, LeavePlanService $plans): JsonResponse
    {
        if (! LeaveAccess::canMakeRequest()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $staffId = LeaveAccess::staffId();
        if (! $staffId) {
            return response()->json(['message' => 'Staff profile not linked to your account.'], 403);
        }

        $plan = StaffLeavePlan::query()->where('id', $id)->where('staff_id', $staffId)->first();
        if (! $plan) {
            return response()->json(['message' => 'Leave plan not found.'], 404);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'entries' => ['present', 'array'],
            // leave_id is ignored — plans are annual leave only (forced in service).
            'entries.*.leave_id' => ['nullable', 'integer', 'min:1'],
            'entries.*.start_date' => ['required', 'date'],
            'entries.*.end_date' => ['required', 'date'],
            'entries.*.planned_days' => ['nullable', 'numeric', 'min:0.5'],
            'entries.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $plan = $plans->saveDraft(
                $plan,
                $validated['entries'] ?? [],
                $validated['notes'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        PortalReadCache::bust('leave');

        return response()->json([
            'message' => 'Leave plan draft saved.',
            'data' => $plans->present($plan, $staffId),
        ]);
    }

    public function submit(int $id, LeavePlanService $plans): JsonResponse
    {
        if (! LeaveAccess::canMakeRequest()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $staffId = LeaveAccess::staffId();
        if (! $staffId) {
            return response()->json(['message' => 'Staff profile not linked to your account.'], 403);
        }

        $plan = StaffLeavePlan::query()->where('id', $id)->where('staff_id', $staffId)->first();
        if (! $plan) {
            return response()->json(['message' => 'Leave plan not found.'], 404);
        }

        $user = auth()->user();
        $userId = $user instanceof PortalUser ? (int) $user->user_id : 0;

        try {
            $plan = $plans->submit($plan, $userId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        PortalReadCache::bust('leave');

        return response()->json([
            'message' => 'Leave plan submitted. It can no longer be edited.',
            'data' => $plans->present($plan, $staffId),
        ]);
    }

    /**
     * @return list<int>
     */
    protected function yearOptions(): array
    {
        $current = (int) now()->year;

        return [$current - 1, $current, $current + 1];
    }
}
