<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Performance\Enums\PerformancePhase;

/**
 * Workflow state for PPA / midterm / endterm.
 *
 * Workflow steps are driven by ppa_configs (Settings → Performance & workflows).
 * Endterm order: submit → first supervisor → employee consent (optional) → second supervisor (optional).
 *
 * Supervisor IDs on ppa_entries are snapshotted per phase; sync from latest contract only
 * while that phase is in-flight (submitted, not fully approved). Approval trails are append-only.
 */
class PerformanceWorkflowService
{
    public function __construct(
        protected SupervisorResolver $supervisors,
        protected PpaSettingsService $settings,
    ) {}

    /**
     * @return array{supervisor_1: ?int, supervisor_2: ?int}
     */
    public function supervisorIdsForPhase(object $entry, PerformancePhase $phase): array
    {
        return match ($phase) {
            PerformancePhase::Ppa => [
                'supervisor_1' => $entry->supervisor_id ? (int) $entry->supervisor_id : null,
                'supervisor_2' => $entry->supervisor2_id ? (int) $entry->supervisor2_id : null,
            ],
            PerformancePhase::Midterm => [
                'supervisor_1' => $entry->midterm_supervisor_1 ? (int) $entry->midterm_supervisor_1 : null,
                'supervisor_2' => $entry->midterm_supervisor_2 ? (int) $entry->midterm_supervisor_2 : null,
            ],
            PerformancePhase::Endterm => [
                'supervisor_1' => $entry->endterm_supervisor_1 ? (int) $entry->endterm_supervisor_1 : null,
                'supervisor_2' => $entry->endterm_supervisor_2 ? (int) $entry->endterm_supervisor_2 : null,
            ],
        };
    }

    /**
     * Sync active-phase supervisor columns from latest contract (does not alter trails or approved phases).
     */
    public function syncSupervisorsFromContract(object $entry, PerformancePhase $phase): void
    {
        if ($this->isPhaseApproved($entry, $phase)) {
            return;
        }

        if (! $this->isPhaseInFlight($entry, $phase)) {
            return;
        }

        $resolved = $this->supervisors->fromLatestContract((int) $entry->staff_id);
        $update = match ($phase) {
            PerformancePhase::Ppa => [
                'supervisor_id' => $resolved['supervisor_1'],
                'supervisor2_id' => $resolved['supervisor_2'],
            ],
            PerformancePhase::Midterm => [
                'midterm_supervisor_1' => $resolved['supervisor_1'],
                'midterm_supervisor_2' => $resolved['supervisor_2'],
            ],
            PerformancePhase::Endterm => [
                'endterm_supervisor_1' => $resolved['supervisor_1'],
                'endterm_supervisor_2' => $resolved['supervisor_2'],
            ],
        };

        DB::table('ppa_entries')->where('entry_id', $entry->entry_id)->update($update);
    }

    public function isPhaseApproved(object $entry, PerformancePhase $phase): bool
    {
        $col = $phase->draftStatusColumn();

        return (int) ($entry->{$col} ?? 1) === 2;
    }

    public function isPhaseInFlight(object $entry, PerformancePhase $phase): bool
    {
        $col = $phase->draftStatusColumn();
        $status = (int) ($entry->{$col} ?? 1);

        if ($phase === PerformancePhase::Ppa) {
            return $status === 0;
        }

        if ($phase === PerformancePhase::Midterm) {
            return ! empty($entry->midterm_created_at) && $status !== 2;
        }

        return ! empty($entry->endterm_created_at) && $status !== 2;
    }

    public function phaseExists(object $entry, PerformancePhase $phase): bool
    {
        return match ($phase) {
            PerformancePhase::Ppa => true,
            PerformancePhase::Midterm => ! empty($entry->midterm_created_at),
            PerformancePhase::Endterm => ! empty($entry->endterm_created_at),
        };
    }

    /**
     * @return list<array{key: string, label: string, status: string, actor: string, hint: string}>
     */
    public function timeline(object $entry, PerformancePhase $phase): array
    {
        $state = $this->resolveState($entry, $phase);
        $sup = $this->supervisorIdsForPhase($entry, $phase);
        $hasSecond = $this->requiresSecondSupervisor($phase, $sup);

        if ($phase === PerformancePhase::Endterm) {
            return $this->endtermTimeline($entry, $state, $sup, $hasSecond);
        }

        return $this->linearSupervisorTimeline($entry, $phase, $state, $sup, $hasSecond);
    }

    /**
     * @return array{step: string, label: string, status_key: string, can_act: bool, actor_staff_id: ?int}
     */
    public function resolveState(object $entry, PerformancePhase $phase): array
    {
        $draftCol = $phase->draftStatusColumn();
        $draft = (int) ($entry->{$draftCol} ?? 1);

        if ($draft === 1) {
            return ['step' => 'draft', 'label' => 'Draft', 'status_key' => 'draft', 'can_act' => false, 'actor_staff_id' => null];
        }

        if ($draft === 2) {
            return ['step' => 'approved', 'label' => 'Approved', 'status_key' => 'approved', 'can_act' => false, 'actor_staff_id' => null];
        }

        $sup = $this->supervisorIdsForPhase($entry, $phase);
        $s1Action = $this->lastTrailAction($entry->entry_id, $phase, $sup['supervisor_1']);
        $s2Action = $this->lastTrailAction($entry->entry_id, $phase, $sup['supervisor_2']);

        if ($phase === PerformancePhase::Endterm) {
            return $this->resolveEndtermState($entry, $sup, $s1Action, $s2Action);
        }

        if ($s1Action !== 'Approved') {
            return [
                'step' => 'supervisor_1',
                'label' => 'Pending first supervisor',
                'status_key' => 'pending_supervisor_1',
                'can_act' => true,
                'actor_staff_id' => $sup['supervisor_1'],
            ];
        }

        if ($this->requiresSecondSupervisor($phase, $sup) && $s2Action !== 'Approved') {
            return [
                'step' => 'supervisor_2',
                'label' => 'Pending second supervisor',
                'status_key' => 'pending_supervisor_2',
                'can_act' => true,
                'actor_staff_id' => $sup['supervisor_2'],
            ];
        }

        return ['step' => 'approved', 'label' => 'Approved', 'status_key' => 'approved', 'can_act' => false, 'actor_staff_id' => null];
    }

    public function canActorApprove(object $entry, PerformancePhase $phase, int $actorStaffId): bool
    {
        $state = $this->resolveState($entry, $phase);

        if ($state['step'] === 'employee_consent') {
            return (int) $entry->staff_id === $actorStaffId;
        }

        return $state['can_act'] && (int) ($state['actor_staff_id'] ?? 0) === $actorStaffId;
    }

    public function lastTrailAction(string $entryId, PerformancePhase $phase, ?int $staffId): ?string
    {
        if (! $staffId) {
            return null;
        }

        $table = $phase->trailTable();
        $row = DB::table($table)
            ->where('entry_id', $entryId)
            ->where('staff_id', $staffId)
            ->orderByDesc('id')
            ->first();

        return $row?->action;
    }

    /**
     * @param  array{supervisor_1: ?int, supervisor_2: ?int}  $sup
     */
    protected function resolveEndtermState(object $entry, array $sup, ?string $s1Action, ?string $s2Action): array
    {
        if ($s1Action !== 'Approved') {
            return [
                'step' => 'supervisor_1',
                'label' => 'Pending first supervisor',
                'status_key' => 'pending_supervisor_1',
                'can_act' => true,
                'actor_staff_id' => $sup['supervisor_1'],
            ];
        }

        if ($this->settings->endtermRequiresEmployeeConsent() && empty($entry->endterm_staff_consent_at)) {
            return [
                'step' => 'employee_consent',
                'label' => 'Pending employee consent on results',
                'status_key' => 'pending_employee_consent',
                'can_act' => true,
                'actor_staff_id' => (int) $entry->staff_id,
            ];
        }

        if ($this->requiresSecondSupervisor(PerformancePhase::Endterm, $sup) && $s2Action !== 'Approved') {
            return [
                'step' => 'supervisor_2',
                'label' => 'Pending second supervisor',
                'status_key' => 'pending_supervisor_2',
                'can_act' => true,
                'actor_staff_id' => $sup['supervisor_2'],
            ];
        }

        return ['step' => 'approved', 'label' => 'Approved', 'status_key' => 'approved', 'can_act' => false, 'actor_staff_id' => null];
    }

    /**
     * @param  array{supervisor_1: ?int, supervisor_2: ?int}  $sup
     */
    protected function requiresSecondSupervisor(PerformancePhase $phase, array $sup): bool
    {
        return $this->settings->requiresSecondSupervisor($phase)
            && ! empty($sup['supervisor_2']);
    }

    /**
     * @param  array{supervisor_1: ?int, supervisor_2: ?int}  $sup
     * @return list<array{key: string, label: string, status: string, actor: string, hint: string}>
     */
    protected function linearSupervisorTimeline(object $entry, PerformancePhase $phase, array $state, array $sup, bool $hasSecond): array
    {
        $steps = [
            ['key' => 'submit', 'label' => 'Employee submission', 'actor' => $this->supervisors->staffName((int) $entry->staff_id)],
            ['key' => 'supervisor_1', 'label' => 'First supervisor', 'actor' => $this->supervisors->staffName($sup['supervisor_1'])],
        ];
        if ($hasSecond) {
            $steps[] = ['key' => 'supervisor_2', 'label' => 'Second supervisor', 'actor' => $this->supervisors->staffName($sup['supervisor_2'])];
        }
        $steps[] = ['key' => 'approved', 'label' => 'Approved', 'actor' => '—'];

        return $this->markTimelineSteps($steps, $state['step']);
    }

    /**
     * @param  array{supervisor_1: ?int, supervisor_2: ?int}  $sup
     * @return list<array{key: string, label: string, status: string, actor: string, hint: string}>
     */
    protected function endtermTimeline(object $entry, array $state, array $sup, bool $hasSecond): array
    {
        $steps = [
            ['key' => 'submit', 'label' => 'Employee submission', 'actor' => $this->supervisors->staffName((int) $entry->staff_id)],
            ['key' => 'supervisor_1', 'label' => 'First supervisor approval', 'actor' => $this->supervisors->staffName($sup['supervisor_1'])],
        ];
        if ($this->settings->endtermRequiresEmployeeConsent()) {
            $steps[] = ['key' => 'employee_consent', 'label' => 'Employee consent on results', 'actor' => $this->supervisors->staffName((int) $entry->staff_id)];
        }
        if ($hasSecond) {
            $steps[] = ['key' => 'supervisor_2', 'label' => 'Second supervisor approval', 'actor' => $this->supervisors->staffName($sup['supervisor_2'])];
        }
        $steps[] = ['key' => 'approved', 'label' => 'Approved', 'actor' => '—'];

        return $this->markTimelineSteps($steps, $state['step']);
    }

    /**
     * @param  list<array{key: string, label: string, actor: string}>  $steps
     * @return list<array{key: string, label: string, status: string, actor: string, hint: string}>
     */
    protected function markTimelineSteps(array $steps, string $currentStep): array
    {
        $order = array_column($steps, 'key');
        $currentIndex = array_search($currentStep, $order, true);
        if ($currentStep === 'draft') {
            $currentIndex = -1;
        }
        if ($currentStep === 'approved') {
            $currentIndex = count($steps) - 1;
        }

        $result = [];
        foreach ($steps as $i => $step) {
            $status = 'pending';
            if ($currentIndex < 0) {
                $status = $i === 0 ? 'current' : 'pending';
            } elseif ($i < $currentIndex) {
                $status = 'done';
            } elseif ($i === $currentIndex) {
                $status = 'current';
            }

            $result[] = [
                'key' => $step['key'],
                'label' => $step['label'],
                'status' => $status,
                'actor' => $step['actor'],
                'hint' => match ($status) {
                    'done' => 'Completed',
                    'current' => 'In progress',
                    default => 'Waiting',
                },
            ];
        }

        return $result;
    }
}
