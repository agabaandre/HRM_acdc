<?php

namespace Modules\Workplan\Console;

use Illuminate\Console\Command;
use Modules\Workplan\Services\PraWorkplanSyncService;

class SyncPraWorkplanCommand extends Command
{
    protected $signature = 'workplan:sync-pra
                            {--year= : Fiscal year (default: PRA_WORKPLAN_FISCAL_YEAR or current year)}
                            {--division=* : PRA division code(s), e.g. MIS (default: all mapped short codes)}';

    protected $description = 'Fetch Africa CDC PRA public workplan and upsert into workplan_tasks / work_planner_tasks';

    public function handle(PraWorkplanSyncService $sync): int
    {
        $year = $this->option('year') !== null && $this->option('year') !== ''
            ? (int) $this->option('year')
            : null;
        $divisions = $this->option('division');
        $divisionList = is_array($divisions) && $divisions !== []
            ? array_values(array_filter(array_map('strval', $divisions)))
            : null;

        $this->info('Syncing PRA workplan…');

        try {
            $result = $sync->sync($year, $divisionList);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Fiscal year: '.$result['fiscal_year']);
        foreach ($result['divisions'] as $row) {
            $this->line(sprintf(
                '  %s → division_id %d — indicators %d, activities %d (API %d)',
                $row['pra_code'],
                $row['division_id'],
                $row['indicators'],
                $row['activities'],
                $row['api_indicators'],
            ));
        }
        foreach ($result['skipped'] as $msg) {
            $this->warn('  skip: '.$msg);
        }
        foreach ($result['errors'] as $msg) {
            $this->error('  error: '.$msg);
        }

        $this->info(sprintf(
            'Done. Upserted %d indicators, %d sub-activities.',
            $result['indicators_upserted'],
            $result['activities_upserted'],
        ));

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
