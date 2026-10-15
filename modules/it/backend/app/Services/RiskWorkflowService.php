<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class RiskWorkflowService
{
    public function __construct(private readonly StaffPortalOrgClient $orgClient) {}

    public function start(int $riskId): void
    {
        $risk = DB::table('rr_risks')->where('id', $riskId)->first();
        if (! $risk) {
            throw new RuntimeException('Risk not found');
        }

        $existing = DB::table('rr_risk_approvals')->where('risk_id', $riskId)->exists();
        if ($existing) {
            return;
        }

        $workflow = $this->resolveWorkflow(
            $risk->division_id !== null ? (int) $risk->division_id : null,
            $risk->directorate_id !== null ? (int) $risk->directorate_id : null
        );
        $steps = DB::table('rr_approval_workflow_steps')
            ->where('workflow_id', $workflow->id)
            ->orderBy('step_order')
            ->get();
        if ($steps->isEmpty()) {
            throw new RuntimeException('Workflow has no steps');
        }

        $org = $this->orgClient->fetchOrg();
        $division = $this->findDivision($org, $risk->division_id !== null ? (int) $risk->division_id : null);

        DB::transaction(function () use ($riskId, $steps, $division) {
            $firstPendingOrder = null;
            foreach ($steps as $step) {
                $assignee = $this->resolveAssignee((string) $step->role, $step->staff_id !== null ? (int) $step->staff_id : null, $division);
                $skip = (bool) $step->skippable_if_empty && ($assignee === null || $assignee < 1);
                $status = $skip ? 'skipped' : 'pending';
                if ($status === 'pending' && $firstPendingOrder === null) {
                    $firstPendingOrder = (int) $step->step_order;
                }
                // Only the first non-skipped step starts as pending; later wait as pending but approve() advances sequentially
                DB::table('rr_risk_approvals')->insert([
                    'risk_id' => $riskId,
                    'workflow_step_id' => $step->id,
                    'step_order' => $step->step_order,
                    'role' => $step->role,
                    'assignee_staff_id' => $assignee,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Mark later pending steps as waiting by leaving them pending; current() uses lowest step_order pending
            DB::table('rr_risks')->where('id', $riskId)->update([
                'workflow_state' => 'in_approval',
                'updated_at' => now(),
            ]);
        });
    }

    public function approve(int $approvalId, int $actorStaffId): void
    {
        $approval = DB::table('rr_risk_approvals')->where('id', $approvalId)->first();
        if (! $approval || $approval->status !== 'pending') {
            throw new RuntimeException('Approval not pending');
        }

        $current = $this->currentPending((int) $approval->risk_id);
        if (! $current || (int) $current->id !== (int) $approval->id) {
            throw new RuntimeException('Not the current approval step');
        }

        DB::table('rr_risk_approvals')->where('id', $approvalId)->update([
            'status' => 'approved',
            'acted_by_staff_id' => $actorStaffId,
            'acted_at' => now(),
            'updated_at' => now(),
        ]);

        $next = $this->currentPending((int) $approval->risk_id);
        if (! $next) {
            DB::table('rr_risks')->where('id', $approval->risk_id)->update([
                'workflow_state' => 'signed_off',
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<int>  $recipientStaffIds
     */
    public function requestFeedback(int $approvalId, int $actorStaffId, array $recipientStaffIds, string $message): void
    {
        $approval = DB::table('rr_risk_approvals')->where('id', $approvalId)->first();
        if (! $approval || $approval->status !== 'pending') {
            throw new RuntimeException('Approval not pending');
        }

        $current = $this->currentPending((int) $approval->risk_id);
        if (! $current || (int) $current->id !== (int) $approval->id) {
            throw new RuntimeException('Not the current approval step');
        }

        $eligible = $this->eligibleFeedbackRecipientIds((int) $approval->risk_id, (int) $approval->step_order);
        foreach ($recipientStaffIds as $rid) {
            if (! in_array((int) $rid, $eligible, true)) {
                throw new InvalidArgumentException('Feedback recipients must be strictly below the current step');
            }
        }
        if ($recipientStaffIds === []) {
            throw new InvalidArgumentException('At least one recipient is required');
        }

        DB::transaction(function () use ($approvalId, $actorStaffId, $recipientStaffIds, $message) {
            DB::table('rr_risk_approvals')->where('id', $approvalId)->update([
                'status' => 'feedback_requested',
                'acted_by_staff_id' => $actorStaffId,
                'acted_at' => now(),
                'updated_at' => now(),
            ]);
            $fid = DB::table('rr_feedback_requests')->insertGetId([
                'risk_approval_id' => $approvalId,
                'requester_staff_id' => $actorStaffId,
                'message' => $message,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($recipientStaffIds as $rid) {
                DB::table('rr_feedback_recipients')->insert([
                    'feedback_request_id' => $fid,
                    'staff_id' => (int) $rid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * @return list<array{staff_id:int,name:string,level:int,role:string}>
     */
    public function eligibleFeedbackRecipients(int $approvalId): array
    {
        $approval = DB::table('rr_risk_approvals')->where('id', $approvalId)->first();
        if (! $approval) {
            return [];
        }

        $lower = DB::table('rr_risk_approvals')
            ->where('risk_id', $approval->risk_id)
            ->where('step_order', '<', $approval->step_order)
            ->whereNotNull('assignee_staff_id')
            ->orderBy('step_order')
            ->get();

        $ids = $lower->pluck('assignee_staff_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $names = $this->orgClient->staffNamesForIds($ids);

        $out = [];
        foreach ($lower as $row) {
            $sid = (int) $row->assignee_staff_id;
            $out[] = [
                'staff_id' => $sid,
                'name' => $names[$sid] ?? ('Staff #'.$sid),
                'level' => (int) $row->step_order,
                'role' => (string) $row->role,
            ];
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private function eligibleFeedbackRecipientIds(int $riskId, int $stepOrder): array
    {
        return DB::table('rr_risk_approvals')
            ->where('risk_id', $riskId)
            ->where('step_order', '<', $stepOrder)
            ->whereNotNull('assignee_staff_id')
            ->pluck('assignee_staff_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function currentPending(int $riskId): ?object
    {
        return DB::table('rr_risk_approvals')
            ->where('risk_id', $riskId)
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->first();
    }

    private function resolveWorkflow(?int $divisionId, ?int $directorateId): object
    {
        if ($divisionId !== null && $directorateId !== null) {
            $w = DB::table('rr_approval_workflows')
                ->where('division_id', $divisionId)
                ->where('directorate_id', $directorateId)
                ->first();
            if ($w) {
                return $w;
            }
        }
        if ($divisionId !== null) {
            $w = DB::table('rr_approval_workflows')
                ->where('division_id', $divisionId)
                ->whereNull('directorate_id')
                ->orderBy('id')
                ->first();
            if ($w) {
                return $w;
            }
            // Any division-scoped workflow as fallback
            $w = DB::table('rr_approval_workflows')
                ->where('division_id', $divisionId)
                ->orderBy('id')
                ->first();
            if ($w) {
                return $w;
            }
        }

        $w = DB::table('rr_approval_workflows')->where('is_default', true)->first();
        if (! $w) {
            throw new RuntimeException('No default approval workflow configured');
        }

        return $w;
    }

    /**
     * @param  array{divisions: list<array<string,mixed>>, directorates: list<array<string,mixed>>}  $org
     * @return array<string, mixed>|null
     */
    private function findDivision(array $org, ?int $divisionId): ?array
    {
        if ($divisionId === null) {
            return null;
        }
        foreach ($org['divisions'] as $div) {
            if ((int) ($div['division_id'] ?? 0) === $divisionId) {
                return $div;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $division
     */
    private function resolveAssignee(string $role, ?int $stepStaffId, ?array $division): ?int
    {
        return match ($role) {
            'risk_focal' => isset($division['risk_focal_person']) && $division['risk_focal_person']
                ? (int) $division['risk_focal_person']
                : null,
            'hod' => isset($division['division_head']) && $division['division_head']
                ? (int) $division['division_head']
                : null,
            'director' => $this->resolveDirector($division),
            'sm_focal', 'extra' => $stepStaffId,
            default => $stepStaffId,
        };
    }

    /**
     * @param  array<string, mixed>|null  $division
     */
    private function resolveDirector(?array $division): ?int
    {
        if ($division === null) {
            return null;
        }
        foreach (['director_id', 'director'] as $key) {
            if (! empty($division[$key])) {
                return (int) $division[$key];
            }
        }

        return null;
    }

    /**
     * Ensure a global default workflow exists (focal → HOD → director → SM → one extra).
     */
    public static function ensureDefaultWorkflow(?int $smFocalStaffId = null, ?int $extraStaffId = null): int
    {
        $existing = DB::table('rr_approval_workflows')->where('is_default', true)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return self::insertWorkflowWithDefaultSteps(
            null,
            null,
            'Global default',
            true,
            $smFocalStaffId,
            $extraStaffId
        );
    }

    /**
     * Ensure each division has a division-scoped default workflow (directorate null).
     *
     * @param  list<array<string, mixed>>  $divisions
     * @return list<array{division_id:int,workflow_id:int,created:bool}>
     */
    public function ensureDivisionWorkflows(array $divisions): array
    {
        self::ensureDefaultWorkflow();
        $out = [];
        foreach ($divisions as $div) {
            $divisionId = (int) ($div['division_id'] ?? 0);
            if ($divisionId < 1) {
                continue;
            }
            $label = trim((string) ($div['division_short_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($div['division_name'] ?? ''));
            }
            if ($label === '') {
                $label = 'Division '.$divisionId;
            }

            $existingId = DB::table('rr_approval_workflows')
                ->where('division_id', $divisionId)
                ->whereNull('directorate_id')
                ->orderBy('id')
                ->value('id');

            if ($existingId) {
                $out[] = ['division_id' => $divisionId, 'workflow_id' => (int) $existingId, 'created' => false];
                continue;
            }

            $wid = self::insertWorkflowWithDefaultSteps(
                $divisionId,
                null,
                $label.' default',
                false,
                null,
                null
            );
            $out[] = ['division_id' => $divisionId, 'workflow_id' => $wid, 'created' => true];
        }

        return $out;
    }

    /**
     * @param  list<array{step_order:int,role:string,staff_id?:?int,skippable_if_empty?:bool}>  $steps
     * @return array<string, mixed>
     */
    public function updateWorkflow(int $workflowId, string $name, array $steps): array
    {
        $wf = DB::table('rr_approval_workflows')->where('id', $workflowId)->first();
        if (! $wf) {
            throw new RuntimeException('Workflow not found');
        }

        DB::transaction(function () use ($workflowId, $name, $steps) {
            DB::table('rr_approval_workflows')->where('id', $workflowId)->update([
                'name' => $name,
                'updated_at' => now(),
            ]);
            DB::table('rr_approval_workflow_steps')->where('workflow_id', $workflowId)->delete();
            foreach ($steps as $step) {
                DB::table('rr_approval_workflow_steps')->insert([
                    'workflow_id' => $workflowId,
                    'step_order' => (int) $step['step_order'],
                    'role' => (string) $step['role'],
                    'staff_id' => isset($step['staff_id']) && $step['staff_id'] !== null && $step['staff_id'] !== ''
                        ? (int) $step['staff_id']
                        : null,
                    'skippable_if_empty' => (bool) ($step['skippable_if_empty'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return $this->workflowPayload($workflowId);
    }

    /**
     * @return array<string, mixed>
     */
    public function workflowPayload(int $workflowId): array
    {
        $wf = DB::table('rr_approval_workflows')->where('id', $workflowId)->first();
        if (! $wf) {
            throw new RuntimeException('Workflow not found');
        }
        $row = (array) $wf;
        $row['steps'] = DB::table('rr_approval_workflow_steps')
            ->where('workflow_id', $workflowId)
            ->orderBy('step_order')
            ->get()
            ->map(fn ($s) => (array) $s)
            ->all();

        return $row;
    }

    /**
     * Division-scoped workflow used for approvals (null directorate preferred).
     */
    public function divisionWorkflowId(int $divisionId): ?int
    {
        $id = DB::table('rr_approval_workflows')
            ->where('division_id', $divisionId)
            ->whereNull('directorate_id')
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private static function insertWorkflowWithDefaultSteps(
        ?int $divisionId,
        ?int $directorateId,
        string $name,
        bool $isDefault,
        ?int $smFocalStaffId,
        ?int $extraStaffId
    ): int {
        $wid = (int) DB::table('rr_approval_workflows')->insertGetId([
            'division_id' => $divisionId,
            'directorate_id' => $directorateId,
            'name' => $name,
            'is_default' => $isDefault,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $steps = [
            [1, 'risk_focal', null, false],
            [2, 'hod', null, false],
            [3, 'director', null, true],
            [4, 'sm_focal', $smFocalStaffId, false],
            [5, 'extra', $extraStaffId, false],
        ];
        foreach ($steps as [$order, $role, $staff, $skip]) {
            DB::table('rr_approval_workflow_steps')->insert([
                'workflow_id' => $wid,
                'step_order' => $order,
                'role' => $role,
                'staff_id' => $staff,
                'skippable_if_empty' => $skip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $wid;
    }
}
