<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AuditLog;
use Carbon\Carbon;

class CleanupAuditLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:cleanup {--days= : Number of days to retain logs (overrides config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old audit logs based on retention period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $retentionDays = $this->option('days') ?: config('audit.retention_days', 60);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $this->info("Cleaning up audit logs older than {$retentionDays} days...");
        $this->info("Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");

        // Count logs to be deleted
        $logsToDelete = AuditLog::where('created_at', '<', $cutoffDate)->count();
        
        if ($logsToDelete === 0) {
            $this->info('No audit logs found to clean up.');
            return 0;
        }

        $this->info("Found {$logsToDelete} audit logs to delete.");

        if ($this->confirm("Do you want to delete {$logsToDelete} audit logs?")) {
            $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();
            
            $this->info("Successfully deleted {$deletedCount} audit logs.");
            
            // Log the cleanup action
            \Log::info("Audit logs cleanup completed", [
                'deleted_count' => $deletedCount,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
            ]);
            
            return 0;
        } else {
            $this->info('Cleanup cancelled.');
            return 1;
        }
    }
}
