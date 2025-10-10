<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Job Management Commands
Artisan::command('jobs:test-daily-notifications', function () {
    $this->info('🧪 Testing daily pending approvals notifications...');
    $this->call('notifications:daily-pending-approvals', ['--test' => true]);
})->purpose('Test daily pending approvals notifications without sending emails');

Artisan::command('jobs:dispatch-daily-notifications', function () {
    $this->info('🚀 Dispatching daily pending approvals notification job...');
    dispatch(new \App\Jobs\SendDailyPendingApprovalsNotificationJob());
    $this->info('✅ Job dispatched successfully!');
})->purpose('Manually dispatch daily pending approvals notification job');

Artisan::command('jobs:process-queue', function () {
    $this->info('⚙️ Processing queue jobs...');
    $this->call('queue:work', ['--once' => true, '--verbose' => true]);
})->purpose('Process one job from the queue');

Artisan::command('jobs:monitor-queue', function () {
    $this->info('📊 Queue Status:');
    $jobsCount = \DB::table('jobs')->count();
    $failedCount = \DB::table('failed_jobs')->count();
    $this->line("  • Jobs in queue: {$jobsCount}");
    $this->line("  • Failed jobs: {$failedCount}");
    
    if ($jobsCount > 0) {
        $this->info('🔄 Processing jobs...');
        $this->call('queue:work', ['--once' => true, '--verbose' => true]);
    } else {
        $this->info('✅ No jobs in queue');
    }
})->purpose('Monitor and process queue jobs');

// System Health Commands
Artisan::command('system:health-check', function () {
    $this->info('🏥 System Health Check:');
    
    // Database connectivity
    try {
        \DB::connection()->getPdo();
        $this->line('  ✅ Database: Connected');
    } catch (\Exception $e) {
        $this->line('  ❌ Database: Failed - ' . $e->getMessage());
    }
    
    // Queue tables
    $jobsExists = \Schema::hasTable('jobs');
    $failedJobsExists = \Schema::hasTable('failed_jobs');
    $this->line('  ' . ($jobsExists ? '✅' : '❌') . ' Jobs table: ' . ($jobsExists ? 'Exists' : 'Missing'));
    $this->line('  ' . ($failedJobsExists ? '✅' : '❌') . ' Failed jobs table: ' . ($failedJobsExists ? 'Exists' : 'Missing'));
    
    // Queue status
    $jobsCount = \DB::table('jobs')->count();
    $failedCount = \DB::table('failed_jobs')->count();
    $this->line("  📊 Queue: {$jobsCount} jobs, {$failedCount} failed");
    
    // Storage
    $storageWritable = is_writable(storage_path());
    $this->line('  ' . ($storageWritable ? '✅' : '❌') . ' Storage: ' . ($storageWritable ? 'Writable' : 'Not writable'));
    
    // Logs
    $logFile = storage_path('logs/laravel.log');
    $logExists = file_exists($logFile);
    $this->line('  ' . ($logExists ? '✅' : '❌') . ' Logs: ' . ($logExists ? 'Available' : 'Missing'));
    
})->purpose('Check system health and configuration');

// Queue Management Commands
Artisan::command('queue:clear-failed', function () {
    $count = \DB::table('failed_jobs')->count();
    if ($count > 0) {
        \DB::table('failed_jobs')->truncate();
        $this->info("✅ Cleared {$count} failed jobs");
    } else {
        $this->info('✅ No failed jobs to clear');
    }
})->purpose('Clear all failed jobs from the queue');

Artisan::command('queue:retry-failed', function () {
    $count = \DB::table('failed_jobs')->count();
    if ($count > 0) {
        $this->call('queue:retry', ['id' => 'all']);
        $this->info("🔄 Retrying {$count} failed jobs");
    } else {
        $this->info('✅ No failed jobs to retry');
    }
})->purpose('Retry all failed jobs');

// Notification Testing Commands
Artisan::command('notifications:test-email', function () {
    $this->info('📧 Testing email notification system...');
    $this->call('test:email');
})->purpose('Test email notification system');

Artisan::command('notifications:test-all', function () {
    $this->info('🧪 Testing all notification systems...');
    $this->call('test:notification');
})->purpose('Test all notification systems');

