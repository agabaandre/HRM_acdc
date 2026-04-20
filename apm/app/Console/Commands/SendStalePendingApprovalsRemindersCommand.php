<?php

namespace App\Console\Commands;

use App\Jobs\SendStalePendingApprovalsReminderJob;
use App\Services\NotificationService;
use App\Services\PendingApprovalsService;
use Illuminate\Console\Command;

class SendStalePendingApprovalsRemindersCommand extends Command
{
    protected $signature = 'approvals:send-stale-pending-reminders
                            {--dry-run : List who would be emailed without dispatching}';

    protected $description = 'Notify approvers whose queue has items pending at their level for at least approval_warning_days (default 7)';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            return $this->runDryRun();
        }

        $this->info('Dispatching stale pending approvals reminder job…');
        dispatch(new SendStalePendingApprovalsReminderJob());
        $this->info('Done. Job queued.');

        return self::SUCCESS;
    }

    private function runDryRun(): int
    {
        $notificationService = new NotificationService();
        $approvers = $notificationService->getAllApprovers();

        $this->info('Dry run — approvers with stale items (would receive email + in-app notification):');
        $totalStale = 0;

        foreach ($approvers as $approver) {
            $svc = new PendingApprovalsService([
                'staff_id' => $approver['staff_id'],
                'division_id' => $approver['division_id'] ?? null,
                'permissions' => [],
                'name' => ($approver['fname'] ?? '') . ' ' . ($approver['lname'] ?? ''),
                'email' => $approver['work_email'] ?? '',
                'base_url' => config('app.url'),
            ]);
            $days = $svc->getApprovalWarningThresholdDays();
            $stale = $svc->getStalePendingItems($days);
            if (count($stale) === 0) {
                continue;
            }
            $c = count($stale);
            $totalStale += $c;
            $this->line("  • {$approver['fname']} {$approver['lname']} <{$approver['work_email']}>: {$c} stale (threshold {$days} days)");
        }

        if ($totalStale === 0) {
            $this->info('No approvers have items past the threshold.');
        } else {
            $this->info("Total stale items counted: {$totalStale}");
        }

        return self::SUCCESS;
    }
}
