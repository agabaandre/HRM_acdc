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
            // Get staff_id from CI session using the helper function
            $staffId = user_session('staff_id');
            
            if (!$staffId) {
                return [];
            }

            // Get staff details from the database
            $staff = Staff::where('staff_id', $staffId)->first();
            
            if (!$staff) {
                return [];
            }

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
            \Log::warning('Custom causer resolver error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get the causer ID for the current session.
     *
     * @return int|string|null
     */
    public function getCauserId(): int|string|null
    {
        return user_session('staff_id');
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
