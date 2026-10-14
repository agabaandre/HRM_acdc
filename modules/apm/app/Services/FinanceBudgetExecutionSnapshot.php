<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Org-wide Finance budget-execution snapshot warmed in the background.
 * Dashboard requests read this cache so users see recent data without waiting on Finance.
 */
final class FinanceBudgetExecutionSnapshot
{
    public const CACHE_KEY_PREFIX = 'apm:finance_budget_execution:snapshot:';

    public const SYNCED_AT_KEY = 'apm:finance_budget_execution:synced_at';

    public function __construct(
        private readonly FinanceBudgetExecutionClient $client = new FinanceBudgetExecutionClient,
    ) {}

    /**
     * @return array{data: array<string, mixed>, synced_at: string}|null
     */
    public function get(?int $year = null): ?array
    {
        $payload = Cache::store($this->store())->get($this->key($year));
        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            return null;
        }

        $syncedAt = (string) ($payload['synced_at'] ?? Cache::store($this->store())->get(self::SYNCED_AT_KEY) ?? '');

        return [
            'data' => $payload['data'],
            'synced_at' => $syncedAt !== '' ? $syncedAt : now()->toIso8601String(),
        ];
    }

    public function syncedAt(): ?string
    {
        $value = Cache::store($this->store())->get(self::SYNCED_AT_KEY);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Pull org-wide budget execution from Finance and store for dashboards.
     *
     * @return array{data: array<string, mixed>, synced_at: string}
     */
    public function refresh(?int $year = null): array
    {
        $query = array_filter(['year' => $year], static fn ($v) => $v !== null && $v !== '');
        $payload = $this->client->budgetExecutionAsSystem($query);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        if (! is_array($data)) {
            throw new \RuntimeException('Finance warm returned an invalid budget-execution payload.');
        }

        $syncedAt = now()->toIso8601String();
        $record = [
            'data' => $data,
            'synced_at' => $syncedAt,
        ];

        $divisionCount = $this->divisionCount($data);
        // Never pin an empty warm for the full TTL — production recovered from auth
        // issues by caching zeros and serving them while Finance already had SAP data.
        $ttl = $divisionCount > 0
            ? max(60, (int) config('services.finance_api.snapshot_ttl', 900))
            : 60;

        if ($divisionCount === 0) {
            Log::warning('Finance budget-execution warm returned zero divisions', [
                'year' => $year,
                'sap_import' => $data['sap_import'] ?? null,
                'kpis' => $data['kpis'] ?? null,
            ]);
        } else {
            ApmPageCache::bust('finance_budget_execution');
        }

        $store = Cache::store($this->store());
        $store->put($this->key($year), $record, $ttl);
        $store->put(self::SYNCED_AT_KEY, $syncedAt, $ttl);

        return $record;
    }

    /**
     * Return a fresh-enough snapshot; refresh synchronously on miss or empty cache.
     *
     * @return array{data: array<string, mixed>, synced_at: string}
     */
    public function remember(?int $year = null, bool $forceRefresh = false): array
    {
        if (! $forceRefresh) {
            $cached = $this->get($year);
            if ($cached !== null && $this->divisionCount($cached['data'] ?? []) > 0) {
                return $cached;
            }
            // Empty cached warm: refresh immediately so APM catches up with Finance.
            if ($cached !== null) {
                $forceRefresh = true;
            }
        }

        return $this->refresh($year);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function divisionCount(array $data): int
    {
        return count($data['divisions'] ?? []);
    }

    /**
     * Apply APM visibility + optional filters onto an org-wide Finance payload.
     *
     * @param  array<string, mixed>  $orgData
     * @param  array{
     *   access: string,
     *   allowed_division_ids: list<int>|null,
     *   is_director?: bool,
     * }  $scope
     * @return array<string, mixed>
     */
    public function presentForScope(
        array $orgData,
        array $scope,
        ?int $filterDivisionId = null,
        string $filterFundCenter = '',
        string $filterFund = ''
    ): array {
        $viewAll = ($scope['access'] ?? '') === BudgetExecutionScope::ACCESS_ALL
            || ($scope['allowed_division_ids'] ?? null) === null;
        $allowed = $viewAll ? null : array_values(array_map('intval', $scope['allowed_division_ids'] ?? []));

        $filterDivisionId = $filterDivisionId !== null && $filterDivisionId > 0 ? $filterDivisionId : null;
        $filterFundCenter = strtoupper(preg_replace('/\s+/', '', $filterFundCenter) ?? '');
        $filterFund = strtoupper(trim($filterFund));

        if ($filterDivisionId !== null && $allowed !== null && ! in_array($filterDivisionId, $allowed, true)) {
            abort(403, 'You are not allowed to view budget execution for this division.');
        }

        $scopedDivisions = [];
        foreach ($orgData['divisions'] ?? [] as $div) {
            if (! is_array($div)) {
                continue;
            }
            $divId = (int) ($div['division_id'] ?? 0);
            if ($divId < 1) {
                continue;
            }
            if ($allowed !== null && ! in_array($divId, $allowed, true)) {
                continue;
            }
            $scopedDivisions[] = $div;
        }

        $filterCatalog = $this->buildFilterCatalog($scopedDivisions);

        $divisions = [];
        foreach ($scopedDivisions as $div) {
            $divId = (int) ($div['division_id'] ?? 0);
            if ($filterDivisionId !== null && $divId !== $filterDivisionId) {
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
                $budgetCode = strtoupper(trim((string) ($fc['budget_code'] ?? '')));
                if ($filterFund !== '' && $budgetCode !== $filterFund) {
                    continue;
                }
                $centers[] = $fc;
            }

            if ($centers === [] && ($filterFundCenter !== '' || $filterFund !== '')) {
                continue;
            }

            $released = 0.0;
            $commitment = 0.0;
            $actual = 0.0;
            $balance = 0.0;
            foreach ($centers !== [] ? $centers : ($div['fund_centers'] ?? []) as $fc) {
                if (! is_array($fc)) {
                    continue;
                }
                $released += (float) ($fc['approved_budget'] ?? 0);
                $commitment += (float) ($fc['commitment'] ?? 0);
                $actual += (float) ($fc['actual_expenditure'] ?? 0);
                $balance += (float) ($fc['budget_balance'] ?? 0);
            }
            $used = $commitment + $actual;
            $row = $div;
            $row['fund_centers'] = $centers !== [] ? $centers : ($div['fund_centers'] ?? []);
            $row['fund_center_count'] = count($row['fund_centers']);
            if ($filterFundCenter !== '' || $filterFund !== '' || $filterDivisionId !== null) {
                $row['approved_budget'] = $released;
                $row['commitment'] = $commitment;
                $row['actual_expenditure'] = $actual;
                $row['budget_balance'] = $balance;
                $row['used_budget'] = $used;
                $row['execution_rate'] = $released > 0 ? (($released - $balance) / $released) : null;
            }
            $divisions[] = $row;
        }

        $approved = 0.0;
        $commitment = 0.0;
        $actual = 0.0;
        $balance = 0.0;
        $fundCenters = 0;
        foreach ($divisions as $div) {
            $approved += (float) ($div['approved_budget'] ?? 0);
            $commitment += (float) ($div['commitment'] ?? 0);
            $actual += (float) ($div['actual_expenditure'] ?? 0);
            $balance += (float) ($div['budget_balance'] ?? 0);
            $fundCenters += (int) ($div['fund_center_count'] ?? count($div['fund_centers'] ?? []));
        }
        $used = $commitment + $actual;

        $mode = $viewAll ? 'all' : (($scope['access'] ?? '') === BudgetExecutionScope::ACCESS_DIRECTORATE || count($allowed ?? []) > 1
            ? 'directorate'
            : 'division');
        $showOrgCharts = $viewAll && $filterDivisionId === null && $filterFundCenter === '' && $filterFund === '';
        $showDirectorateCharts = ! $viewAll && $mode === 'directorate'
            && $filterFundCenter === '' && $filterFund === '';
        $showBudgetCodeCharts = ! $viewAll || $filterDivisionId !== null || $filterFundCenter !== '' || $filterFund !== '';

        $budgetCodes = [];
        if ($showBudgetCodeCharts || ! $viewAll) {
            foreach ($divisions as $div) {
                foreach ($div['fund_centers'] ?? [] as $fc) {
                    if (! is_array($fc)) {
                        continue;
                    }
                    $budgetCodes[] = array_merge($fc, [
                        'division_id' => (int) ($div['division_id'] ?? 0),
                        'division_name' => (string) ($div['division_name'] ?? ''),
                        'division_short_name' => (string) ($div['division_short_name'] ?? ''),
                    ]);
                }
            }
        }

        return array_merge($orgData, [
            'scope' => $mode,
            'can_view_all' => $viewAll,
            'show_org_charts' => $showOrgCharts,
            'show_directorate_charts' => $showDirectorateCharts,
            'show_budget_code_charts' => $showBudgetCodeCharts,
            'allowed_division_ids' => $allowed ?? [],
            'filter_division_id' => $filterDivisionId,
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
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $divisions
     * @return array{
     *   divisions: list<array{id: int, name: string, short_name: string}>,
     *   fund_centers: list<array{code: string, division_id: int, funds: list<string>}>,
     *   funds: list<array{code: string, division_ids: list<int>, fund_centers: list<string>}>
     * }
     */
    private function buildFilterCatalog(array $divisions): array
    {
        $divOpts = [];
        $centers = [];
        $funds = [];

        foreach ($divisions as $div) {
            $divId = (int) ($div['division_id'] ?? 0);
            if ($divId < 1) {
                continue;
            }
            $divOpts[$divId] = [
                'id' => $divId,
                'name' => (string) ($div['division_name'] ?? ''),
                'short_name' => (string) ($div['division_short_name'] ?? ''),
            ];
            foreach ($div['fund_centers'] ?? [] as $fc) {
                if (! is_array($fc)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($fc['fund_center'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $fund = strtoupper(trim((string) ($fc['budget_code'] ?? '')));
                if (! isset($centers[$code])) {
                    $centers[$code] = [
                        'code' => $code,
                        'division_id' => $divId,
                        'funds' => [],
                    ];
                }
                if ($fund !== '' && ! in_array($fund, $centers[$code]['funds'], true)) {
                    $centers[$code]['funds'][] = $fund;
                }
                if ($fund === '') {
                    continue;
                }
                if (! isset($funds[$fund])) {
                    $funds[$fund] = [
                        'code' => $fund,
                        'division_ids' => [],
                        'fund_centers' => [],
                    ];
                }
                if (! in_array($divId, $funds[$fund]['division_ids'], true)) {
                    $funds[$fund]['division_ids'][] = $divId;
                }
                if (! in_array($code, $funds[$fund]['fund_centers'], true)) {
                    $funds[$fund]['fund_centers'][] = $code;
                }
            }
        }

        usort($divOpts, static fn ($a, $b) => strcasecmp($a['short_name'] ?: $a['name'], $b['short_name'] ?: $b['name']));
        ksort($centers);
        ksort($funds);

        return [
            'divisions' => array_values($divOpts),
            'fund_centers' => array_values($centers),
            'funds' => array_values($funds),
        ];
    }

    private function key(?int $year): string
    {
        $y = $year ?? (int) date('Y');

        return self::CACHE_KEY_PREFIX.$y;
    }

    private function store(): string
    {
        return (string) config('apm.page_cache_store', config('cache.default', 'redis'));
    }

    public function tryRefresh(?int $year = null): bool
    {
        try {
            $this->refresh($year);

            return true;
        } catch (Throwable $e) {
            Log::warning('Finance budget-execution snapshot warm failed', [
                'year' => $year,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
