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
    protected $signature = 'staff:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync staff data from Africa CDC API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting staff sync from Africa CDC API...');

        try {
            // Fetch data from API
            $response = Http::get('https://tools.africacdc.org/staff/share/get_current_staff/YWZyY2FjZGNzdGFmZnRyYWNrZXI');

            if (!$response->successful()) {
                throw new Exception('Failed to fetch data from API: ' . $response->status());
            }

            $staffData = $response->json();

            if (!is_array($staffData)) {
                throw new Exception('Invalid response format from API');
            }

            $created = 0;
            $updated = 0;
            $failed = 0;

            // Process each staff member
            foreach ($staffData as $data) {
                try {
                    // Validate and sanitize data
                    $workEmail = $data['work_email'] ?? $data['private_email'] ?? "noemail_{$data['staff_id']}@placeholder.com";

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

                    $staff = Staff::updateOrCreate(
                        ['staff_id' => $data['staff_id']],
                        [
                            'sap_no' => $data['SAPNO'] ?? '',
                            'work_email' => $workEmail,
                            'title' => $data['title'],
                            'fname' => $data['fname'],
                            'lname' => $data['lname'],
                            'oname' => $data['oname'] ?? '',
                            'grade' => $data['grade'],
                            'gender' => $data['gender'],
                            'date_of_birth' => $dateOfBirth,
                            'job_name' => $data['job_name'],
                            'contracting_institution' => $data['contracting_institution'],
                            'contract_type' => $data['contract_type'],
                            'nationality' => $data['nationality'],
                            'division_name' => $data['division_name'],
                            'division_id' => $data['division_id'],
                            'duty_station_id' => $data['duty_station_id'],
                            'status' => $data['status'],
                            'tel_1' => $data['tel_1'] ?? '',
                            'whatsapp' => $data['whatsapp'] ?? '',
                            'private_email' => $data['private_email'] ?? '',
                            'photo' => $data['photo'] ?? '',
                            'physical_location' => $data['physical_location'] ?? '',
                        ]
                    );

                    if ($staff->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    Log::error("Failed to sync staff member {$data['staff_id']}: " . $e->getMessage());
                    $this->error("Failed to sync staff member {$data['staff_id']}: " . $e->getMessage());
                }
            }

            $this->info("\nSync completed:");
            $this->line("Created: $created");
            $this->line("Updated: $updated");
            $this->line("Failed: $failed");

            return 0;
        } catch (Exception $e) {
            Log::error('Staff sync failed: ' . $e->getMessage());
            $this->error('Staff sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}