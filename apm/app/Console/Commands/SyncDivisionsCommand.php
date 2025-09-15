<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\Division;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncDivisionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'divisions:sync {--force : Force sync even if counts match}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Divisions data from Africa CDC API with count verification and dynamic URLs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting divisions sync from Africa CDC API...');

        try {
            // Get API credentials from config
            $username = config('services.staff_api.username');
            $password = config('services.staff_api.password');

            //dd($username, $password);

            // Validate credentials
            if (empty($username) || empty($password)) {
                throw new Exception('STAFF_API_USERNAME and STAFF_API_PASSWORD must be set in .env file');
            }

            // Get dynamic API URL using BASE_URL
            $apiBaseUrl = config('services.staff_api.base_url');
            $apiToken = config('services.staff_api.token', 'YWZyY2FjZGNzdGFmZnRyYWNrZXI');
            $apiEndpoint = config('services.staff_api.endpoints.divisions', '/staff/share/divisions');
            $apiUrl = rtrim($apiBaseUrl, '/') . $apiEndpoint . '/' . $apiToken;
            
            $this->info('Making API request to: ' . $apiUrl);
            
            $response = Http::withBasicAuth($username, $password)
                ->timeout(60)
                ->retry(2, 1000)
                ->get($apiUrl);
            //dd($response);

            if (!$response->successful()) {
                throw new Exception('Failed to fetch data from API: ' . $response->status());
            }

            $divisionsData = $response->json();
            // dd($divisionsData);

            if (!is_array($divisionsData)) {
                throw new Exception('Invalid response format from API');
            }

            $sourceCount = count($divisionsData);
            $this->info("Successfully fetched {$sourceCount} records from API");
            
            // Get current database count
            $dbCount = Division::count();
            $this->info("Current database count: {$dbCount}");

            $created = 0;
            $updated = 0;
            $failed = 0;
            $skipped = 0;

            // Process each division
            $this->info("Processing {$sourceCount} division records...");
            $progressBar = $this->output->createProgressBar($sourceCount);
            $progressBar->start();
            
            foreach ($divisionsData as $data) {
                try {
                    // Map division_id from API to id in the model, and name to division_name
                    $id = $data['division_id'] ?? $data['id'] ?? null;
                    
                    // Helper function to convert '0000-00-00' to null
                    $cleanDate = function($date) {
                        return ($date === '0000-00-00' || $date === '0000-00-00 00:00:00' || empty($date)) ? null : $date;
                    };
                    
                    $divisionData = [
                        'id' => $id,
                        'division_name' => $data['name'] ?? $data['division_name'] ?? null,
                        'division_short_name' => $data['division_short_name'] ?? null,
                        'division_head' => $data['division_head'] ?? null,
                        'focal_person' => $data['focal_person'] ?? null,
                        'admin_assistant' => $data['admin_assistant'] ?? null,
                        'finance_officer' => $data['finance_officer'] ?? null,
                        'directorate_id' => $data['directorate_id'] ?? null,
                        'head_oic_id' => $data['head_oic_id'] ?? null,
                        'head_oic_start_date' => $cleanDate($data['head_oic_start_date'] ?? null),
                        'head_oic_end_date' => $cleanDate($data['head_oic_end_date'] ?? null),
                        'director_id' => $data['director_id'] ?? null,
                        'director_oic_id' => $data['director_oic_id'] ?? null,
                        'director_oic_start_date' => $cleanDate($data['director_oic_start_date'] ?? null),
                        'director_oic_end_date' => $cleanDate($data['director_oic_end_date'] ?? null),
                        'category' => $data['category'] ?? null,
                    ];

                    if (empty($divisionData['division_name'])) {
                        continue; // Skip if no division_name
                    }

                    if ($id) {
                        $division = Division::updateOrCreate(
                            ['id' => $id],
                            $divisionData
                            
                        );
                        if ($division->wasRecentlyCreated) {
                            $created++;
                        } else {
                            $updated++;
                        }
                    } else {
                        Division::create($divisionData);
                        $created++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    $divisionName = $data['name'] ?? $data['division_name'] ?? 'unknown';
                    Log::error("Failed to sync division {$divisionName}: " . $e->getMessage());
                    $this->error("Failed to sync division {$divisionName}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();
            
            // Get final database count
            $finalDbCount = Division::count();
            
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
            Log::info('Divisions sync completed', [
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
            Log::error('Divisions sync failed: ' . $e->getMessage());
            $this->error('Divisions sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}