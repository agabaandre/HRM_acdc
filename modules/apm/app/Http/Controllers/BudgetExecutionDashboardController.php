<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesApmPageResponses;
use App\Services\BudgetExecutionScope;
use App\Services\FinanceBudgetExecutionClient;
use App\Services\FinanceBudgetExecutionSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\View\View;
use Throwable;

/**
 * Budget execution dashboard — serves a background-warmed Finance snapshot with APM scope.
 */
class BudgetExecutionDashboardController extends Controller
{
    use CachesApmPageResponses;

    public function __construct(
        private readonly FinanceBudgetExecutionClient $finance = new FinanceBudgetExecutionClient,
        private readonly FinanceBudgetExecutionSnapshot $snapshot = new FinanceBudgetExecutionSnapshot,
    ) {}

    public function index(): View
    {
        $year = (int) date('Y');

        // Kick a background warm when the shared snapshot is missing so the next load is hot.
        if ($this->snapshot->get($year) === null) {
            $snapshot = $this->snapshot;
            Bus::dispatchAfterResponse(static function () use ($snapshot, $year): void {
                $snapshot->tryRefresh($year);
            });
        }

        $financeSpa = rtrim((string) config('services.finance_api.spa_url', 'http://localhost/staff/finance'), '/');

        return view('budget-execution.index', [
            'pageConfig' => [
                'currentYear' => $year,
                'years' => range($year, $year - 4),
                'financePortalUrl' => $financeSpa.'/budget-execution',
                'routes' => [
                    'data' => route('budget-execution.data'),
                    'expenses' => url('/budget-execution/fund-centers/__CODE__/expenses'),
                ],
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $year = $request->filled('year') ? (int) $request->input('year') : (int) date('Y');
        $divisionId = $request->filled('division_id') ? (int) $request->input('division_id') : null;
        $fundCenter = trim((string) $request->input('fund_center', ''));
        $fund = trim((string) $request->input('fund', ''));

        $keyParts = $this->apmCacheKeyFromRequest($request, ['year', 'division_id', 'fund_center', 'fund'], [
            'source' => 'finance_snapshot',
            'scope_v' => 4,
        ]);

        try {
            $build = function () use ($year, $divisionId, $fundCenter, $fund) {
                $record = $this->snapshot->remember($year);
                $scope = BudgetExecutionScope::resolve();
                $data = $this->snapshot->presentForScope(
                    $record['data'],
                    $scope,
                    $divisionId,
                    $fundCenter,
                    $fund
                );

                // Empty snapshot while Finance SPA has data: force a fresh org-wide warm
                // (ACCESS_ALL) or a live user fetch (scoped users). Never keep a wiped empty.
                if ($this->snapshot->divisionCount($data) === 0) {
                    $live = null;
                    if (($scope['access'] ?? '') === BudgetExecutionScope::ACCESS_ALL) {
                        try {
                            $record = $this->snapshot->remember($year, forceRefresh: true);
                            $data = $this->snapshot->presentForScope(
                                $record['data'],
                                $scope,
                                $divisionId,
                                $fundCenter,
                                $fund
                            );
                        } catch (Throwable $e) {
                            report($e);
                        }
                    }
                    if ($this->snapshot->divisionCount($data) === 0) {
                        $live = $this->liveFinancePayload($year, $divisionId, $fundCenter, $fund);
                    }
                    if ($live !== null && $this->snapshot->divisionCount($live['data']) > 0) {
                        $data = $this->snapshot->presentForScope(
                            $live['data'],
                            $scope,
                            $divisionId,
                            $fundCenter,
                            $fund
                        );
                        $record = [
                            'data' => $live['data'],
                            'synced_at' => $live['synced_at'],
                        ];
                    }
                }

                $data['finance_synced_at'] = $record['synced_at'];

                // Soft refresh in the background when snapshot is aging.
                $synced = strtotime($record['synced_at'] ?: '') ?: 0;
                $softTtl = max(60, (int) config('services.finance_api.snapshot_ttl', 900) / 2);
                if ($synced > 0 && (time() - $synced) >= $softTtl) {
                    $snapshot = $this->snapshot;
                    Bus::dispatchAfterResponse(static function () use ($snapshot, $year): void {
                        $snapshot->tryRefresh($year);
                    });
                }

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'finance_synced_at' => $record['synced_at'],
                    'cached_at' => now()->toIso8601String(),
                ]);
            };

            // Never page-cache an empty dashboard — it sticks zeros for minutes after Finance imports.
            $cached = $this->snapshot->get($year);
            if ($cached === null || $this->snapshot->divisionCount($cached['data'] ?? []) === 0) {
                return $build();
            }

            return $this->apmCachedJson('finance_budget_execution', $request, $keyParts, $build);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Live Finance API call as the signed-in APM user (same path as Finance SPA).
     *
     * @return array{data: array<string, mixed>, synced_at: string}|null
     */
    private function liveFinancePayload(
        int $year,
        ?int $divisionId,
        string $fundCenter,
        string $fund
    ): ?array {
        try {
            $query = array_filter([
                'year' => $year,
                'division_id' => $divisionId,
                'fund_center' => $fundCenter !== '' ? $fundCenter : null,
                'fund' => $fund !== '' ? $fund : null,
            ], static fn ($v) => $v !== null && $v !== '');
            $payload = $this->finance->budgetExecution($query);
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
            if (! is_array($data)) {
                return null;
            }

            return [
                'data' => $data,
                'synced_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
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
