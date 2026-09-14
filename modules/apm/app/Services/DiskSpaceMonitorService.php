<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class DiskSpaceMonitorService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('backup.disk_monitor', []);
    }

    /**
     * Get disk space information
     * 
     * @param string $path Path to check (default: backup storage path)
     * @return array|false Disk space info or false on failure
     */
    public function getDiskSpace($path = null)
    {
        try {
            if (!$path) {
                $path = config('backup.storage_path', storage_path('app/backups'));
            }
            
            // Ensure path exists
            if (!is_dir($path)) {
                $path = dirname($path);
            }
            
            // Get disk space information
            $totalBytes = disk_total_space($path);
            $freeBytes = disk_free_space($path);
            $usedBytes = $totalBytes - $freeBytes;
            
            if ($totalBytes === false || $freeBytes === false) {
                return false;
            }
            
            $usagePercent = ($totalBytes > 0) ? ($usedBytes / $totalBytes) * 100 : 0;
            
            return [
                'total' => $totalBytes,
                'free' => $freeBytes,
                'used' => $usedBytes,
                'usage_percent' => round($usagePercent, 2),
                'total_formatted' => $this->formatBytes($totalBytes),
                'free_formatted' => $this->formatBytes($freeBytes),
                'used_formatted' => $this->formatBytes($usedBytes),
                'path' => $path,
                'status' => $this->getStatus($usagePercent)
            ];
        } catch (Exception $e) {
            Log::error('Failed to get disk space', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);
            return false;
        }
    }

    /**
     * Check if disk space is low and send notification if needed
     * 
     * @return bool True if notification was sent, false otherwise
     */
    public function checkAndNotify()
    {
        if (!$this->config['enabled'] ?? false) {
            return false;
        }
        
        $diskSpace = $this->getDiskSpace();
        
        if (!$diskSpace) {
            return false;
        }
        
        $usagePercent = $diskSpace['usage_percent'];
        $warningThreshold = $this->config['warning_threshold'] ?? 80;
        $criticalThreshold = $this->config['critical_threshold'] ?? 90;
        
        // Check if we need to send notification
        if ($usagePercent >= $criticalThreshold) {
            return $this->sendNotification($diskSpace, 'critical');
        } elseif ($usagePercent >= $warningThreshold) {
            // Only send warning if we haven't sent one recently
            return $this->sendNotification($diskSpace, 'warning');
        }
        
        return false;
    }

    /**
     * Get status based on usage percentage
     */
    protected function getStatus($usagePercent)
    {
        $warningThreshold = $this->config['warning_threshold'] ?? 80;
        $criticalThreshold = $this->config['critical_threshold'] ?? 90;
        
        if ($usagePercent >= $criticalThreshold) {
            return 'critical';
        } elseif ($usagePercent >= $warningThreshold) {
            return 'warning';
        }
        
        return 'ok';
    }

    /**
     * Send disk space notification email
     */
    protected function sendNotification($diskSpace, $level = 'warning')
    {
        try {
            $recipients = $this->config['notification_emails'] ?? [];
            
            if (empty($recipients)) {
                Log::warning('No notification emails configured for disk space monitoring');
                return false;
            }
            
            $subject = $level === 'critical' 
                ? '🚨 CRITICAL: Server Disk Space Almost Full'
                : '⚠️ WARNING: Server Disk Space Running Low';
            
            $message = $this->buildNotificationMessage($diskSpace, $level);
            
            // Use existing email service
            require_once app_path('ExchangeEmailService/ExchangeOAuth.php');
            $exchangeConfig = config('exchange-email');
            
            $oauth = new \AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth(
                $exchangeConfig['tenant_id'],
                $exchangeConfig['client_id'],
                $exchangeConfig['client_secret'],
                $exchangeConfig['redirect_uri'],
                $exchangeConfig['scope'],
                $exchangeConfig['auth_method']
            );
            
            $sent = false;
            foreach ($recipients as $email) {
                try {
                    $oauth->sendEmail(
                        $email,
                        $subject,
                        $message,
                        true // HTML format
                    );
                    $sent = true;
                } catch (Exception $e) {
                    Log::error('Failed to send disk space notification', [
                        'email' => $email,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            if ($sent) {
                Log::info('Disk space notification sent', [
                    'level' => $level,
                    'usage_percent' => $diskSpace['usage_percent']
                ]);
            }
            
            return $sent;
            
        } catch (Exception $e) {
            Log::error('Disk space notification exception', [
                'error' => $e->getMessage(),
                'level' => $level
            ]);
            return false;
        }
    }

    /**
     * Build notification message HTML
     */
    protected function buildNotificationMessage($diskSpace, $level)
    {
        $color = $level === 'critical' ? '#dc3545' : '#ffc107';
        $icon = $level === 'critical' ? '🚨' : '⚠️';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .alert-box { background-color: {$color}; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .info-box { background-color: #f8f9fa; border-left: 4px solid {$color}; padding: 15px; margin: 10px 0; }
                .stat-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e9ecef; }
                .stat-label { font-weight: 600; color: #555; }
                .stat-value { color: #333; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='alert-box'>
                    <h2 style='margin: 0;'>{$icon} Disk Space Alert</h2>
                    <p style='margin: 10px 0 0 0;'>Server disk space is running low and requires immediate attention.</p>
                </div>
                
                <div class='info-box'>
                    <h3 style='margin-top: 0; color: {$color};'>Disk Space Details</h3>
                    <div class='stat-row'>
                        <span class='stat-label'>Total Space:</span>
                        <span class='stat-value'>{$diskSpace['total_formatted']}</span>
                    </div>
                    <div class='stat-row'>
                        <span class='stat-label'>Used Space:</span>
                        <span class='stat-value'>{$diskSpace['used_formatted']}</span>
                    </div>
                    <div class='stat-row'>
                        <span class='stat-label'>Free Space:</span>
                        <span class='stat-value'>{$diskSpace['free_formatted']}</span>
                    </div>
                    <div class='stat-row'>
                        <span class='stat-label'>Usage:</span>
                        <span class='stat-value'><strong style='color: {$color};'>{$diskSpace['usage_percent']}%</strong></span>
                    </div>
                    <div class='stat-row' style='border-bottom: none;'>
                        <span class='stat-label'>Path:</span>
                        <span class='stat-value'><code>{$diskSpace['path']}</code></span>
                    </div>
                </div>
                
                <div class='info-box'>
                    <h4 style='margin-top: 0;'>Recommended Actions:</h4>
                    <ul>
                        <li>Review and clean up old backup files</li>
                        <li>Check for large log files that can be archived</li>
                        <li>Remove temporary files and unused data</li>
                        <li>Consider expanding disk capacity if usage continues to grow</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p>This is an automated notification from the Africa CDC APM System.</p>
                    <p>Please take appropriate action to prevent disk space issues.</p>
                </div>
            </div>
        </body>
        </html>
        ";
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
}

