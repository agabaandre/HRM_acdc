<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DiskSpaceMonitorService;

class DiskSpaceMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:check-disk-space';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check server disk space and send notifications if needed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking disk space...');
        
        $monitorService = new DiskSpaceMonitorService();
        $diskSpace = $monitorService->getDiskSpace();
        
        if (!$diskSpace) {
            $this->error('Failed to get disk space information');
            return 1;
        }
        
        // Display current status
        $this->line("Total Space: {$diskSpace['total_formatted']}");
        $this->line("Used Space: {$diskSpace['used_formatted']}");
        $this->line("Free Space: {$diskSpace['free_formatted']}");
        $this->line("Usage: {$diskSpace['usage_percent']}%");
        
        // Check status
        $status = $diskSpace['status'];
        if ($status === 'critical') {
            $this->error("CRITICAL: Disk usage is {$diskSpace['usage_percent']}%");
        } elseif ($status === 'warning') {
            $this->warn("WARNING: Disk usage is {$diskSpace['usage_percent']}%");
        } else {
            $this->info("OK: Disk usage is {$diskSpace['usage_percent']}%");
        }
        
        // Check and send notifications if needed
        $notificationSent = $monitorService->checkAndNotify();
        
        if ($notificationSent) {
            $this->info('Notification email sent to administrators');
        }
        
        return 0;
    }
}

