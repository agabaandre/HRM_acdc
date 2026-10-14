<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesApmPageResponses;
use App\Services\FinanceBudgetExecutionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Budget execution dashboard — proxies Finance GET /api/v1/budget-execution with Redis page cache.
 */
class BudgetExecutionDashboardController extends Controller
{
    use CachesApmPageResponses;

    public function __construct(
        private readonly FinanceBudgetExecutionClient $finance = new FinanceBudgetExecutionClient,
    ) {}

    public function index(): View
    {
        $year = (int) date('Y');

        return view('budget-execution.index', [
            'pageConfig' => [
                'currentYear' => $year,
                'years' => range($year, $year - 4),
                'routes' => [
                    'data' => route('budget-execution.data'),
                    'expenses' => url('/budget-execution/fund-centers/__CODE__/expenses'),
                ],
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $divisionId = $request->filled('division_id') ? (int) $request->input('division_id') : null;
        $fundCenter = trim((string) $request->input('fund_center', ''));
        $fund = trim((string) $request->input('fund', ''));

        $query = array_filter([
            'year' => $year,
            'division_id' => $divisionId,
            'fund_center' => $fundCenter !== '' ? $fundCenter : null,
            'fund' => $fund !== '' ? $fund : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $keyParts = $this->apmCacheKeyFromRequest($request, ['year', 'division_id', 'fund_center', 'fund'], [
            'source' => 'finance_api',
        ]);

        try {
            return $this->apmCachedJson('finance_budget_execution', $request, $keyParts, function () use ($query) {
                $payload = $this->finance->budgetExecution($query);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'cached_at' => now()->toIso8601String(),
                ]);
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function fundCenterExpenses(Request $request, string $fundCenter): JsonResponse
    {
        $code = trim($fundCenter);
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Fund center required.'], 422);
        }

        $keyParts = $this->apmCacheKeyFromRequest($request, [], [
            'source' => 'finance_expenses',
            'fund_center' => strtoupper($code),
        ]);

        try {
            return $this->apmCachedJson('finance_budget_execution', $request, $keyParts, function () use ($code) {
                $payload = $this->finance->fundCenterExpenses($code);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

                return response()->json([
                    'success' => true,
                    'data' => $data,
                ]);
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function exportExcel(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Use Export on the Budget execution page (client-side Excel).',
        ], 410);
    }

    public function exportPdf(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'PDF export is not available for Finance-backed budget execution.',
        ], 410);
    }
}
