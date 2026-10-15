<?php

namespace App\Services;

use App\Jobs\SyncSapImportToApmJob;
use App\Support\SapExportParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class SapImportService
{
    public function __construct(
        private readonly SapExportParser $parser = new SapExportParser,
        private readonly ?ApmFundCodeSyncClient $apm = null,
    ) {}

    /**
     * @return array{
     *   import_id: int,
     *   rows: int,
     *   totals: int,
     *   apm_updated: int,
     *   sync_status: string,
     *   budget_year: int,
     *   period_ym: string
     * }
     */
    public function import(
        string $path,
        string $originalFilename,
        ?int $userId = null,
        ?string $periodYm = null,
    ): array {
        $periodYm = $this->normalizePeriodYm($periodYm);
        $budgetYear = (int) substr($periodYm, 0, 4);
        $parsed = $this->parser->parse($path);
        $hasBudgetCodeCol = Schema::hasColumn('fin_sap_fund_centers', 'budget_code');
        $hasRowExpenseCols = Schema::hasColumn('fin_sap_rows', 'budget_code');
        $hasPeriodCol = Schema::hasColumn('fin_sap_imports', 'period_ym');

        $importId = (int) DB::transaction(function () use (
            $parsed,
            $budgetYear,
            $periodYm,
            $originalFilename,
            $userId,
            $hasBudgetCodeCol,
            $hasRowExpenseCols,
            $hasPeriodCol,
        ) {
            // Keep only the last upload for this month (daily refresh within the month).
            $replaceIds = DB::table('fin_sap_imports')
                ->where(function ($q) use ($periodYm, $hasPeriodCol, $budgetYear) {
                    if ($hasPeriodCol) {
                        $q->where('period_ym', $periodYm);
                    } else {
                        $q->where('budget_year', $budgetYear)->where('is_latest', true);
                    }
                })
                ->pluck('id')
                ->all();
            if ($replaceIds !== []) {
                DB::table('fin_sap_rows')->whereIn('import_id', $replaceIds)->delete();
                DB::table('fin_sap_fund_centers')->whereIn('import_id', $replaceIds)->delete();
                DB::table('fin_sap_imports')->whereIn('id', $replaceIds)->delete();
            }

            $importData = [
                'uploaded_by' => $userId,
                'original_filename' => $originalFilename,
                'budget_year' => $budgetYear,
                'row_count' => count($parsed['rows']),
                'total_row_count' => count($parsed['totals']),
                'apm_updated_count' => 0,
                'sync_status' => 'pending',
                'sync_error' => null,
                'is_latest' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasPeriodCol) {
                $importData['period_ym'] = $periodYm;
            }

            $importId = DB::table('fin_sap_imports')->insertGetId($importData);

            $rowInserts = [];
            foreach ($parsed['rows'] as $row) {
                $glRaw = trim((string) ($row['gl account'] ?? ''));
                $gl = strtolower($glRaw);
                $fc = trim((string) ($row['fund center'] ?? ''));
                $isTotal = $gl === 'total';
                $isExpense = ! $isTotal && $glRaw !== '';
                $base = [
                    'import_id' => $importId,
                    'budget_year' => $budgetYear,
                    'fund_center' => $fc !== '' ? $fc : null,
                    'gl_account' => $glRaw !== '' ? $glRaw : null,
                    'is_total_row' => $isTotal,
                    'payload' => json_encode($row, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($hasRowExpenseCols) {
                    $budgetCode = trim((string) ($row['fund'] ?? ''));
                    $base['budget_code'] = $budgetCode !== '' ? $budgetCode : null;
                    $costCenter = trim((string) ($row['cost center'] ?? ''));
                    $base['cost_center'] = $costCenter !== '' ? $costCenter : null;
                    $fcText = trim((string) ($row['fund center text'] ?? ''));
                    $base['fund_center_text'] = $fcText !== '' ? $fcText : null;
                    $glDesc = trim((string) ($row['gl description'] ?? ''));
                    $base['gl_description'] = $glDesc !== '' ? $glDesc : null;
                    $base['is_expense_row'] = $isExpense;
                    $base['commitment'] = $this->decimal($row['commitment'] ?? 0);
                    $base['actual_expenditure'] = $this->decimal($row['actual expenditure'] ?? 0);
                    $base['total_released_budget'] = $this->decimal($row['total released budget'] ?? 0);
                }
                $rowInserts[] = $base;
            }
            foreach (array_chunk($rowInserts, 200) as $chunk) {
                DB::table('fin_sap_rows')->insert($chunk);
            }

            $centersByCode = [];
            foreach ($parsed['totals'] as $total) {
                $fc = trim((string) ($total['fund_center'] ?? ''));
                if ($fc === '') {
                    continue;
                }
                $center = [
                    'import_id' => $importId,
                    'budget_year' => $budgetYear,
                    'fund_center' => $fc,
                    'total_released_budget' => $total['total_released_budget'],
                    'released_budget_balance' => $total['released_budget_balance'],
                    'payload' => json_encode($total['raw'] ?? [], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($hasBudgetCodeCol) {
                    $center['budget_code'] = $total['budget_code'] ?? null;
                    $center['fund_center_text'] = $total['fund_center_text'] ?? null;
                    $center['commitment'] = (float) ($total['commitment'] ?? 0);
                    $center['actual_expenditure'] = (float) ($total['actual_expenditure'] ?? 0);
                }
                $centersByCode[$fc] = $center;
            }
            $centerInserts = array_values($centersByCode);
            $updateCols = [
                'budget_year',
                'total_released_budget',
                'released_budget_balance',
                'payload',
                'updated_at',
            ];
            if ($hasBudgetCodeCol) {
                array_push($updateCols, 'budget_code', 'fund_center_text', 'commitment', 'actual_expenditure');
            }
            foreach (array_chunk($centerInserts, 200) as $chunk) {
                DB::table('fin_sap_fund_centers')->upsert(
                    $chunk,
                    ['import_id', 'fund_center'],
                    $updateCols
                );
            }

            $this->refreshLatestFlags();

            return $importId;
        });

        SyncSapImportToApmJob::dispatch($importId);

        return [
            'import_id' => $importId,
            'rows' => count($parsed['rows']),
            'totals' => count($parsed['totals']),
            'apm_updated' => 0,
            'sync_status' => 'pending',
            'budget_year' => $budgetYear,
            'period_ym' => $periodYm,
        ];
    }

    /**
     * @return array{import_id: int, apm_updated: int, sync_status: string}
     */
    public function retrySync(int $importId): array
    {
        DB::table('fin_sap_imports')->where('id', $importId)->update([
            'sync_status' => 'pending',
            'sync_error' => null,
            'updated_at' => now(),
        ]);

        SyncSapImportToApmJob::dispatch($importId);

        return [
            'import_id' => $importId,
            'apm_updated' => 0,
            'sync_status' => 'pending',
        ];
    }

    /**
     * Run the APM sync for an import (called by the queue worker).
     *
     * @return array{import_id: int, apm_updated: int, sync_status: string}
     */
    public function performApmSync(int $importId): array
    {
        $import = DB::table('fin_sap_imports')->where('id', $importId)->first();
        if (! $import) {
            throw new RuntimeException('Import not found.');
        }

        $totals = DB::table('fin_sap_fund_centers')
            ->where('import_id', $importId)
            ->get()
            ->map(fn ($r) => [
                'fund_center' => (string) $r->fund_center,
                'total_released_budget' => (float) $r->total_released_budget,
                'released_budget_balance' => (float) $r->released_budget_balance,
            ])
            ->all();

        $apmUpdated = 0;
        $syncStatus = 'synced';
        $syncError = null;

        try {
            $client = $this->apm ?? new ApmFundCodeSyncClient;
            $apmUpdated = $client->syncTotals((int) $import->budget_year, $totals);
        } catch (Throwable $e) {
            $syncStatus = 'sync_failed';
            $syncError = $e->getMessage();
            Log::error('SAP→APM sync failed', [
                'import_id' => $importId,
                'error' => $e->getMessage(),
            ]);
        }

        DB::table('fin_sap_imports')->where('id', $importId)->update([
            'apm_updated_count' => $apmUpdated,
            'sync_status' => $syncStatus,
            'sync_error' => $syncError,
            'updated_at' => now(),
        ]);

        return [
            'import_id' => $importId,
            'apm_updated' => $apmUpdated,
            'sync_status' => $syncStatus,
        ];
    }

    /**
     * Expense (GL) lines for a fund center from an import snapshot.
     *
     * @return list<array<string, mixed>>
     */
    public function fundCenterExpenses(string $fundCenter, ?int $importId = null): array
    {
        $fc = strtoupper(preg_replace('/\s+/', '', trim($fundCenter)) ?? '');
        if ($fc === '') {
            return [];
        }

        if ($importId === null) {
            $import = DB::table('fin_sap_imports')
                ->where('is_latest', true)
                ->orderByDesc('id')
                ->first();
            $importId = $import ? (int) $import->id : 0;
        }
        if ($importId < 1) {
            return [];
        }

        $hasBudgetCode = Schema::hasColumn('fin_sap_rows', 'budget_code');

        // Prefer denormalized expense flag when set; also accept any non-empty GL
        // that is not a Total row (covers imports before expense columns were backfilled).
        $rows = DB::table('fin_sap_rows')
            ->where('import_id', $importId)
            ->where('is_total_row', false)
            ->whereRaw('UPPER(fund_center) = ?', [$fc])
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $payload = [];
            if (is_string($row->payload ?? null) && $row->payload !== '') {
                try {
                    $decoded = json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR);
                    $payload = is_array($decoded) ? $decoded : [];
                } catch (Throwable) {
                    $payload = [];
                }
            }

            $glAccount = trim((string) ($row->gl_account ?? ''));
            if ($glAccount === '') {
                $glAccount = trim((string) ($payload['gl account'] ?? ''));
            }
            $glLower = strtolower($glAccount);
            $flaggedExpense = Schema::hasColumn('fin_sap_rows', 'is_expense_row')
                && (bool) ($row->is_expense_row ?? false);
            $looksLikeExpense = $glAccount !== '' && $glLower !== 'total';
            if (! $flaggedExpense && ! $looksLikeExpense) {
                continue;
            }

            $budgetCode = $hasBudgetCode ? trim((string) ($row->budget_code ?? '')) : '';
            if ($budgetCode === '') {
                $budgetCode = trim((string) ($payload['fund'] ?? ''));
            }
            $glDesc = $hasBudgetCode ? trim((string) ($row->gl_description ?? '')) : '';
            if ($glDesc === '') {
                $glDesc = trim((string) ($payload['gl description'] ?? ''));
            }
            $commitment = $hasBudgetCode ? (float) ($row->commitment ?? 0) : 0.0;
            if ($commitment == 0.0) {
                $commitment = $this->decimal($payload['commitment'] ?? 0);
            }
            $actual = $hasBudgetCode ? (float) ($row->actual_expenditure ?? 0) : 0.0;
            if ($actual == 0.0) {
                $actual = $this->decimal($payload['actual expenditure'] ?? 0);
            }

            $out[] = [
                'fund_center' => (string) ($row->fund_center ?? $fc),
                'budget_code' => $budgetCode !== '' ? $budgetCode : null,
                'gl_account' => $glAccount,
                'gl_description' => $glDesc !== '' ? $glDesc : null,
                'commitment' => $commitment,
                'actual_expenditure' => $actual,
            ];
        }

        return $out;
    }

    private function refreshLatestFlags(): void
    {
        DB::table('fin_sap_imports')->update(['is_latest' => false, 'updated_at' => now()]);

        $query = DB::table('fin_sap_imports');
        if (Schema::hasColumn('fin_sap_imports', 'period_ym')) {
            $query->orderByDesc('period_ym')->orderByDesc('id');
        } else {
            $query->orderByDesc('budget_year')->orderByDesc('id');
        }
        $latest = $query->first();
        if ($latest) {
            DB::table('fin_sap_imports')->where('id', $latest->id)->update([
                'is_latest' => true,
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizePeriodYm(?string $periodYm): string
    {
        $periodYm = trim((string) $periodYm);
        if ($periodYm === '') {
            return date('Y-m');
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodYm)) {
            throw new RuntimeException('Invalid period_ym. Use YYYY-MM.');
        }

        return $periodYm;
    }

    private function decimal(mixed $value): float
    {
        $clean = str_replace([',', ' '], '', (string) $value);
        if ($clean === '' || ! is_numeric($clean)) {
            return 0.0;
        }

        return (float) $clean;
    }
}
