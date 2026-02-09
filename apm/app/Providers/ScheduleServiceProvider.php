<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $this->schedule($schedule);
        });
    }

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Set timezone to GMT+3 (East Africa Time)
        $schedule->timezone('Africa/Addis_Ababa');
        
        // Early morning sync - 6:00 AM GMT+3
        $schedule->command('directorates:sync')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Directorates sync failed at scheduled time');
            });
            
        $schedule->command('divisions:sync')
            ->dailyAt('06:05')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Divisions sync failed at scheduled time');
            });
            
        $schedule->command('staff:sync')
            ->dailyAt('06:10')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Staff sync failed at scheduled time');
            });
        
        // Late night sync - 11:00 PM GMT+3
        $schedule->command('directorates:sync')
            ->dailyAt('23:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Directorates sync failed at scheduled time');
            });
            
        $schedule->command('divisions:sync')
            ->dailyAt('23:05')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Divisions sync failed at scheduled time');
            });
            
        $schedule->command('staff:sync')
            ->dailyAt('23:10')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Staff sync failed at scheduled time');
            });
        
        // Daily pending approvals notifications
        $schedule->command('reminders:schedule')
            ->dailyAt('09:00')
            ->description('Send morning pending approvals notifications to all approvers')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Morning pending approvals notification failed');
            });
            
        // Midday pending approvals notifications
        $schedule->command('reminders:schedule')
            ->dailyAt('12:00')
            ->description('Send midday pending approvals notifications to all approvers')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Midday pending approvals notification failed');
            });
            
        $schedule->command('reminders:schedule')
            ->dailyAt('16:00')
            ->description('Send evening pending approvals notifications to all approvers')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Evening pending approvals notification failed');
            });
            
        // Daily returned memos notifications
        $schedule->command('reminders:returned-memos')
            ->dailyAt('08:00')
            ->description('Send morning returned memos notifications to all staff')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Morning returned memos notification failed');
            });
            
        $schedule->command('reminders:returned-memos')
            ->dailyAt('13:00')
            ->description('Send midday returned memos notifications to all staff')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Midday returned memos notification failed');
            });
            
        $schedule->command('reminders:returned-memos')
            ->dailyAt('17:00')
            ->description('Send evening returned memos notifications to all staff')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Evening returned memos notification failed');
            });

        // Deactivate fund codes from previous years on the first day of each new year
        $schedule->command('fund-codes:deactivate-past-year')
            ->yearlyOn(1, 1, '01:00')
            ->description('Set is_active=0 for fund codes with year < current year')
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::error('fund-codes:deactivate-past-year failed at scheduled time');
            });
    }
}
