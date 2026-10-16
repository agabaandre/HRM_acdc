<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RatingBandResolver;
use App\Services\RiskAccessScope;
use App\Services\StaffPortalOrgClient;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskDashboardController extends Controller
{
    public function __invoke(Request $request, RatingBandResolver $bands): JsonResponse
    {
        $permissions = $request->attributes->get('risk_permissions', []);
        $sessionDivisionId = (int) $request->attributes->get('risk_division_id', 0);
        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        $scope = app(RiskAccessScope::class)->resolve($permissions, $staffId, $sessionDivisionId);

        $filterDivision = $request->query('division_id');
        $filterDirectorate = $request->query('directorate_id');
        $filterTheme = $request->query('enterprise_theme_id');
        $year = $request->query('year');
        $quarter = $request->query('quarter');
        $useReview = $year !== null && $year !== '' && $quarter !== null && $quarter !== '';

        $orgClient = app(StaffPortalOrgClient::class);
        $org = [];
        try {
            $org = $orgClient->fetchOrg();
        } catch (\Throwable) {
            $org = ['divisions' => [], 'directorates' => []];
        }
        $divisionLabels = $orgClient->divisionLabelsById();
        $filterOptions = $this->filterOptions($org, $divisionLabels, $scope);

        $empty = [
            'total' => 0,
            'unmatched_bu' => 0,
            'kpis' => $this->emptyKpis(),
            'by_rating' => [],
            'by_inherent_rating' => [],
            'by_type' => [],
            'by_category' => [],
            'by_bu' => [],
            'heat' => [],
            'top_residual' => [],
            'by_status' => [],
            'by_effectiveness' => [],
            'rating_bands' => $bands->workingBands(),
            'filters' => [
                'year' => $useReview ? (int) $year : null,
                'quarter' => $useReview ? (int) $quarter : null,
                'division_id' => $filterDivision !== null && $filterDivision !== '' ? (int) $filterDivision : null,
                'directorate_id' => $filterDirectorate !== null && $filterDirectorate !== '' ? (int) $filterDirectorate : null,
                'enterprise_theme_id' => $filterTheme !== null && $filterTheme !== '' ? (int) $filterTheme : null,
            ],
            'divisions' => $filterOptions['divisions'],
            'directorates' => $filterOptions['directorates'],
        ];

        if ($scope['mode'] === 'none') {
            return response()->json($empty);
        }

        $base = $this->filteredRiskQuery(
            $scope,
            $filterDivision,
            $filterDirectorate,
            $filterTheme,
            $useReview ? (int) $year : null,
            $useReview ? (int) $quarter : null,
        );

        $scoreExpr = $useReview
            ? 'COALESCE(rev.inherent_score, r.inherent_score)'
            : 'r.inherent_score';
        $likelihoodExpr = $useReview
            ? 'COALESCE(rev.likelihood, r.inherent_likelihood)'
            : 'r.inherent_likelihood';
        $impactExpr = $useReview
            ? 'COALESCE(rev.impact, r.inherent_impact)'
            : 'r.inherent_impact';
        $inherentRatingExpr = $useReview
            ? "COALESCE(rev.inherent_rating, r.inherent_rating, 'Unrated')"
            : "COALESCE(r.inherent_rating, 'Unrated')";

        $total = (clone $base)->count();
        $unmatched = (clone $base)->whereNotNull('r.unmapped_business_unit')->count();

        $byResidualRating = (clone $base)
            ->selectRaw("COALESCE(r.residual_rating, 'Unrated') as label, count(*) as c")
            ->groupBy('label')
            ->pluck('c', 'label');

        $byInherentRating = (clone $base)
            ->selectRaw("{$inherentRatingExpr} as label, count(*) as c")
            ->groupBy('label')
            ->pluck('c', 'label');

        $byTypeRows = (clone $base)
            ->leftJoin('rr_risk_types as t', 't.id', '=', 'r.risk_type_id')
            ->selectRaw("COALESCE(t.name, 'Unspecified') as label, count(*) as c, AVG({$scoreExpr}) as avg_inherent")
            ->groupBy('label')
            ->orderByDesc('c')
            ->get();
        $byType = [];
        $byCategory = [];
        foreach ($byTypeRows as $row) {
            $label = (string) $row->label;
            $count = (int) $row->c;
            $byType[$label] = $count;
            $byCategory[] = [
                'risk_type' => $label,
                'count' => $count,
                'avg_inherent_score' => $row->avg_inherent !== null ? round((float) $row->avg_inherent, 1) : null,
                'pct_of_total' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ];
        }

        $byBuRows = (clone $base)
            ->select('r.division_id', DB::raw('count(*) as c'))
            ->groupBy('r.division_id')
            ->orderByDesc('c')
            ->limit(20)
            ->get();
        $byBu = [];
        foreach ($byBuRows as $row) {
            $did = $row->division_id !== null ? (int) $row->division_id : 0;
            $label = $did > 0
                ? ($divisionLabels[$did] ?? StaffPortalOrgClient::formatDivisionLabel(null, null, $did))
                : 'Unassigned';
            $byBu[$label] = ($byBu[$label] ?? 0) + (int) $row->c;
        }
        arsort($byBu);

        $heat = (clone $base)
            ->selectRaw("{$likelihoodExpr} as inherent_likelihood, {$impactExpr} as inherent_impact, count(*) as c")
            ->whereRaw("{$likelihoodExpr} is not null")
            ->whereRaw("{$impactExpr} is not null")
            ->groupByRaw("{$likelihoodExpr}, {$impactExpr}")
            ->get()
            ->map(static fn ($r) => [
                'likelihood' => (int) $r->inherent_likelihood,
                'impact' => (int) $r->inherent_impact,
                'count' => (int) $r->c,
            ])
            ->values()
            ->all();

        $topSelect = [
            'r.id',
            'r.name',
            'r.division_id',
            'r.residual_score',
            'r.residual_rating',
            DB::raw("{$scoreExpr} as inherent_score"),
            DB::raw("{$inherentRatingExpr} as inherent_rating"),
            'r.source_business_unit',
            'r.unmapped_business_unit',
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('rr_risks', 'residual_rating_key')) {
            $topSelect[] = 'r.residual_rating_key';
            if ($useReview) {
                $topSelect[] = DB::raw('COALESCE(rev.inherent_rating_key, r.inherent_rating_key) as inherent_rating_key');
                $topSelect[] = DB::raw('COALESCE(rev.rating_key_version, r.rating_key_version) as rating_key_version');
            } else {
                $topSelect[] = 'r.inherent_rating_key';
                $topSelect[] = 'r.rating_key_version';
            }
        }

        $topResidual = (clone $base)
            ->orderByDesc('r.residual_score')
            ->orderByRaw("{$scoreExpr} desc")
            ->limit(10)
            ->get($topSelect)
            ->map(function ($row) use ($bands, $divisionLabels) {
                $arr = (array) $row;
                $version = isset($arr['rating_key_version']) && $arr['rating_key_version'] !== null
                    ? (int) $arr['rating_key_version']
                    : null;
                $inh = $bands->styleForStored(
                    $arr['inherent_rating_key'] ?? null,
                    $arr['inherent_rating'] ?? null,
                    $version
                );
                $res = $bands->styleForStored(
                    $arr['residual_rating_key'] ?? null,
                    $arr['residual_rating'] ?? null,
                    $version
                );
                $did = isset($arr['division_id']) && $arr['division_id'] !== null ? (int) $arr['division_id'] : 0;
                if ($did > 0) {
                    $bu = $divisionLabels[$did] ?? StaffPortalOrgClient::formatDivisionLabel(null, null, $did);
                } else {
                    $bu = trim((string) ($arr['source_business_unit'] ?? ''));
                    if ($bu === '') {
                        $bu = trim((string) ($arr['unmapped_business_unit'] ?? ''));
                    }
                    if ($bu === '') {
                        $bu = 'Unassigned';
                    }
                }

                return [
                    'id' => (int) $arr['id'],
                    'name' => (string) $arr['name'],
                    'business_unit' => $bu,
                    'inherent_score' => $arr['inherent_score'] !== null ? (int) $arr['inherent_score'] : null,
                    'inherent_rating' => $arr['inherent_rating'] ?? null,
                    'residual_score' => $arr['residual_score'] !== null ? (int) $arr['residual_score'] : null,
                    'residual_rating' => $arr['residual_rating'] ?? null,
                    'inherent_fill_color' => $inh['fill_color'],
                    'inherent_text_color' => $inh['text_color'],
                    'residual_fill_color' => $res['fill_color'],
                    'residual_text_color' => $res['text_color'],
                ];
            })
            ->values()
            ->all();

        $byStatus = (clone $base)
            ->leftJoin('rr_statuses as s', 's.id', '=', 'r.status_id')
            ->selectRaw("COALESCE(s.name, 'Unspecified') as label, count(*) as c")
            ->groupBy('label')
            ->pluck('c', 'label');

        $byEffectiveness = (clone $base)
            ->leftJoin('rr_mitigation_effectiveness as e', 'e.id', '=', 'r.mitigation_effectiveness_id')
            ->selectRaw("COALESCE(e.name, 'Not Assessed') as label, count(*) as c")
            ->groupBy('label')
            ->pluck('c', 'label');

        $kpis = $this->buildKpis($base, $scoreExpr, $byInherentRating, $byStatus, $byEffectiveness, $total);

        return response()->json([
            'total' => $total,
            'unmatched_bu' => $unmatched,
            'kpis' => $kpis,
            'by_rating' => $byResidualRating,
            'by_inherent_rating' => $byInherentRating,
            'by_type' => $byType,
            'by_category' => $byCategory,
            'by_bu' => $byBu,
            'heat' => $heat,
            'top_residual' => $topResidual,
            'by_status' => $byStatus,
            'by_effectiveness' => $byEffectiveness,
            'rating_bands' => $bands->workingBands(),
            'filters' => [
                'year' => $useReview ? (int) $year : null,
                'quarter' => $useReview ? (int) $quarter : null,
                'division_id' => $filterDivision !== null && $filterDivision !== '' ? (int) $filterDivision : null,
                'directorate_id' => $filterDirectorate !== null && $filterDirectorate !== '' ? (int) $filterDirectorate : null,
                'enterprise_theme_id' => $filterTheme !== null && $filterTheme !== '' ? (int) $filterTheme : null,
            ],
            'divisions' => $filterOptions['divisions'],
            'directorates' => $filterOptions['directorates'],
        ]);
    }

    /**
     * @param  array{mode:string,division_ids?:list<int>}  $scope
     */
    private function filteredRiskQuery(
        array $scope,
        mixed $filterDivision,
        mixed $filterDirectorate,
        mixed $filterTheme,
        ?int $year,
        ?int $quarter,
    ): Builder {
        $q = DB::table('rr_risks as r');

        if ($year !== null && $quarter !== null) {
            $q->leftJoin('rr_risk_reviews as rev', function ($join) use ($year, $quarter) {
                $join->on('rev.risk_id', '=', 'r.id')
                    ->where('rev.year', '=', $year)
                    ->where('rev.quarter', '=', $quarter);
            });
        }

        if ($scope['mode'] === 'divisions') {
            $q->whereIn('r.division_id', $scope['division_ids'] ?? []);
        }
        if ($filterDivision !== null && $filterDivision !== '') {
            $wanted = (int) $filterDivision;
            if (($scope['mode'] ?? '') === 'divisions' && ! in_array($wanted, $scope['division_ids'] ?? [], true)) {
                $q->whereRaw('1 = 0');
            } else {
                $q->where('r.division_id', $wanted);
            }
        }
        if ($filterDirectorate !== null && $filterDirectorate !== '') {
            $q->where('r.directorate_id', (int) $filterDirectorate);
        }
        if ($filterTheme !== null && $filterTheme !== '') {
            $q->where('r.enterprise_theme_id', (int) $filterTheme);
        }

        return $q;
    }

    /**
     * @param  array<string, mixed>  $org
     * @param  array<int, string>  $divisionLabels
     * @param  array{mode:string,division_ids?:list<int>}  $scope
     * @return array{divisions:list<array{division_id:?int,division_name:string}>,directorates:list<array{directorate_id:int,directorate_name:string}>}
     */
    private function filterOptions(array $org, array $divisionLabels, array $scope): array
    {
        $divisions = [];
        foreach ($org['divisions'] ?? [] as $div) {
            $id = (int) ($div['division_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if (($scope['mode'] ?? '') === 'divisions' && ! in_array($id, $scope['division_ids'] ?? [], true)) {
                continue;
            }
            $divisions[] = [
                'division_id' => $id,
                'division_name' => $divisionLabels[$id]
                    ?? StaffPortalOrgClient::formatDivisionLabel(
                        (string) ($div['division_name'] ?? ''),
                        (string) ($div['division_short_name'] ?? ''),
                        $id
                    ),
            ];
        }
        usort($divisions, static fn ($a, $b) => strcasecmp($a['division_name'], $b['division_name']));

        $directorates = [];
        foreach ($org['directorates'] ?? [] as $dir) {
            $id = (int) ($dir['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $directorates[] = [
                'directorate_id' => $id,
                'directorate_name' => (string) ($dir['name'] ?? 'Directorate '.$id),
            ];
        }
        usort($directorates, static fn ($a, $b) => strcasecmp($a['directorate_name'], $b['directorate_name']));

        return ['divisions' => $divisions, 'directorates' => $directorates];
    }

    /**
     * @param  Builder  $base
     * @param  \Illuminate\Support\Collection<string, int|string>  $byInherent
     * @param  \Illuminate\Support\Collection<string, int|string>  $byStatus
     * @param  \Illuminate\Support\Collection<string, int|string>  $byEffectiveness
     * @return array<string, int|float>
     */
    private function buildKpis($base, string $scoreExpr, $byInherent, $byStatus, $byEffectiveness, int $total): array
    {
        $avgInherent = (clone $base)->avg(DB::raw($scoreExpr));
        $avgResidual = (clone $base)->avg('r.residual_score');
        $avgI = $avgInherent !== null ? round((float) $avgInherent, 1) : 0.0;
        $avgR = $avgResidual !== null ? round((float) $avgResidual, 1) : 0.0;
        $reduction = $avgI > 0 ? round((($avgI - $avgR) / $avgI) * 100, 1) : 0.0;

        $assessed = 0;
        $notAssessed = 0;
        foreach ($byEffectiveness as $label => $count) {
            $n = (int) $count;
            if (strcasecmp((string) $label, 'Not Assessed') === 0) {
                $notAssessed += $n;
            } else {
                $assessed += $n;
            }
        }
        if ($assessed + $notAssessed === 0 && $total > 0) {
            $notAssessed = $total;
        }

        $openExtended = 0;
        $notUpdated = 0;
        foreach ($byStatus as $label => $count) {
            $n = (int) $count;
            $l = strtolower((string) $label);
            if (str_contains($l, 'open') || str_contains($l, 'extend')) {
                $openExtended += $n;
            }
            if (str_contains($l, 'not updated') || str_contains($l, 'unspecified') || $l === '0') {
                $notUpdated += $n;
            }
        }

        $bandCount = static function (string $key) use ($byInherent): int {
            foreach ($byInherent as $label => $count) {
                if (strcasecmp((string) $label, $key) === 0) {
                    return (int) $count;
                }
            }

            return 0;
        };

        return [
            'total_risks' => $total,
            'critical_inherent' => $bandCount('Critical'),
            'high_inherent' => $bandCount('High'),
            'medium_inherent' => $bandCount('Medium'),
            'low_inherent' => $bandCount('Low'),
            'avg_inherent_score' => $avgI,
            'avg_residual_score' => $avgR,
            'overall_risk_reduction_pct' => $reduction,
            'residual_assessed' => $assessed,
            'not_yet_assessed' => $notAssessed,
            'status_open_extended' => $openExtended,
            'status_not_updated' => $notUpdated,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyKpis(): array
    {
        return [
            'total_risks' => 0,
            'critical_inherent' => 0,
            'high_inherent' => 0,
            'medium_inherent' => 0,
            'low_inherent' => 0,
            'avg_inherent_score' => 0,
            'avg_residual_score' => 0,
            'overall_risk_reduction_pct' => 0,
            'residual_assessed' => 0,
            'not_yet_assessed' => 0,
            'status_open_extended' => 0,
            'status_not_updated' => 0,
        ];
    }
}
