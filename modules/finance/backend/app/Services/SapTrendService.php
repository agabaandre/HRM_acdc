<?php

namespace App\Services;

use App\Support\FinanceYearConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Build monthly / quarterly intramural trends from retained SAP month snapshots.
 */
final class SapTrendService
{
    public function __construct(
        private readonly FinanceYearConfig $fy = new FinanceYearConfig,
    ) {}

    /**
     * @param  list<string>|null  $fundCenterFilter  normalized fund centers to include; null = all
     * @return array{
     *   financial_year: array<string, mixed>,
     *   monthly: list<array<string, mixed>>,
     *   quarterly: list<array<string, mixed>>
     * }
     */
    public function trends(?int $fyYear = null, ?array $fundCenterFilter = null): array
    {
        $startMonth = $this->fy->startMonth();
        $fyYear = $fyYear ?? $this->fy->currentFyYear($startMonth);
        $meta = [
            'fy_year' => $fyYear,
            'start_month' => $startMonth,
            'start_month_name' => $this->fy->monthName($startMonth),
            'label' => 'FY '.$fyYear,
        ];

        if (! Schema::hasTable('fin_sap_imports') || ! Schema::hasTable('fin_sap_fund_centers')) {
            return ['financial_year' => $meta, 'monthly' => [], 'quarterly' => []];
        }

        $snapshots = $this->monthlySnapshots($fundCenterFilter);
        $periods = $this->fy->periodsForFyYear($fyYear, $startMonth);

        $monthly = [];
        foreach ($periods as $periodYm) {
            $point = $snapshots[$periodYm] ?? null;
            $classified = $this->fy->classifyPeriod($periodYm, $startMonth);
            $monthly[] = [
                'period_ym' => $periodYm,
                'label' => $this->fy->periodLabel($periodYm),
                'fy_year' => $classified['fy_year'],
                'quarter' => $classified['quarter'],
                'has_data' => $point !== null,
                'approved_budget' => $point['approved_budget'] ?? 0.0,
                'budget_balance' => $point['budget_balance'] ?? 0.0,
                'commitment' => $point['commitment'] ?? 0.0,
                'actual_expenditure' => $point['actual_expenditure'] ?? 0.0,
                'execution_rate' => $point['execution_rate'] ?? null,
                'fund_center_count' => $point['fund_center_count'] ?? 0,
                'import_id' => $point['import_id'] ?? null,
            ];
        }

        $quarterly = [];
        for ($q = 1; $q <= 4; $q++) {
            $slice = array_values(array_filter($monthly, fn (array $row) => (int) $row['quarter'] === $q && $row['has_data']));
            if ($slice === []) {
                $quarterly[] = [
                    'fy_year' => $fyYear,
                    'quarter' => $q,
                    'label' => 'FY'.$fyYear.' Q'.$q,
                    'has_data' => false,
                    'approved_budget' => 0.0,
                    'budget_balance' => 0.0,
                    'commitment' => 0.0,
                    'actual_expenditure' => 0.0,
                    'execution_rate' => null,
                    'fund_center_count' => 0,
                    'months_with_data' => 0,
                ];
                continue;
            }
            // Use the latest month in the quarter as the quarter-end snapshot (cumulative SAP figures).
            usort($slice, fn (array $a, array $b) => strcmp((string) $a['period_ym'], (string) $b['period_ym']));
            $latest = $slice[array_key_last($slice)];
            $quarterly[] = [
                'fy_year' => $fyYear,
                'quarter' => $q,
                'label' => 'FY'.$fyYear.' Q'.$q,
                'has_data' => true,
                'approved_budget' => (float) $latest['approved_budget'],
                'budget_balance' => (float) $latest['budget_balance'],
                'commitment' => (float) $latest['commitment'],
                'actual_expenditure' => (float) $latest['actual_expenditure'],
                'execution_rate' => $latest['execution_rate'],
                'fund_center_count' => (int) $latest['fund_center_count'],
                'months_with_data' => count($slice),
                'as_of' => $latest['period_ym'],
            ];
        }

        return [
            'financial_year' => $meta,
            'monthly' => $monthly,
            'quarterly' => $quarterly,
        ];
    }

    /**
     * @param  list<string>|null  $fundCenterFilter
     * @return array<string, array<string, mixed>> period_ym => metrics
     */
    private function monthlySnapshots(?array $fundCenterFilter): array
    {
        $hasPeriod = Schema::hasColumn('fin_sap_imports', 'period_ym');
        $imports = DB::table('fin_sap_imports')
            ->when($hasPeriod, fn ($q) => $q->whereNotNull('period_ym'))
            ->orderBy($hasPeriod ? 'period_ym' : 'budget_year')
            ->orderByDesc('id')
            ->get(['id', 'budget_year', 'period_ym', 'created_at']);

        // One snapshot per period (highest id wins — already enforced on import, but be safe).
        $byPeriod = [];
        foreach ($imports as $import) {
            $periodYm = $hasPeriod && ! empty($import->period_ym)
                ? (string) $import->period_ym
                : ($import->created_at ? date('Y-m', strtotime((string) $import->created_at)) : sprintf('%04d-01', (int) $import->budget_year));
            if (! isset($byPeriod[$periodYm])) {
                $byPeriod[$periodYm] = (int) $import->id;
            }
        }

        $hasBudgetCodeCol = Schema::hasColumn('fin_sap_fund_centers', 'budget_code');
        $normalizedFilter = null;
        if (is_array($fundCenterFilter)) {
            $normalizedFilter = [];
            foreach ($fundCenterFilter as $code) {
                $n = strtoupper(preg_replace('/\s+/', '', trim((string) $code)) ?? '');
                if ($n !== '') {
                    $normalizedFilter[$n] = true;
                }
            }
        }

        $out = [];
        foreach ($byPeriod as $periodYm => $importId) {
            $centers = DB::table('fin_sap_fund_centers')->where('import_id', $importId)->get();
            $approved = 0.0;
            $balance = 0.0;
            $commitment = 0.0;
            $actual = 0.0;
            $count = 0;
            foreach ($centers as $c) {
                $code = strtoupper(preg_replace('/\s+/', '', trim((string) $c->fund_center)) ?? '');
                if ($normalizedFilter !== null && ! isset($normalizedFilter[$code])) {
                    continue;
                }
                $payload = $this->decodePayload($c->payload ?? null);
                $approved += (float) $c->total_released_budget;
                $balance += (float) $c->released_budget_balance;
                if ($hasBudgetCodeCol && trim((string) ($c->budget_code ?? '')) !== '') {
                    $commitment += (float) ($c->commitment ?? 0);
                    $actual += (float) ($c->actual_expenditure ?? 0);
                } else {
                    $commitment += $this->payloadAmount($payload, ['commitment']);
                    $actual += $this->payloadAmount($payload, ['actual expenditure', 'actual_expenditure']);
                }
                $count++;
            }
            $out[$periodYm] = [
                'import_id' => $importId,
                'approved_budget' => $approved,
                'budget_balance' => $balance,
                'commitment' => $commitment,
                'actual_expenditure' => $actual,
                'execution_rate' => $approved > 0 ? (($approved - $balance) / $approved) : null,
                'fund_center_count' => $count,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (! is_string($payload) || $payload === '') {
            return [];
        }
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function payloadAmount(array $payload, array $keys): float
    {
        $normalized = [];
        foreach ($payload as $k => $v) {
            $normalized[strtolower(trim((string) $k))] = $v;
        }
        foreach ($keys as $key) {
            $k = strtolower(trim($key));
            if (! array_key_exists($k, $normalized)) {
                continue;
            }
            $raw = $normalized[$k];
            if (is_numeric($raw)) {
                return (float) $raw;
            }
            if (is_string($raw)) {
                $clean = preg_replace('/[^0-9.\-]/', '', $raw) ?? '';

                return $clean === '' || $clean === '-' || $clean === '.' ? 0.0 : (float) $clean;
            }
        }

        return 0.0;
    }
}
