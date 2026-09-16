<?php

namespace App\Console\Commands;

use App\Services\ExcelRiskImportService;
use Illuminate\Console\Command;

class ImportRiskExcelCommand extends Command
{
    protected $signature = 'risk:import-excel
                            {path? : Path to the Risk Register xlsx}
                            {--fresh : Truncate rr_risks / rr_risk_owners before import}';

    protected $description = 'One-time import of Africa CDC Risk Register Excel into rr_risks';

    public function handle(ExcelRiskImportService $importService): int
    {
        $path = $this->argument('path') ?: (string) config('risk-register.excel_default_path');
        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            \Illuminate\Support\Facades\DB::table('rr_risk_owners')->delete();
            \Illuminate\Support\Facades\DB::table('rr_risks')->delete();
            $this->warn('Cleared existing rr_risks / rr_risk_owners.');
        }

        $this->info('Importing from: '.$path);
        $result = $importService->import($path);
        $this->table(
            ['imported', 'unmatched', 'owners_defaulted', 'skipped'],
            [[$result['imported'], $result['unmatched'], $result['owners_defaulted'], $result['skipped']]]
        );

        return self::SUCCESS;
    }
}
