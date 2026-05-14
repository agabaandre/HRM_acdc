<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seeds Staff Share reference caches so ticket creation can resolve requesters by staff_id in tests.
     */
    protected function seedHelpdeskStaffDirectoryCache(
        int $staffId,
        string $workEmail = 'staff.dir@example.org',
        string $fname = 'Dir',
        string $lname = 'Requester',
        int $divisionId = 1,
        int $directorateId = 2,
        ?string $dutyStationName = 'HQ Lab',
    ): void {
        Cache::put('helpdesk_reference_bundle_v1', [
            'divisions' => [
                [
                    'id' => $divisionId,
                    'name' => 'Test Division',
                    'short_name' => null,
                    'directorate_id' => $directorateId,
                ],
            ],
            'directorates' => [
                [
                    'id' => $directorateId,
                    'name' => 'Test Directorate',
                    'director_id' => null,
                    'director' => null,
                ],
            ],
        ], 3600);

        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);
        Cache::put('helpdesk_reference_staff_v1_'.$limit, [
            [
                'staff_id' => $staffId,
                'fname' => $fname,
                'lname' => $lname,
                'work_email' => $workEmail,
                'division_id' => $divisionId,
                'duty_station_name' => $dutyStationName,
            ],
        ], 3600);
    }
}
