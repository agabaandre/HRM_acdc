<?php

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
        // Add session expiry check to web middleware group
        $middleware->web(append: [
            \App\Http\Middleware\CheckSessionExpiry::class,
        ]);
        $middleware->alias([
            'apm.api.context' => \App\Http\Middleware\SetApmApiUserContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        // Schedule instant reminders at 9 AM and 4 PM
        $schedule->command('reminders:schedule')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground();
            
        $schedule->command('reminders:schedule')
            ->dailyAt('12:00')
            ->withoutOverlapping()
            ->runInBackground();
            
        $schedule->command('reminders:schedule')
            ->dailyAt('16:00')
            ->withoutOverlapping()
            ->runInBackground();
            
        // Schedule returned memos reminders at 8 AM, 1 PM, and 5 PM
        $schedule->command('reminders:returned-memos')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->runInBackground();
            
        $schedule->command('reminders:returned-memos')
            ->dailyAt('13:00')
            ->withoutOverlapping()
            ->runInBackground();
            
        $schedule->command('reminders:returned-memos')
            ->dailyAt('17:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Sync users from staff app user table into apm_api_users (hourly)
        $schedule->command('users:sync')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    })->create();
