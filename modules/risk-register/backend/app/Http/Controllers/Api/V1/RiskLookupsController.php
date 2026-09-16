<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StaffPortalOrgClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RiskLookupsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'likelihoods' => DB::table('rr_likelihoods')->orderBy('sort_order')->get(),
            'impacts' => DB::table('rr_impacts')->orderBy('sort_order')->get(),
            'risk_types' => DB::table('rr_risk_types')->orderBy('sort_order')->get(),
            'enterprise_themes' => DB::table('rr_enterprise_themes')->orderBy('sort_order')->get(),
            'statuses' => DB::table('rr_statuses')->orderBy('sort_order')->get(),
            'mitigation_effectiveness' => DB::table('rr_mitigation_effectiveness')->orderBy('sort_order')->get(),
            'rating_bands' => DB::table('rr_rating_bands')->orderBy('sort_order')->get(),
        ]);
    }

    public function org(StaffPortalOrgClient $client): JsonResponse
    {
        return response()->json(['data' => $client->fetchOrg()]);
    }
}
