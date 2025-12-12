<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BackupService;
use App\Services\DiskSpaceMonitorService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupController extends Controller
{
    protected $backupService;
    protected $diskMonitorService;

    public function __construct(BackupService $backupService, DiskSpaceMonitorService $diskMonitorService)
    {
        $this->backupService = $backupService;
        $this->diskMonitorService = $diskMonitorService;
    }

    /**
     * Display backup management page
     */
    public function index()
    {
        $stats = $this->backupService->getBackupStats();
        $config = config('backup');
        
        // Get list of backup files
        $backups = $this->getBackupList();
        
        // Get disk space information
        $diskSpace = $this->diskMonitorService->getDiskSpace();
        
        return view('backups.index', compact('stats', 'config', 'backups', 'diskSpace'));
    }

    /**
     * Get list of backup files
     */
    protected function getBackupList()
    {
        $storagePath = config('backup.storage_path');
        $files = [];
        
        if (File::isDirectory($storagePath)) {
            $fileList = File::files($storagePath);
            
            foreach ($fileList as $file) {
                $filename = $file->getFilename();
                $filePath = $file->getPathname();
                $fileSize = File::size($filePath);
                $modifiedTime = File::lastModified($filePath);
                
                // Parse backup type and date from filename
                $type = 'unknown';
                $date = null;
                
                if (preg_match('/backup_(daily|monthly|annual)_(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                    $type = $matches[1];
                    $date = Carbon::parse($matches[2]);
                }
                
                $files[] = [
                    'filename' => $filename,
                    'path' => $filePath,
                    'size' => $fileSize,
                    'size_formatted' => $this->formatBytes($fileSize),
                    'type' => $type,
                    'date' => $date,
                    'modified_at' => Carbon::createFromTimestamp($modifiedTime),
                    'is_compressed' => preg_match('/\.(gz|zip)$/', $filename)
                ];
            }
            
            // Sort by date descending
            usort($files, function($a, $b) {
                return $b['modified_at']->gt($a['modified_at']) ? 1 : -1;
            });
        }
        
        return $files;
    }

    /**
     * Create a backup manually
     */
    public function create(Request $request)
    {
        $type = $request->input('type', 'daily');
        
        if (!in_array($type, ['daily', 'monthly', 'annual'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid backup type'
            ], 400);
        }
        
        try {
            $result = $this->backupService->createBackup($type);
            
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => ucfirst($type) . ' backup created successfully',
                    'backup' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create backup'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a backup file
     */
    public function download($filename)
    {
        $storagePath = config('backup.storage_path');
        $filePath = $storagePath . '/' . basename($filename);
        
        if (!File::exists($filePath)) {
            abort(404, 'Backup file not found');
        }
        
        return response()->download($filePath);
    }

    /**
     * Delete a backup file
     */
    public function delete($filename)
    {
        $storagePath = config('backup.storage_path');
        $filePath = $storagePath . '/' . basename($filename);
        
        if (!File::exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
        }
        
        try {
            File::delete($filePath);
            
            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run cleanup
     */
    public function cleanup()
    {
        try {
            $result = $this->backupService->cleanupOldBackups();
            
            if ($result !== false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cleanup completed successfully',
                    'deleted_count' => $result['deleted_count'],
                    'deleted_size' => $this->formatBytes($result['deleted_size'])
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Cleanup failed'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get backup statistics
     */
    public function stats()
    {
        $stats = $this->backupService->getBackupStats();
        $backups = $this->getBackupList();
        $diskSpace = $this->diskMonitorService->getDiskSpace();
        
        return response()->json([
            'success' => true,
            'stats' => $stats,
            'backups' => $backups,
            'disk_space' => $diskSpace
        ]);
    }

    /**
     * Check disk space manually
     */
    public function checkDiskSpace()
    {
        try {
            $diskSpace = $this->diskMonitorService->getDiskSpace();
            $notificationSent = $this->diskMonitorService->checkAndNotify();
            
            return response()->json([
                'success' => true,
                'disk_space' => $diskSpace,
                'notification_sent' => $notificationSent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
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
}

