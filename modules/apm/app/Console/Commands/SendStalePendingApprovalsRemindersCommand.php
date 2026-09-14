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

    protected $description = 'Notify approvers whose queue has items pending at their level for more than approval_warning_days (default 7); escalate to creator, HOD, and senior/configured approvers; run daily at 11:00 until cleared';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            return $this->runDryRun();
        }

        $this->info('Running stale pending approvals reminder job (reminders + escalation)…');
        dispatch_sync(new SendStalePendingApprovalsReminderJob());
        $this->info('Done.');

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

        $this->newLine();
        $this->info('Dry run — escalation recipients (creator / HOD / senior or configured approvers):');
        $preview = app(\App\Services\StaleApprovalEscalationService::class)->previewEscalationRecipients();
        if ($preview === []) {
            $this->info('  No escalation recipients for stale items.');
        } else {
            foreach ($preview as $row) {
                $reasons = implode(', ', $row['reasons'] ?? []);
                $this->line("  • {$row['name']} <{$row['email']}>: {$row['item_count']} item(s) [{$reasons}]");
            }
        }

        return self::SUCCESS;
    }
}
