<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RiskWorkflowService;
use App\Services\StaffPortalOrgClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RiskWorkflowController extends Controller
{
    public function submit(Request $request, int $id, RiskWorkflowService $workflow): JsonResponse
    {
        try {
            RiskWorkflowService::ensureDefaultWorkflow();
            $workflow->start($id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->approvalsForRisk($id)]);
    }

    public function myApprovals(Request $request, StaffPortalOrgClient $orgClient): JsonResponse
    {
        $staffId = (int) $request->attributes->get('risk_staff_id');
        $staleDays = max(1, (int) $request->query('stale_days', 7));

        $rows = DB::table('rr_risk_approvals as a')
            ->join('rr_risks as r', 'r.id', '=', 'a.risk_id')
            ->where('a.assignee_staff_id', $staffId)
            ->where('a.status', 'pending')
            ->orderBy('a.id')
            ->select([
                'a.id',
                'a.risk_id',
                'a.workflow_step_id',
                'a.step_order',
                'a.role',
                'a.assignee_staff_id',
                'a.status',
                'a.created_at',
                'a.updated_at',
                'r.name as risk_name',
                'r.division_id',
                'r.inherent_score',
                'r.inherent_rating',
                'r.residual_score',
                'r.residual_rating',
                'r.workflow_state',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'total_pending' => 0,
                    'stale_count' => 0,
                    'stale_days' => $staleDays,
                    'by_role' => [],
                    'divisions' => [],
                ],
            ]);
        }

        $riskIds = $rows->pluck('risk_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        $currentByRisk = DB::table('rr_risk_approvals')
            ->whereIn('risk_id', $riskIds)
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->get()
            ->groupBy('risk_id')
            ->map(fn ($group) => $group->first());

        $filtered = $rows->filter(function ($row) use ($currentByRisk) {
            $current = $currentByRisk->get($row->risk_id);

            return $current && (int) $current->id === (int) $row->id;
        })->values();

        $ownerRows = DB::table('rr_risk_owners')
            ->whereIn('risk_id', $filtered->pluck('risk_id')->all() ?: [0])
            ->orderBy('id')
            ->get();
        $ownerStaffIds = $ownerRows->pluck('staff_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $staffNames = $orgClient->staffNamesForIds($ownerStaffIds);
        $ownersByRisk = [];
        foreach ($ownerRows as $owner) {
            $rid = (int) $owner->risk_id;
            $sid = (int) $owner->staff_id;
            $ownersByRisk[$rid][] = $staffNames[$sid] ?? ('Staff #'.$sid);
        }

        $divisionLabels = $orgClient->divisionLabelsById();
        $now = now();
        $enriched = $filtered->map(function ($row) use ($ownersByRisk, $divisionLabels, $now, $staleDays) {
            $receivedAt = $row->updated_at ?: $row->created_at;
            $received = $receivedAt ? \Illuminate\Support\Carbon::parse($receivedAt) : null;
            $daysWaiting = $received ? max(0, (int) $received->diffInDays($now)) : 0;
            $divisionId = $row->division_id !== null ? (int) $row->division_id : null;

            return [
                'id' => (int) $row->id,
                'risk_id' => (int) $row->risk_id,
                'risk_name' => (string) $row->risk_name,
                'division_id' => $divisionId,
                'division_name' => $divisionId !== null
                    ? ($divisionLabels[$divisionId] ?? ('Division '.$divisionId))
                    : null,
                'role' => (string) $row->role,
                'step_order' => (int) $row->step_order,
                'status' => (string) $row->status,
                'workflow_state' => (string) ($row->workflow_state ?? ''),
                'inherent_score' => $row->inherent_score !== null ? (int) $row->inherent_score : null,
                'inherent_rating' => $row->inherent_rating,
                'residual_score' => $row->residual_score !== null ? (int) $row->residual_score : null,
                'residual_rating' => $row->residual_rating,
                'owners' => $ownersByRisk[(int) $row->risk_id] ?? [],
                'submitted_by' => implode(', ', $ownersByRisk[(int) $row->risk_id] ?? []) ?: null,
                'date_received' => $received?->toIso8601String(),
                'days_waiting' => $daysWaiting,
                'is_stale' => $daysWaiting > $staleDays,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        })->values();

        $byRole = [];
        foreach ($enriched as $item) {
            $role = (string) $item['role'];
            $byRole[$role] = ($byRole[$role] ?? 0) + 1;
        }

        $divisions = $enriched
            ->filter(fn ($item) => $item['division_id'] !== null)
            ->map(fn ($item) => [
                'division_id' => $item['division_id'],
                'division_name' => $item['division_name'],
            ])
            ->unique('division_id')
            ->sortBy('division_name')
            ->values()
            ->all();

        return response()->json([
            'data' => $enriched,
            'meta' => [
                'total_pending' => $enriched->count(),
                'stale_count' => $enriched->where('is_stale', true)->count(),
                'stale_days' => $staleDays,
                'by_role' => $byRole,
                'divisions' => $divisions,
            ],
        ]);
    }

    public function approve(Request $request, int $approvalId, RiskWorkflowService $workflow): JsonResponse
    {
        try {
            $workflow->approve($approvalId, (int) $request->attributes->get('risk_staff_id'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function requestFeedback(Request $request, int $approvalId, RiskWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'recipient_staff_ids' => 'required|array|min:1',
            'recipient_staff_ids.*' => 'integer',
            'message' => 'required|string|max:5000',
        ]);

        try {
            $workflow->requestFeedback(
                $approvalId,
                (int) $request->attributes->get('risk_staff_id'),
                $data['recipient_staff_ids'],
                $data['message']
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function eligibleRecipients(int $approvalId, RiskWorkflowService $workflow): JsonResponse
    {
        return response()->json(['data' => $workflow->eligibleFeedbackRecipients($approvalId)]);
    }

    public function showWorkflow(Request $request): JsonResponse
    {
        RiskWorkflowService::ensureDefaultWorkflow();
        $divisionId = $request->query('division_id');
        $q = DB::table('rr_approval_workflows')->orderByDesc('is_default')->orderBy('division_id')->orderBy('id');
        if ($divisionId !== null && $divisionId !== '') {
            $q->where(function ($w) use ($divisionId) {
                $w->where('division_id', (int) $divisionId)->orWhere('is_default', true);
            });
        }
        $workflows = $q->get();
        $steps = DB::table('rr_approval_workflow_steps')
            ->whereIn('workflow_id', $workflows->pluck('id')->all() ?: [0])
            ->orderBy('step_order')
            ->get()
            ->groupBy('workflow_id');

        $data = $workflows->map(function ($wf) use ($steps) {
            $row = (array) $wf;
            $row['steps'] = $steps->get($wf->id, collect())->values();

            return $row;
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Divisions table: every org division gets a default workflow (created if missing).
     */
    public function byDivision(RiskWorkflowService $workflow, StaffPortalOrgClient $orgClient): JsonResponse
    {
        RiskWorkflowService::ensureDefaultWorkflow();
        $org = $orgClient->fetchOrg();
        $divisions = $org['divisions'] ?? [];
        $workflow->ensureDivisionWorkflows($divisions);

        $workflows = DB::table('rr_approval_workflows')
            ->where(function ($q) {
                $q->whereNotNull('division_id')->orWhere('is_default', true);
            })
            ->orderByDesc('is_default')
            ->orderBy('division_id')
            ->orderBy('id')
            ->get();

        $stepsByWf = DB::table('rr_approval_workflow_steps')
            ->whereIn('workflow_id', $workflows->pluck('id')->all() ?: [0])
            ->orderBy('step_order')
            ->get()
            ->groupBy('workflow_id');

        // Resolve names only for staff ids on this page (full directory loads via /org/staff).
        $neededIds = [];
        foreach ($divisions as $div) {
            foreach (['risk_focal_person', 'division_head', 'director_id', 'director'] as $key) {
                $id = isset($div[$key]) ? (int) $div[$key] : 0;
                if ($id > 0) {
                    $neededIds[$id] = true;
                }
            }
        }
        foreach ($stepsByWf as $steps) {
            foreach ($steps as $step) {
                $id = isset($step->staff_id) ? (int) $step->staff_id : 0;
                if ($id > 0) {
                    $neededIds[$id] = true;
                }
            }
        }
        $names = $orgClient->staffNamesForIds(array_keys($neededIds));
        $nameOf = static function (?int $id) use ($names): ?string {
            if ($id === null || $id < 1) {
                return null;
            }

            return $names[$id] ?? ('Staff #'.$id);
        };
        $enrichSteps = static function (array $steps) use ($nameOf): array {
            return array_map(static function ($step) use ($nameOf) {
                $row = is_array($step) ? $step : (array) $step;
                $sid = isset($row['staff_id']) && $row['staff_id'] !== null && $row['staff_id'] !== ''
                    ? (int) $row['staff_id']
                    : null;
                $row['staff_id'] = $sid;
                $row['staff_name'] = $nameOf($sid);

                return $row;
            }, $steps);
        };

        $byDivisionId = [];
        foreach ($workflows as $wf) {
            if ($wf->division_id === null) {
                continue;
            }
            $did = (int) $wf->division_id;
            if (isset($byDivisionId[$did])) {
                continue; // first (oldest) is the division default
            }
            $row = (array) $wf;
            $row['steps'] = $enrichSteps($stepsByWf->get($wf->id, collect())->values()->all());
            $byDivisionId[$did] = $row;
        }

        $global = $workflows->firstWhere('is_default', true);
        $globalPayload = null;
        if ($global) {
            $globalPayload = (array) $global;
            $globalPayload['steps'] = $enrichSteps($stepsByWf->get($global->id, collect())->values()->all());
        }

        $rows = [];
        foreach ($divisions as $div) {
            $did = (int) ($div['division_id'] ?? 0);
            if ($did < 1) {
                continue;
            }
            $focal = isset($div['risk_focal_person']) && $div['risk_focal_person'] ? (int) $div['risk_focal_person'] : null;
            $hod = isset($div['division_head']) && $div['division_head'] ? (int) $div['division_head'] : null;
            $director = isset($div['director_id']) && $div['director_id']
                ? (int) $div['director_id']
                : (isset($div['director']) && $div['director'] ? (int) $div['director'] : null);
            $wf = $byDivisionId[$did] ?? null;
            $rows[] = [
                'division_id' => $did,
                'division_short_name' => (string) ($div['division_short_name'] ?? ''),
                'division_name' => (string) ($div['division_name'] ?? ''),
                'directorate_id' => isset($div['directorate_id']) ? (int) $div['directorate_id'] : null,
                'risk_focal_person' => $focal,
                'risk_focal_person_name' => $nameOf($focal),
                'division_head' => $hod,
                'division_head_name' => $nameOf($hod),
                'director_id' => $director,
                'director_name' => $nameOf($director),
                'workflow' => $wf,
            ];
        }

        usort($rows, static function ($a, $b) {
            return strcasecmp($a['division_short_name'] ?: $a['division_name'], $b['division_short_name'] ?: $b['division_name']);
        });

        return response()->json([
            'data' => [
                'global_default' => $globalPayload,
                'divisions' => $rows,
            ],
        ])->header('Cache-Control', 'private, max-age=30');
    }

    public function updateWorkflow(Request $request, int $id, RiskWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:128',
            'steps' => 'required|array|min:1',
            'steps.*.step_order' => 'required|integer|min:1',
            'steps.*.role' => 'required|string|in:risk_focal,hod,director,sm_focal,extra',
            'steps.*.staff_id' => 'nullable|integer',
            'steps.*.skippable_if_empty' => 'boolean',
        ]);

        try {
            $row = $workflow->updateWorkflow($id, $data['name'], $data['steps']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['data' => $row]);
    }

    public function upsertWorkflow(Request $request, RiskWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'division_id' => 'nullable|integer',
            'directorate_id' => 'nullable|integer',
            'name' => 'required|string|max:128',
            'steps' => 'required|array|min:1',
            'steps.*.step_order' => 'required|integer|min:1',
            'steps.*.role' => 'required|string|in:risk_focal,hod,director,sm_focal,extra',
            'steps.*.staff_id' => 'nullable|integer',
            'steps.*.skippable_if_empty' => 'boolean',
        ]);

        $divisionId = isset($data['division_id']) ? (int) $data['division_id'] : null;
        $directorateId = isset($data['directorate_id']) ? (int) $data['directorate_id'] : null;

        // Upsert division default (null directorate): update existing instead of duplicating
        if ($divisionId !== null && $divisionId > 0 && ($directorateId === null || $directorateId < 1)) {
            $existingId = $workflow->divisionWorkflowId($divisionId);
            if ($existingId) {
                $row = $workflow->updateWorkflow($existingId, $data['name'], $data['steps']);

                return response()->json(['data' => $row]);
            }
        }

        $id = DB::transaction(function () use ($data, $divisionId, $directorateId) {
            $wid = DB::table('rr_approval_workflows')->insertGetId([
                'division_id' => $divisionId,
                'directorate_id' => $directorateId && $directorateId > 0 ? $directorateId : null,
                'name' => $data['name'],
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($data['steps'] as $step) {
                DB::table('rr_approval_workflow_steps')->insert([
                    'workflow_id' => $wid,
                    'step_order' => $step['step_order'],
                    'role' => $step['role'],
                    'staff_id' => $step['staff_id'] ?? null,
                    'skippable_if_empty' => (bool) ($step['skippable_if_empty'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $wid;
        });

        return response()->json(['data' => $workflow->workflowPayload($id)], 201);
    }

    private function approvalsForRisk(int $riskId): array
    {
        $rows = DB::table('rr_risk_approvals')
            ->where('risk_id', $riskId)
            ->orderBy('step_order')
            ->get();

        $names = [];
        try {
            $names = app(StaffPortalOrgClient::class)->staffNameMap();
        } catch (\Throwable) {
            $names = [];
        }

        $current = DB::table('rr_risk_approvals')
            ->where('risk_id', $riskId)
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->first();
        $currentId = $current ? (int) $current->id : null;

        return $rows->map(function ($row) use ($names, $currentId) {
            $arr = (array) $row;
            $sid = (int) ($arr['assignee_staff_id'] ?? 0);
            $arr['assignee_name'] = $sid > 0
                ? ($names[$sid] ?? ('Staff #'.$sid))
                : null;
            $arr['is_current'] = $currentId !== null && (int) ($arr['id'] ?? 0) === $currentId;

            return $arr;
        })->all();
    }

    public function riskApprovals(int $id): JsonResponse
    {
        if (! DB::table('rr_risks')->where('id', $id)->exists()) {
            return response()->json(['message' => 'Risk not found'], 404);
        }

        return response()->json(['data' => $this->approvalsForRisk($id)]);
    }
}
