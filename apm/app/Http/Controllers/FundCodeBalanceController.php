<?php

namespace App\Http\Controllers;

use App\Models\FundCode;
use App\Services\FundCodeWorkingBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundCodeBalanceController extends Controller
{
    public function __construct(
        private readonly FundCodeWorkingBalanceService $balances
    ) {}

    /**
     * Live working balances for selected fund codes (Redis-cached).
     */
    public function workingBalances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:fund_codes,id',
            'exclude_activity_id' => 'nullable|integer',
            'exclude_non_travel_memo_id' => 'nullable|integer',
            'exclude_special_memo_id' => 'nullable|integer',
            'exclude_change_request_id' => 'nullable|integer',
        ]);

        $exclude = array_filter([
            'activity_id' => $validated['exclude_activity_id'] ?? null,
            'non_travel_memo_id' => $validated['exclude_non_travel_memo_id'] ?? null,
            'special_memo_id' => $validated['exclude_special_memo_id'] ?? null,
            'change_request_id' => $validated['exclude_change_request_id'] ?? null,
        ], fn ($v) => $v !== null && (int) $v > 0);

        $snapshots = $this->balances->snapshotsFor($validated['ids'], $exclude);
        $fundCodeBalances = FundCode::query()
            ->whereIn('id', $validated['ids'])
            ->pluck('budget_balance', 'id');

        $payload = [];
        foreach ($snapshots as $id => $snap) {
            $payload[$id] = array_merge($snap, [
                'fund_code_id' => $id,
                'working_balance' => $snap['working_balance'],
                'budget_balance' => (float) ($fundCodeBalances[$id] ?? 0),
            ]);
        }

        return response()->json([
            'balances' => $payload,
            'cached_at' => now()->toIso8601String(),
        ]);
    }
}
