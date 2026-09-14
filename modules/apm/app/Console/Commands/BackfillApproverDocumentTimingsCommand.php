<?php

namespace App\Console\Commands;

use App\Models\ApprovalTrail;
use App\Models\OtherMemoApprovalTrail;
use App\Services\ApproverDocumentTimingService;
use Illuminate\Console\Command;

class BackfillApproverDocumentTimingsCommand extends Command
{
    protected $signature = 'apm:backfill-approver-document-timings
                            {--chunk=300 : Rows per batch}
                            {--other-memo-only : Only rebuild Other Memo trails}
                            {--approval-only : Only rebuild unified approval_trails}';

    protected $description = 'Populate approver_document_timing_records from historical approve/reject actions';

    public function handle(ApproverDocumentTimingService $service): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $onlyOm = (bool) $this->option('other-memo-only');
        $onlyMain = (bool) $this->option('approval-only');

        if (! $onlyOm) {
            $this->info('Backfilling approval_trails…');
            $count = 0;
            ApprovalTrail::query()
                ->where('is_archived', 0)
                ->whereIn('action', ['approved', 'rejected'])
                ->whereNotNull('forward_workflow_id')
                ->whereNotNull('approval_order')
                ->orderBy('id')
                ->chunkById($chunk, function ($trails) use ($service, &$count): void {
                    foreach ($trails as $trail) {
                        $service->recordFromApprovalTrailId((int) $trail->id);
                        $count++;
                    }
                });
            $this->info("Processed {$count} approval trail rows (skipped duplicates automatically).");
        }

        if (! $onlyMain) {
            $this->info('Backfilling other_memos_approval_trails…');
            $countOm = 0;
            OtherMemoApprovalTrail::query()
                ->where('action', 'approved')
                ->where('approval_order', '>=', 1)
                ->orderBy('id')
                ->chunkById($chunk, function ($trails) use ($service, &$countOm): void {
                    foreach ($trails as $trail) {
                        $service->recordFromOtherMemoTrailId((int) $trail->id);
                        $countOm++;
                    }
                });
            $this->info("Processed {$countOm} other memo approval rows.");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
