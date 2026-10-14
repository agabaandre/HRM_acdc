<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BudgetExecutionScope;
use App\Services\PortfolioIntramuralService;
use App\Services\SapTrendService;
use App\Support\FinanceYearConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Org-wide, directorate, or division-scoped intramural budget execution.
 */
class BudgetExecutionController extends Controller
{
    public function __construct(
        private readonly PortfolioIntramuralService $intramural = new PortfolioIntramuralService,
        private readonly BudgetExecutionScope $scope = new BudgetExecutionScope,
        private readonly SapTrendService $trends = new SapTrendService,
        private readonly FinanceYearConfig $fy = new FinanceYearConfig,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year') ?: $this->fy->currentFyYear());
        $permissions = $request->attributes->get('risk_permissions', []);
        $roleId = (int) $request->attributes->get('risk_role_id', 0);
        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        $sessionDivisionId = (int) $request->attributes->get('risk_division_id', 0);

        $sessionSource = (string) $request->attributes->get('risk_session_source', '');
        $isApmWarm = str_contains($sessionSource, 'apm_finance_budget_execution_warm')
            || str_starts_with((string) $request->bearerToken(), 'apm_fin_warm_');

        $scope = $this->scope->resolve(
            is_array($permissions) ? $permissions : [],
            $roleId,
            $staffId,
            $sessionDivisionId
        );
        // APM org-wide Redis warm must never be scoped to staff_id=1's empty divisions.
        $viewAll = (bool) $scope['can_view_all'] || $isApmWarm;
        $allowedDivisionIds = $viewAll ? [] : $scope['division_ids'];

        $filterDivisionId = (int) $request->query('division_id', 0);
        $filterFundCenter = strtoupper(preg_replace('/\s+/', '', trim((string) $request->query('fund_center', ''))) ?? '');
        $filterFund = strtoupper(trim((string) $request->query('fund', '')));

        if ($filterDivisionId > 0) {
            if (! $viewAll && ! in_array($filterDivisionId, $allowedDivisionIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view budget execution for your division / directorate.',
                ], 403);
            }
        }

        $raw = $this->intramural->forYear($year, [
            'include_zero_divisions' => false,
            'include_apm_divisions' => $viewAll,
            'fund_center_preview' => 0,
            'cache' => true,
        ]);

        if (! $viewAll && $allowedDivisionIds === []) {
            $raw['by_division'] = [];
        }

        $scopedDivisions = [];
        foreach ($raw['by_division'] as $div) {
            if (! is_array($div)) {
                continue;
            }
            $divId = (int) ($div['division_id'] ?? 0);
            if ($divId <= 0 || (int) ($div['fund_center_count'] ?? 0) < 1) {
                continue;
            }
            if (! $viewAll && ! in_array($divId, $allowedDivisionIds, true)) {
                continue;
            }
            $scopedDivisions[] = $div;
        }

        $filterCatalog = $this->buildFilterCatalog($scopedDivisions);

        $divisions = [];
        foreach ($scopedDivisions as $div) {
            $divId = (int) ($div['division_id'] ?? 0);
            if ($filterDivisionId > 0 && $divId !== $filterDivisionId) {
                continue;
            }

            $centers = [];
            foreach ($div['fund_centers'] ?? [] as $fc) {
                if (! is_array($fc)) {
                    continue;
                }
                $code = strtoupper(preg_replace('/\s+/', '', trim((string) ($fc['fund_center'] ?? ''))) ?? '');
                if ($code === '') {
                    continue;
                }
                if ($filterFundCenter !== '' && $code !== $filterFundCenter) {
                    continue;
                }
                if ($filterFund !== '' && ! $this->fundMatches($fc['budget_code'] ?? null, $filterFund)) {
                    continue;
                }
                $centers[] = $fc;
            }
            if ($centers === []) {
                continue;
            }

            $divisions[] = $this->rollupDivision($div, $centers);
        }

        $approved = 0.0;
        $balance = 0.0;
        $commitment = 0.0;
        $actual = 0.0;
        $fundCenters = 0;
        $budgetCodes = [];
        $trendFundCenters = [];
        foreach ($divisions as $div) {
            $approved += (float) ($div['approved_budget'] ?? 0);
            $balance += (float) ($div['budget_balance'] ?? 0);
            $commitment += (float) ($div['commitment'] ?? 0);
            $actual += (float) ($div['actual_expenditure'] ?? 0);
            $fundCenters += (int) ($div['fund_center_count'] ?? 0);
            foreach ($div['fund_centers'] ?? [] as $fc) {
                if (! is_array($fc)) {
                    continue;
                }
                $code = (string) ($fc['fund_center'] ?? '');
                if ($code !== '') {
                    $trendFundCenters[] = $code;
                }
                $budgetCodes[] = [
                    'fund_center' => $code,
                    'budget_code' => $fc['budget_code'] ?? null,
                    'division_id' => (int) ($fc['division_id'] ?? $div['division_id'] ?? 0),
                    'division_name' => (string) ($fc['division_name'] ?? $div['division_name'] ?? ''),
                    'division_short_name' => (string) ($fc['division_short_name'] ?? $div['division_short_name'] ?? ''),
                    'approved_budget' => (float) ($fc['approved_budget'] ?? 0),
                    'commitment' => (float) ($fc['commitment'] ?? 0),
                    'actual_expenditure' => (float) ($fc['actual_expenditure'] ?? 0),
                    'budget_balance' => (float) ($fc['budget_balance'] ?? 0),
                    'execution_rate' => $fc['execution_rate'] ?? null,
                ];
            }
        }
        usort($budgetCodes, static function (array $a, array $b): int {
            return ($b['approved_budget'] <=> $a['approved_budget'])
                ?: strcasecmp($a['fund_center'], $b['fund_center']);
        });

        $used = $commitment + $actual;
        if ($used <= 0 && $approved > 0) {
            $used = max(0.0, $approved - $balance);
        }

        $showOrgCharts = $viewAll && $filterDivisionId < 1 && $filterFundCenter === '' && $filterFund === '';
        $showDirectorateCharts = ! $viewAll && (bool) $scope['show_division_charts']
            && $filterFundCenter === '' && $filterFund === '';
        $showBudgetCodeCharts = ! $viewAll || $filterDivisionId > 0 || $filterFundCenter !== '' || $filterFund !== '';

        $fundCenterFilter = $trendFundCenters;
        if ($fundCenterFilter === [] && ($filterDivisionId > 0 || $filterFundCenter !== '' || $filterFund !== '')) {
            $fundCenterFilter = ['__none__'];
        } elseif ($viewAll && $filterDivisionId < 1 && $filterFundCenter === '' && $filterFund === '') {
            $fundCenterFilter = null;
        }

        $trendPayload = $this->trends->trends($year, $fundCenterFilter);
        $sapMeta = $this->sapImportMeta($raw['sap_import'] ?? null);

        return response()->json([
            'success' => true,
            'data' => [
                'budget_year' => $year,
                'financial_year' => $trendPayload['financial_year'],
                'scope' => $scope['mode'],
                'can_view_all' => $viewAll,
                'show_org_charts' => $showOrgCharts,
                'show_directorate_charts' => $showDirectorateCharts,
                'show_budget_code_charts' => $showBudgetCodeCharts,
                'allowed_division_ids' => $allowedDivisionIds,
                'directorate_ids' => $scope['directorate_ids'],
                'filter_division_id' => $filterDivisionId > 0 ? $filterDivisionId : null,
                'filter_fund_center' => $filterFundCenter !== '' ? $filterFundCenter : null,
                'filter_fund' => $filterFund !== '' ? $filterFund : null,
                'filter_options' => $filterCatalog['divisions'],
                'filter_catalog' => $filterCatalog,
                'kpis' => [
                    'approved_budget' => $approved,
                    'used_budget' => $used,
                    'burn_rate' => $approved > 0 ? ($used / $approved) : null,
                    'commitment' => $commitment,
                    'actual_expenditure' => $actual,
                    'budget_balance' => $balance,
                    'execution_rate' => $approved > 0 ? (($approved - $balance) / $approved) : null,
                    'fund_center_count' => $fundCenters,
                    'division_count' => count($divisions),
                ],
                'divisions' => $divisions,
                'budget_codes' => $budgetCodes,
                'trends' => [
                    'monthly' => $trendPayload['monthly'],
                    'quarterly' => $trendPayload['quarterly'],
                ],
                'data_as_at' => $sapMeta['data_as_at'],
                'last_updated_at' => $sapMeta['last_updated_at'],
                'sap_import' => $sapMeta['sap_import'],
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $divisions
     * @return array{
     *   divisions: list<array{id: int, name: string, short_name: string}>,
     *   fund_centers: list<array{code: string, division_id: int, funds: list<string>}>,
     *   funds: list<array{code: string, fund_centers: list<string>, division_ids: list<int>}>
     * }
     */
    private function buildFilterCatalog(array $divisions): array
    {
        $divOpts = [];
        $centers = [];
        /** @var array<string, array{code: string, fund_centers: array<string, true>, division_ids: array<int, true>}> */
        $funds = [];

        foreach ($divisions as $div) {
            $divId = (int) ($div['division_id'] ?? 0);
            if ($divId <= 0) {
                continue;
            }
            $divOpts[] = [
                'id' => $divId,
                'name' => (string) ($div['division_name'] ?? ''),
                'short_name' => (string) ($div['division_short_name'] ?? ''),
            ];
            foreach ($div['fund_centers'] ?? [] as $fc) {
                if (! is_array($fc)) {
                    continue;
                }
                $code = strtoupper(preg_replace('/\s+/', '', trim((string) ($fc['fund_center'] ?? ''))) ?? '');
                if ($code === '') {
                    continue;
                }
                $fundList = $this->splitFunds($fc['budget_code'] ?? null);
                $centers[$code] = [
                    'code' => $code,
                    'division_id' => $divId,
                    'funds' => $fundList,
                ];
                foreach ($fundList as $fund) {
                    if (! isset($funds[$fund])) {
                        $funds[$fund] = [
                            'code' => $fund,
                            'fund_centers' => [],
                            'division_ids' => [],
                        ];
                    }
                    $funds[$fund]['fund_centers'][$code] = true;
                    $funds[$fund]['division_ids'][$divId] = true;
                }
            }
        }

        usort($divOpts, static fn (array $a, array $b): int => strcasecmp($a['short_name'].$a['name'], $b['short_name'].$b['name']));
        $centerList = array_values($centers);
        usort($centerList, static fn (array $a, array $b): int => strcasecmp($a['code'], $b['code']));

        $fundList = [];
        foreach ($funds as $fund) {
            $fcCodes = array_keys($fund['fund_centers']);
            sort($fcCodes);
            $divIds = array_map('intval', array_keys($fund['division_ids']));
            sort($divIds);
            $fundList[] = [
                'code' => $fund['code'],
                'fund_centers' => $fcCodes,
                'division_ids' => $divIds,
            ];
        }
        usort($fundList, static fn (array $a, array $b): int => strcasecmp($a['code'], $b['code']));

        return [
            'divisions' => $divOpts,
            'fund_centers' => $centerList,
            'funds' => $fundList,
        ];
    }

    /**
     * @param  array<string, mixed>  $div
     * @param  list<array<string, mixed>>  $centers
     * @return array<string, mixed>
     */
    private function rollupDivision(array $div, array $centers): array
    {
        $approved = 0.0;
        $balance = 0.0;
        $commitment = 0.0;
        $actual = 0.0;
        $main = 0.0;
        foreach ($centers as $fc) {
            $approved += (float) ($fc['approved_budget'] ?? 0);
            $balance += (float) ($fc['budget_balance'] ?? 0);
            $commitment += (float) ($fc['commitment'] ?? 0);
            $actual += (float) ($fc['actual_expenditure'] ?? 0);
            $main += (float) ($fc['main_budget'] ?? $fc['approved_budget'] ?? 0);
        }

        $div['fund_centers'] = $centers;
        $div['fund_center_count'] = count($centers);
        $div['approved_budget'] = $approved;
        $div['budget_balance'] = $balance;
        $div['commitment'] = $commitment;
        $div['actual_expenditure'] = $actual;
        $div['main_budget'] = $main;
        $div['execution_rate'] = $approved > 0 ? (($approved - $balance) / $approved) : null;
        $div['cy_expenditure'] = $commitment + $actual;

        return $div;
    }

    /**
     * @return list<string>
     */
    private function splitFunds(mixed $budgetCode): array
    {
        $parts = preg_split('/\s*,\s*/', trim((string) $budgetCode)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $code = strtoupper(trim((string) $part));
            if ($code !== '') {
                $out[] = $code;
            }
        }

        return array_values(array_unique($out));
    }

    private function fundMatches(mixed $budgetCode, string $selectedFund): bool
    {
        foreach ($this->splitFunds($budgetCode) as $fund) {
            if ($fund === $selectedFund) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{data_as_at: string|null, last_updated_at: string|null, sap_import: array<string, mixed>|null}
     */
    private function sapImportMeta(mixed $import): array
    {
        if (! is_object($import) && ! is_array($import)) {
            return [
                'data_as_at' => null,
                'last_updated_at' => null,
                'sap_import' => null,
            ];
        }
        $row = is_array($import) ? $import : (array) $import;
        $created = isset($row['created_at']) ? (string) $row['created_at'] : null;
        $updated = isset($row['updated_at']) ? (string) $row['updated_at'] : $created;
        $periodYm = isset($row['period_ym']) ? trim((string) $row['period_ym']) : '';
        $dataAsAt = null;
        if ($periodYm !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodYm)) {
            $ts = strtotime($periodYm.'-01');
            if ($ts !== false) {
                $dataAsAt = date('Y-m-t', $ts);
            }
        }
        if ($dataAsAt === null && $created) {
            $ts = strtotime($created);
            $dataAsAt = $ts !== false ? date('Y-m-d', $ts) : null;
        }

        return [
            'data_as_at' => $dataAsAt,
            'last_updated_at' => $updated ?: $created,
            'sap_import' => [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'original_filename' => $row['original_filename'] ?? null,
                'period_ym' => $periodYm !== '' ? $periodYm : null,
                'budget_year' => isset($row['budget_year']) ? (int) $row['budget_year'] : null,
                'sync_status' => $row['sync_status'] ?? null,
                'created_at' => $created,
                'updated_at' => $updated,
            ],
        ];
    }
}
