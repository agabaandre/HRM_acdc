<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily pending approvals notifications at 09:00, 16:00, and 23:45
Schedule::command('notifications:daily-pending-approvals')
    ->dailyAt('09:00')
    ->timezone('Africa/Addis_Ababa') // Africa CDC timezone
    ->description('Send morning pending approvals notifications to all approvers')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:daily-pending-approvals')
    ->dailyAt('16:00')
    ->timezone('Africa/Addis_Ababa') // Africa CDC timezone
    ->description('Send evening pending approvals notifications to all approvers')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:daily-pending-approvals')
    ->dailyAt('23:45')
    ->timezone('Africa/Addis_Ababa') // Africa CDC timezone
    ->description('Send test pending approvals notifications to all approvers')
    ->withoutOverlapping()
    ->runInBackground();
