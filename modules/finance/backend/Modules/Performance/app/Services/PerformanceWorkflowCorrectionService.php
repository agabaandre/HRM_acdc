<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Performance\Enums\PerformancePhase;
use RuntimeException;

/**
 * Marks PPA / midterm (and endterm when second supervisor is off) as approved
 * once workflow resolveState() already says approved — typically after the first
 * supervisor approved while a second supervisor ID is still snapshotted.
 */
class PerformanceWorkflowCorrectionService
{
    public function __construct(
        protected PerformanceWorkflowService $workflow,
        protected PpaSettingsService $settings,
        protected SupervisorResolver $supervisors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $entryId): array
    {
        return $this->describe($this->requireEntry($entryId));
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(string $entryId): array
    {
        $entry = $this->requireEntry($entryId);
        $corrected = [];

        foreach (PerformancePhase::cases() as $phase) {
            if ($this->finalizeIfReady($entry, $phase)) {
                $corrected[] = $phase->value;
                $entry = $this->requireEntry($entryId);
            }
        }

        $payload = $this->describe($entry);
        $payload['corrected_phases'] = $corrected;

        return $payload;
    }

    public function finalizeIfReady(object $entry, PerformancePhase $phase): bool
    {
        if (! $this->workflow->phaseExists($entry, $phase)) {
            return false;
        }

        if ($this->workflow->isPhaseApproved($entry, $phase)) {
            return false;
        }

        if (($this->workflow->resolveState($entry, $phase)['step'] ?? '') !== 'approved') {
            return false;
        }

        $update = [
            $phase->draftStatusColumn() => 2,
            'updated_at' => now(),
        ];

        if ($phase === PerformancePhase::Midterm) {
            $update['midterm_updated_at'] = now();
        }

        if ($phase === PerformancePhase::Endterm) {
            $update['endterm_updated_at'] = now();
        }

        DB::table('ppa_entries')->where('entry_id', $entry->entry_id)->update($update);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function describe(object $entry): array
    {
        $phases = [];

        foreach (PerformancePhase::cases() as $phase) {
            $exists = $this->workflow->phaseExists($entry, $phase);
            $sup = $this->workflow->supervisorIdsForPhase($entry, $phase);
            $state = $exists
                ? $this->workflow->resolveState($entry, $phase)
                : ['step' => 'missing', 'label' => 'Not started', 'status_key' => 'missing'];
            $canCorrect = $exists
                && ! $this->workflow->isPhaseApproved($entry, $phase)
                && ($state['step'] ?? '') === 'approved';

            $phases[$phase->value] = [
                'label' => $phase->label(),
                'exists' => $exists,
                'draft_status' => (int) ($entry->{$phase->draftStatusColumn()} ?? 1),
                'requires_second_supervisor' => $this->settings->requiresSecondSupervisor($phase)
                    && ! empty($sup['supervisor_2']),
                'supervisor_1_action' => $exists
                    ? $this->workflow->lastTrailAction((string) $entry->entry_id, $phase, $sup['supervisor_1'])
                    : null,
                'supervisor_2_action' => $exists
                    ? $this->workflow->lastTrailAction((string) $entry->entry_id, $phase, $sup['supervisor_2'])
                    : null,
                'state' => $state['label'] ?? 'Unknown',
                'state_step' => $state['step'] ?? null,
                'can_correct' => $canCorrect,
            ];
        }

        return [
            'entry_id' => (string) $entry->entry_id,
            'staff_id' => (int) $entry->staff_id,
            'staff_name' => $this->supervisors->staffName((int) $entry->staff_id),
            'performance_period' => (string) ($entry->performance_period ?? ''),
            'settings' => [
                'ppa_requires_second_supervisor' => $this->settings->requiresSecondSupervisor(PerformancePhase::Ppa),
                'midterm_requires_second_supervisor' => $this->settings->requiresSecondSupervisor(PerformancePhase::Midterm),
                'endterm_requires_second_supervisor' => $this->settings->requiresSecondSupervisor(PerformancePhase::Endterm),
            ],
            'phases' => $phases,
            'can_correct' => collect($phases)->contains(fn (array $phase) => ! empty($phase['can_correct'])),
        ];
    }

    protected function requireEntry(string $entryId): object
    {
        $entry = DB::table('ppa_entries')->where('entry_id', $entryId)->first();
        if (! $entry) {
            throw new RuntimeException('PPA entry not found.');
        }

        return $entry;
    }
}
