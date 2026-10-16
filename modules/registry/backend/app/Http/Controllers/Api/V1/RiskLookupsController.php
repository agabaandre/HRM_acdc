<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RatingBandResolver;
use App\Services\StaffPortalOrgClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RiskLookupsController extends Controller
{
    public function index(RatingBandResolver $bands): JsonResponse
    {
        $payload = Cache::remember(RatingBandResolver::LOOKUPS_CACHE_KEY, 300, static function () use ($bands) {
            return [
                'likelihoods' => DB::table('rr_likelihoods')->orderBy('sort_order')->get(),
                'impacts' => DB::table('rr_impacts')->orderBy('sort_order')->get(),
                'risk_types' => DB::table('rr_risk_types')->orderBy('sort_order')->get(),
                'enterprise_themes' => DB::table('rr_enterprise_themes')->orderBy('sort_order')->get(),
                'statuses' => DB::table('rr_statuses')->orderBy('sort_order')->get(),
                'mitigation_effectiveness' => DB::table('rr_mitigation_effectiveness')->orderBy('sort_order')->get(),
                'rating_bands' => collect($bands->workingBands())->values()->all(),
                'active_rating_key_version' => $bands->activeVersion(),
            ];
        });

        return response()->json($payload)->header('Cache-Control', 'private, max-age=60');
    }

    public function org(StaffPortalOrgClient $client): JsonResponse
    {
        return response()->json(['data' => $client->fetchOrg()]);
    }

    public function staff(StaffPortalOrgClient $client): JsonResponse
    {
        return response()->json(['data' => $client->fetchStaffDirectory()])
            ->header('Cache-Control', 'private, max-age=120');
    }
}
