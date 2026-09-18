<?php

namespace Modules\Jobs\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Jobs\Console\AuditExtendedContractsCommand;
use Modules\Jobs\Console\ManageAccountsCommand;
use Modules\Jobs\Console\MarkDueContractsCommand;
use Modules\Jobs\Console\PerformanceApprovalReminderCommand;
use Modules\Jobs\Console\PerformanceNotificationsCommand;
use Modules\Jobs\Console\PruneUserLogsGetAccessCommand;
use Modules\Jobs\Console\SendInstantMailsCommand;
use Modules\Jobs\Console\SendMailsCommand;
use Modules\Jobs\Console\StaffBirthdayCommand;
use Modules\Jobs\Console\StaffProfileCompletionReminderCommand;
use Modules\Jobs\Services\StaffJobsScheduleService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class JobsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Jobs';

    protected string $nameLower = 'jobs';

    protected array $commands = [
        SendInstantMailsCommand::class,
        SendMailsCommand::class,
        PerformanceNotificationsCommand::class,
        PerformanceApprovalReminderCommand::class,
        MarkDueContractsCommand::class,
        AuditExtendedContractsCommand::class,
        StaffBirthdayCommand::class,
        StaffProfileCompletionReminderCommand::class,
        ManageAccountsCommand::class,
        PruneUserLogsGetAccessCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    protected function configureSchedules(Schedule $schedule): void
    {
        /** @var StaffJobsScheduleService $resolver */
        $resolver = $this->app->make(StaffJobsScheduleService::class);
        $cfg = $resolver->resolved();
        // Non-schedule keys (mail URLs, etc.) still come from config.
        $static = config('jobs.schedule', []);
        $cfg = array_merge($static, $cfg);

        if (! empty($cfg['send_instant_mails'])) {
            $schedule->command('jobs:send-instant-mails')
                ->everyMinute()
                ->withoutOverlapping()
                ->name('jobs-send-instant-mails');
        }

        $interval = (int) ($cfg['send_mails_interval_minutes'] ?? 0);
        if ($interval > 0) {
            $schedule->command('jobs:send-mails')
                ->cron('*/'.max(1, min(59, $interval)).' * * * *')
                ->withoutOverlapping()
                ->name('jobs-send-mails');
        }

        $this->dailyAt($schedule, 'jobs:performance-notifications', $cfg['performance_notifications'] ?? false);
        $this->dailyAt($schedule, 'jobs:performance-approval-reminder', $cfg['performance_approval_reminder'] ?? false);
        $this->dailyAt($schedule, 'jobs:mark-due-contracts', $cfg['mark_due_contracts'] ?? false);
        $this->dailyAt($schedule, 'jobs:audit-extended-contracts', $cfg['audit_extended_contracts'] ?? false);
        $this->dailyAt($schedule, 'jobs:staff-profile-completion-reminder', $cfg['staff_profile_completion_reminder'] ?? false);

        $bday = $cfg['staff_birthday'] ?? false;
        if (is_array($bday)) {
            $h = (int) ($bday['hour'] ?? 3);
            $m = (int) ($bday['minute'] ?? 0);
            $schedule->command('jobs:staff-birthday')
                ->dailyAt(sprintf('%02d:%02d', $h, $m))
                ->withoutOverlapping()
                ->name('jobs-staff-birthday');
            $schedule->command('jobs:staff-birthday')
                ->hourly()
                ->between('3:00', '9:59')
                ->withoutOverlapping()
                ->name('jobs-staff-birthday-catchup');
        }

        if (isset($cfg['manage_accounts_hourly_minute']) && $cfg['manage_accounts_hourly_minute'] !== null && $cfg['manage_accounts_hourly_minute'] !== '') {
            $minute = (int) $cfg['manage_accounts_hourly_minute'];
            $schedule->command('jobs:manage-accounts')
                ->cron("{$minute} * * * *")
                ->withoutOverlapping()
                ->name('jobs-manage-accounts');
        }

        $prune = $cfg['user_logs_prune_get_access'] ?? false;
        if (is_array($prune)) {
            $h = (int) ($prune['hour'] ?? 0);
            $m = (int) ($prune['minute'] ?? 0);
            $wd = (int) ($prune['weekday'] ?? 2);
            $schedule->command('jobs:prune-user-logs-get-access')
                ->weeklyOn($wd, sprintf('%02d:%02d', $h, $m))
                ->withoutOverlapping()
                ->name('jobs-prune-user-logs');
        }

        $pra = $cfg['sync_pra_workplan'] ?? false;
        if (is_array($pra)) {
            $h = (int) ($pra['hour'] ?? 0);
            $m = (int) ($pra['minute'] ?? 5);
            $schedule->command('workplan:sync-pra')
                ->dailyAt(sprintf('%02d:%02d', $h, $m))
                ->withoutOverlapping()
                ->name('jobs-workplan-sync-pra');
        }
    }

    /**
     * @param  array{hour?:int,minute?:int}|false  $spec
     */
    private function dailyAt(Schedule $schedule, string $command, mixed $spec): void
    {
        if (! is_array($spec)) {
            return;
        }
        $h = (int) ($spec['hour'] ?? 0);
        $m = (int) ($spec['minute'] ?? 0);
        $schedule->command($command)
            ->dailyAt(sprintf('%02d:%02d', $h, $m))
            ->withoutOverlapping()
            ->name(str_replace(':', '-', $command));
    }
}
