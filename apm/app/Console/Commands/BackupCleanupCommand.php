<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;

class BackupCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old database backups based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Cleaning up old backups...");
        
        $backupService = new BackupService();
        $result = $backupService->cleanupOldBackups();
        
        if ($result) {
            $this->info("Cleanup completed!");
            $this->line("Deleted: {$result['deleted_count']} files");
            $this->line("Freed: " . $this->formatBytes($result['deleted_size']));
            return 0;
        } else {
            $this->error("Cleanup failed!");
            return 1;
        }
    }
    
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

