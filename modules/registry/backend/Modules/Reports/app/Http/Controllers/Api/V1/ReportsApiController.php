<?php

namespace Modules\Reports\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Support\PortalPermission;

class ReportsApiController extends Controller
{
    public function hub(): JsonResponse
    {
        PortalPermission::authorize(72);

        return response()->json([
            'data' => [
                'links' => [
                    [
                        'to' => '/staff',
                        'label' => 'Staff directory',
                        'description' => 'Browse and filter staff records.',
                        'permission' => 72,
                    ],
                    [
                        'to' => '/staff/data-quality',
                        'label' => 'Staff data quality',
                        'description' => 'Gaps and consistency checks across staff fields.',
                        'permission' => 72,
                    ],
                    [
                        'to' => '/staff/birthdays',
                        'label' => 'Birthdays',
                        'description' => 'Upcoming staff birthdays.',
                        'permission' => null,
                    ],
                    [
                        'to' => '/dashboard',
                        'label' => 'HR dashboard',
                        'description' => 'Aggregated workforce charts and counts.',
                        'permission' => 76,
                    ],
                    [
                        'to' => '/admanager/expired',
                        'label' => 'AD accounts to disable',
                        'description' => 'Expired Active Directory account report.',
                        'permission' => 77,
                    ],
                    [
                        'to' => '/admanager/disabled',
                        'label' => 'Disabled AD accounts',
                        'description' => 'Recently disabled accounts.',
                        'permission' => 77,
                    ],
                ],
            ],
            'meta' => [
                'note' => 'Report builders from CI3 reports/staff are linked via Staff and Dashboard SPA pages.',
            ],
        ]);
    }
}
