<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\RiskPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $permissions = $request->attributes->get('risk_permissions', []);
        $divisionId = (int) $request->attributes->get('risk_division_id', 0);
        $scoped = ! RiskPermissions::canViewAll($permissions);
        $scopeDivision = $scoped ? ($divisionId > 0 ? $divisionId : -1) : null;

        $base = DB::table('rr_risks');
        if ($scopeDivision !== null) {
            $base->where('division_id', $scopeDivision);
        }

        $total = (clone $base)->count();
        $unmatched = (clone $base)->whereNotNull('unmapped_business_unit')->count();

        $byRating = (clone $base)
            ->select('residual_rating', DB::raw('count(*) as c'))
            ->groupBy('residual_rating')
            ->pluck('c', 'residual_rating');

        $typeQ = DB::table('rr_risks as r')
            ->leftJoin('rr_risk_types as t', 't.id', '=', 'r.risk_type_id');
        if ($scopeDivision !== null) {
            $typeQ->where('r.division_id', $scopeDivision);
        }
        $byType = $typeQ
            ->selectRaw("COALESCE(t.name, 'Unspecified') as label, count(*) as c")
            ->groupBy('label')
            ->pluck('c', 'label');

        $byBu = (clone $base)
            ->selectRaw("COALESCE(source_business_unit, unmapped_business_unit, CAST(division_id AS CHAR), 'Unknown') as bu, count(*) as c")
            ->groupBy('bu')
            ->orderByDesc('c')
            ->limit(20)
            ->pluck('c', 'bu');

        $heat = (clone $base)
            ->select('inherent_likelihood', 'inherent_impact', DB::raw('count(*) as c'))
            ->whereNotNull('inherent_likelihood')
            ->whereNotNull('inherent_impact')
            ->groupBy('inherent_likelihood', 'inherent_impact')
            ->get();

        $topResidual = (clone $base)
            ->orderByDesc('residual_score')
            ->limit(10)
            ->get(['id', 'name', 'residual_score', 'residual_rating', 'source_business_unit']);

        $statusQ = DB::table('rr_risks as r')
            ->leftJoin('rr_statuses as s', 's.id', '=', 'r.status_id');
        if ($scopeDivision !== null) {
            $statusQ->where('r.division_id', $scopeDivision);
        }
        $byStatus = $statusQ
            ->selectRaw("COALESCE(s.name, 'Unspecified') as label, count(*) as c")
            ->groupBy('label')
            ->pluck('c', 'label');

        $effQ = DB::table('rr_risks as r')
            ->leftJoin('rr_mitigation_effectiveness as e', 'e.id', '=', 'r.mitigation_effectiveness_id');
        if ($scopeDivision !== null) {
            $effQ->where('r.division_id', $scopeDivision);
        }
        $byEffectiveness = $effQ
            ->selectRaw("COALESCE(e.name, 'Not Assessed') as label, count(*) as c")
            ->groupBy('label')
            ->pluck('c', 'label');

        return response()->json([
            'total' => $total,
            'unmatched_bu' => $unmatched,
            'by_rating' => $byRating,
            'by_type' => $byType,
            'by_bu' => $byBu,
            'heat' => $heat,
            'top_residual' => $topResidual,
            'by_status' => $byStatus,
            'by_effectiveness' => $byEffectiveness,
        ]);
    }
}
