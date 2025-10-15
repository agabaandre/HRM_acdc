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
use App\Jobs\ResetDocumentCountersJob;
use App\Models\DocumentCounter;
use App\Models\Division;

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
            'command' => 'required|string|in:cache:clear,config:clear,route:clear,view:clear,storage:link,storage:unlink,optimize,config:cache,route:cache,view:cache,divisions:sync,staff:sync,directorates:sync,audit:cleanup,reminders:schedule'
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
                'updated_by' => 'system'
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
                'server_time' => date('Y-m-d H:i:s'),
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
     * Get document counters for display.
     */
    public function getDocumentCounters(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', date('Y'));
            $division = $request->input('division');
            $type = $request->input('type');

            $query = DocumentCounter::where('year', $year);
            
            if ($division) {
                $query->where('division_short_name', $division);
            }
            
            if ($type) {
                $query->where('document_type', $type);
            }

            $counters = $query->orderBy('division_short_name')
                            ->orderBy('document_type')
                            ->get();

            return response()->json([
                'success' => true,
                'counters' => $counters,
                'filters' => [
                    'year' => $year,
                    'division' => $division,
                    'type' => $type
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get document counters', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get document counters: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset document counters.
     */
    public function resetDocumentCounters(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'division' => 'nullable|string|max:10',
            'type' => 'nullable|string|in:QM,NT,SPM,SM,CR,SR,ARF',
            'sync' => 'boolean'
        ]);

        try {
            $year = $request->input('year');
            $division = $request->input('division');
            $type = $request->input('type');
            $sync = $request->input('sync', false);

            // Validate division if provided
            if ($division && !Division::where('division_short_name', $division)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "Division '{$division}' not found"
                ], 400);
            }

            if ($sync) {
                // Run synchronously
                $job = new ResetDocumentCountersJob($year, $division, $type);
                $job->handle();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Document counters reset successfully!',
                    'execution_mode' => 'synchronous'
                ]);
            } else {
                // Dispatch job
                ResetDocumentCountersJob::dispatch($year, $division, $type);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Reset job dispatched successfully! Check queue worker logs for progress.',
                    'execution_mode' => 'asynchronous'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to reset document counters', [
                'error' => $e->getMessage(),
                'year' => $request->input('year'),
                'division' => $request->input('division'),
                'type' => $request->input('type')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset document counters: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available divisions and document types for filters.
     */
    public function getDocumentCounterFilters(): JsonResponse
    {
        try {
            $divisions = Division::whereNotNull('division_short_name')
                                ->orderBy('division_short_name')
                                ->pluck('division_short_name', 'id')
                                ->toArray();

            $documentTypes = DocumentCounter::getDocumentTypes();

            return response()->json([
                'success' => true,
                'divisions' => $divisions,
                'document_types' => $documentTypes
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get document counter filters', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get filters: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute reminders schedule command with force option.
     */
    public function executeRemindersSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'force' => 'boolean'
        ]);

        $force = $request->input('force', false);
        $startTime = microtime(true);

        try {
            // Build the command with optional --force flag
            $command = 'reminders:schedule';
            if ($force) {
                $command .= ' --force';
            }

            // Execute the artisan command
            $exitCode = Artisan::call($command);
            $output = Artisan::output();
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($exitCode === 0) {
                Log::info("Reminders schedule command executed successfully: {$command}", [
                    'output' => $output,
                    'execution_time' => $executionTime,
                    'force_mode' => $force
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Reminders schedule executed successfully',
                    'output' => $output,
                    'execution_time' => $executionTime,
                    'command' => $command,
                    'force_mode' => $force
                ]);
            } else {
                Log::error("Reminders schedule command failed: {$command}", [
                    'exit_code' => $exitCode,
                    'output' => $output,
                    'force_mode' => $force
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Reminders schedule command failed to execute',
                    'output' => $output,
                    'execution_time' => $executionTime,
                    'command' => $command,
                    'force_mode' => $force
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("Reminders schedule command exception: {$command}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'force_mode' => $force
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Reminders schedule command execution failed: ' . $e->getMessage(),
                'output' => $e->getMessage(),
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2),
                'command' => $command,
                'force_mode' => $force
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