<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class JobsController extends Controller
{
    /**
     * Display the jobs management page.
     */
    public function index(): View
    {
        return view('jobs.index');
    }

    /**
     * Execute an artisan command.
     */
    public function executeCommand(Request $request): JsonResponse
    {
        $request->validate([
            'command' => 'required|string|in:cache:clear,config:clear,route:clear,view:clear,storage:link,storage:unlink,optimize,config:cache,route:cache,view:cache,divisions:sync,staff:sync,directorates:sync'
        ]);

        $command = $request->input('command');
        $startTime = microtime(true);

        try {
            // Execute the artisan command
            $exitCode = Artisan::call($command);
            $output = Artisan::output();
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($exitCode === 0) {
                Log::info("Artisan command executed successfully: {$command}", [
                    'output' => $output,
                    'execution_time' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Command executed successfully',
                    'output' => $output,
                    'execution_time' => $executionTime,
                    'command' => $command
                ]);
            } else {
                Log::error("Artisan command failed: {$command}", [
                    'exit_code' => $exitCode,
                    'output' => $output
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Command failed to execute',
                    'output' => $output,
                    'execution_time' => $executionTime,
                    'command' => $command
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("Artisan command exception: {$command}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Command execution failed: ' . $e->getMessage(),
                'output' => $e->getMessage(),
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2),
                'command' => $command
            ], 500);
        }
    }

    /**
     * Get the current environment file content.
     */
    public function getEnvContent(): JsonResponse
    {
        try {
            $envPath = base_path('.env');
            
            if (!File::exists($envPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Environment file not found'
                ], 404);
            }

            $content = File::get($envPath);

            return response()->json([
                'success' => true,
                'content' => $content
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to read environment file', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to read environment file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the environment file content.
     */
    public function updateEnvContent(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string'
        ]);

        try {
            $envPath = base_path('.env');
            $content = $request->input('content');

            // Create backup
            $backupPath = base_path('.env.backup.' . date('Y-m-d-H-i-s'));
            if (File::exists($envPath)) {
                File::copy($envPath, $backupPath);
            }

            // Write new content
            File::put($envPath, $content);

            Log::info('Environment file updated', [
                'backup_created' => $backupPath,
                'updated_by' => auth()->id() ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Environment file updated successfully',
                'backup_path' => $backupPath
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update environment file', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update environment file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system information.
     */
    public function getSystemInfo(): JsonResponse
    {
        try {
            $info = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_time' => now()->format('Y-m-d H:i:s'),
                'timezone' => config('app.timezone'),
                'environment' => config('app.env'),
                'debug_mode' => config('app.debug'),
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
                'database_connection' => config('database.default'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'disk_free_space' => $this->formatBytes(disk_free_space(base_path())),
                'disk_total_space' => $this->formatBytes(disk_total_space(base_path()))
            ];

            return response()->json([
                'success' => true,
                'info' => $info
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get system information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}