<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        $retentionDays = $this->option('days') ?: config('audit-logger.retention.days', 365);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $this->info("Cleaning up audit logs older than {$retentionDays} days...");
        $this->info("Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");

        // Get all audit tables
        $auditTables = $this->getAuditTables();
        
        if (empty($auditTables)) {
            $this->info('No audit tables found.');
            return 0;
        }

        $totalDeleted = 0;
        
        foreach ($auditTables as $table) {
            $this->info("Processing table: {$table}");
            
            // Count logs to be deleted
            $logsToDelete = DB::table($table)->where('created_at', '<', $cutoffDate)->count();
            
            if ($logsToDelete === 0) {
                $this->info("  No logs to delete in {$table}");
                continue;
            }

            $this->info("  Found {$logsToDelete} audit logs to delete in {$table}");

            if ($this->confirm("Do you want to delete {$logsToDelete} audit logs from {$table}?")) {
                $deletedCount = DB::table($table)->where('created_at', '<', $cutoffDate)->delete();
                $totalDeleted += $deletedCount;
                
                $this->info("  Successfully deleted {$deletedCount} audit logs from {$table}");
            }
        }

        if ($totalDeleted > 0) {
            $this->info("Total audit logs deleted: {$totalDeleted}");
            
            // Log the cleanup action
            \Log::info("Audit logs cleanup completed", [
                'deleted_count' => $totalDeleted,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
                'tables_processed' => $auditTables
            ]);
        } else {
            $this->info('No audit logs were deleted.');
        }
        
        return 0;
    }

    /**
     * Get all audit tables from the database.
     */
    private function getAuditTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $auditTables = [];
        
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            if (strpos($tableName, 'audit_') === 0 && strpos($tableName, '_logs') !== false) {
                $auditTables[] = $tableName;
            }
        }
        
        return $auditTables;
    }
}
