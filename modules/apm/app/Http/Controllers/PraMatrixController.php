<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\Staff;
use App\Services\Pra\PraActivitiesCacheService;
use App\Services\Pra\PraActivityUsageService;
use App\Services\Pra\PraMatrixImportService;
use App\Services\Pra\PraSettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PraMatrixController extends Controller
{
    public function __construct(
        protected PraSettingsService $settings,
        protected PraActivitiesCacheService $cache,
        protected PraMatrixImportService $import,
        protected PraActivityUsageService $usages,
    ) {}

    public function activities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2035'],
            'quarter' => ['nullable', 'in:Q1,Q2,Q3,Q4,q1,q2,q3,q4'],
            'division_id' => ['nullable', 'integer'],
            'matrix_id' => ['nullable', 'integer'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $divisionId = (int) ($validated['division_id'] ?? 0);
        if ($divisionId < 1 && ! empty($validated['matrix_id'])) {
            $matrix = Matrix::query()->find((int) $validated['matrix_id']);
            $divisionId = (int) ($matrix?->division_id ?? 0);
        }
        if ($divisionId < 1) {
            $divisionId = (int) (user_session('division_id') ?? 0);
        }
        if ($divisionId < 1) {
            return response()->json(['success' => false, 'message' => 'Division is required.'], 422);
        }

        if (! $this->settings->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'RBM is not configured. Set it up under Divisions → RBM integration.',
            ], 422);
        }

        $year = (int) $validated['year'];
        $quarter = ! empty($validated['quarter']) ? strtoupper((string) $validated['quarter']) : null;

        if (! empty($validated['refresh'])) {
            // PRA workplan sync can take several minutes across divisions.
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            try {
                $this->cache->refresh($year);
            } catch (Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
            }
        }

        // Return all division activities; quarter is only a matches_quarter hint for UI filtering.
        $activities = $this->cache->activitiesForDivision($divisionId, $year, $quarter);
        $praCodes = $this->cache->praCodesForLocalDivision($divisionId);
        $praIds = array_map(fn ($r) => (int) ($r['pra_activity_id'] ?? 0), $activities);
        $usageMap = $this->usages->usagesByPraIds($praIds);

        foreach ($activities as &$row) {
            $pid = (int) ($row['pra_activity_id'] ?? 0);
            $existing = $usageMap[$pid] ?? [];
            $row['existing'] = $existing;
            $row['already_exists'] = $existing !== [];
            $row['has_submitted'] = (bool) array_filter($existing, fn ($u) => ! empty($u['is_submitted']));
        }
        unset($row);

        return response()->json([
            'success' => true,
            'data' => [
                'activities' => $activities,
                'meta' => [
                    'division_id' => $divisionId,
                    'year' => $year,
                    'quarter' => $quarter,
                    'pra_codes' => $praCodes,
                    'count' => count($activities),
                    'existing_count' => count(array_filter($activities, fn ($r) => ! empty($r['already_exists']))),
                    'configured' => true,
                ],
            ],
        ]);
    }

    public function showActivity(Request $request, int $praActivityId): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        $quarter = $request->query('quarter');
        $quarter = is_string($quarter) && $quarter !== '' ? strtoupper($quarter) : null;

        $row = $this->cache->findActivity($praActivityId, $year);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'RBM activity not found in cache.'], 404);
        }

        $matrixId = (int) $request->query('matrix_id', 0);
        $prefill = null;
        if ($matrixId > 0) {
            $matrix = Matrix::query()->find($matrixId);
            if ($matrix) {
                $prefill = $this->import->prefillForForm($row, $matrix);
            }
        } else {
            $prefill = $this->import->prefillForMemo($row, $year, $quarter);
        }

        $existing = $this->usages->usagesByPraIds([$praActivityId])[$praActivityId] ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'activity' => $row,
                'prefill' => $prefill,
                'existing' => $existing,
            ],
        ]);
    }

    public function createMatrix(Request $request): JsonResponse
    {
        $isAdmin = (int) (user_session('user_role') ?? session('user.user_role')) === 10;
        $userDivisionId = (int) (user_session('division_id') ?? session('user.division_id') ?? 0);
        $userStaffId = (int) (function_exists('resolved_session_staff_id')
            ? (resolved_session_staff_id() ?? 0)
            : (user_session('staff_id') ?? user_session('auth_staff_id') ?? session('user.auth_staff_id') ?? 0));

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2035'],
            'quarter' => ['required', 'in:Q1,Q2,Q3,Q4'],
            'division_id' => ['nullable', 'integer'],
            'focal_person_id' => ['nullable', 'integer'],
            'pra_activity_ids' => ['required', 'array', 'min:1'],
            'pra_activity_ids.*' => ['integer'],
            'allow_existing' => ['nullable', 'boolean'],
        ]);

        $divisionId = $isAdmin
            ? (int) ($validated['division_id'] ?? $userDivisionId)
            : $userDivisionId;
        $focalPersonId = $isAdmin
            ? (int) ($validated['focal_person_id'] ?? $userStaffId)
            : $userStaffId;

        if ($divisionId < 1 || $focalPersonId < 1 || $userStaffId < 1) {
            return response()->json(['success' => false, 'message' => 'Missing division or staff context.'], 422);
        }

        $usageMap = $this->usages->usagesByPraIds($validated['pra_activity_ids']);
        $existingIds = array_keys(array_filter($usageMap, fn ($list) => $list !== []));
        if ($existingIds !== [] && empty($validated['allow_existing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some selected RBM activities already exist in APM. Confirm to continue anyway.',
                'data' => [
                    'existing_pra_activity_ids' => $existingIds,
                    'existing' => $usageMap,
                    'requires_confirmation' => true,
                ],
            ], 409);
        }

        try {
            $result = $this->import->createMatrixWithActivities(
                $divisionId,
                $focalPersonId,
                $userStaffId,
                (int) $validated['year'],
                (string) $validated['quarter'],
                $validated['pra_activity_ids'],
            );
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $matrix = $result['matrix'];
        $appended = ! empty($result['appended']);
        $recipients = Staff::active()
            ->where('division_id', $divisionId)
            ->whereNotNull('work_email')
            ->get();
        if (! $appended && function_exists('send_matrix_notification')) {
            send_matrix_notification($matrix, 'created', $recipients);
        }

        $warning = $existingIds !== []
            ? ' Note: some RBM activities were already linked elsewhere in APM.'
            : '';

        $count = count($result['activities']);
        $message = $appended
            ? sprintf(
                'Added %d RBM activit%s to the existing draft matrix.%s Review and complete details on the matrix page.',
                $count,
                $count === 1 ? 'y' : 'ies',
                $warning
            )
            : sprintf(
                'Matrix created with %d RBM activit%s.%s Review and complete details on the matrix page.',
                $count,
                $count === 1 ? 'y' : 'ies',
                $warning
            );

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'matrix_id' => $matrix->id,
                'appended' => $appended,
                'redirect_url' => route('matrices.show', $matrix),
                'activity_ids' => array_map(fn ($a) => $a->id, $result['activities']),
                'key_result_areas' => $result['key_result_areas'],
            ],
        ]);
    }

    /**
     * Link (or clear) an RBM activity id on an existing APM document.
     * Only updates pra_activity_id — does not create or rewrite other fields.
     */
    public function linkDocument(Request $request): JsonResponse
    {
        if (function_exists('pra_create_enabled') && ! pra_create_enabled()) {
            return response()->json(['success' => false, 'message' => 'RBM mapping is disabled.'], 403);
        }

        $validated = $request->validate([
            'document_type' => ['required', 'in:activity,non_travel,special_memo'],
            'document_id' => ['required', 'integer', 'min:1'],
            'pra_activity_id' => ['nullable', 'integer', 'min:1'],
            'clear' => ['nullable', 'boolean'],
            'allow_existing' => ['nullable', 'boolean'],
        ]);

        $clear = ! empty($validated['clear']) || empty($validated['pra_activity_id']);
        $praActivityId = $clear ? null : (int) $validated['pra_activity_id'];

        $model = $this->resolveLinkDocument(
            (string) $validated['document_type'],
            (int) $validated['document_id']
        );
        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        if (! $this->canLinkDocument($model)) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to map this document.'], 403);
        }

        if ($praActivityId !== null) {
            $usageMap = $this->usages->usagesByPraIds([$praActivityId]);
            $others = array_values(array_filter(
                $usageMap[$praActivityId] ?? [],
                function (array $u) use ($validated, $model) {
                    $type = (string) ($u['type'] ?? '');
                    $id = (int) ($u['id'] ?? 0);
                    if ($id !== (int) $model->id) {
                        return true;
                    }
                    // Same row under a different usage label still counts as self.
                    $selfTypes = match ((string) $validated['document_type']) {
                        'activity' => ['matrix_activity', 'single_memo'],
                        'non_travel' => ['non_travel'],
                        'special_memo' => ['special_memo'],
                        default => [],
                    };

                    return ! in_array($type, $selfTypes, true);
                }
            ));

            if ($others !== [] && empty($validated['allow_existing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This RBM activity is already linked in APM. Confirm to continue anyway.',
                    'data' => [
                        'existing' => $others,
                        'requires_confirmation' => true,
                    ],
                ], 409);
            }
        }

        $model->forceFill(['pra_activity_id' => $praActivityId])->save();

        return response()->json([
            'success' => true,
            'message' => $praActivityId === null
                ? 'RBM mapping cleared.'
                : 'Mapped to RBM activity '.$praActivityId.'.',
            'data' => [
                'document_type' => $validated['document_type'],
                'document_id' => (int) $model->id,
                'pra_activity_id' => $praActivityId,
            ],
        ]);
    }

    protected function resolveLinkDocument(string $type, int $id): ?Model
    {
        return match ($type) {
            'activity' => Activity::query()->find($id),
            'non_travel' => NonTravelMemo::query()->find($id),
            'special_memo' => SpecialMemo::query()->find($id),
            default => null,
        };
    }

    protected function canLinkDocument(Model $model): bool
    {
        $role = (int) (user_session('user_role') ?? session('user.user_role') ?? 0);
        if (in_array($role, [1, 10], true)) {
            return true;
        }

        $staffId = (int) (function_exists('resolved_session_staff_id')
            ? (resolved_session_staff_id() ?? 0)
            : (user_session('staff_id') ?? user_session('auth_staff_id') ?? session('user.auth_staff_id') ?? 0));

        if ($staffId < 1) {
            return false;
        }

        $ownerId = (int) ($model->getAttribute('staff_id') ?? 0);
        $responsibleId = (int) ($model->getAttribute('responsible_person_id') ?? 0);

        return $ownerId === $staffId || $responsibleId === $staffId;
    }
}
