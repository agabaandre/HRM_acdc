<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BackupService;
use App\Services\DiskSpaceMonitorService;
use App\Models\BackupDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
        
        // Get configured databases
        $databases = BackupDatabase::orderedByPriority()->get();
        
        return view('backups.index', compact('stats', 'config', 'backups', 'diskSpace', 'databases'));
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
                
                // Parse backup type, database name, and date from filename
                // Format: backup_type_dbname_YYYY-MM-DD_HH-MM-SS.sql[.gz]
                $type = 'unknown';
                $date = null;
                $databaseName = null;
                
                if (preg_match('/backup_(daily|monthly|annual)_([^_]+)_(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                    $type = $matches[1];
                    $databaseName = $matches[2];
                    $date = Carbon::parse($matches[3]);
                } elseif (preg_match('/backup_(daily|monthly|annual)_(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                    // Fallback for old format without database name
                    $type = $matches[1];
                    $date = Carbon::parse($matches[2]);
                }
                
                $files[] = [
                    'filename' => $filename,
                    'path' => $filePath,
                    'size' => $fileSize,
                    'size_formatted' => $this->formatBytes($fileSize),
                    'type' => $type,
                    'database' => $databaseName,
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
        $databaseId = $request->input('database_id', null);
        
        if (!in_array($type, ['daily', 'monthly', 'annual'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid backup type'
            ], 400);
        }
        
        try {
            $result = $this->backupService->createBackup($type, $databaseId);
            
            if ($result) {
                $message = ucfirst($type) . ' backup created successfully';
                if (isset($result['success_count']) && isset($result['total_count'])) {
                    $message .= " ({$result['success_count']}/{$result['total_count']} databases)";
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
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
     * 
     * DISABLED: This method has been disabled for security reasons.
     * Backups can only be deleted through the automated cleanup process
     * which follows the retention policy. Manual deletion is not allowed
     * to prevent accidental or malicious deletion of important backups.
     */
    public function delete($filename)
    {
        return response()->json([
            'success' => false,
            'message' => 'Manual backup deletion is disabled for security. Backups are automatically cleaned up based on retention policy.'
        ], 403);
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
     * Get all configured databases
     */
    public function getDatabases()
    {
        $databases = BackupDatabase::orderedByPriority()->get();
        
        return response()->json([
            'success' => true,
            'databases' => $databases
        ]);
    }

    /**
     * Get a single database configuration
     */
    public function getDatabase($id)
    {
        try {
            $database = BackupDatabase::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'database' => $database
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database not found'
            ], 404);
        }
    }

    /**
     * Store a new database configuration
     */
    public function storeDatabase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:backup_databases,name',
            'display_name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'integer|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // Convert boolean values properly
            $data['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $data['is_default'] = filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            // If this is set as default, unset other defaults
            if ($data['is_default']) {
                BackupDatabase::where('is_default', true)->update(['is_default' => false]);
            }

            $database = BackupDatabase::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Database configuration created successfully',
                'database' => $database
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a database configuration
     */
    public function updateDatabase(Request $request, $id)
    {
        $database = BackupDatabase::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:backup_databases,name,' . $id,
            'display_name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'integer|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // If this is set as default, unset other defaults
            if ($request->input('is_default', false) && !$database->is_default) {
                BackupDatabase::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
            }

            $data = $request->all();
            // Only update password if provided
            if (empty($data['password'])) {
                unset($data['password']);
            }
            
            // Convert boolean values properly
            $data['is_active'] = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $data['is_default'] = filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $database->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Database configuration updated successfully',
                'database' => $database->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a database configuration
     */
    public function deleteDatabase($id)
    {
        try {
            $database = BackupDatabase::findOrFail($id);
            $database->delete();

            return response()->json([
                'success' => true,
                'message' => 'Database configuration deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test database connection
     */
    public function testDatabaseConnection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string',
            'port' => 'required|integer',
            'username' => 'required|string',
            'password' => 'required|string',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $host = $request->input('host');
            $port = $request->input('port');
            $username = $request->input('username');
            $password = $request->input('password');
            $database = $request->input('name');

            // Test connection
            $connection = @mysqli_connect($host, $username, $password, $database, $port);

            if ($connection) {
                mysqli_close($connection);
                return response()->json([
                    'success' => true,
                    'message' => 'Database connection successful'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Database connection failed: ' . mysqli_connect_error()
                ], 400);
            }
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

