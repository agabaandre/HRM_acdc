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
    protected $signature = 'directorates:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Directorates data from Africa CDC API';

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

            $response = Http::withBasicAuth($username, $password)
                ->get('https://tools.africacdc.org/staff/share/directorates/YWZyY2FjZGNzdGFmZnRyYWNrZXI');

            if (!$response->successful()) {
                throw new Exception('Failed to fetch data from API: ' . $response->status());
            }

            $directoratesData = $response->json();
            // dd($directoratesData);

            if (!is_array($directoratesData)) {
                throw new Exception('Invalid response format from API');
            }

            $created = 0;
            $updated = 0;
            $failed = 0;

            // Process each directorate
            foreach ($directoratesData as $data) {
                try {
                    // Validate and sanitize data
                    $name = $data['name'] ?? null;
                    if (empty($name)) {
                        continue; // Skip if no name
                    }
                    $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;

                    // Try to find by name
                    $directorate = Directorate::where('name', $name)->first();

                    $directorateData = [
                        'name' => $name,
                        'is_active' => $isActive,
                    ];

                    if ($directorate) {
                        $directorate->update($directorateData);
                        $updated++;
                    } else {
                        Directorate::create($directorateData);
                        $created++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    Log::error("Failed to sync directorate {$data['name']}: " . $e->getMessage());
                    $this->error("Failed to sync directorate {$data['name']}: " . $e->getMessage());
                }
            }

            $this->info("\nSync completed:");
            $this->line("Created: $created");
            $this->line("Updated: $updated");
            $this->line("Failed: $failed");

            return 0;
        } catch (Exception $e) {
            Log::error('Directorates sync failed: ' . $e->getMessage());
            $this->error('Directorates sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}