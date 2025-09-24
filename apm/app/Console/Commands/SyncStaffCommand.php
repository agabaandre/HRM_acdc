<?php

namespace App\Console\Commands;

use App\Models\Staff;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncStaffCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:sync {--force : Force sync even if counts match}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync staff data from Africa CDC API with count verification and dynamic URLs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting staff sync from Africa CDC API...');

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
            $apiEndpoint = config('services.staff_api.endpoints.staff', '/staff/share/get_current_staff');
            $apiUrl = rtrim($apiBaseUrl, '/') . $apiEndpoint . '/' . $apiToken;
            
            $this->info('Making API request to: ' . $apiUrl);
            
            $response = Http::withBasicAuth($username, $password)
                ->timeout(60)
                ->retry(2, 1000)
                ->get($apiUrl);

            if (!$response->successful()) {
                throw new Exception('Failed to fetch data from API: ' . $response->status());
            }

            $staffData = $response->json();
            //dd($staffData);

            if (!is_array($staffData)) {
                throw new Exception('Invalid response format from API');
            }

            $sourceCount = count($staffData);
            $this->info("Successfully fetched {$sourceCount} records from API");
            
            // Get current database count
            $dbCount = Staff::count();
            $this->info("Current database count: {$dbCount}");

        $created = 0;
        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $skippedReasons = [];

            // Process each staff member
            $this->info("Processing {$sourceCount} staff records...");
            $progressBar = $this->output->createProgressBar($sourceCount);
            $progressBar->start();
            
            foreach ($staffData as $data) {
                try {
                    $staffId = $data['staff_id'] ?? 'unknown';
                    $skipReason = null;
                    
                    // Validate required fields
                    if (empty($data['staff_id'])) {
                        $skipReason = 'Missing staff_id';
                    } elseif (empty($data['fname']) || empty($data['lname'])) {
                        $skipReason = 'Missing name fields (fname/lname)';
                    } else {
                        // Validate and sanitize email - only allow @africacdc.org emails
                        $workEmail = $data['work_email'] ?? $data['private_email'] ?? null;
                        $privateEmail = $data['private_email'] ?? null;
                        
                        // Clean up empty or invalid emails
                        if (empty($workEmail) || $workEmail === '' || $workEmail === 'null') {
                            $workEmail = null;
                        }
                        if (empty($privateEmail) || $privateEmail === '' || $privateEmail === 'null') {
                            $privateEmail = null;
                        }
                        
                        // Only sync staff with a valid @africacdc.org work email
                        if (empty($workEmail) || stripos($workEmail, '@africacdc.org') === false) {
                            $skipReason = 'Invalid or missing @africacdc.org email: ' . ($workEmail ?: 'empty');
                        } else {
                            // Check if staff_id already exists
                            $existingStaffById = Staff::where('staff_id', $data['staff_id'])->first();
                            
                            if (!$existingStaffById && !empty($workEmail)) {
                                // Staff ID doesn't exist - check for email conflicts before creating
                                $existingStaffWithEmail = Staff::where('work_email', $workEmail)
                                    ->orWhere('private_email', $workEmail)
                                    ->first();
                                    
                                if ($existingStaffWithEmail) {
                                    $skipReason = "Email conflict: {$workEmail} already exists for staff_id {$existingStaffWithEmail->staff_id}";
                                }
                            }
                            // If staff_id exists, update regardless of email (staff_id is primary key)
                        }
                    }
                    
                    if ($skipReason) {
                        $skipped++;
                        $skippedReasons[] = "Staff ID {$staffId}: {$skipReason}";
                        try {
                            Log::warning("Skipped staff member {$staffId}: {$skipReason}");
                        } catch (\Exception $logException) {
                            // Ignore logging errors
                        }
                        $progressBar->advance();
                        continue;
                    }

                    // Enhanced date validation
                    $dateOfBirth = null;
                    if (!empty($data['date_of_birth']) && $data['date_of_birth'] !== '-0001-11-30 00:00:00') {
                        try {
                            $timestamp = strtotime($data['date_of_birth']);
                            if ($timestamp !== false && $timestamp > strtotime('1900-01-01')) {
                                $dateOfBirth = date('Y-m-d', $timestamp);
                            }
                        } catch (\Exception $e) {
                            // Invalid date, keep as null
                        }
                    }

                    // Find existing staff by staff_id only
                    $existingStaff = Staff::where('staff_id', $data['staff_id'])->first();

                    // Prepare the data array with all available fields
                    $staffData = [
                        'staff_id' => $data['staff_id'],
                        'sap_no' => $data['SAPNO'] ?? $data['sap_no'] ?? '',
                        'work_email' => $workEmail,
                        'title' => $data['title'] ?? '',
                        'fname' => $data['fname'],
                        'lname' => $data['lname'],
                        'oname' => $data['oname'] ?? '',
                        'grade' => $data['grade'] ?? '',
                        'gender' => $data['gender'] ?? '',
                        'date_of_birth' => $dateOfBirth,
                        'job_name' => $data['job_name'] ?? '',
                        'contracting_institution' => $data['contracting_institution'] ?? '',
                        'contract_type' => $data['contract_type'] ?? '',
                        'nationality' => $data['nationality'] ?? '',
                        'division_name' => $data['division_name'] ?? '',
                        'division_id' => $data['division_id'] ?? null,
                        'duty_station_id' => $data['duty_station_id'] ?? null,
                        'duty_station_name' => $data['duty_station_name'] ?? '',
                        'status' => $data['status'] ?? 'Active',
                        'tel_1' => $data['tel_1'] ?? '',
                        'whatsapp' => $data['whatsapp'] ?? '',
                        'private_email' => $privateEmail,
                        'photo' => $data['photo'] ?? '',
                        'signature' => $data['signature'] ?? '',
                        'physical_location' => $data['physical_location'] ?? '',
                        'active' => isset($data['active']) ? (bool)$data['active'] : true,
                    ];

                    if ($existingStaff) {
                        // Update existing staff
                        $existingStaff->update($staffData);
                        $updated++;
                        try {
                            Log::info("Updated staff member {$staffId}: {$workEmail}");
                        } catch (\Exception $logException) {
                            // Ignore logging errors
                        }
                    } else {
                        // Create new staff member
                        Staff::create($staffData);
                        $created++;
                        try {
                            Log::info("Created staff member {$staffId}: {$workEmail}");
                        } catch (\Exception $logException) {
                            // Ignore logging errors
                        }
                    }
                } catch (Exception $e) {
                    $failed++;
                    $staffId = $data['staff_id'] ?? 'unknown';
                    try {
                        Log::error("Failed to sync staff member {$staffId}: " . $e->getMessage());
                    } catch (\Exception $logException) {
                        // Ignore logging errors
                    }
                    $this->error("Failed to sync staff member {$staffId}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();
            
            // Get final database count
            $finalDbCount = Staff::count();
            
            $this->info("\n" . str_repeat('=', 50));
            $this->info("SYNC RESULTS");
            $this->info(str_repeat('=', 50));
            $this->line("Source API Records: {$sourceCount}");
            $this->line("Database Records: {$finalDbCount}");
            $this->line("Created: $created");
            $this->line("Updated: $updated");
            $this->line("Failed: $failed");
            $this->line("Skipped: $skipped");
            
            if ($skipped > 0) {
                $this->warn("\n⚠️  SKIPPED RECORDS:");
                foreach (array_slice($skippedReasons, 0, 10) as $reason) {
                    $this->warn("  • {$reason}");
                }
                if (count($skippedReasons) > 10) {
                    $this->warn("  • ... and " . (count($skippedReasons) - 10) . " more (check logs for details)");
                }
            }
            
            if ($sourceCount !== $finalDbCount) {
                $this->warn("\n⚠️  WARNING: Source count ({$sourceCount}) does not match database count ({$finalDbCount})");
                $this->warn("  This is likely due to skipped records. Check the reasons above.");
            } else {
                $this->info("\n✅ SUCCESS: Source count matches database count");
            }
            
            $this->info(str_repeat('=', 50));
            
            // Log results
            try {
                Log::info('Staff sync completed', [
                    'source_count' => $sourceCount,
                    'db_count' => $finalDbCount,
                    'created' => $created,
                    'updated' => $updated,
                    'failed' => $failed,
                    'skipped' => $skipped,
                    'skipped_reasons' => $skippedReasons,
                    'count_match' => $sourceCount === $finalDbCount
                ]);
            } catch (\Exception $logException) {
                // Ignore logging errors
            }

            return 0;
        } catch (Exception $e) {
            try {
                Log::error('Staff sync failed: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Ignore logging errors
            }
            $this->error('Staff sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}