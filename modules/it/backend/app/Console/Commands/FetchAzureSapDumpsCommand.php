<?php

namespace App\Console\Commands;

use App\Services\AzureSapDumpSyncService;
use Illuminate\Console\Command;

class FetchAzureSapDumpsCommand extends Command
{
    protected $signature = 'finance:fetch-azure-sap-dumps
                            {--keep : Keep remote blobs after a successful import}
                            {--name= : Fetch and process only this blob name}';

    protected $description = 'Fetch SAP dump files from Azure Blob, import them, then delete remote files on success (runs every 4 hours)';

    public function handle(AzureSapDumpSyncService $sync): int
    {
        $delete = ! (bool) $this->option('keep');
        $only = $this->option('name');
        $onlyName = is_string($only) && trim($only) !== '' ? trim($only) : null;

        $this->info('Checking Azure SAP storage for new dumps…');
        $result = $sync->sync($delete, $onlyName);

        if (! $result['configured']) {
            $this->warn('Azure SAP storage is not configured — skipped.');

            return self::SUCCESS;
        }

        $this->line('Files seen: '.$result['files_seen']);
        foreach ($result['imported'] as $row) {
            $this->info(sprintf(
                '  imported %s (id=%d, period=%s, rows=%d)%s',
                $row['name'],
                $row['import_id'],
                $row['period_ym'],
                $row['rows'],
                $row['deleted'] ? ', deleted remote' : '',
            ));
        }
        foreach ($result['skipped'] as $row) {
            $this->line('  skipped '.$row['name'].' — '.$row['reason']);
        }
        foreach ($result['errors'] as $row) {
            $label = $row['name'] !== '' ? $row['name'] : '(sync)';
            $this->error('  error '.$label.' — '.$row['message']);
        }

        $this->info(sprintf(
            'Done. Imported %d, deleted %d, errors %d.',
            count($result['imported']),
            count($result['deleted']),
            count($result['errors']),
        ));

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
