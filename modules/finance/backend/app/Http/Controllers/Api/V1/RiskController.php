<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RatingBandResolver;
use App\Services\RiskAccessScope;
use App\Services\RiskWriteService;
use App\Services\StaffPortalOrgClient;
use App\Support\RiskPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RiskController extends Controller
{
    public function index(Request $request): JsonResponse
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
        $divisionPage = max(1, (int) $request->query('division_page', 1));
        $showAll = filter_var($request->query('show_all', false), FILTER_VALIDATE_BOOLEAN);

        $orgClient = app(StaffPortalOrgClient::class);
        $org = [];
        try {
            $org = $orgClient->fetchOrg();
        } catch (\Throwable) {
            $org = ['divisions' => [], 'directorates' => []];
        }
        $divisionNames = $orgClient->divisionLabelsById();
        $directorateNames = [];
        foreach ($org['directorates'] ?? [] as $dir) {
            $xid = (int) ($dir['id'] ?? 0);
            if ($xid > 0) {
                $directorateNames[$xid] = (string) ($dir['name'] ?? 'Directorate '.$xid);
            }
        }

        $base = DB::table('rr_risks as r')
            ->leftJoin('rr_risk_types as rt', 'rt.id', '=', 'r.risk_type_id')
            ->leftJoin('rr_enterprise_themes as et', 'et.id', '=', 'r.enterprise_theme_id')
            ->leftJoin('rr_likelihoods as ll', 'll.score', '=', 'r.inherent_likelihood')
            ->leftJoin('rr_impacts as ii', 'ii.score', '=', 'r.inherent_impact');

        $useReview = $year !== null && $year !== '' && $quarter !== null && $quarter !== '';
        if ($useReview) {
            $base->leftJoin('rr_risk_reviews as rev', function ($join) use ($year, $quarter) {
                $join->on('rev.risk_id', '=', 'r.id')
                    ->where('rev.year', '=', (int) $year)
                    ->where('rev.quarter', '=', (int) $quarter);
            });
            $base->leftJoin('rr_likelihoods as rll', 'rll.score', '=', 'rev.likelihood')
                ->leftJoin('rr_impacts as rii', 'rii.score', '=', 'rev.impact');
        }

        if ($scope['mode'] === 'none') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        if ($scope['mode'] === 'divisions') {
            $base->whereIn('r.division_id', $scope['division_ids']);
        }
        if ($filterDivision !== null && $filterDivision !== '') {
            $wanted = (int) $filterDivision;
            if ($scope['mode'] === 'divisions' && ! in_array($wanted, $scope['division_ids'], true)) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            $base->where('r.division_id', $wanted);
        }

        if ($filterDirectorate !== null && $filterDirectorate !== '') {
            $base->where('r.directorate_id', (int) $filterDirectorate);
        }
        if ($filterTheme !== null && $filterTheme !== '') {
            $base->where('r.enterprise_theme_id', (int) $filterTheme);
        }
        // Period filter is soft: use review scores when present, else current risk baseline.

        // Distinct divisions for pager (ordered by name then id)
        $divisionIds = (clone $base)
            ->select('r.division_id')
            ->distinct()
            ->pluck('r.division_id')
            ->map(fn ($id) => $id !== null ? (int) $id : 0)
            ->unique()
            ->values()
            ->all();

        usort($divisionIds, static function ($a, $b) use ($divisionNames) {
            $na = $divisionNames[$a] ?? ($a > 0 ? 'Division '.$a : 'Unassigned');
            $nb = $divisionNames[$b] ?? ($b > 0 ? 'Division '.$b : 'Unassigned');
            $cmp = strcasecmp($na, $nb);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a <=> $b;
        });

        $totalDivisions = count($divisionIds);
        if ($totalDivisions === 0) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'division_page' => 1,
                    'division_pages' => 0,
                    'total_divisions' => 0,
                    'division_id' => null,
                    'division_name' => null,
                    'total_rows' => 0,
                    'show_all' => $showAll,
                    'filters' => [
                        'year' => $useReview ? (int) $year : null,
                        'quarter' => $useReview ? (int) $quarter : null,
                        'division_id' => $filterDivision !== null && $filterDivision !== '' ? (int) $filterDivision : null,
                        'directorate_id' => $filterDirectorate !== null && $filterDirectorate !== '' ? (int) $filterDirectorate : null,
                        'enterprise_theme_id' => $filterTheme !== null && $filterTheme !== '' ? (int) $filterTheme : null,
                        'show_all' => $showAll,
                    ],
                    'divisions' => array_map(static function ($id) use ($divisionNames) {
                        return [
                            'division_id' => $id > 0 ? $id : null,
                            'division_name' => $divisionNames[$id] ?? ($id > 0 ? 'Division '.$id : 'Unassigned'),
                        ];
                    }, $divisionIds),
                    'directorates' => array_map(static function ($id, $name) {
                        return ['directorate_id' => (int) $id, 'directorate_name' => $name];
                    }, array_keys($directorateNames), array_values($directorateNames)),
                ],
            ]);
        }

        if ($divisionPage > $totalDivisions) {
            $divisionPage = $totalDivisions;
        }
        $pageDivisionId = $divisionIds[$divisionPage - 1];

        $select = [
            'r.id',
            'r.name',
            'r.division_id',
            'r.directorate_id',
            'r.enterprise_theme_id',
            'r.risk_type_id',
            'r.source_business_unit',
            'r.unmapped_business_unit',
            'r.workflow_state',
            'r.risk_movement',
            'r.residual_score',
            'r.residual_rating',
            'r.residual_rating_key',
            'r.rating_key_version',
            'rt.name as risk_type_name',
            'et.name as enterprise_theme_name',
        ];

        if ($useReview) {
            $select = array_merge($select, [
                DB::raw((int) $year.' as review_year'),
                DB::raw((int) $quarter.' as review_quarter'),
                DB::raw('COALESCE(rev.likelihood, r.inherent_likelihood) as likelihood_score'),
                DB::raw('COALESCE(rev.impact, r.inherent_impact) as impact_score'),
                DB::raw('COALESCE(rev.inherent_score, r.inherent_score) as inherent_score'),
                DB::raw('COALESCE(rev.inherent_rating, r.inherent_rating) as inherent_rating'),
                DB::raw('COALESCE(rev.inherent_rating_key, r.inherent_rating_key) as inherent_rating_key'),
                DB::raw('COALESCE(rev.rating_key_version, r.rating_key_version) as review_rating_key_version'),
                DB::raw('COALESCE(rll.label, ll.label) as likelihood_label'),
                DB::raw('COALESCE(rii.label, ii.label) as impact_label'),
            ]);
        } else {
            $select = array_merge($select, [
                DB::raw('NULL as review_year'),
                DB::raw('NULL as review_quarter'),
                'r.inherent_likelihood as likelihood_score',
                'r.inherent_impact as impact_score',
                'r.inherent_score as inherent_score',
                'r.inherent_rating as inherent_rating',
                'r.inherent_rating_key as inherent_rating_key',
                DB::raw('NULL as review_rating_key_version'),
                'll.label as likelihood_label',
                'ii.label as impact_label',
            ]);
        }

        $rowsQuery = (clone $base)->select($select);
        if (! $showAll) {
            if ($pageDivisionId === 0) {
                $rowsQuery->whereNull('r.division_id');
            } else {
                $rowsQuery->where('r.division_id', $pageDivisionId);
            }
        }
        $rowsQuery->orderBy('r.division_id')->orderBy('r.name')->orderBy('r.id');
        if ($showAll) {
            $rowsQuery->limit(5000);
        }

        $bandResolver = app(RatingBandResolver::class);
        $rows = $rowsQuery->get()->map(function ($row, $idx) use ($divisionNames, $directorateNames, $bandResolver) {
            $arr = (array) $row;
            $did = $arr['division_id'] !== null ? (int) $arr['division_id'] : 0;
            $arr['counter'] = $idx + 1;
            $arr['division_name'] = $divisionNames[$did] ?? ($did > 0 ? 'Division '.$did : 'Unassigned');
            $xid = $arr['directorate_id'] !== null ? (int) $arr['directorate_id'] : 0;
            $arr['directorate_name'] = $xid > 0 ? ($directorateNames[$xid] ?? 'Directorate '.$xid) : null;

            $inhVersion = isset($arr['review_rating_key_version']) && $arr['review_rating_key_version'] !== null
                ? (int) $arr['review_rating_key_version']
                : (isset($arr['rating_key_version']) && $arr['rating_key_version'] !== null ? (int) $arr['rating_key_version'] : null);
            $inhStyle = $bandResolver->styleForStored(
                $arr['inherent_rating_key'] ?? null,
                $arr['inherent_rating'] ?? null,
                $inhVersion
            );
            $resStyle = $bandResolver->styleForStored(
                $arr['residual_rating_key'] ?? null,
                $arr['residual_rating'] ?? null,
                isset($arr['rating_key_version']) && $arr['rating_key_version'] !== null ? (int) $arr['rating_key_version'] : null
            );
            $arr['inherent_fill_color'] = $inhStyle['fill_color'];
            $arr['inherent_text_color'] = $inhStyle['text_color'];
            $arr['residual_fill_color'] = $resStyle['fill_color'];
            $arr['residual_text_color'] = $resStyle['text_color'];

            $theme = trim((string) ($arr['enterprise_theme_name'] ?? ''));
            if ($theme !== '') {
                $arr['enterprise_theme_name'] = preg_replace('/^\s*\d+\.\s*/', '', $theme) ?: $theme;
            }

            return $arr;
        })->values()->all();

        if ($showAll) {
            usort($rows, static function (array $a, array $b) {
                $cmp = strcasecmp((string) ($a['division_name'] ?? ''), (string) ($b['division_name'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }

                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            });
            foreach ($rows as $i => &$row) {
                $row['counter'] = $i + 1;
            }
            unset($row);
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'division_page' => $showAll ? 1 : $divisionPage,
                'division_pages' => $showAll ? 1 : $totalDivisions,
                'total_divisions' => $totalDivisions,
                'division_id' => $showAll ? null : ($pageDivisionId > 0 ? $pageDivisionId : null),
                'division_name' => $showAll
                    ? 'All divisions'
                    : ($divisionNames[$pageDivisionId] ?? ($pageDivisionId > 0 ? 'Division '.$pageDivisionId : 'Unassigned')),
                'total_rows' => count($rows),
                'show_all' => $showAll,
                'filters' => [
                    'year' => $useReview ? (int) $year : null,
                    'quarter' => $useReview ? (int) $quarter : null,
                    'division_id' => $filterDivision !== null && $filterDivision !== '' ? (int) $filterDivision : null,
                    'directorate_id' => $filterDirectorate !== null && $filterDirectorate !== '' ? (int) $filterDirectorate : null,
                    'enterprise_theme_id' => $filterTheme !== null && $filterTheme !== '' ? (int) $filterTheme : null,
                    'show_all' => $showAll,
                ],
                'divisions' => array_map(static function ($id) use ($divisionNames) {
                    return [
                        'division_id' => $id > 0 ? $id : null,
                        'division_name' => $divisionNames[$id] ?? ($id > 0 ? 'Division '.$id : 'Unassigned'),
                    ];
                }, $divisionIds),
                'directorates' => array_values(array_map(static function ($id, $name) {
                    return ['directorate_id' => (int) $id, 'directorate_name' => $name];
                }, array_keys($directorateNames), array_values($directorateNames))),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $risk = DB::table('rr_risks')->where('id', $id)->first();
        if (! $risk) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if (! $this->canViewRisk($request, $risk)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $owners = DB::table('rr_risk_owners')->where('risk_id', $id)->get();
        $orgClient = app(StaffPortalOrgClient::class);
        $staffIds = $owners->pluck('staff_id')->map(fn ($sid) => (int) $sid)->all();
        $staffWithJobs = $orgClient->staffWithJobsForIds($staffIds);
        $owners = $owners->map(function ($o) use ($staffWithJobs) {
            $arr = (array) $o;
            $sid = (int) ($arr['staff_id'] ?? 0);
            $info = $staffWithJobs[$sid] ?? null;
            $arr['staff_name'] = $info['name'] ?? ('Staff #'.$sid);
            $arr['job_title'] = $info['job_title'] ?? null;
            $arr['staff_label'] = $info['label'] ?? ($arr['staff_name']);

            return $arr;
        })->values();
        $audit = DB::table('rr_audit_logs')
            ->where('entity_type', 'rr_risks')
            ->where('entity_id', $id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $data = (array) $risk;
        $did = isset($data['division_id']) && $data['division_id'] !== null ? (int) $data['division_id'] : 0;
        $labels = $orgClient->divisionLabelsById();
        $data['division_name'] = $did > 0
            ? ($labels[$did] ?? StaffPortalOrgClient::formatDivisionLabel(null, null, $did))
            : 'Unassigned';

        $themeId = isset($data['enterprise_theme_id']) && $data['enterprise_theme_id'] !== null
            ? (int) $data['enterprise_theme_id'] : 0;
        $typeId = isset($data['risk_type_id']) && $data['risk_type_id'] !== null
            ? (int) $data['risk_type_id'] : 0;
        $statusId = isset($data['status_id']) && $data['status_id'] !== null
            ? (int) $data['status_id'] : 0;
        $effId = isset($data['mitigation_effectiveness_id']) && $data['mitigation_effectiveness_id'] !== null
            ? (int) $data['mitigation_effectiveness_id'] : 0;

        $themeName = $themeId > 0
            ? (string) (DB::table('rr_enterprise_themes')->where('id', $themeId)->value('name') ?? '')
            : '';
        $data['enterprise_theme_name'] = $themeName !== ''
            ? preg_replace('/^\s*\d+\.\s*/', '', $themeName) ?: $themeName
            : null;
        $data['risk_type_name'] = $typeId > 0
            ? DB::table('rr_risk_types')->where('id', $typeId)->value('name')
            : null;
        $data['status_name'] = $statusId > 0
            ? DB::table('rr_statuses')->where('id', $statusId)->value('name')
            : null;
        $data['mitigation_effectiveness_name'] = $effId > 0
            ? DB::table('rr_mitigation_effectiveness')->where('id', $effId)->value('name')
            : null;

        $bandResolver = app(RatingBandResolver::class);
        $version = isset($data['rating_key_version']) && $data['rating_key_version'] !== null
            ? (int) $data['rating_key_version'] : null;
        $inh = $bandResolver->styleForStored(
            $data['inherent_rating_key'] ?? null,
            $data['inherent_rating'] ?? null,
            $version
        );
        $res = $bandResolver->styleForStored(
            $data['residual_rating_key'] ?? null,
            $data['residual_rating'] ?? null,
            $version
        );
        $data['inherent_fill_color'] = $inh['fill_color'];
        $data['inherent_text_color'] = $inh['text_color'];
        $data['residual_fill_color'] = $res['fill_color'];
        $data['residual_text_color'] = $res['text_color'];

        return response()->json([
            'data' => $data,
            'owners' => $owners,
            'audit' => $audit,
        ]);
    }

    public function store(Request $request, RiskWriteService $writer): JsonResponse
    {
        $permissions = $request->attributes->get('risk_permissions', []);
        if (! RiskPermissions::canManage($permissions) && ! RiskPermissions::canViewDivision($permissions)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:512',
            'division_id' => 'nullable|integer',
            'directorate_id' => 'nullable|integer',
            'enterprise_theme_id' => 'nullable|integer',
            'risk_type_id' => 'nullable|integer',
            'status_id' => 'nullable|integer',
            'mitigation_effectiveness_id' => 'nullable|integer',
            'inherent_likelihood' => 'nullable|integer|min:1|max:5',
            'inherent_impact' => 'nullable|integer|min:1|max:5',
            'consequence' => 'nullable|string',
            'root_causes' => 'nullable|string',
            'mitigation' => 'nullable|string',
            'management_response' => 'nullable|string',
            'timeline' => 'nullable|string|max:255',
            'action_update' => 'nullable|string',
            'date_of_update' => 'nullable|date',
            'oio_verification_notes' => 'nullable|string',
            'owner_staff_ids' => 'nullable|array',
            'owner_staff_ids.*' => 'integer',
        ]);

        try {
            $created = $writer->create(
                $data,
                (int) $request->attributes->get('risk_staff_id'),
                $permissions
            );
        } catch (RuntimeException $e) {
            $code = $e->getMessage() === 'Forbidden' ? 403 : 422;

            return response()->json(['message' => $e->getMessage()], $code);
        }

        return response()->json(['data' => $created], 201);
    }

    public function update(Request $request, int $id, RiskWriteService $writer): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:512',
            'division_id' => 'nullable|integer',
            'directorate_id' => 'nullable|integer',
            'enterprise_theme_id' => 'nullable|integer',
            'risk_type_id' => 'nullable|integer',
            'status_id' => 'nullable|integer',
            'mitigation_effectiveness_id' => 'nullable|integer',
            'inherent_likelihood' => 'nullable|integer|min:1|max:5',
            'inherent_impact' => 'nullable|integer|min:1|max:5',
            'consequence' => 'nullable|string',
            'root_causes' => 'nullable|string',
            'mitigation' => 'nullable|string',
            'management_response' => 'nullable|string',
            'timeline' => 'nullable|string|max:255',
            'action_update' => 'nullable|string',
            'date_of_update' => 'nullable|date',
            'oio_verification_notes' => 'nullable|string',
            'owner_staff_ids' => 'nullable|array',
            'owner_staff_ids.*' => 'integer',
        ]);

        try {
            $updated = $writer->update(
                $id,
                $data,
                (int) $request->attributes->get('risk_staff_id'),
                $request->attributes->get('risk_permissions', [])
            );
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $code = match ($msg) {
                'Forbidden' => 403,
                'Not found' => 404,
                default => 422,
            };

            return response()->json(['message' => $msg], $code);
        }

        return response()->json(['data' => $updated]);
    }

    private function canViewRisk(Request $request, object $risk): bool
    {
        $permissions = $request->attributes->get('risk_permissions', []);
        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        $sessionDivisionId = (int) $request->attributes->get('risk_division_id', 0);
        $scope = app(RiskAccessScope::class)->resolve($permissions, $staffId, $sessionDivisionId);

        return app(RiskAccessScope::class)->allowsDivision(
            $scope,
            isset($risk->division_id) ? (int) $risk->division_id : null
        );
    }
}
