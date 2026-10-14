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

        $scope = $this->scope->resolve(
            is_array($permissions) ? $permissions : [],
            $roleId,
            $staffId,
            $sessionDivisionId
        );
        $viewAll = (bool) $scope['can_view_all'];
        $allowedDivisionIds = $scope['division_ids'];

        $filterDivisionId = (int) $request->query('division_id', 0);
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

        $divisions = [];
        if (! $viewAll && $allowedDivisionIds === []) {
            $raw['by_division'] = [];
        }
        foreach ($raw['by_division'] as $div) {
            if (! is_array($div)) {
                continue;
            }
            $divId = (int) ($div['division_id'] ?? 0);
            if ($divId <= 0) {
                continue;
            }
            if ((int) ($div['fund_center_count'] ?? 0) < 1) {
                continue;
            }
            if (! $viewAll && ! in_array($divId, $allowedDivisionIds, true)) {
                continue;
            }
            if ($filterDivisionId > 0 && $divId !== $filterDivisionId) {
                continue;
            }
            $divisions[] = $div;
        }

        $approved = 0.0;
        $balance = 0.0;
        $commitment = 0.0;
        $actual = 0.0;
        $fundCenters = 0;
        $budgetCodes = [];
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
                $budgetCodes[] = [
                    'fund_center' => (string) ($fc['fund_center'] ?? ''),
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

        $filterOptions = [];
        $sourceForFilters = $viewAll ? ($raw['by_division'] ?? []) : $divisions;
        foreach ($sourceForFilters as $div) {
            if (! is_array($div) || (int) ($div['division_id'] ?? 0) <= 0) {
                continue;
            }
            if ((int) ($div['fund_center_count'] ?? 0) < 1) {
                continue;
            }
            $filterOptions[] = [
                'id' => (int) $div['division_id'],
                'name' => (string) ($div['division_name'] ?? ''),
                'short_name' => (string) ($div['division_short_name'] ?? ''),
            ];
        }

        $showOrgCharts = $viewAll;
        $showDirectorateCharts = ! $viewAll && (bool) $scope['show_division_charts'];

        $fundCenterFilter = null;
        if (! $viewAll || $filterDivisionId > 0) {
            $fundCenterFilter = [];
            foreach ($divisions as $div) {
                foreach ($div['fund_centers'] ?? [] as $fc) {
                    if (! is_array($fc)) {
                        continue;
                    }
                    $code = trim((string) ($fc['fund_center'] ?? ''));
                    if ($code !== '') {
                        $fundCenterFilter[] = $code;
                    }
                }
            }
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
                'show_budget_code_charts' => ! $viewAll,
                'allowed_division_ids' => $allowedDivisionIds,
                'directorate_ids' => $scope['directorate_ids'],
                'filter_division_id' => $filterDivisionId > 0 ? $filterDivisionId : null,
                'filter_options' => $filterOptions,
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
        // Prefer calendar last day of the reporting month when period is set; else upload timestamp.
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
