<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\BackupDatabase;
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
     * @param string $type 'daily', 'monthly', or 'annual'
     * @param int|null $databaseId Specific database ID to backup, or null for all active databases
     * @return array|false Backup file info or false on failure
     */
    public function createBackup($type = 'daily', $databaseId = null)
    {
        try {
            // Get databases to backup
            $databases = [];
            
            if ($databaseId) {
                // Backup specific database
                $db = BackupDatabase::find($databaseId);
                if ($db && $db->is_active) {
                    $databases[] = $db;
                }
            } else {
                // Backup all active databases from database config
                $databases = BackupDatabase::getActiveDatabases();
                
                // If no databases in DB config, fallback to env config
                if ($databases->isEmpty()) {
                    $dbConfig = $this->config['database'];
                    if (!empty($dbConfig['database'])) {
                        // Create a temporary database object from config
                        $databases = collect([(object)[
                            'id' => 0,
                            'name' => $dbConfig['database'],
                            'display_name' => $dbConfig['database'],
                            'host' => $dbConfig['host'],
                            'port' => $dbConfig['port'],
                            'username' => $dbConfig['username'],
                            'password' => $dbConfig['password'],
                            'decrypted_password' => $dbConfig['password']
                        ]]);
                    }
                }
            }
            
            if ($databases->isEmpty()) {
                Log::warning('No databases configured for backup');
                return false;
            }
            
            $results = [];
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            
            foreach ($databases as $db) {
                $dbName = is_object($db) && isset($db->name) ? $db->name : $db['name'];
                $dbDisplayName = is_object($db) && isset($db->display_name) ? $db->display_name : ($db['display_name'] ?? $dbName);
                
                // Generate backup filename with database name
                $filename = "backup_{$type}_{$dbName}_{$timestamp}.sql";
                $filePath = $this->storagePath . '/' . $filename;
                
                // Get database credentials
                $host = is_object($db) && isset($db->host) ? $db->host : ($db['host'] ?? '127.0.0.1');
                $port = is_object($db) && isset($db->port) ? $db->port : ($db['port'] ?? 3306);
                $username = is_object($db) && isset($db->username) ? $db->username : $db['username'];
                
                // Get password - handle encrypted passwords from model
                if (is_object($db) && $db instanceof BackupDatabase) {
                    $password = $db->decrypted_password; // Accessor will decrypt automatically
                } elseif (is_object($db) && isset($db->decrypted_password)) {
                    $password = $db->decrypted_password;
                } else {
                    $password = $db['password'] ?? '';
                }
                
                // Build mysqldump command
                $command = sprintf(
                    'mysqldump -h %s -P %d -u %s -p%s %s > %s 2>&1',
                    escapeshellarg($host),
                    $port,
                    escapeshellarg($username),
                    escapeshellarg($password),
                    escapeshellarg($dbName),
                    escapeshellarg($filePath)
                );
                
                // Execute backup
                exec($command, $output, $returnVar);
                
                if ($returnVar !== 0) {
                    Log::error('Database backup failed', [
                        'database' => $dbName,
                        'error' => implode("\n", $output),
                        'return_code' => $returnVar
                    ]);
                    
                    $results[] = [
                        'database' => $dbDisplayName,
                        'success' => false,
                        'error' => implode("\n", $output)
                    ];
                    continue;
                }
                
                // Compress if enabled
                if ($this->config['compression']['enabled']) {
                    $filePath = $this->compressBackup($filePath);
                    $filename = basename($filePath);
                }
                
                $fileSize = File::size($filePath);
                
                Log::info('Database backup created successfully', [
                    'database' => $dbName,
                    'file' => $filename,
                    'size' => $this->formatBytes($fileSize),
                    'type' => $type
                ]);
                
                // Upload to OneDrive if enabled
                if ($this->config['onedrive']['enabled']) {
                    $this->uploadToOneDrive($filePath, $filename);
                }
                
                $results[] = [
                    'database' => $dbDisplayName,
                    'filename' => $filename,
                    'path' => $filePath,
                    'size' => $fileSize,
                    'success' => true
                ];
            }
            
            // Send notification if enabled
            if ($this->config['notification']['enabled']) {
                $successCount = count(array_filter($results, fn($r) => $r['success']));
                $totalCount = count($results);
                $totalSize = array_sum(array_column($results, 'size'));
                
                $message = "Backed up {$successCount}/{$totalCount} database(s).\n\n";
                foreach ($results as $result) {
                    if ($result['success']) {
                        $message .= "✓ {$result['database']}: {$result['filename']} (" . $this->formatBytes($result['size']) . ")\n";
                    } else {
                        $message .= "✗ {$result['database']}: Failed - " . ($result['error'] ?? 'Unknown error') . "\n";
                    }
                }
                
                $this->sendNotification(
                    $successCount === $totalCount ? "All backups completed" : "Partial backup completion",
                    $totalSize,
                    $successCount === $totalCount,
                    $successCount < $totalCount ? $message : null
                );
            }
            
            return [
                'results' => $results,
                'type' => $type,
                'created_at' => Carbon::now()->toDateTimeString(),
                'success_count' => count(array_filter($results, fn($r) => $r['success'])),
                'total_count' => count($results)
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
            $annualRetention = $this->config['retention']['annual_years'] ?? 1;
            
            $cutoffDate = Carbon::now()->subDays($dailyRetention);
            $monthlyCutoffDate = Carbon::now()->subMonths($monthlyRetention);
            $annualCutoffDate = Carbon::now()->subYears($annualRetention);
            
            // Group files by type and date
            $dailyBackups = [];
            $monthlyBackups = [];
            $annualBackups = [];
            
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
                } elseif (preg_match('/backup_annual_(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                    $date = Carbon::parse($matches[1]);
                    $annualBackups[] = [
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
            
            // Keep only one annual backup per year, remove older ones
            $annualGroups = [];
            foreach ($annualBackups as $backup) {
                $key = $backup['date']->format('Y');
                if (!isset($annualGroups[$key])) {
                    $annualGroups[$key] = [];
                }
                $annualGroups[$key][] = $backup;
            }
            
            foreach ($annualGroups as $year => $backups) {
                // Sort by date descending
                usort($backups, function($a, $b) {
                    return $b['date']->gt($a['date']) ? 1 : -1;
                });
                
                // Keep only the most recent backup for each year
                // Delete the rest if they're older than retention period
                foreach ($backups as $index => $backup) {
                    if ($index > 0 || $backup['date']->lt($annualCutoffDate)) {
                        $size = File::size($backup['file']);
                        File::delete($backup['file']);
                        $deletedCount++;
                        $deletedSize += $size;
                        
                        Log::info('Deleted old annual backup', [
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
        // Get recipients from disk monitor config (preferred) or fallback to backup notification email
        $recipients = [];
        
        // Try to get disk notification emails first
        $diskMonitorConfig = config('backup.disk_monitor', []);
        if (!empty($diskMonitorConfig['notification_emails'])) {
            $recipients = $diskMonitorConfig['notification_emails'];
        }
        
        // Fallback to backup notification email if disk emails not configured
        if (empty($recipients) && !empty($this->config['notification']['email'])) {
            $recipients = [$this->config['notification']['email']];
        }
        
        // If no recipients configured, skip notification
        if (empty($recipients)) {
            return;
        }
        
        try {
            $subject = $success 
                ? '✅ Database Backup Completed Successfully'
                : '❌ Database Backup Failed';
            
            if (is_string($error) && !empty($error)) {
                $message = $error;
            } elseif ($success) {
                $message = "Database backup completed successfully.\n\nFile: {$filename}\nSize: " . $this->formatBytes($fileSize);
            } else {
                $message = "Database backup failed.\n\nError: " . ($error ?? 'Unknown error');
            }
            
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
            
            // Send to all recipients
            $sent = false;
            foreach ($recipients as $email) {
                try {
                    $oauth->sendEmail(
                        $email,
                        $subject,
                        $message,
                        false
                    );
                    $sent = true;
                } catch (Exception $e) {
                    Log::error('Failed to send backup notification', [
                        'email' => $email,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            if ($sent) {
                Log::info('Backup notification sent', [
                    'recipients' => count($recipients),
                    'success' => $success
                ]);
            }
            
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
            $annualCount = 0;
            
            foreach ($files as $file) {
                $totalSize += File::size($file);
                $filename = $file->getFilename();
                
                if (strpos($filename, 'backup_daily_') === 0) {
                    $dailyCount++;
                } elseif (strpos($filename, 'backup_monthly_') === 0) {
                    $monthlyCount++;
                } elseif (strpos($filename, 'backup_annual_') === 0) {
                    $annualCount++;
                }
            }
            
            return [
                'total_files' => count($files),
                'daily_backups' => $dailyCount,
                'monthly_backups' => $monthlyCount,
                'annual_backups' => $annualCount,
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

