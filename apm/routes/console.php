<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Job Management Commands
Artisan::command('jobs:test-daily-notifications', function () {
    $this->info('🧪 Testing daily pending approvals notifications...');
    $this->call('reminders:schedule', ['--test' => true]);
})->purpose('Test daily pending approvals notifications without sending emails');

Artisan::command('jobs:dispatch-daily-notifications', function () {
    $this->info('🚀 Dispatching daily pending approvals notification job...');
    $this->call('reminders:schedule', ['--force' => true]);
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

// Returned Memos Commands
Artisan::command('jobs:test-returned-memos', function () {
    $this->info('🧪 Testing returned memos notifications...');
    
    try {
        // Get all staff who have returned memos
        $staffIds = collect();
        
        $matrixStaffIds = \App\Models\Matrix::where('overall_status', 'returned')->pluck('staff_id')->unique();
        $specialMemoStaffIds = \App\Models\SpecialMemo::where('overall_status', 'returned')->pluck('responsible_person_id')->unique();
        $nonTravelStaffIds = \App\Models\NonTravelMemo::where('overall_status', 'returned')->pluck('staff_id')->unique();
        $singleMemoStaffIds = \App\Models\Activity::where('is_single_memo', true)->whereIn('overall_status', ['returned', 'draft'])->pluck('staff_id')->merge(\App\Models\Activity::where('is_single_memo', true)->whereIn('overall_status', ['returned', 'draft'])->pluck('responsible_person_id'))->unique();
        $serviceRequestStaffIds = \App\Models\ServiceRequest::where('overall_status', 'returned')->pluck('staff_id')->unique();
        $arfStaffIds = \App\Models\RequestARF::where('overall_status', 'returned')->pluck('staff_id')->unique();
        $changeRequestStaffIds = \App\Models\ChangeRequest::where('overall_status', 'returned')->pluck('responsible_person_id')->unique();
        
        $allStaffIds = $staffIds->merge($matrixStaffIds)->merge($specialMemoStaffIds)->merge($nonTravelStaffIds)->merge($singleMemoStaffIds)->merge($serviceRequestStaffIds)->merge($arfStaffIds)->merge($changeRequestStaffIds)->unique()->filter()->values()->toArray();
        
        $staff = \App\Models\Staff::whereIn('staff_id', $allStaffIds)->where('active', 1)->whereNotNull('work_email')->get()->toArray();
        
        $this->info("Found " . count($staff) . " staff with returned memos");
        
        foreach ($staff as $s) {
            $sessionData = ['staff_id' => $s['staff_id'], 'division_id' => $s['division_id'] ?? null, 'permissions' => []];
            $service = new \App\Services\ReturnedMemosService($sessionData);
            $stats = $service->getSummaryStats();
            
            if ($stats['total_returned'] > 0) {
                $this->line("  📧 {$s['fname']} {$s['lname']} - {$stats['total_returned']} returned items");
            }
        }
    } catch (\Exception $e) {
        $this->error('❌ Test failed: ' . $e->getMessage());
    }
})->purpose('Test returned memos notifications without sending emails');

Artisan::command('jobs:dispatch-returned-memos', function () {
    $this->info('🚀 Dispatching returned memos notification job...');
    
    try {
        dispatch(new \App\Jobs\SendReturnedMemosNotificationJob());
        $this->info('✅ Job dispatched successfully!');
    } catch (\Exception $e) {
        $this->error('❌ Failed: ' . $e->getMessage());
    }
})->purpose('Manually dispatch returned memos notification job');

// Instant Reminders Command - Registered in SendInstantRemindersCommand.php
// Scheduled tasks are now configured in app/Console/Kernel.php

// Database Backup Commands
Artisan::command('backup:stats', function () {
    $this->info('📊 Backup Statistics:');
    
    try {
        $service = new \App\Services\BackupService();
        $stats = $service->getBackupStats();
        
        if ($stats) {
            $this->line("  Total Files: {$stats['total_files']}");
            $this->line("  Daily Backups: {$stats['daily_backups']}");
            $this->line("  Monthly Backups: {$stats['monthly_backups']}");
            $this->line("  Total Size: {$stats['total_size_formatted']}");
            $this->line("  Storage Path: {$stats['storage_path']}");
        } else {
            $this->error('Failed to get backup statistics');
        }
    } catch (\Exception $e) {
        $this->error('Error: ' . $e->getMessage());
    }
})->purpose('Display backup statistics');

// Disk Space Monitoring Command
Artisan::command('backup:check-disk', function () {
    $this->info('🔍 Checking disk space...');
    $this->call('backup:check-disk-space');
})->purpose('Check server disk space and send notifications');

