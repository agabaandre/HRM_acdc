<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Roll up latest SAP fund-center totals using APM fund_codes + APM divisions.
 */
final class PortfolioIntramuralService
{
    /**
     * @param  array{include_zero_divisions?: bool, include_apm_divisions?: bool, fund_center_preview?: int, cache?: bool}  $options
     * @return array{
     *   approved_budget: float,
     *   budget_balance: float,
     *   commitment: float,
     *   actual_expenditure: float,
     *   fund_center_count: int,
     *   execution_rate: float|null,
     *   mapped_count: int,
     *   unmapped_count: int,
     *   divisions_source: string,
     *   apm_divisions: list<array{division_id: int, division_name: string, division_short_name: string}>,
     *   by_division: list<array<string, mixed>>,
     *   by_fund_center: list<array<string, mixed>>,
     *   sap_import: object|null
     * }
     */
    public function forYear(int $year, array $options = []): array
    {
        $includeZero = (bool) ($options['include_zero_divisions'] ?? true);
        $includeApmList = (bool) ($options['include_apm_divisions'] ?? true);
        $fcPreview = max(0, (int) ($options['fund_center_preview'] ?? 40));
        $useCache = (bool) ($options['cache'] ?? true);

        $latestImport = DB::table('fin_sap_imports')
            ->where('budget_year', $year)
            ->where('is_latest', true)
            ->orderByDesc('id')
            ->first();

        $cacheKey = sprintf(
            'fin_intramural:y%d:i%d:z%d:a%d:p%d',
            $year,
            (int) ($latestImport->id ?? 0),
            $includeZero ? 1 : 0,
            $includeApmList ? 1 : 0,
            $fcPreview
        );

        if ($useCache && $latestImport) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['sap_import'] = $latestImport;

                return $cached;
            }
        }

        $apmDivisions = $this->apmDivisionMeta();
        $empty = [
            'approved_budget' => 0.0,
            'budget_balance' => 0.0,
            'commitment' => 0.0,
            'actual_expenditure' => 0.0,
            'fund_center_count' => 0,
            'execution_rate' => null,
            'mapped_count' => 0,
            'unmapped_count' => 0,
            'divisions_source' => 'apm',
            'apm_divisions' => $includeApmList ? array_values($apmDivisions) : [],
            'by_division' => [],
            'by_fund_center' => [],
            'sap_import' => $latestImport,
        ];

        if (! $latestImport) {
            return $empty;
        }

        $centers = DB::table('fin_sap_fund_centers')
            ->where('import_id', $latestImport->id)
            ->orderBy('fund_center')
            ->get();

        $codeToDivision = $this->fundCenterDivisionMap($year);
        $budgetCodesFromRows = $this->budgetCodesByFundCenter((int) $latestImport->id);

        $byDivision = [];
        $byFc = [];
        $approved = 0.0;
        $balance = 0.0;
        $commitment = 0.0;
        $actual = 0.0;
        $mapped = 0;
        $unmapped = 0;

        $hasBudgetCodeCol = Schema::hasColumn('fin_sap_fund_centers', 'budget_code');

        foreach ($centers as $c) {
            $code = $this->normalizeCode((string) $c->fund_center);
            $payload = $this->decodePayload($c->payload ?? null);
            $a = (float) $c->total_released_budget;
            $b = (float) $c->released_budget_balance;
            $comm = $this->payloadAmount($payload, ['commitment']);
            $act = $this->payloadAmount($payload, ['actual expenditure', 'actual_expenditure']);
            // Prefer denormalized columns only when this snapshot was imported with them.
            if ($hasBudgetCodeCol && trim((string) ($c->budget_code ?? '')) !== '') {
                $comm = (float) ($c->commitment ?? $comm);
                $act = (float) ($c->actual_expenditure ?? $act);
            }
            $main = $this->payloadAmount($payload, ['original budget', 'main budget']);
            if ($main <= 0) {
                $main = $a;
            }
            $budgetCode = null;
            if ($hasBudgetCodeCol) {
                $budgetCode = trim((string) ($c->budget_code ?? ''));
            }
            if ($budgetCode === null || $budgetCode === '') {
                $budgetCode = trim((string) ($payload['fund'] ?? ''));
            }
            if ($budgetCode === '') {
                $budgetCode = $budgetCodesFromRows[$code] ?? null;
            }
            if ($budgetCode === '') {
                $budgetCode = null;
            }
            $fcText = '';
            if ($hasBudgetCodeCol) {
                $fcText = trim((string) ($c->fund_center_text ?? ''));
            }
            if ($fcText === '') {
                $fcText = trim((string) ($payload['fund center text'] ?? ''));
            }
            if ($fcText === '') {
                $fcText = null;
            }

            $divId = $codeToDivision[$code] ?? null;
            $meta = $divId !== null ? ($apmDivisions[$divId] ?? null) : null;
            if ($divId !== null && $meta !== null) {
                $mapped++;
            } else {
                $unmapped++;
                $divId = 0;
                $meta = [
                    'division_id' => 0,
                    'division_name' => 'Unmapped in APM',
                    'division_short_name' => '—',
                ];
            }

            $approved += $a;
            $balance += $b;
            $commitment += $comm;
            $actual += $act;

            $fcRow = [
                'fund_center' => $code,
                'budget_code' => $budgetCode,
                'fund_center_text' => $fcText,
                'division_id' => $divId,
                'division_name' => $meta['division_name'],
                'division_short_name' => $meta['division_short_name'],
                'main_budget' => $main,
                'approved_budget' => $a,
                'budget_balance' => $b,
                'commitment' => $comm,
                'actual_expenditure' => $act,
                'execution_rate' => $a > 0 ? (($a - $b) / $a) : null,
            ];
            $byFc[] = $fcRow;

            if (! isset($byDivision[$divId])) {
                $byDivision[$divId] = [
                    'division_id' => $divId,
                    'division_name' => $meta['division_name'],
                    'division_short_name' => $meta['division_short_name'],
                    'main_budget' => 0.0,
                    'approved_budget' => 0.0,
                    'budget_balance' => 0.0,
                    'commitment' => 0.0,
                    'actual_expenditure' => 0.0,
                    'fund_center_count' => 0,
                    'fund_centers' => [],
                ];
            }
            $byDivision[$divId]['main_budget'] += $main;
            $byDivision[$divId]['approved_budget'] += $a;
            $byDivision[$divId]['budget_balance'] += $b;
            $byDivision[$divId]['commitment'] += $comm;
            $byDivision[$divId]['actual_expenditure'] += $act;
            $byDivision[$divId]['fund_center_count']++;
            $byDivision[$divId]['fund_centers'][] = $fcRow;
        }

        // Include every APM division (zero-filled) so portfolio entry filters match org chart.
        if ($includeZero) {
            foreach ($apmDivisions as $divId => $meta) {
                if (isset($byDivision[$divId])) {
                    continue;
                }
                $byDivision[$divId] = [
                    'division_id' => $divId,
                    'division_name' => $meta['division_name'],
                    'division_short_name' => $meta['division_short_name'],
                    'main_budget' => 0.0,
                    'approved_budget' => 0.0,
                    'budget_balance' => 0.0,
                    'commitment' => 0.0,
                    'actual_expenditure' => 0.0,
                    'fund_center_count' => 0,
                    'fund_centers' => [],
                ];
            }
        }

        $divisionRows = array_values($byDivision);
        usort($divisionRows, function (array $x, array $y): int {
            if (($x['division_id'] === 0) !== ($y['division_id'] === 0)) {
                return $x['division_id'] === 0 ? 1 : -1;
            }
            $xHas = (int) $x['fund_center_count'] > 0 ? 0 : 1;
            $yHas = (int) $y['fund_center_count'] > 0 ? 0 : 1;
            if ($xHas !== $yHas) {
                return $xHas <=> $yHas;
            }
            $xRate = $x['approved_budget'] > 0
                ? (((float) $x['approved_budget'] - (float) $x['budget_balance']) / (float) $x['approved_budget'])
                : -1.0;
            $yRate = $y['approved_budget'] > 0
                ? (((float) $y['approved_budget'] - (float) $y['budget_balance']) / (float) $y['approved_budget'])
                : -1.0;
            if ($xRate !== $yRate) {
                return $yRate <=> $xRate;
            }

            return strcasecmp((string) $x['division_name'], (string) $y['division_name']);
        });
        foreach ($divisionRows as &$row) {
            $a = (float) $row['approved_budget'];
            $b = (float) $row['budget_balance'];
            $row['execution_rate'] = $a > 0 ? (($a - $b) / $a) : null;
            $row['cy_expenditure'] = (float) $row['commitment'] + (float) $row['actual_expenditure'];
            usort($row['fund_centers'], function (array $x, array $y): int {
                return ($y['execution_rate'] ?? -1) <=> ($x['execution_rate'] ?? -1);
            });
        }
        unset($row);

        $payload = [
            'approved_budget' => $approved,
            'budget_balance' => $balance,
            'commitment' => $commitment,
            'actual_expenditure' => $actual,
            'fund_center_count' => count($byFc),
            'execution_rate' => $approved > 0 ? (($approved - $balance) / $approved) : null,
            'mapped_count' => $mapped,
            'unmapped_count' => $unmapped,
            'divisions_source' => 'apm',
            'apm_divisions' => $includeApmList ? array_values($apmDivisions) : [],
            'by_division' => $divisionRows,
            'by_fund_center' => $fcPreview > 0 ? array_slice($byFc, 0, $fcPreview) : [],
            'sap_import' => null,
        ];

        if ($useCache) {
            Cache::put($cacheKey, $payload, 90);
        }
        $payload['sap_import'] = $latestImport;

        return $payload;
    }

    /**
     * Map budget codes → division for the given year only.
     * APM fund_codes are year-dependent; never fall back to other years.
     *
     * @return array<string, int> normalized code => division_id
     */
    private function fundCenterDivisionMap(int $year): array
    {
        try {
            if (! $this->apmReady() || ! Schema::connection('apm')->hasTable('fund_codes')) {
                return [];
            }
            $rows = DB::connection('apm')
                ->table('fund_codes')
                ->where('year', $year)
                ->whereNotNull('division_id')
                ->select(['code', 'division_id', 'is_active'])
                ->get();

            // Prefer active rows when the same code appears twice in-year.
            $best = [];
            foreach ($rows as $row) {
                $code = $this->normalizeCode((string) $row->code);
                if ($code === '' || (int) $row->division_id <= 0) {
                    continue;
                }
                $active = (int) ($row->is_active ?? 0) === 1 ? 1 : 0;
                if (! isset($best[$code]) || $active > $best[$code]['active']) {
                    $best[$code] = [
                        'division_id' => (int) $row->division_id,
                        'active' => $active,
                    ];
                }
            }

            $map = [];
            foreach ($best as $code => $info) {
                $map[$code] = $info['division_id'];
            }

            return $map;
        } catch (Throwable $e) {
            Log::debug('APM fund_codes division map unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array{division_id: int, division_name: string, division_short_name: string}>
     */
    private function apmDivisionMeta(): array
    {
        try {
            if (! $this->apmReady() || ! Schema::connection('apm')->hasTable('divisions')) {
                return [];
            }
            $idCol = Schema::connection('apm')->hasColumn('divisions', 'id') ? 'id' : 'division_id';
            $rows = DB::connection('apm')
                ->table('divisions')
                ->select([
                    $idCol.' as division_id',
                    'division_name',
                    'division_short_name',
                ])
                ->orderBy('division_name')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $id = (int) $row->division_id;
                if ($id <= 0) {
                    continue;
                }
                $out[$id] = [
                    'division_id' => $id,
                    'division_name' => trim((string) ($row->division_name ?? '')) ?: ('Division '.$id),
                    'division_short_name' => trim((string) ($row->division_short_name ?? '')) ?: '—',
                ];
            }

            return $out;
        } catch (Throwable $e) {
            Log::debug('APM divisions unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function apmReady(): bool
    {
        return (string) config('database.connections.apm.database', '') !== '';
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    /**
     * Distinct SAP "Fund" (budget code) values per fund center from line rows.
     *
     * @return array<string, string> normalized fund center => comma-joined budget codes
     */
    private function budgetCodesByFundCenter(int $importId): array
    {
        if ($importId < 1 || ! Schema::hasTable('fin_sap_rows')) {
            return [];
        }

        $hasBudgetCode = Schema::hasColumn('fin_sap_rows', 'budget_code');
        $rows = DB::table('fin_sap_rows')
            ->where('import_id', $importId)
            ->where('is_total_row', false)
            ->whereNotNull('fund_center')
            ->select(['fund_center', 'budget_code', 'payload'])
            ->get();

        /** @var array<string, array<string, true>> $map */
        $map = [];
        foreach ($rows as $row) {
            $fc = $this->normalizeCode((string) ($row->fund_center ?? ''));
            if ($fc === '') {
                continue;
            }
            $code = '';
            if ($hasBudgetCode) {
                $code = trim((string) ($row->budget_code ?? ''));
            }
            if ($code === '') {
                $payload = $this->decodePayload($row->payload ?? null);
                $code = trim((string) ($payload['fund'] ?? ''));
            }
            if ($code === '') {
                continue;
            }
            $map[$fc][$code] = true;
        }

        $out = [];
        foreach ($map as $fc => $codes) {
            $list = array_keys($codes);
            sort($list);
            $out[$fc] = implode(', ', $list);
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
        } catch (Throwable) {
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
