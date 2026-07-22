<?php

namespace App\Jobs;

use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskSetting;
use App\Services\BusinessUnitMailboxIntakeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PollBusinessUnitMailboxesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('helpdesk');
    }

    public function handle(BusinessUnitMailboxIntakeService $intake): void
    {
        if (! HelpdeskSetting::emailTicketIntakeEnabled()) {
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
                if (($result['created'] ?? 0) > 0 || ($result['errors'] ?? 0) > 0) {
                    Log::info('helpdesk.email_intake.poll', [
                        'business_unit_id' => $unit->id,
                        'mailbox' => $unit->support_mailbox,
                        'created' => $result['created'],
                        'skipped' => $result['skipped'],
                        'errors' => $result['errors'],
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
