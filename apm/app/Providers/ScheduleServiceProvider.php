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
        $schedule->timezone('Africa/Nairobi');
        
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
        $schedule->command('notifications:daily-pending-approvals')
            ->dailyAt('09:00')
            ->timezone('Africa/Addis_Ababa')
            ->description('Send morning pending approvals notifications to all approvers')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Morning pending approvals notification failed');
            });
            
        $schedule->command('notifications:daily-pending-approvals')
            ->dailyAt('16:00')
            ->timezone('Africa/Addis_Ababa')
            ->description('Send evening pending approvals notifications to all approvers')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Evening pending approvals notification failed');
            });
            
        $schedule->command('notifications:daily-pending-approvals')
            ->dailyAt('01:07')
            ->timezone('Africa/Addis_Ababa')
            ->description('Send test pending approvals notifications to all approvers')
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                Log::error('Test pending approvals notification failed');
            });
    }
}
