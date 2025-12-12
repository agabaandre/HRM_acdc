<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Exception;

class BackupService
{
    protected $config;
    protected $storagePath;

    public function __construct()
    {
        $this->config = config('backup');
        $this->storagePath = $this->config['storage_path'];
        
        // Ensure backup directory exists
        if (!File::isDirectory($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    /**
     * Create a database backup
     * 
     * @param string $type 'daily' or 'monthly'
     * @return array|false Backup file info or false on failure
     */
    public function createBackup($type = 'daily')
    {
        try {
            $dbConfig = $this->config['database'];
            
            // Generate backup filename
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "backup_{$type}_{$timestamp}.sql";
            $filePath = $this->storagePath . '/' . $filename;
            
            // Build mysqldump command (password without space after -p for security)
            $command = sprintf(
                'mysqldump -h %s -P %d -u %s -p%s %s > %s 2>&1',
                escapeshellarg($dbConfig['host']),
                $dbConfig['port'],
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                escapeshellarg($dbConfig['database']),
                escapeshellarg($filePath)
            );
            
            // Alternative: Use environment variable for password (more secure)
            // Putenv('MYSQL_PWD=' . $dbConfig['password']);
            // $command = sprintf(
            //     'mysqldump -h %s -P %d -u %s %s > %s 2>&1',
            //     escapeshellarg($dbConfig['host']),
            //     $dbConfig['port'],
            //     escapeshellarg($dbConfig['username']),
            //     escapeshellarg($dbConfig['database']),
            //     escapeshellarg($filePath)
            // );
            
            // Execute backup
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                Log::error('Database backup failed', [
                    'error' => implode("\n", $output),
                    'return_code' => $returnVar
                ]);
                return false;
            }
            
            // Compress if enabled
            if ($this->config['compression']['enabled']) {
                $filePath = $this->compressBackup($filePath);
                $filename = basename($filePath);
            }
            
            $fileSize = File::size($filePath);
            
            Log::info('Database backup created successfully', [
                'file' => $filename,
                'size' => $this->formatBytes($fileSize),
                'type' => $type
            ]);
            
            // Upload to OneDrive if enabled
            if ($this->config['onedrive']['enabled']) {
                $this->uploadToOneDrive($filePath, $filename);
            }
            
            // Send notification if enabled
            if ($this->config['notification']['enabled']) {
                $this->sendNotification($filename, $fileSize, true);
            }
            
            return [
                'filename' => $filename,
                'path' => $filePath,
                'size' => $fileSize,
                'type' => $type,
                'created_at' => Carbon::now()->toDateTimeString()
            ];
            
        } catch (Exception $e) {
            Log::error('Database backup exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($this->config['notification']['enabled']) {
                $this->sendNotification('', 0, false, $e->getMessage());
            }
            
            return false;
        }
    }

    /**
     * Compress backup file
     */
    protected function compressBackup($filePath)
    {
        $format = $this->config['compression']['format'];
        
        if ($format === 'gzip') {
            $command = "gzip -f " . escapeshellarg($filePath);
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0) {
                $filePath = $filePath . '.gz';
            }
        } elseif ($format === 'zip') {
            $zipPath = $filePath . '.zip';
            $command = "zip -j " . escapeshellarg($zipPath) . " " . escapeshellarg($filePath);
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0) {
                File::delete($filePath);
                $filePath = $zipPath;
            }
        }
        
        return $filePath;
    }

    /**
     * Upload backup to OneDrive
     */
    protected function uploadToOneDrive($filePath, $filename)
    {
        try {
            $onedriveService = new OneDriveService();
            $result = $onedriveService->uploadFile($filePath, $filename);
            
            if ($result) {
                Log::info('Backup uploaded to OneDrive successfully', [
                    'file' => $filename
                ]);
            } else {
                Log::warning('Failed to upload backup to OneDrive', [
                    'file' => $filename
                ]);
            }
            
            return $result;
        } catch (Exception $e) {
            Log::error('OneDrive upload exception', [
                'error' => $e->getMessage(),
                'file' => $filename
            ]);
            return false;
        }
    }

    /**
     * Clean up old backups based on retention policy
     */
    public function cleanupOldBackups()
    {
        try {
            $files = File::files($this->storagePath);
            $deletedCount = 0;
            $deletedSize = 0;
            
            $dailyRetention = $this->config['retention']['daily_days'];
            $monthlyRetention = $this->config['retention']['monthly_months'];
            
            $cutoffDate = Carbon::now()->subDays($dailyRetention);
            $monthlyCutoffDate = Carbon::now()->subMonths($monthlyRetention);
            
            // Group files by type and date
            $dailyBackups = [];
            $monthlyBackups = [];
            
            foreach ($files as $file) {
                $filename = $file->getFilename();
                
                if (preg_match('/backup_daily_(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                    $date = Carbon::parse($matches[1]);
                    $dailyBackups[] = [
                        'file' => $file,
                        'date' => $date
                    ];
                } elseif (preg_match('/backup_monthly_(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                    $date = Carbon::parse($matches[1]);
                    $monthlyBackups[] = [
                        'file' => $file,
                        'date' => $date
                    ];
                }
            }
            
            // Clean up daily backups older than retention period
            foreach ($dailyBackups as $backup) {
                if ($backup['date']->lt($cutoffDate)) {
                    $size = File::size($backup['file']);
                    File::delete($backup['file']);
                    $deletedCount++;
                    $deletedSize += $size;
                    
                    Log::info('Deleted old daily backup', [
                        'file' => $backup['file']->getFilename(),
                        'date' => $backup['date']->toDateString()
                    ]);
                }
            }
            
            // Keep only one monthly backup per month, remove older ones
            $monthlyGroups = [];
            foreach ($monthlyBackups as $backup) {
                $key = $backup['date']->format('Y-m');
                if (!isset($monthlyGroups[$key])) {
                    $monthlyGroups[$key] = [];
                }
                $monthlyGroups[$key][] = $backup;
            }
            
            foreach ($monthlyGroups as $month => $backups) {
                // Sort by date descending
                usort($backups, function($a, $b) {
                    return $b['date']->gt($a['date']) ? 1 : -1;
                });
                
                // Keep only the most recent backup for each month
                // Delete the rest if they're older than retention period
                foreach ($backups as $index => $backup) {
                    if ($index > 0 || $backup['date']->lt($monthlyCutoffDate)) {
                        $size = File::size($backup['file']);
                        File::delete($backup['file']);
                        $deletedCount++;
                        $deletedSize += $size;
                        
                        Log::info('Deleted old monthly backup', [
                            'file' => $backup['file']->getFilename(),
                            'date' => $backup['date']->toDateString()
                        ]);
                    }
                }
            }
            
            Log::info('Backup cleanup completed', [
                'deleted_count' => $deletedCount,
                'deleted_size' => $this->formatBytes($deletedSize)
            ]);
            
            return [
                'deleted_count' => $deletedCount,
                'deleted_size' => $deletedSize
            ];
            
        } catch (Exception $e) {
            Log::error('Backup cleanup exception', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send notification email
     */
    protected function sendNotification($filename, $fileSize, $success, $error = null)
    {
        if (empty($this->config['notification']['email'])) {
            return;
        }
        
        try {
            $subject = $success 
                ? 'Database Backup Completed Successfully'
                : 'Database Backup Failed';
            
            $message = $success
                ? "Database backup completed successfully.\n\nFile: {$filename}\nSize: " . $this->formatBytes($fileSize)
                : "Database backup failed.\n\nError: " . ($error ?? 'Unknown error');
            
            // Use existing email service
            require_once app_path('ExchangeEmailService/ExchangeOAuth.php');
            $config = config('exchange-email');
            
            $oauth = new \AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth(
                $config['tenant_id'],
                $config['client_id'],
                $config['client_secret'],
                $config['redirect_uri'],
                $config['scope'],
                $config['auth_method']
            );
            
            $oauth->sendEmail(
                $this->config['notification']['email'],
                $subject,
                $message,
                false
            );
            
        } catch (Exception $e) {
            Log::error('Failed to send backup notification', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats()
    {
        try {
            $files = File::files($this->storagePath);
            $totalSize = 0;
            $dailyCount = 0;
            $monthlyCount = 0;
            
            foreach ($files as $file) {
                $totalSize += File::size($file);
                $filename = $file->getFilename();
                
                if (strpos($filename, 'backup_daily_') === 0) {
                    $dailyCount++;
                } elseif (strpos($filename, 'backup_monthly_') === 0) {
                    $monthlyCount++;
                }
            }
            
            return [
                'total_files' => count($files),
                'daily_backups' => $dailyCount,
                'monthly_backups' => $monthlyCount,
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'storage_path' => $this->storagePath
            ];
        } catch (Exception $e) {
            Log::error('Failed to get backup stats', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

