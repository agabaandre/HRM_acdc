<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RiskWorkflowService;
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

    public function myApprovals(Request $request): JsonResponse
    {
        $staffId = (int) $request->attributes->get('risk_staff_id');
        $rows = DB::table('rr_risk_approvals as a')
            ->join('rr_risks as r', 'r.id', '=', 'a.risk_id')
            ->where('a.assignee_staff_id', $staffId)
            ->where('a.status', 'pending')
            ->orderBy('a.id')
            ->select(['a.*', 'r.name as risk_name', 'r.division_id'])
            ->get();

        // Only current step for each risk
        $filtered = $rows->filter(function ($row) {
            $current = DB::table('rr_risk_approvals')
                ->where('risk_id', $row->risk_id)
                ->where('status', 'pending')
                ->orderBy('step_order')
                ->first();

            return $current && (int) $current->id === (int) $row->id;
        })->values();

        return response()->json(['data' => $filtered]);
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
        $q = DB::table('rr_approval_workflows')->orderByDesc('is_default');
        if ($divisionId !== null && $divisionId !== '') {
            $q->where(function ($w) use ($divisionId) {
                $w->where('division_id', (int) $divisionId)->orWhere('is_default', true);
            });
        }
        $workflows = $q->get();
        $steps = DB::table('rr_approval_workflow_steps')
            ->whereIn('workflow_id', $workflows->pluck('id'))
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

    public function upsertWorkflow(Request $request): JsonResponse
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

        $id = DB::transaction(function () use ($data) {
            $wid = DB::table('rr_approval_workflows')->insertGetId([
                'division_id' => $data['division_id'] ?? null,
                'directorate_id' => $data['directorate_id'] ?? null,
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

        return response()->json(['data' => ['id' => $id]], 201);
    }

    private function approvalsForRisk(int $riskId): array
    {
        return DB::table('rr_risk_approvals')->where('risk_id', $riskId)->orderBy('step_order')->get()->all();
    }
}
