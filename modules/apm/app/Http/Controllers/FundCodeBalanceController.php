<?php

namespace App\Http\Controllers;

use App\Models\FundCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Live fund-code balances for memo forms.
 * Uses fund_codes.budget_balance as synced from Finance/SAP (commitments already in SAP).
 */
class FundCodeBalanceController extends Controller
{
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

        $cols = ['id', 'budget_balance', 'approved_budget'];
        if (Schema::hasColumn('fund_codes', 'sap_synced_at')) {
            $cols[] = 'sap_synced_at';
        }

        $rows = FundCode::query()
            ->whereIn('id', $validated['ids'])
            ->get($cols)
            ->keyBy('id');

        $payload = [];
        foreach ($validated['ids'] as $rawId) {
            $id = (int) $rawId;
            $row = $rows->get($id);
            $financeBalance = $row
                ? (float) str_replace(',', '', (string) ($row->budget_balance ?? 0))
                : 0.0;
            $approved = $row
                ? (float) str_replace(',', '', (string) ($row->approved_budget ?? 0))
                : 0.0;
            $sapSyncedAt = $row && $row->sap_synced_at
                ? $row->sap_synced_at->format('d M Y H:i')
                : null;

            $payload[$id] = [
                'fund_code_id' => $id,
                'approved_budget' => $approved,
                'committed_total' => 0.0,
                'working_balance' => $financeBalance,
                'budget_balance' => $financeBalance,
                'finance_budget_balance' => $financeBalance,
                'sap_synced_at' => $sapSyncedAt,
            ];
        }

        return response()->json([
            'balances' => $payload,
            'cached_at' => now()->toIso8601String(),
        ]);
    }
}
