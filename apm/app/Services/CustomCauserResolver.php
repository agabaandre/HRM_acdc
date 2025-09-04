<?php

declare(strict_types=1);

namespace App\Services;

use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use App\Models\Staff;

class CustomCauserResolver implements CauserResolverInterface
{
    /**
     * Resolve the causer (user) from the custom CI session system.
     *
     * @return array
     */
    public function resolve(): array
    {
        try {
            // Try multiple methods to get staff_id
            $staffId = $this->getStaffIdFromSession();
            
            if (!$staffId) {
                \Log::info('No staff_id found in session for audit logging');
                return [
                    'id' => null,
                    'type' => null,
                    'name' => 'System',
                    'email' => null,
                    'additional_data' => []
                ];
            }

            // Get staff details from the database
            $staff = Staff::where('staff_id', $staffId)->first();
            
            if (!$staff) {
                \Log::warning("Staff not found for staff_id: {$staffId}");
                return [
                    'id' => $staffId,
                    'type' => Staff::class,
                    'name' => 'Unknown User',
                    'email' => null,
                    'additional_data' => []
                ];
            }

            \Log::info("Audit logging for staff: {$staff->fname} {$staff->lname} (ID: {$staffId})");

            return [
                'id' => $staff->staff_id,
                'type' => Staff::class,
                'name' => trim($staff->fname . ' ' . $staff->lname),
                'email' => $staff->work_email ?? $staff->personal_email ?? null,
                'additional_data' => [
                    'division_id' => $staff->division_id ?? null,
                    'position' => $staff->position ?? null,
                    'department' => $staff->department ?? null,
                ]
            ];
        } catch (\Exception $e) {
            // Log the error but don't break the audit logging
            \Log::error('Custom causer resolver error: ' . $e->getMessage());
            return [
                'id' => null,
                'type' => null,
                'name' => 'System (Error)',
                'email' => null,
                'additional_data' => []
            ];
        }
    }
    
    /**
     * Get staff_id from session using multiple methods
     */
    private function getStaffIdFromSession(): ?string
    {
        // Method 1: Try the user_session helper function
        if (function_exists('user_session')) {
            try {
                $staffId = user_session('staff_id');
                if ($staffId) {
                    return $staffId;
                }
            } catch (\Exception $e) {
                \Log::warning('user_session helper failed: ' . $e->getMessage());
            }
        }
        
        // Method 2: Try Laravel session
        if (session()->has('staff_id')) {
            return session('staff_id');
        }
        
        // Method 3: Try CodeIgniter session if available
        if (class_exists('CI_Session')) {
            try {
                $ci =& get_instance();
                if (isset($ci->session)) {
                    return $ci->session->userdata('staff_id');
                }
            } catch (\Exception $e) {
                \Log::warning('CodeIgniter session access failed: ' . $e->getMessage());
            }
        }
        
        // Method 4: Try global session variables
        if (isset($_SESSION['staff_id'])) {
            return $_SESSION['staff_id'];
        }
        
        return null;
    }

    /**
     * Get the causer ID for the current session.
     *
     * @return int|string|null
     */
    public function getCauserId(): int|string|null
    {
        return $this->getStaffIdFromSession();
    }

    /**
     * Get the causer type (model class).
     *
     * @return string|null
     */
    public function getCauserType(): ?string
    {
        return Staff::class;
    }
}
