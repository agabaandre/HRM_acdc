<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Enums\PerformancePhase;

class PerformanceApprovalService
{
    public function __construct(
        protected PerformanceWorkflowService $workflow,
        protected SupervisorResolver $supervisors,
        protected PerformanceService $performance,
    ) {}

    public function findEntry(string $entryId): ?object
    {
        return DB::table('ppa_entries')->where('entry_id', $entryId)->first();
    }

    /**
     * All workflow actions awaiting this staff member (supervisor or employee consent).
     *
     * @return Collection<int, object>
     */
    public function pendingActionsFor(int $staffId): Collection
    {
        $ppa = $this->performance->pendingApprovals($staffId);
        $midterm = $this->pendingMidterm($staffId);
        $endterm = $this->pendingEndterm($staffId);
        $consent = $this->pendingEmployeeConsent($staffId);

        return $ppa->map(fn ($r) => (object) array_merge((array) $r, ['approval_type' => 'ppa']))
            ->concat($midterm->map(fn ($r) => (object) array_merge((array) $r, ['approval_type' => 'midterm'])))
            ->concat($endterm->map(fn ($r) => (object) array_merge((array) $r, ['approval_type' => 'endterm'])))
            ->concat($consent);
    }

    public function approve(
        string $entryId,
        PerformancePhase $phase,
        int $actorStaffId,
        string $comments = '',
        ?bool $supervisor2Agreement = null,
    ): void {
        $entry = $this->findEntry($entryId);
        if (! $entry) {
            throw new \RuntimeException('PPA entry not found.');
        }

        $this->workflow->syncSupervisorsFromContract($entry, $phase);
        $entry = $this->findEntry($entryId);

        if (! $this->workflow->canActorApprove($entry, $phase, $actorStaffId)) {
            throw new \RuntimeException('You are not authorized to approve at this workflow step.');
        }

        $this->appendTrail($entryId, $phase, $actorStaffId, 'Approved', $comments);

        if ($phase === PerformancePhase::Endterm) {
            $sup = $this->workflow->supervisorIdsForPhase($entry, $phase);
            if ((int) ($sup['supervisor_1'] ?? 0) === $actorStaffId) {
                DB::table('ppa_entries')->where('entry_id', $entryId)->update([
                    'endterm_supervisor1_discussion_confirmed' => 1,
                ]);
            }
            if ((int) ($sup['supervisor_2'] ?? 0) === $actorStaffId && $supervisor2Agreement !== null) {
                DB::table('ppa_entries')->where('entry_id', $entryId)->update([
                    'endterm_supervisor2_agreement' => $supervisor2Agreement ? 1 : 0,
                ]);
            }
        }

        $entry = $this->findEntry($entryId);
        if ($this->workflow->resolveState($entry, $phase)['step'] === 'approved') {
            $col = $phase->draftStatusColumn();
            DB::table('ppa_entries')->where('entry_id', $entryId)->update([
                $col => 2,
                'updated_at' => now(),
            ]);
        }
    }

    public function returnForRevision(
        string $entryId,
        PerformancePhase $phase,
        int $actorStaffId,
        string $comments,
    ): void {
        $entry = $this->findEntry($entryId);
        if (! $entry) {
            throw new \RuntimeException('PPA entry not found.');
        }

        $this->appendTrail($entryId, $phase, $actorStaffId, 'Returned', $comments);

        $col = $phase->draftStatusColumn();
        DB::table('ppa_entries')->where('entry_id', $entryId)->update([
            $col => 1,
            'updated_at' => now(),
        ]);
    }

    public function recordEmployeeConsent(string $entryId, int $staffId, string $comments, bool $acceptRating): void
    {
        $entry = $this->findEntry($entryId);
        if (! $entry || (int) $entry->staff_id !== $staffId) {
            throw new \RuntimeException('Invalid entry or staff.');
        }

        $phase = PerformancePhase::Endterm;
        if (! app(PpaSettingsService::class)->endtermRequiresEmployeeConsent()) {
            throw new \RuntimeException('Employee consent is not required for end-of-year reviews.');
        }
        if ($this->workflow->resolveState($entry, $phase)['step'] !== 'employee_consent') {
            throw new \RuntimeException('Employee consent is not the current workflow step.');
        }

        DB::table('ppa_entries')->where('entry_id', $entryId)->update([
            'endterm_staff_consent_at' => now(),
            'endterm_staff_discussion_confirmed' => 1,
            'endterm_staff_rating_acceptance' => $acceptRating ? 1 : 0,
        ]);

        $this->appendTrail($entryId, $phase, $staffId, 'Consented', $comments);

        $entry = $this->findEntry($entryId);
        if ($entry && $this->workflow->resolveState($entry, $phase)['step'] === 'approved') {
            DB::table('ppa_entries')->where('entry_id', $entryId)->update([
                'endterm_draft_status' => 2,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return Collection<int, object>
     */
    public function trail(string $entryId, PerformancePhase $phase): Collection
    {
        return DB::table($phase->trailTable())
            ->where('entry_id', $entryId)
            ->orderBy('created_at')
            ->get();
    }

    protected function appendTrail(
        string $entryId,
        PerformancePhase $phase,
        int $staffId,
        string $action,
        string $comments,
    ): void {
        $row = [
            'entry_id' => $entryId,
            'staff_id' => $staffId,
            'comments' => $comments,
            'action' => $action,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];

        if ($phase === PerformancePhase::Midterm) {
            $row['type'] = 'MID-TERM REVIEW';
        }

        if ($phase === PerformancePhase::Endterm) {
            $row['type'] = 'END-TERM REVIEW';
        }

        DB::table($phase->trailTable())->insert($row);
    }

    /**
     * @return Collection<int, object>
     */
    protected function pendingMidterm(int $supervisorStaffId): Collection
    {
        $sid = (int) $supervisorStaffId;
        $items = collect();

        $rows = DB::table('ppa_entries as p')
            ->join('staff as s', 's.staff_id', '=', 'p.staff_id')
            ->whereNotNull('p.midterm_created_at')
            ->where('p.midterm_draft_status', 0)
            ->select('p.*', DB::raw("CONCAT(s.fname, ' ', s.lname) AS staff_name"))
            ->orderByDesc('p.midterm_created_at')
            ->limit(500)
            ->get();

        foreach ($rows as $entry) {
            $state = $this->workflow->resolveState($entry, PerformancePhase::Midterm);
            if ($state['can_act'] && (int) ($state['actor_staff_id'] ?? 0) === $sid) {
                $entry->overall_status = $state['label'];
                $items->push($entry);
            }
        }

        return $items;
    }

    /**
     * @return Collection<int, object>
     */
    protected function pendingEndterm(int $supervisorStaffId): Collection
    {
        $items = collect();
        $sid = (int) $supervisorStaffId;

        $entries = DB::table('ppa_entries as p')
            ->join('staff as s', 's.staff_id', '=', 'p.staff_id')
            ->whereNotNull('p.endterm_created_at')
            ->where('p.endterm_draft_status', 0)
            ->select('p.*', DB::raw("CONCAT(s.fname, ' ', s.lname) AS staff_name"))
            ->orderByDesc('p.endterm_updated_at')
            ->limit(200)
            ->get();

        foreach ($entries as $entry) {
            $state = $this->workflow->resolveState($entry, PerformancePhase::Endterm);
            if ($state['can_act'] && (int) ($state['actor_staff_id'] ?? 0) === $sid) {
                $entry->overall_status = $state['label'];
                $items->push($entry);
            }
        }

        return $items;
    }

    /**
     * @return Collection<int, object>
     */
    protected function pendingEmployeeConsent(int $staffId): Collection
    {
        $items = collect();

        if (! app(PpaSettingsService::class)->endtermRequiresEmployeeConsent()) {
            return $items;
        }

        $entries = DB::table('ppa_entries')
            ->where('staff_id', $staffId)
            ->whereNotNull('endterm_created_at')
            ->where('endterm_draft_status', 0)
            ->get();

        foreach ($entries as $entry) {
            $state = $this->workflow->resolveState($entry, PerformancePhase::Endterm);
            if ($state['step'] === 'employee_consent') {
                $entry->staff_name = $this->supervisors->staffName($staffId);
                $entry->overall_status = $state['label'];
                $entry->approval_type = 'endterm';
                $items->push($entry);
            }
        }

        return $items;
    }
}
