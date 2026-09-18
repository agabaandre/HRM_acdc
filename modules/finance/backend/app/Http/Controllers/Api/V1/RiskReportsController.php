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
use Illuminate\Support\Facades\Schema;

class RiskReportsController extends Controller
{
    public function catalog(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'key' => 'enterprise-themes',
                    'title' => 'Enterprise Risk Register by Theme',
                    'description' => 'High-level risks by enterprise risk theme. Drill into a theme to see divisions and risks.',
                    'path' => '/reports/enterprise-themes',
                ],
                [
                    'key' => 'heat-map',
                    'title' => 'Enterprise Risk Heat Map',
                    'description' => 'Principal institutional risks plotted by impact and likelihood for the selected period.',
                    'path' => '/reports/heat-map',
                ],
            ],
        ]);
    }

    public function enterpriseThemes(Request $request, RatingBandResolver $bands): JsonResponse
    {
        [$scope, $filters, $useReview, $year, $quarter] = $this->resolveScopeAndFilters($request);
        $orgClient = app(StaffPortalOrgClient::class);
        $filterOptions = $this->filterOptions($orgClient, $scope);

        if ($scope['mode'] === 'none') {
            return response()->json([
                'data' => [],
                'filters' => $filters,
                'divisions' => $filterOptions['divisions'],
                'directorates' => $filterOptions['directorates'],
                'rating_bands' => $bands->workingBands(),
            ]);
        }

        $base = $this->filteredRiskQuery(
            $scope,
            $filters['division_id'],
            $filters['directorate_id'],
            null,
            $useReview ? $year : null,
            $useReview ? $quarter : null,
        );

        $scoreExpr = $useReview
            ? 'COALESCE(rev.inherent_score, r.inherent_score)'
            : 'r.inherent_score';
        $inherentRatingExpr = $useReview
            ? "COALESCE(rev.inherent_rating, r.inherent_rating)"
            : 'r.inherent_rating';
        $mitigationExpr = $useReview
            ? 'COALESCE(rev.mitigation_strategy, r.mitigation)'
            : 'r.mitigation';
        $timelineExpr = $useReview
            ? 'COALESCE(rev.timeline, r.timeline)'
            : 'r.timeline';

        $riskRows = (clone $base)
            ->whereNotNull('r.enterprise_theme_id')
            ->select([
                'r.id',
                'r.enterprise_theme_id',
                'r.division_id',
                'r.name',
                'r.mitigation',
                'r.management_response',
                'r.timeline',
                'r.action_update',
                'r.residual_score',
                'r.residual_rating',
                DB::raw("{$scoreExpr} as inherent_score"),
                DB::raw("{$inherentRatingExpr} as inherent_rating"),
                DB::raw("{$mitigationExpr} as assurance_text"),
                DB::raw("{$timelineExpr} as planned_timeline"),
            ])
            ->when(Schema::hasColumn('rr_risks', 'residual_rating_key'), function ($q) use ($useReview) {
                $q->addSelect('r.residual_rating_key');
                if ($useReview) {
                    $q->addSelect(DB::raw('COALESCE(rev.inherent_rating_key, r.inherent_rating_key) as inherent_rating_key'));
                    $q->addSelect(DB::raw('COALESCE(rev.rating_key_version, r.rating_key_version) as rating_key_version'));
                } else {
                    $q->addSelect('r.inherent_rating_key', 'r.rating_key_version');
                }
            })
            ->get();

        $riskIds = $riskRows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownersByRisk = $this->ownersByRiskIds($riskIds, $orgClient);

        $byTheme = [];
        foreach ($riskRows as $row) {
            $tid = (int) $row->enterprise_theme_id;
            if ($tid < 1) {
                continue;
            }
            $byTheme[$tid][] = $row;
        }

        $themes = DB::table('rr_enterprise_themes')->orderBy('sort_order')->orderBy('id')->get();
        $data = [];
        $index = 0;
        foreach ($themes as $theme) {
            $index++;
            $tid = (int) $theme->id;
            $rows = $byTheme[$tid] ?? [];
            if ($rows === [] && ($filters['division_id'] || $filters['directorate_id'])) {
                continue;
            }

            $principal = null;
            foreach ($rows as $row) {
                if ($principal === null) {
                    $principal = $row;
                    continue;
                }
                $pRes = (int) ($principal->residual_score ?? 0);
                $rRes = (int) ($row->residual_score ?? 0);
                if ($rRes > $pRes) {
                    $principal = $row;
                    continue;
                }
                if ($rRes === $pRes) {
                    $pInh = (int) ($principal->inherent_score ?? 0);
                    $rInh = (int) ($row->inherent_score ?? 0);
                    if ($rInh > $pInh) {
                        $principal = $row;
                    }
                }
            }

            $ownerLabels = [];
            $seenOwners = [];
            foreach ($rows as $row) {
                foreach ($ownersByRisk[(int) $row->id] ?? [] as $owner) {
                    $sid = (int) $owner['staff_id'];
                    if (isset($seenOwners[$sid])) {
                        continue;
                    }
                    $seenOwners[$sid] = true;
                    $ownerLabels[] = $owner['label'];
                }
            }

            $inhScore = $principal?->inherent_score !== null ? (int) $principal->inherent_score : null;
            $resScore = $principal?->residual_score !== null ? (int) $principal->residual_score : null;
            $version = $principal && isset($principal->rating_key_version) && $principal->rating_key_version !== null
                ? (int) $principal->rating_key_version
                : null;
            $inhStyle = $bands->styleForStored(
                $principal?->inherent_rating_key ?? null,
                $principal?->inherent_rating ?? null,
                $version
            );
            $resStyle = $bands->styleForStored(
                $principal?->residual_rating_key ?? null,
                $principal?->residual_rating ?? null,
                $version
            );

            $code = trim((string) ($theme->code ?? ''));
            $ref = $code !== ''
                ? (ctype_digit($code) ? 'ER-'.str_pad($code, 2, '0', STR_PAD_LEFT) : $code)
                : 'ER-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);

            $plannedParts = [];
            if ($principal) {
                foreach ([
                    trim((string) ($principal->management_response ?? '')),
                    trim((string) ($principal->planned_timeline ?? '')),
                    trim((string) ($principal->action_update ?? '')),
                ] as $part) {
                    if ($part !== '') {
                        $plannedParts[] = $part;
                    }
                }
            }
            $planned = $plannedParts !== [] ? implode("\n", $plannedParts) : '';

            $data[] = [
                'enterprise_theme_id' => $tid,
                'ref' => $ref,
                'theme_name' => $this->cleanThemeName((string) $theme->name),
                'theme_name_raw' => (string) $theme->name,
                'risk_count' => count($rows),
                'division_count' => count(array_unique(array_map(
                    static fn ($r) => $r->division_id !== null ? (int) $r->division_id : 0,
                    $rows
                ))),
                'strategic_risk_statement' => $principal ? (string) $principal->name : null,
                'existing_assurance' => $principal ? (trim((string) ($principal->assurance_text ?? '')) ?: null) : null,
                'planned_management_actions' => $planned !== '' ? $planned : null,
                'primary_owners' => $ownerLabels,
                'inherent_score' => $inhScore,
                'inherent_rating' => $principal?->inherent_rating ?? null,
                'residual_score' => $resScore,
                'residual_rating' => $principal?->residual_rating ?? null,
                'inherent_fill_color' => $inhStyle['fill_color'],
                'inherent_text_color' => $inhStyle['text_color'],
                'residual_fill_color' => $resStyle['fill_color'],
                'residual_text_color' => $resStyle['text_color'],
            ];
        }

        return response()->json([
            'data' => $data,
            'filters' => $filters,
            'divisions' => $filterOptions['divisions'],
            'directorates' => $filterOptions['directorates'],
            'rating_bands' => $bands->workingBands(),
        ]);
    }

    public function enterpriseThemeShow(Request $request, int $id, RatingBandResolver $bands): JsonResponse
    {
        $theme = DB::table('rr_enterprise_themes')->where('id', $id)->first();
        if (! $theme) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        [$scope, $filters, $useReview, $year, $quarter] = $this->resolveScopeAndFilters($request);
        $filters['enterprise_theme_id'] = $id;
        $orgClient = app(StaffPortalOrgClient::class);
        $divisionLabels = $orgClient->divisionLabelsById();
        $filterOptions = $this->filterOptions($orgClient, $scope);

        if ($scope['mode'] === 'none') {
            return response()->json([
                'theme' => [
                    'id' => (int) $theme->id,
                    'ref' => $this->themeRef($theme),
                    'name' => $this->cleanThemeName((string) $theme->name),
                ],
                'divisions' => [],
                'filters' => $filters,
                'filter_divisions' => $filterOptions['divisions'],
                'filter_directorates' => $filterOptions['directorates'],
                'rating_bands' => $bands->workingBands(),
            ]);
        }

        $base = $this->filteredRiskQuery(
            $scope,
            $filters['division_id'],
            $filters['directorate_id'],
            $id,
            $useReview ? $year : null,
            $useReview ? $quarter : null,
        );

        $scoreExpr = $useReview
            ? 'COALESCE(rev.inherent_score, r.inherent_score)'
            : 'r.inherent_score';
        $inherentRatingExpr = $useReview
            ? "COALESCE(rev.inherent_rating, r.inherent_rating)"
            : 'r.inherent_rating';
        $mitigationExpr = $useReview
            ? 'COALESCE(rev.mitigation_strategy, r.mitigation)'
            : 'r.mitigation';
        $timelineExpr = $useReview
            ? 'COALESCE(rev.timeline, r.timeline)'
            : 'r.timeline';

        $riskRows = (clone $base)
            ->select([
                'r.id',
                'r.division_id',
                'r.name',
                'r.consequence',
                'r.management_response',
                'r.action_update',
                'r.residual_score',
                'r.residual_rating',
                DB::raw("{$scoreExpr} as inherent_score"),
                DB::raw("{$inherentRatingExpr} as inherent_rating"),
                DB::raw("{$mitigationExpr} as assurance_text"),
                DB::raw("{$timelineExpr} as planned_timeline"),
            ])
            ->when(Schema::hasColumn('rr_risks', 'residual_rating_key'), function ($q) use ($useReview) {
                $q->addSelect('r.residual_rating_key');
                if ($useReview) {
                    $q->addSelect(DB::raw('COALESCE(rev.inherent_rating_key, r.inherent_rating_key) as inherent_rating_key'));
                    $q->addSelect(DB::raw('COALESCE(rev.rating_key_version, r.rating_key_version) as rating_key_version'));
                } else {
                    $q->addSelect('r.inherent_rating_key', 'r.rating_key_version');
                }
            })
            ->orderBy('r.division_id')
            ->orderByDesc('r.residual_score')
            ->orderBy('r.name')
            ->get();

        $ownersByRisk = $this->ownersByRiskIds(
            $riskRows->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $orgClient
        );

        $grouped = [];
        foreach ($riskRows as $row) {
            $did = $row->division_id !== null ? (int) $row->division_id : 0;
            $grouped[$did][] = $row;
        }

        uksort($grouped, static function ($a, $b) use ($divisionLabels) {
            $na = $divisionLabels[$a] ?? ($a > 0 ? 'Division '.$a : 'Unassigned');
            $nb = $divisionLabels[$b] ?? ($b > 0 ? 'Division '.$b : 'Unassigned');
            $cmp = strcasecmp($na, $nb);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a <=> $b;
        });

        $divisionsOut = [];
        foreach ($grouped as $did => $rows) {
            $risks = [];
            foreach ($rows as $row) {
                $version = isset($row->rating_key_version) && $row->rating_key_version !== null
                    ? (int) $row->rating_key_version
                    : null;
                $inhStyle = $bands->styleForStored(
                    $row->inherent_rating_key ?? null,
                    $row->inherent_rating ?? null,
                    $version
                );
                $resStyle = $bands->styleForStored(
                    $row->residual_rating_key ?? null,
                    $row->residual_rating ?? null,
                    $version
                );

                $plannedParts = array_filter([
                    trim((string) ($row->management_response ?? '')),
                    trim((string) ($row->planned_timeline ?? '')),
                    trim((string) ($row->action_update ?? '')),
                ], static fn ($p) => $p !== '');

                $risks[] = [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'consequence' => $row->consequence !== null ? (string) $row->consequence : null,
                    'existing_assurance' => trim((string) ($row->assurance_text ?? '')) ?: null,
                    'planned_management_actions' => $plannedParts !== [] ? implode("\n", $plannedParts) : null,
                    'primary_owners' => array_map(
                        static fn ($o) => $o['label'],
                        $ownersByRisk[(int) $row->id] ?? []
                    ),
                    'inherent_score' => $row->inherent_score !== null ? (int) $row->inherent_score : null,
                    'inherent_rating' => $row->inherent_rating ?? null,
                    'residual_score' => $row->residual_score !== null ? (int) $row->residual_score : null,
                    'residual_rating' => $row->residual_rating ?? null,
                    'inherent_fill_color' => $inhStyle['fill_color'],
                    'inherent_text_color' => $inhStyle['text_color'],
                    'residual_fill_color' => $resStyle['fill_color'],
                    'residual_text_color' => $resStyle['text_color'],
                ];
            }

            $divisionsOut[] = [
                'division_id' => $did > 0 ? $did : null,
                'division_name' => $divisionLabels[$did] ?? ($did > 0 ? 'Division '.$did : 'Unassigned'),
                'risks' => $risks,
            ];
        }

        return response()->json([
            'theme' => [
                'id' => (int) $theme->id,
                'ref' => $this->themeRef($theme),
                'name' => $this->cleanThemeName((string) $theme->name),
            ],
            'divisions' => $divisionsOut,
            'filters' => $filters,
            'filter_divisions' => $filterOptions['divisions'],
            'filter_directorates' => $filterOptions['directorates'],
            'rating_bands' => $bands->workingBands(),
        ]);
    }

    public function heatMap(Request $request, RatingBandResolver $bands): JsonResponse
    {
        [$scope, $filters, $useReview, $year, $quarter] = $this->resolveScopeAndFilters($request);
        $orgClient = app(StaffPortalOrgClient::class);
        $filterOptions = $this->filterOptions($orgClient, $scope);

        if ($scope['mode'] === 'none') {
            return response()->json([
                'points' => [],
                'filters' => $filters,
                'divisions' => $filterOptions['divisions'],
                'directorates' => $filterOptions['directorates'],
                'rating_bands' => $bands->workingBands(),
            ]);
        }

        $base = $this->filteredRiskQuery(
            $scope,
            $filters['division_id'],
            $filters['directorate_id'],
            $filters['enterprise_theme_id'],
            $useReview ? $year : null,
            $useReview ? $quarter : null,
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
            ? "COALESCE(rev.inherent_rating, r.inherent_rating)"
            : 'r.inherent_rating';

        $themes = DB::table('rr_enterprise_themes')->orderBy('sort_order')->orderBy('id')->get();
        $points = [];
        $index = 0;
        foreach ($themes as $theme) {
            $index++;
            $tid = (int) $theme->id;
            $row = (clone $base)
                ->where('r.enterprise_theme_id', $tid)
                ->orderByDesc('r.residual_score')
                ->orderByRaw("{$scoreExpr} desc")
                ->select([
                    'r.id',
                    'r.name',
                    'r.residual_score',
                    'r.residual_rating',
                    DB::raw("{$scoreExpr} as inherent_score"),
                    DB::raw("{$inherentRatingExpr} as inherent_rating"),
                    DB::raw("{$likelihoodExpr} as likelihood"),
                    DB::raw("{$impactExpr} as impact"),
                ])
                ->first();
            if (! $row || $row->likelihood === null || $row->impact === null) {
                continue;
            }

            $resScore = $row->residual_score !== null ? (int) $row->residual_score : null;
            $resStyle = $resScore !== null
                ? $bands->resolve($resScore)
                : $bands->styleForStored(null, $row->residual_rating ?? null, null);

            $points[] = [
                'enterprise_theme_id' => $tid,
                'ref' => 'R'.$index,
                'theme_name' => $this->cleanThemeName((string) $theme->name),
                'likelihood' => (int) $row->likelihood,
                'impact' => (int) $row->impact,
                'residual_score' => $resScore,
                'residual_rating' => $row->residual_rating ?? ($resStyle['rating'] ?? null),
                'fill_color' => $resStyle['fill_color'] ?? '#94A3B8',
                'text_color' => $resStyle['text_color'] ?? '#0F172A',
                'principal_risk_id' => (int) $row->id,
                'principal_risk_name' => (string) $row->name,
            ];
        }

        return response()->json([
            'points' => $points,
            'filters' => $filters,
            'divisions' => $filterOptions['divisions'],
            'directorates' => $filterOptions['directorates'],
            'rating_bands' => $bands->workingBands(),
        ]);
    }

    /**
     * @return array{0:array{mode:string,division_ids?:list<int>},1:array{year:?int,quarter:?int,division_id:?int,directorate_id:?int,enterprise_theme_id:?int},2:bool,3:?int,4:?int}
     */
    private function resolveScopeAndFilters(Request $request): array
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

        $filters = [
            'year' => $useReview ? (int) $year : null,
            'quarter' => $useReview ? (int) $quarter : null,
            'division_id' => $filterDivision !== null && $filterDivision !== '' ? (int) $filterDivision : null,
            'directorate_id' => $filterDirectorate !== null && $filterDirectorate !== '' ? (int) $filterDirectorate : null,
            'enterprise_theme_id' => $filterTheme !== null && $filterTheme !== '' ? (int) $filterTheme : null,
        ];

        return [$scope, $filters, $useReview, $useReview ? (int) $year : null, $useReview ? (int) $quarter : null];
    }

    /**
     * @param  array{mode:string,division_ids?:list<int>}  $scope
     */
    private function filteredRiskQuery(
        array $scope,
        ?int $filterDivision,
        ?int $filterDirectorate,
        ?int $filterTheme,
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
        if (($scope['mode'] ?? '') === 'divisions') {
            $q->whereIn('r.division_id', $scope['division_ids'] ?? []);
        }
        if ($filterDivision !== null) {
            if (($scope['mode'] ?? '') === 'divisions' && ! in_array($filterDivision, $scope['division_ids'] ?? [], true)) {
                $q->whereRaw('1 = 0');
            } else {
                $q->where('r.division_id', $filterDivision);
            }
        }
        if ($filterDirectorate !== null) {
            $q->where('r.directorate_id', $filterDirectorate);
        }
        if ($filterTheme !== null) {
            $q->where('r.enterprise_theme_id', $filterTheme);
        }

        return $q;
    }

    /**
     * @param  array{mode:string,division_ids?:list<int>}  $scope
     * @return array{divisions:list<array{division_id:?int,division_name:string}>,directorates:list<array{directorate_id:int,directorate_name:string}>}
     */
    private function filterOptions(StaffPortalOrgClient $orgClient, array $scope): array
    {
        $org = [];
        try {
            $org = $orgClient->fetchOrg();
        } catch (\Throwable) {
            $org = ['divisions' => [], 'directorates' => []];
        }
        $divisionLabels = $orgClient->divisionLabelsById();

        $divisions = [];
        foreach ($org['divisions'] ?? [] as $div) {
            $id = (int) ($div['id'] ?? $div['division_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if (($scope['mode'] ?? '') === 'divisions' && ! in_array($id, $scope['division_ids'] ?? [], true)) {
                continue;
            }
            $divisions[] = [
                'division_id' => $id,
                'division_name' => $divisionLabels[$id] ?? (string) ($div['name'] ?? ('Division '.$id)),
            ];
        }

        $directorates = [];
        foreach ($org['directorates'] ?? [] as $dir) {
            $id = (int) ($dir['id'] ?? $dir['directorate_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $directorates[] = [
                'directorate_id' => $id,
                'directorate_name' => (string) ($dir['name'] ?? ('Directorate '.$id)),
            ];
        }

        return ['divisions' => $divisions, 'directorates' => $directorates];
    }

    /**
     * @param  list<int>  $riskIds
     * @return array<int, list<array{staff_id:int,name:string,job_title:?string,label:string}>>
     */
    private function ownersByRiskIds(array $riskIds, StaffPortalOrgClient $orgClient): array
    {
        if ($riskIds === []) {
            return [];
        }
        $ownerRows = DB::table('rr_risk_owners')
            ->whereIn('risk_id', $riskIds)
            ->orderBy('id')
            ->get(['risk_id', 'staff_id']);
        $staffIds = $ownerRows->pluck('staff_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $staffMap = $orgClient->staffWithJobsForIds($staffIds);

        $out = [];
        foreach ($ownerRows as $row) {
            $rid = (int) $row->risk_id;
            $sid = (int) $row->staff_id;
            $out[$rid][] = $staffMap[$sid] ?? [
                'staff_id' => $sid,
                'name' => 'Staff #'.$sid,
                'job_title' => null,
                'label' => 'Staff #'.$sid,
            ];
        }

        return $out;
    }

    private function cleanThemeName(string $name): string
    {
        $cleaned = preg_replace('/^\s*\d+\.\s*/', '', $name) ?: $name;

        return trim($cleaned);
    }

    private function themeRef(object $theme): string
    {
        $code = trim((string) ($theme->code ?? ''));
        if ($code !== '') {
            return ctype_digit($code) ? 'ER-'.str_pad($code, 2, '0', STR_PAD_LEFT) : $code;
        }
        $order = (int) ($theme->sort_order ?? 0);

        return 'ER-'.str_pad((string) max(1, $order), 2, '0', STR_PAD_LEFT);
    }
}
