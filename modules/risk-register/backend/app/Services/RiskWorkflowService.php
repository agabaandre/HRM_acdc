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

        $out = [];
        foreach ($lower as $row) {
            $out[] = [
                'staff_id' => (int) $row->assignee_staff_id,
                'name' => 'Staff #'.$row->assignee_staff_id,
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

        $wid = (int) DB::table('rr_approval_workflows')->insertGetId([
            'division_id' => null,
            'directorate_id' => null,
            'name' => 'Global default',
            'is_default' => true,
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
