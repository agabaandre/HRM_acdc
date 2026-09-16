<?php

namespace App\Services;

use App\Support\SapExportParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     *   budget_year: int
     * }
     */
    public function import(string $path, string $originalFilename, ?int $userId = null): array
    {
        $parsed = $this->parser->parse($path);
        $budgetYear = (int) date('Y');

        $importId = (int) DB::transaction(function () use ($parsed, $budgetYear, $originalFilename, $userId) {
            DB::table('fin_sap_imports')
                ->where('budget_year', $budgetYear)
                ->where('is_latest', true)
                ->update(['is_latest' => false]);

            $importId = DB::table('fin_sap_imports')->insertGetId([
                'uploaded_by' => $userId,
                'original_filename' => $originalFilename,
                'budget_year' => $budgetYear,
                'row_count' => count($parsed['rows']),
                'total_row_count' => count($parsed['totals']),
                'apm_updated_count' => 0,
                'sync_status' => 'pending',
                'sync_error' => null,
                'is_latest' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rowInserts = [];
            foreach ($parsed['rows'] as $row) {
                $gl = strtolower(trim((string) ($row['gl account'] ?? '')));
                $fc = trim((string) ($row['fund center'] ?? ''));
                $rowInserts[] = [
                    'import_id' => $importId,
                    'budget_year' => $budgetYear,
                    'fund_center' => $fc !== '' ? $fc : null,
                    'gl_account' => ($row['gl account'] ?? null) !== null ? (string) $row['gl account'] : null,
                    'is_total_row' => $gl === 'total',
                    'payload' => json_encode($row, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($rowInserts, 200) as $chunk) {
                DB::table('fin_sap_rows')->insert($chunk);
            }

            $centerInserts = [];
            foreach ($parsed['totals'] as $total) {
                $centerInserts[] = [
                    'import_id' => $importId,
                    'budget_year' => $budgetYear,
                    'fund_center' => $total['fund_center'],
                    'total_released_budget' => $total['total_released_budget'],
                    'released_budget_balance' => $total['released_budget_balance'],
                    'payload' => json_encode($total['raw'] ?? [], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($centerInserts, 200) as $chunk) {
                DB::table('fin_sap_fund_centers')->insert($chunk);
            }

            return $importId;
        });

        $apmUpdated = 0;
        $syncStatus = 'synced';
        $syncError = null;

        try {
            $client = $this->apm ?? new ApmFundCodeSyncClient;
            $apmUpdated = $client->syncTotals($budgetYear, $parsed['totals']);
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
            'rows' => count($parsed['rows']),
            'totals' => count($parsed['totals']),
            'apm_updated' => $apmUpdated,
            'sync_status' => $syncStatus,
            'budget_year' => $budgetYear,
        ];
    }

    /**
     * @return array{import_id: int, apm_updated: int, sync_status: string}
     */
    public function retrySync(int $importId): array
    {
        $import = DB::table('fin_sap_imports')->where('id', $importId)->first();
        if (! $import) {
            throw new \RuntimeException('Import not found.');
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
            Log::error('SAP→APM retry sync failed', [
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
}
