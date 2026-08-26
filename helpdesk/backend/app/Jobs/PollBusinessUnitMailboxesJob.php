<?php

namespace App\Jobs;

use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskSetting;
use App\Services\BusinessUnitMailboxIntakeService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs inline from the scheduler (not queued) so mailbox intake does not depend
 * on the helpdesk queue worker. Preview is HTTP; logging must be equally direct.
 */
class PollBusinessUnitMailboxesJob
{
    use Dispatchable, SerializesModels;

    public int $timeout = 300;

    public function handle(BusinessUnitMailboxIntakeService $intake): void
    {
        if (! HelpdeskSetting::emailTicketIntakeEnabled()) {
            Log::info('helpdesk.email_intake.skipped', ['reason' => 'master_disabled']);

            return;
        }

        $units = HelpdeskBusinessUnit::query()
            ->where('is_active', true)
            ->where('email_intake_enabled', true)
            ->whereNotNull('support_mailbox')
            ->where('support_mailbox', '!=', '')
            ->orderBy('id')
            ->get();

        foreach ($units as $unit) {
            try {
                $result = $intake->pollUnit($unit);
                if (($result['created'] ?? 0) > 0 || ($result['errors'] ?? 0) > 0 || ($result['skipped'] ?? 0) > 0 || ($result['reason'] ?? null)) {
                    Log::info('helpdesk.email_intake.poll', [
                        'business_unit_id' => $unit->id,
                        'mailbox' => $unit->support_mailbox,
                        'created' => $result['created'],
                        'skipped' => $result['skipped'],
                        'errors' => $result['errors'],
                        'reason' => $result['reason'] ?? null,
                        'skipped_items' => $result['skipped_items'] ?? [],
                        'created_items' => $result['created_items'] ?? [],
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('helpdesk.email_intake.bu_failed', [
                    'business_unit_id' => $unit->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
