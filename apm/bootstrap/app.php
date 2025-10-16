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
            ->dailyAt('16:00')
            ->withoutOverlapping()
            ->runInBackground();
            
        // Schedule returned memos reminders at 10 AM and 3 PM
        $schedule->command('reminders:returned-memos')
            ->dailyAt('10:00')
            ->withoutOverlapping()
            ->runInBackground();
            
        $schedule->command('reminders:returned-memos')
            ->dailyAt('15:00')
            ->withoutOverlapping()
            ->runInBackground();
    })->create();
