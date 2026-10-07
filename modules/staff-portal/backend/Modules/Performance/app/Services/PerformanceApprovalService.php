<?php

namespace Modules\Performance\Services;

use App\Support\StaffPhoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * Actions this staff member can take now (supervisor approval or employee consent).
     * Used by reminders and pending-count badges.
     *
     * @return Collection<int, object>
     */
    public function pendingActionsFor(int $staffId): Collection
    {
        return $this->buildPendingQueue($staffId, includeAssignedWaiting: false);
    }

    /**
     * Hub Pending reviews queue: actionable items plus in-flight forms where this staff
     * is a named supervisor but it is not their turn yet (status explains the wait).
     *
     * @return Collection<int, object>
     */
    public function pendingQueueFor(int $staffId): Collection
    {
        return $this->buildPendingQueue($staffId, includeAssignedWaiting: true)
            ->sortByDesc(fn ($row) => sprintf(
                '%d|%s|%s',
                ! empty($row->can_act) ? 1 : 0,
                (string) ($row->approval_type ?? ''),
                (string) ($row->updated_at ?? $row->created_at ?? $row->midterm_created_at ?? $row->endterm_updated_at ?? ''),
            ))
            ->values();
    }

    public function pendingCountFor(int $staffId): int
    {
        if ($staffId < 1) {
            return 0;
        }

        return $this->pendingActionsFor($staffId)->count();
    }

    /**
     * @return Collection<int, object>
     */
    protected function buildPendingQueue(int $staffId, bool $includeAssignedWaiting): Collection
    {
        $sid = (int) $staffId;
        if ($sid < 1) {
            return collect();
        }

        $ppa = $this->performance->pendingApprovals($sid, $includeAssignedWaiting);
        $midterm = $this->pendingMidterm($sid, $includeAssignedWaiting);
        $endterm = $this->pendingEndterm($sid, $includeAssignedWaiting);
        $consent = $this->pendingEmployeeConsent($sid);

        return $ppa->map(fn ($r) => (object) array_merge((array) $r, [
            'approval_type' => 'ppa',
            'can_act' => (bool) ($r->can_act ?? true),
        ]))
            ->concat($midterm->map(fn ($r) => (object) array_merge((array) $r, [
                'approval_type' => 'midterm',
                'can_act' => (bool) ($r->can_act ?? true),
            ])))
            ->concat($endterm->map(fn ($r) => (object) array_merge((array) $r, [
                'approval_type' => 'endterm',
                'can_act' => (bool) ($r->can_act ?? true),
            ])))
            ->concat($consent->map(fn ($r) => (object) array_merge((array) $r, [
                'can_act' => true,
            ])));
    }

    /**
     * Forms this staff member approved or returned (newest first).
     *
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function approvalHistoryFor(
        int $actorStaffId,
        ?string $period,
        ?PerformancePhase $phase,
        int $page,
        int $perPage,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        if ($actorStaffId < 1) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ];
        }

        $phases = $phase instanceof PerformancePhase
            ? [$phase]
            : [PerformancePhase::Ppa, PerformancePhase::Midterm, PerformancePhase::Endterm];

        $rows = collect();
        foreach ($phases as $phaseItem) {
            $table = $phaseItem->trailTable();
            if (! Schema::hasTable($table) || ! Schema::hasTable('ppa_entries')) {
                continue;
            }

            $query = DB::table($table.' as t')
                ->join('ppa_entries as p', 'p.entry_id', '=', 't.entry_id')
                ->leftJoin('staff as s', 's.staff_id', '=', 'p.staff_id')
                ->where('t.staff_id', $actorStaffId)
                ->where(function ($q): void {
                    $q->whereRaw('LOWER(t.action) = ?', ['approved'])
                        ->orWhereRaw('LOWER(t.action) = ?', ['returned']);
                })
                ->when($period !== null && $period !== '', fn ($q) => $q->where('p.performance_period', $period))
                ->orderByDesc('t.created_at')
                ->orderByDesc('t.id')
                ->select([
                    't.id',
                    't.entry_id',
                    't.action',
                    't.comments',
                    't.created_at as acted_at',
                    'p.staff_id',
                    'p.performance_period',
                    DB::raw("TRIM(CONCAT(COALESCE(s.fname, ''), ' ', COALESCE(s.lname, ''))) AS staff_name"),
                ]);

            foreach ($query->get() as $row) {
                $rows->push((object) array_merge((array) $row, [
                    'phase' => $phaseItem->value,
                    'phase_label' => $phaseItem->label(),
                ]));
            }
        }

        $sorted = $rows
            ->sortByDesc(fn ($row) => sprintf('%s|%010d', (string) ($row->acted_at ?? ''), (int) ($row->id ?? 0)))
            ->values();

        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $data = $slice->map(function (object $row): array {
            $entryId = (string) ($row->entry_id ?? '');
            $subjectId = (int) ($row->staff_id ?? 0);
            $phaseValue = (string) ($row->phase ?? 'ppa');
            $phaseEnum = PerformancePhase::tryFrom($phaseValue) ?? PerformancePhase::Ppa;
            $name = trim((string) ($row->staff_name ?? ''));

            return [
                'entry_id' => $entryId,
                'staff_id' => $subjectId,
                'staff_name' => $name !== '' ? $name : ('#'.$subjectId),
                'phase' => $phaseEnum->value,
                'phase_label' => $phaseEnum->label(),
                'performance_period' => (string) ($row->performance_period ?? ''),
                'action' => (string) ($row->action ?? ''),
                'comments' => (string) ($row->comments ?? ''),
                'acted_at' => (string) ($row->acted_at ?? ''),
                'form_url' => $entryId !== '' && $subjectId > 0
                    ? '/performance/form/'.$phaseEnum->value.'/'.$entryId.'/'.$subjectId
                    : '',
            ];
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
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
        $update = [
            $col => 1,
            'updated_at' => now(),
        ];

        if ($phase === PerformancePhase::Midterm) {
            $update['midterm_updated_at'] = now();
        }

        if ($phase === PerformancePhase::Endterm) {
            $update['endterm_updated_at'] = now();
            $update['endterm_staff_discussion_confirmed'] = 0;
            $update['endterm_staff_rating_acceptance'] = null;
            $update['endterm_staff_consent_at'] = null;
            $update['endterm_supervisor1_discussion_confirmed'] = 0;
            $update['endterm_supervisor2_agreement'] = null;
        }

        DB::table('ppa_entries')->where('entry_id', $entryId)->update($update);
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
     * Approval trail newest-first, with staff display fields for the SPA.
     *
     * @return Collection<int, object>
     */
    public function trail(string $entryId, PerformancePhase $phase): Collection
    {
        $table = $phase->trailTable();

        $rows = DB::table($table.' as t')
            ->leftJoin('staff as s', 's.staff_id', '=', 't.staff_id')
            ->where('t.entry_id', $entryId)
            ->orderByDesc('t.created_at')
            ->orderByDesc('t.id')
            ->select([
                't.id',
                't.entry_id',
                't.staff_id',
                't.action',
                't.comments',
                't.created_at',
                DB::raw("TRIM(CONCAT(COALESCE(s.fname, ''), ' ', COALESCE(s.lname, ''))) AS staff_name"),
                's.photo as staff_photo',
                's.fname as staff_fname',
                's.lname as staff_lname',
            ])
            ->get();

        return $rows->map(function (object $row): object {
            $photo = trim((string) ($row->staff_photo ?? ''));
            $row->photo_url = StaffPhoto::url($photo !== '' ? $photo : null);
            $name = trim((string) ($row->staff_name ?? ''));
            $row->staff_name = $name !== '' ? $name : null;
            unset($row->staff_photo);

            return $row;
        });
    }

    /**
     * Oldest-first trail rows with role labels for the printable PDF.
     *
     * @return list<array{staff_name: string, role: string, action: string, created_at: string, comments: string, badge: string}>
     */
    public function printTrail(string $entryId, PerformancePhase $phase, object $entry): array
    {
        $staffId = (int) ($entry->staff_id ?? 0);
        $supervisor1Id = match ($phase) {
            PerformancePhase::Midterm => (int) ($entry->midterm_supervisor_1 ?? $entry->supervisor_id ?? 0),
            PerformancePhase::Endterm => (int) ($entry->endterm_supervisor_1 ?? $entry->supervisor_id ?? 0),
            default => (int) ($entry->supervisor_id ?? 0),
        };
        $supervisor2Id = match ($phase) {
            PerformancePhase::Midterm => (int) ($entry->midterm_supervisor_2 ?? $entry->supervisor2_id ?? 0),
            PerformancePhase::Endterm => (int) ($entry->endterm_supervisor_2 ?? $entry->supervisor2_id ?? 0),
            default => (int) ($entry->supervisor2_id ?? 0),
        };
        $sameSupervisor = $supervisor1Id > 0 && ($supervisor2Id === 0 || $supervisor1Id === $supervisor2Id);
        $firstSupervisorApprovals = 0;

        return $this->trail($entryId, $phase)
            ->reverse()
            ->values()
            ->map(function (object $log) use (
                $staffId,
                $supervisor1Id,
                $supervisor2Id,
                $sameSupervisor,
                &$firstSupervisorApprovals,
            ): array {
                $sid = (int) ($log->staff_id ?? 0);
                $action = trim((string) ($log->action ?? ''));
                $role = 'Other';

                if ($sid === $staffId) {
                    $role = 'Staff';
                } elseif ($sid === $supervisor1Id && $supervisor1Id > 0) {
                    if ($sameSupervisor && strcasecmp($action, 'Approved') === 0) {
                        $firstSupervisorApprovals++;
                        $role = $firstSupervisorApprovals === 2 ? 'Second Supervisor' : 'First Supervisor';
                    } else {
                        $role = 'First Supervisor';
                    }
                } elseif ($supervisor2Id > 0 && $sid === $supervisor2Id && ! $sameSupervisor) {
                    $role = 'Second Supervisor';
                }

                $when = $log->created_at ?? null;
                try {
                    $whenLabel = $when ? \Carbon\Carbon::parse($when)->format('d M Y · H:i') : '—';
                } catch (\Throwable) {
                    $whenLabel = $when ? (string) $when : '—';
                }

                $lower = strtolower($action);

                return [
                    'staff_name' => trim((string) ($log->staff_name ?? '')) !== ''
                        ? (string) $log->staff_name
                        : ('Staff #'.$sid),
                    'role' => $role,
                    'action' => $action !== '' ? $action : 'Update',
                    'created_at' => $whenLabel,
                    'comments' => (string) ($log->comments ?? ''),
                    'badge' => match (true) {
                        str_contains($lower, 'approv') => 'approved',
                        str_contains($lower, 'consent') => 'consent',
                        str_contains($lower, 'return') || str_contains($lower, 'reject') => 'returned',
                        str_contains($lower, 'submit') => 'submitted',
                        default => 'other',
                    },
                ];
            })
            ->all();
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
    protected function pendingMidterm(int $supervisorStaffId, bool $includeAssignedWaiting = false): Collection
    {
        $sid = (int) $supervisorStaffId;
        $items = collect();
        $latestContract = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        $rows = DB::table('ppa_entries as p')
            ->join('staff as s', 's.staff_id', '=', 'p.staff_id')
            ->leftJoinSub($latestContract, 'lc', 'lc.staff_id', '=', 'p.staff_id')
            ->leftJoin('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->whereNotNull('p.midterm_created_at')
            ->where('p.midterm_draft_status', 0)
            ->where(function ($q) use ($sid) {
                $q->where('p.midterm_supervisor_1', $sid)
                    ->orWhere('p.midterm_supervisor_2', $sid)
                    ->orWhere('p.supervisor_id', $sid)
                    ->orWhere('p.supervisor2_id', $sid)
                    ->orWhere('sc.first_supervisor', $sid)
                    ->orWhere('sc.second_supervisor', $sid);
            })
            ->select('p.*', DB::raw("CONCAT(s.fname, ' ', s.lname) AS staff_name"))
            ->orderByDesc('p.midterm_created_at')
            ->limit(200)
            ->get();

        foreach ($rows as $entry) {
            if (empty($entry->midterm_supervisor_1) && empty($entry->midterm_supervisor_2)) {
                $this->workflow->syncSupervisorsFromContract($entry, PerformancePhase::Midterm);
                $fresh = DB::table('ppa_entries')->where('entry_id', $entry->entry_id)->first();
                if ($fresh) {
                    foreach ((array) $fresh as $key => $value) {
                        $entry->{$key} = $value;
                    }
                }
            }

            $state = $this->workflow->resolveState($entry, PerformancePhase::Midterm);
            $canAct = $state['can_act'] && (int) ($state['actor_staff_id'] ?? 0) === $sid;
            $named = $this->isNamedSupervisor($entry, PerformancePhase::Midterm, $sid);
            if (! $canAct && ! ($includeAssignedWaiting && $named && ($state['status_key'] ?? '') !== 'approved')) {
                continue;
            }

            $entry->overall_status = $state['label'];
            $entry->can_act = $canAct;
            $items->push($entry);
        }

        return $items;
    }

    /**
     * @return Collection<int, object>
     */
    protected function pendingEndterm(int $supervisorStaffId, bool $includeAssignedWaiting = false): Collection
    {
        $items = collect();
        $sid = (int) $supervisorStaffId;
        $latestContract = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        $entries = DB::table('ppa_entries as p')
            ->join('staff as s', 's.staff_id', '=', 'p.staff_id')
            ->leftJoinSub($latestContract, 'lc', 'lc.staff_id', '=', 'p.staff_id')
            ->leftJoin('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->whereNotNull('p.endterm_created_at')
            ->where('p.endterm_draft_status', 0)
            ->where(function ($q) use ($sid) {
                $q->where('p.endterm_supervisor_1', $sid)
                    ->orWhere('p.endterm_supervisor_2', $sid)
                    ->orWhere('p.supervisor_id', $sid)
                    ->orWhere('p.supervisor2_id', $sid)
                    ->orWhere('sc.first_supervisor', $sid)
                    ->orWhere('sc.second_supervisor', $sid);
            })
            ->select('p.*', DB::raw("CONCAT(s.fname, ' ', s.lname) AS staff_name"))
            ->orderByDesc('p.endterm_updated_at')
            ->limit(200)
            ->get();

        foreach ($entries as $entry) {
            if (empty($entry->endterm_supervisor_1) && empty($entry->endterm_supervisor_2)) {
                $this->workflow->syncSupervisorsFromContract($entry, PerformancePhase::Endterm);
                $fresh = DB::table('ppa_entries')->where('entry_id', $entry->entry_id)->first();
                if ($fresh) {
                    foreach ((array) $fresh as $key => $value) {
                        $entry->{$key} = $value;
                    }
                }
            }

            $state = $this->workflow->resolveState($entry, PerformancePhase::Endterm);
            $canAct = $state['can_act'] && (int) ($state['actor_staff_id'] ?? 0) === $sid;
            $named = $this->isNamedSupervisor($entry, PerformancePhase::Endterm, $sid);
            // Named supervisors still track forms waiting on consent / the other supervisor.
            if (! $canAct && ! ($includeAssignedWaiting && $named && ($state['status_key'] ?? '') !== 'approved')) {
                continue;
            }

            $entry->overall_status = $state['label'];
            $entry->can_act = $canAct;
            $items->push($entry);
        }

        return $items;
    }

    protected function isNamedSupervisor(object $entry, PerformancePhase $phase, int $staffId): bool
    {
        $sup = $this->workflow->supervisorIdsForPhase($entry, $phase);

        return (int) ($sup['supervisor_1'] ?? 0) === $staffId
            || (int) ($sup['supervisor_2'] ?? 0) === $staffId;
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
