<?php

use App\Jobs\EmailMonthlyAgentReportsJob;
use App\Jobs\GenerateMonthlyAgentReportsJob;
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
        $middleware->api(prepend: [
            \App\Http\Middleware\AssignCorrelationId::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Previous month: generate on the 1st, email shortly after (queued on helpdesk worker).
        $schedule->job(new GenerateMonthlyAgentReportsJob)->monthlyOn(1, '02:00');
        $schedule->job(new EmailMonthlyAgentReportsJob)->monthlyOn(1, '08:00');
        $schedule->job(new PurgeOldAgentReportsJob)->dailyAt('03:15');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
