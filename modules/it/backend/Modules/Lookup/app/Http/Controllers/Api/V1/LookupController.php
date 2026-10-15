<?php

namespace Modules\Lookup\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Sanctum lookup lists for the Vue SPA (same data as CI3 /lists/{type}).
 */
class LookupController extends Controller
{
    /** @var array<string, array{table: string, order?: string}> */
    private const MAP = [
        'supervisor' => ['table' => 'staff', 'order' => 'lname'],
        'divisions' => ['table' => 'divisions', 'order' => 'division_name'],
        'contracts' => ['table' => 'divisions', 'order' => 'division_name'],
        'contractors' => ['table' => 'contracting_institutions', 'order' => 'contracting_institution'],
        'funder' => ['table' => 'funders', 'order' => 'funder_name'],
        'grades' => ['table' => 'grades', 'order' => 'grade'],
        'jobs' => ['table' => 'jobs', 'order' => 'job_name'],
        'jobsacting' => ['table' => 'jobs_acting', 'order' => 'job_acting_name'],
        'nationality' => ['table' => 'nationalities', 'order' => 'nationality'],
        'stations' => ['table' => 'duty_stations', 'order' => 'duty_station_name'],
        'status' => ['table' => 'status', 'order' => 'status_name'],
        'contracttype' => ['table' => 'contract_types', 'order' => 'contract_type'],
        'leave' => ['table' => 'leave_types', 'order' => 'leave_type'],
    ];

    public function show(string $type): JsonResponse
    {
        if (! isset(self::MAP[$type])) {
            return response()->json(['message' => 'Unknown list type.'], 404);
        }

        $cfg = self::MAP[$type];
        $q = DB::table($cfg['table']);
        if (! empty($cfg['order'])) {
            $q->orderBy($cfg['order']);
        }

        return response()->json(['data' => $q->get()]);
    }
}
