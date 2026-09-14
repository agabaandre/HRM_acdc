<?php

use App\Jobs\AgentOpenTicketReminderJob;
use App\Jobs\AutoCloseResolvedTicketsJob;
use App\Jobs\EmailMonthlyAgentReportsJob;
use App\Jobs\GenerateMonthlyAgentReportsJob;
use App\Jobs\LicenseExpiryAlertJob;
use App\Jobs\PollBusinessUnitMailboxesJob;
use App\Jobs\PurgeOldAgentReportsJob;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'sso/accept',
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\AssignCorrelationId::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Previous month: generate on the 1st, email shortly after (queued on helpdesk worker).
        $schedule->job(new GenerateMonthlyAgentReportsJob)->monthlyOn(1, '02:00');
        $schedule->job(new EmailMonthlyAgentReportsJob)->monthlyOn(1, '08:00');
        $schedule->job(new PurgeOldAgentReportsJob)->dailyAt('03:15');
        $schedule->job(new AutoCloseResolvedTicketsJob)->dailyAt('04:00');
        $schedule->job(new AgentOpenTicketReminderJob)->dailyAt('08:30');
        $schedule->job(new LicenseExpiryAlertJob)->dailyAt('09:00');
        $schedule->job(new PollBusinessUnitMailboxesJob)
            ->everyMinute()
            ->withoutOverlapping(5);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
