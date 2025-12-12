<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;
use Carbon\Carbon;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database 
                            {--type=daily : Backup type (daily or monthly)}
                            {--cleanup : Run cleanup after backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        
        if (!in_array($type, ['daily', 'monthly'])) {
            $this->error('Invalid backup type. Use "daily" or "monthly"');
            return 1;
        }
        
        $this->info("Creating {$type} database backup...");
        
        $backupService = new BackupService();
        $result = $backupService->createBackup($type);
        
        if ($result) {
            $this->info("Backup created successfully!");
            $this->line("File: {$result['filename']}");
            $this->line("Size: " . $this->formatBytes($result['size']));
            $this->line("Path: {$result['path']}");
            
            // Run cleanup if requested
            if ($this->option('cleanup')) {
                $this->info("Running cleanup...");
                $cleanupResult = $backupService->cleanupOldBackups();
                
                if ($cleanupResult) {
                    $this->info("Cleanup completed!");
                    $this->line("Deleted: {$cleanupResult['deleted_count']} files");
                    $this->line("Freed: " . $this->formatBytes($cleanupResult['deleted_size']));
                }
            }
            
            return 0;
        } else {
            $this->error("Backup failed!");
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

