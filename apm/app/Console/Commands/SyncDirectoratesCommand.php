<?php

namespace App\Console\Commands;

use App\Models\Directorate;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncDirectoratesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'directorates:sync {--force : Force sync even if counts match}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Directorates data from Africa CDC API with count verification and dynamic URLs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting directorates sync from Africa CDC API...');

        try {
            // Get API credentials from config
            $username = config('services.staff_api.username');
            $password = config('services.staff_api.password');

            // Validate credentials
            if (empty($username) || empty($password)) {
                throw new Exception('STAFF_API_USERNAME and STAFF_API_PASSWORD must be set in .env file');
            }

            // Get dynamic API URL using BASE_URL
            $apiBaseUrl = config('services.staff_api.base_url');
            $apiToken = config('services.staff_api.token', 'YWZyY2FjZGNzdGFmZnRyYWNrZXI');
            $apiEndpoint = config('services.staff_api.endpoints.directorates', '/staff/share/directorates');
            $apiUrl = rtrim($apiBaseUrl, '/') . $apiEndpoint . '/' . $apiToken;
            
            $this->info('Making API request to: ' . $apiUrl);
            
            $response = Http::withBasicAuth($username, $password)
                ->timeout(60)
                ->retry(2, 1000)
                ->get($apiUrl);

            if (!$response->successful()) {
                throw new Exception('Failed to fetch data from API: ' . $response->status());
            }

            $directoratesData = $response->json();
            // dd($directoratesData);

            if (!is_array($directoratesData)) {
                throw new Exception('Invalid response format from API');
            }

            $sourceCount = count($directoratesData);
            $this->info("Successfully fetched {$sourceCount} records from API");
            
            // Get current database count
            $dbCount = Directorate::count();
            $this->info("Current database count: {$dbCount}");

            $created = 0;
            $updated = 0;
            $failed = 0;
            $skipped = 0;

            // Process each directorate
            $this->info("Processing {$sourceCount} directorate records...");
            $progressBar = $this->output->createProgressBar($sourceCount);
            $progressBar->start();
            
            foreach ($directoratesData as $data) {
                try {
                    $sourceId = (int) ($data['id'] ?? $data['directorate_id'] ?? 0);
                    if ($sourceId <= 0) {
                        $skipped++;
                        Log::warning('Skipped directorate: missing or invalid id', ['row' => $data]);
                        $progressBar->advance();
                        continue;
                    }

                    $name = isset($data['name']) ? trim((string) $data['name']) : '';
                    if ($name === '') {
                        $skipped++;
                        Log::warning("Skipped directorate id={$sourceId}: empty name");
                        $progressBar->advance();
                        continue;
                    }

                    $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;
                    $directorRaw = $data['director_id'] ?? null;
                    if (($directorRaw === null || $directorRaw === '' || $directorRaw === false)
                        && ! empty($data['director']) && is_array($data['director'])) {
                        $directorRaw = $data['director']['id'] ?? $data['director']['staff_id'] ?? null;
                    }
                    $directorId = null;
                    if ($directorRaw !== null && $directorRaw !== '' && $directorRaw !== false) {
                        $directorId = (int) $directorRaw;
                        if ($directorId <= 0) {
                            $directorId = null;
                        }
                    }

                    $directorateData = [
                        'id' => $sourceId,
                        'name' => $name,
                        'is_active' => $isActive,
                        'director_id' => $directorId,
                    ];

                    // Match Staff tracker primary key so renames sync; do not key by name.
                    $directorate = Directorate::updateOrCreate(
                        ['id' => $sourceId],
                        $directorateData
                    );

                    if ($directorate->wasRecentlyCreated) {
                        $created++;
                        Log::info("Created directorate id={$sourceId}: {$name}");
                    } else {
                        $updated++;
                        Log::info("Updated directorate id={$sourceId}: {$name}");
                    }
                } catch (Exception $e) {
                    $failed++;
                    $directorateLabel = (string) (($data['id'] ?? $data['directorate_id'] ?? '?').':'.($data['name'] ?? 'unknown'));
                    Log::error("Failed to sync directorate {$directorateLabel}: ".$e->getMessage());
                    $this->error("Failed to sync directorate {$directorateLabel}: ".$e->getMessage());
                }
                
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();
            
            // Get final database count
            $finalDbCount = Directorate::count();
            
            $this->info("\n" . str_repeat('=', 50));
            $this->info("SYNC RESULTS");
            $this->info(str_repeat('=', 50));
            $this->line("Source API Records: {$sourceCount}");
            $this->line("Database Records: {$finalDbCount}");
            $this->line("Created: $created");
            $this->line("Updated: $updated");
            $this->line("Failed: $failed");
            $this->line("Skipped: $skipped");
            
            if ($sourceCount !== $finalDbCount) {
                $this->warn("⚠️  WARNING: Source count ({$sourceCount}) does not match database count ({$finalDbCount})");
            } else {
                $this->info("✅ SUCCESS: Source count matches database count");
            }
            
            $this->info(str_repeat('=', 50));
            
            // Log results
            Log::info('Directorates sync completed', [
                'source_count' => $sourceCount,
                'db_count' => $finalDbCount,
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'count_match' => $sourceCount === $finalDbCount
            ]);

            return 0;
        } catch (Exception $e) {
            Log::error('Directorates sync failed: ' . $e->getMessage());
            $this->error('Directorates sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}