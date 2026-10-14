<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Support\EndtermScore;

/**
 * CI3-parity analytics for PPA / midterm / endterm dashboards.
 */
class PerformanceAnalyticsService
{
    public function __construct(
        protected PerformanceWorkflowService $workflow,
        protected SupervisorResolver $supervisors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(
        PerformancePhase $phase,
        ?int $divisionId,
        string $period,
        ?int $restrictStaffId = null,
        ?int $funderId = null,
    ): array {
        $draftCol = $phase->draftStatusColumn();
        $trailTable = $phase->trailTable();
        $trendCol = match ($phase) {
            PerformancePhase::Ppa => 'pe.created_at',
            PerformancePhase::Midterm => 'pe.midterm_updated_at',
            PerformancePhase::Endterm => 'pe.endterm_updated_at',
        };

        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        $base = DB::table('ppa_entries as pe')
            ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                $j->on('sc.staff_id', '=', 'pe.staff_id')
                    ->whereIn('sc.staff_contract_id', $latestContractSub);
            })
            ->where('pe.performance_period', $period)
            ->whereNotNull("pe.{$draftCol}")
            ->where("pe.{$draftCol}", '!=', 1);

        if ($phase === PerformancePhase::Endterm) {
            $base->whereNotNull('pe.endterm_updated_at');
        }
        if ($divisionId) {
            $base->where('sc.division_id', $divisionId);
        }
        if ($funderId && $phase === PerformancePhase::Endterm) {
            $base->where('sc.funder_id', $funderId);
        }
        if ($restrictStaffId) {
            $base->where('pe.staff_id', $restrictStaffId);
        }

        [$approved, $submitted] = $this->approvalCounts($base, $trailTable, $draftCol);
        $total = (clone $base)->count();
        $draft = DB::table('ppa_entries as pe')
            ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                $j->on('sc.staff_id', '=', 'pe.staff_id')
                    ->whereIn('sc.staff_contract_id', $latestContractSub);
            })
            ->where('pe.performance_period', $period)
            ->where("pe.{$draftCol}", 1)
            ->when($divisionId, fn ($q) => $q->where('sc.division_id', $divisionId))
            ->when($funderId && $phase === PerformancePhase::Endterm, fn ($q) => $q->where('sc.funder_id', $funderId))
            ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId))
            ->count();

        $eligibleStaffIds = $this->eligibleStaffIds($divisionId, $funderId, $restrictStaffId, $phase === PerformancePhase::Endterm);
        $withPhase = (clone $base)->distinct()->pluck('pe.staff_id');
        $without = max(0, $eligibleStaffIds->diff($withPhase)->count());

        $byDivision = (clone $base)
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->selectRaw('COALESCE(d.division_name, \'Unassigned\') as name, COUNT(pe.entry_id) as y')
            ->groupBy('sc.division_id', 'd.division_name')
            ->orderByDesc('y')
            ->get()
            ->map(fn ($r) => ['name' => (string) $r->name, 'y' => (int) $r->y])
            ->values()
            ->all();

        $byContract = (clone $base)
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->selectRaw('COALESCE(ct.contract_type, \'Unknown\') as name, COUNT(pe.entry_id) as y')
            ->groupBy('ct.contract_type_id', 'ct.contract_type')
            ->orderByDesc('y')
            ->get()
            ->map(fn ($r) => ['name' => (string) $r->name, 'y' => (int) $r->y])
            ->values()
            ->all();

        $trendDate = "DATE({$trendCol})";
        $trend = (clone $base)
            ->whereNotNull(DB::raw($trendCol))
            ->selectRaw("{$trendDate} as date, COUNT(pe.entry_id) as count")
            ->groupBy(DB::raw($trendDate))
            ->orderBy('date')
            ->limit(90)
            ->get()
            ->map(fn ($r) => ['date' => (string) $r->date, 'count' => (int) $r->count])
            ->values()
            ->all();

        $avgApprovalDays = $this->averageApprovalDays($phase, $period, $divisionId, $restrictStaffId, $funderId);

        $payload = [
            'phase' => $phase->value,
            'phase_label' => $phase->label(),
            'period' => $period,
            'summary' => [
                'total' => $total,
                'approved' => $approved,
                'submitted' => $submitted,
                'draft' => $draft,
                'without' => $without,
                'staff_count' => $eligibleStaffIds->count(),
                'pdps' => 0,
                'require_calibration' => 0,
            ],
            'approval_breakdown' => [
                ['name' => 'Approved', 'y' => $approved],
                ['name' => 'Pending', 'y' => max(0, $submitted)],
            ],
            'avg_approval_days' => $avgApprovalDays,
            'by_division' => $byDivision,
            'by_contract' => $byContract,
            'trend' => $trend,
            'training_categories' => [],
            'training_skills' => [],
            'avg_score' => null,
            'score_bands' => null,
            'division_averages' => [],
            'funder_averages' => [],
            'create_url' => $phase === PerformancePhase::Ppa
                ? route('performance.ppa.create', ['period' => $period])
                : null,
        ];

        if ($phase === PerformancePhase::Ppa || $phase === PerformancePhase::Midterm) {
            $payload['summary']['pdps'] = $this->pdpCount($phase, $period, $divisionId, $restrictStaffId);
            $payload['training_categories'] = $this->trainingCategories($period, $divisionId, $restrictStaffId, $draftCol);
            $payload['training_skills'] = $this->trainingSkills($period, $divisionId, $restrictStaffId, $draftCol);
        }

        if ($phase === PerformancePhase::Endterm) {
            $scores = $this->endtermScores($period, $divisionId, $funderId, $restrictStaffId);
            $payload['avg_score'] = $scores['avg_score'];
            $payload['score_bands'] = $scores['score_bands'];
            $payload['division_averages'] = $scores['division_averages'];
            $payload['funder_averages'] = $scores['funder_averages'];
            $payload['summary']['require_calibration'] = $scores['require_calibration'];
        }

        return $payload;
    }

    /**
     * CI3-parity KPI card drill-down: staff/entry rows for a summary cohort.
     *
     * @param  'total'|'approved'|'submitted'|'pdps'|'without'|'require_calibration'  $filter
     * @return array{filter: string, title: string, phase: string, period: string, rows: list<array<string, mixed>>}
     */
    public function cohort(
        PerformancePhase $phase,
        string $filter,
        ?int $divisionId,
        string $period,
        ?int $restrictStaffId = null,
        ?int $funderId = null,
    ): array {
        $allowed = ['total', 'approved', 'submitted', 'pdps', 'without', 'require_calibration'];
        if (! in_array($filter, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported analytics cohort filter.');
        }
        if ($filter === 'pdps' && $phase === PerformancePhase::Endterm) {
            throw new \InvalidArgumentException('PDPs are not available for endterm.');
        }
        if ($filter === 'require_calibration' && $phase !== PerformancePhase::Endterm) {
            throw new \InvalidArgumentException('Calibration is only available for endterm.');
        }

        $rows = match ($filter) {
            'without' => $this->withoutRows($phase, $period, $divisionId, $restrictStaffId, $funderId),
            'pdps' => $this->entryCohortRows($phase, $period, $divisionId, $restrictStaffId, $funderId, 'pdps'),
            'approved' => $this->entryCohortRows($phase, $period, $divisionId, $restrictStaffId, $funderId, 'approved'),
            'submitted' => $this->entryCohortRows($phase, $period, $divisionId, $restrictStaffId, $funderId, 'submitted'),
            'require_calibration' => $this->entryCohortRows($phase, $period, $divisionId, $restrictStaffId, $funderId, 'require_calibration'),
            default => $this->entryCohortRows($phase, $period, $divisionId, $restrictStaffId, $funderId, 'total'),
        };

        $noun = match ($phase) {
            PerformancePhase::Midterm => 'midterms',
            PerformancePhase::Endterm => 'endterm reviews',
            default => 'PPAs',
        };

        $title = match ($filter) {
            'approved' => 'Approved '.$noun,
            'submitted' => 'Pending '.$noun,
            'pdps' => 'Staff PDPs',
            'without' => 'Without '.$noun,
            'require_calibration' => 'Require calibration',
            default => 'Staff '.$noun,
        };

        return [
            'filter' => $filter,
            'title' => $title,
            'phase' => $phase->value,
            'period' => $period,
            'rows' => $rows,
        ];
    }

    /**
     * @param  'total'|'approved'|'submitted'|'pdps'|'require_calibration'  $filter
     * @return list<array<string, mixed>>
     */
    protected function entryCohortRows(
        PerformancePhase $phase,
        string $period,
        ?int $divisionId,
        ?int $restrictStaffId,
        ?int $funderId,
        string $filter,
    ): array {
        $draftCol = $phase->draftStatusColumn();
        $trailTable = $phase->trailTable();
        $updatedCol = match ($phase) {
            PerformancePhase::Ppa => 'pe.updated_at',
            PerformancePhase::Midterm => 'pe.midterm_updated_at',
            PerformancePhase::Endterm => 'pe.endterm_updated_at',
        };

        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        $q = DB::table('ppa_entries as pe')
            ->leftJoin('staff as s', 's.staff_id', '=', 'pe.staff_id')
            ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                $j->on('sc.staff_id', '=', 'pe.staff_id')
                    ->whereIn('sc.staff_contract_id', $latestContractSub);
            })
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->where('pe.performance_period', $period)
            ->whereNotNull("pe.{$draftCol}")
            ->where("pe.{$draftCol}", '!=', 1);

        if ($phase === PerformancePhase::Endterm) {
            $q->whereNotNull('pe.endterm_updated_at');
        }
        if ($divisionId) {
            $q->where('sc.division_id', $divisionId);
        }
        if ($funderId && $phase === PerformancePhase::Endterm) {
            $q->where('sc.funder_id', $funderId);
        }
        if ($restrictStaffId) {
            $q->where('pe.staff_id', $restrictStaffId);
        }

        if ($filter === 'pdps') {
            if ($phase === PerformancePhase::Ppa) {
                $q->where('pe.training_recommended', 'Yes');
            } else {
                $q->whereNotNull('pe.midterm_recommended_skills')
                    ->where('pe.midterm_recommended_skills', '!=', '')
                    ->where('pe.midterm_recommended_skills', '!=', '[]')
                    ->where('pe.midterm_recommended_skills', '!=', 'null');
            }
        }

        if (in_array($filter, ['approved', 'submitted'], true) && Schema::hasTable($trailTable)) {
            $latest = DB::table($trailTable.' as pat1')
                ->joinSub(
                    DB::table($trailTable)->selectRaw('entry_id, MAX(id) as max_id')->groupBy('entry_id'),
                    'latest',
                    'pat1.id',
                    '=',
                    'latest.max_id'
                )
                ->select('pat1.entry_id', 'pat1.action');

            $q->leftJoinSub($latest, 'trail', function ($join): void {
                $join->whereRaw('trail.entry_id COLLATE utf8mb4_unicode_ci = pe.entry_id COLLATE utf8mb4_unicode_ci');
            });

            if ($filter === 'approved') {
                $q->where(function ($w) use ($draftCol): void {
                    $w->whereRaw("trail.action COLLATE utf8mb4_unicode_ci = 'Approved'")
                        ->orWhere("pe.{$draftCol}", 2);
                });
            } else {
                $q->where(function ($w) use ($draftCol): void {
                    $w->whereRaw("trail.action COLLATE utf8mb4_unicode_ci = 'Submitted'")
                        ->orWhere(function ($inner) use ($draftCol): void {
                            $inner->where("pe.{$draftCol}", 0)
                                ->where(function ($trail) {
                                    $trail->whereNull('trail.action')
                                        ->orWhereRaw("trail.action COLLATE utf8mb4_unicode_ci != 'Approved'");
                                });
                        });
                });
            }
        } elseif ($filter === 'approved') {
            $q->where("pe.{$draftCol}", 2);
        } elseif ($filter === 'submitted') {
            $q->where("pe.{$draftCol}", 0);
        }

        $rawRows = $q->orderBy('s.lname')->orderBy('s.fname')->get([
            'pe.entry_id',
            'pe.staff_id',
            's.fname',
            's.lname',
            's.SAPNO',
            'd.division_name',
            'ct.contract_type',
            "pe.{$draftCol} as draft_status",
            DB::raw("{$updatedCol} as updated_at"),
            'pe.objectives',
            'pe.midterm_objectives',
            'pe.endterm_objectives',
        ]);

        $out = [];
        foreach ($rawRows as $row) {
            $score = null;
            $category = null;
            if ($phase === PerformancePhase::Endterm) {
                $objectives = $row->endterm_objectives ?: ($row->midterm_objectives ?: $row->objectives);
                $rating = EndtermScore::fromObjectives($objectives);
                $score = $rating['score'];
                $category = $rating['category'];
                if ($filter === 'require_calibration' && ! ($score > 0 && $score < 51)) {
                    continue;
                }
            }

            $draftStatus = (int) ($row->draft_status ?? 0);
            $status = match (true) {
                $draftStatus === 2 => 'Approved',
                $draftStatus === 0 => 'Submitted',
                default => 'In progress',
            };
            if ($filter === 'require_calibration') {
                $status = 'Requires calibration'.($score !== null ? ' ('.$score.'%)' : '');
            }

            $staffId = (int) $row->staff_id;
            $entryId = (string) $row->entry_id;
            $out[] = [
                'entry_id' => $entryId,
                'staff_id' => $staffId,
                'staff_name' => trim(($row->fname ?? '').' '.($row->lname ?? '')) ?: ('Staff #'.$staffId),
                'sapno' => $row->SAPNO ?: null,
                'division' => $row->division_name ?: 'Unassigned',
                'contract_type' => $row->contract_type ?: 'Unknown',
                'status' => $status,
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
                'score' => $score,
                'score_category' => $category,
                'form_url' => '/performance/form/'.$phase->value.'/'.$entryId.'/'.$staffId,
                'can_preview' => true,
                'pending_with' => null,
                'pending_supervisor_id' => null,
                'can_remind_supervisor' => false,
            ];
        }

        return $this->enrichPendingSupervisors($out, $phase);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function enrichPendingSupervisors(array $rows, PerformancePhase $phase): array
    {
        if ($rows === []) {
            return $rows;
        }

        $entryIds = array_values(array_filter(array_map(
            fn (array $row) => $row['entry_id'] ?? null,
            $rows,
        )));
        if ($entryIds === []) {
            return $rows;
        }

        $entries = DB::table('ppa_entries')->whereIn('entry_id', $entryIds)->get()->keyBy('entry_id');

        foreach ($rows as &$row) {
            $entryId = (string) ($row['entry_id'] ?? '');
            $entry = $entries->get($entryId);
            if (! $entry) {
                continue;
            }

            $state = $this->workflow->resolveState($entry, $phase);
            $statusKey = (string) ($state['status_key'] ?? '');
            $actorId = (int) ($state['actor_staff_id'] ?? 0);

            if (str_starts_with($statusKey, 'pending_')) {
                $row['status'] = (string) ($state['label'] ?? $row['status']);
            }

            if (str_starts_with($statusKey, 'pending_supervisor') && $actorId > 0) {
                $row['pending_with'] = $this->supervisors->staffName($actorId);
                $row['pending_supervisor_id'] = $actorId;
                $row['can_remind_supervisor'] = true;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function withoutRows(
        PerformancePhase $phase,
        string $period,
        ?int $divisionId,
        ?int $restrictStaffId,
        ?int $funderId,
    ): array {
        $draftCol = $phase->draftStatusColumn();
        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        $withPhase = DB::table('ppa_entries as pe')
            ->where('pe.performance_period', $period)
            ->whereNotNull("pe.{$draftCol}")
            ->where("pe.{$draftCol}", '!=', 1)
            ->when($phase === PerformancePhase::Endterm, fn ($q) => $q->whereNotNull('pe.endterm_updated_at'))
            ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId))
            ->distinct()
            ->pluck('pe.staff_id');

        $eligible = $this->eligibleStaffIds($divisionId, $funderId, $restrictStaffId, $phase === PerformancePhase::Endterm);
        $withoutIds = $eligible->diff($withPhase)->values();
        if ($withoutIds->isEmpty()) {
            return [];
        }

        return DB::table('staff as s')
            ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                $j->on('sc.staff_id', '=', 's.staff_id')
                    ->whereIn('sc.staff_contract_id', $latestContractSub);
            })
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->whereIn('s.staff_id', $withoutIds->all())
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->get([
                's.staff_id',
                's.fname',
                's.lname',
                's.SAPNO',
                'd.division_name',
                'ct.contract_type',
            ])
            ->map(function ($row) use ($phase): array {
                $staffId = (int) $row->staff_id;
                $noun = match ($phase) {
                    PerformancePhase::Midterm => 'midterm',
                    PerformancePhase::Endterm => 'endterm',
                    default => 'PPA',
                };

                return [
                    'entry_id' => null,
                    'staff_id' => $staffId,
                    'staff_name' => trim(($row->fname ?? '').' '.($row->lname ?? '')) ?: ('Staff #'.$staffId),
                    'sapno' => $row->SAPNO ?: null,
                    'division' => $row->division_name ?: 'Unassigned',
                    'contract_type' => $row->contract_type ?: 'Unknown',
                    'status' => 'Without '.$noun,
                    'updated_at' => null,
                    'score' => null,
                    'score_category' => null,
                    'form_url' => null,
                    'can_preview' => false,
                    'pending_with' => null,
                    'pending_supervisor_id' => null,
                    'can_remind_supervisor' => false,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $base
     * @return array{0: int, 1: int}
     */
    protected function approvalCounts($base, string $trailTable, string $draftCol): array
    {
        if (Schema::hasTable($trailTable)) {
            $latest = DB::table($trailTable.' as pat1')
                ->joinSub(
                    DB::table($trailTable)->selectRaw('entry_id, MAX(id) as max_id')->groupBy('entry_id'),
                    'latest',
                    'pat1.id',
                    '=',
                    'latest.max_id'
                )
                ->select('pat1.entry_id', 'pat1.action');

            $row = (clone $base)
                ->leftJoinSub($latest, 'trail', function ($join): void {
                    // Avoid Illegal mix of collations when trail tables differ from ppa_entries.
                    $join->whereRaw('trail.entry_id COLLATE utf8mb4_unicode_ci = pe.entry_id COLLATE utf8mb4_unicode_ci');
                })
                ->selectRaw("
                    SUM(CASE WHEN trail.action COLLATE utf8mb4_unicode_ci = 'Approved' OR pe.{$draftCol} = 2 THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN trail.action COLLATE utf8mb4_unicode_ci = 'Submitted' OR (pe.{$draftCol} = 0 AND (trail.action IS NULL OR trail.action COLLATE utf8mb4_unicode_ci != 'Approved')) THEN 1 ELSE 0 END) as submitted
                ")
                ->first();

            return [(int) ($row->approved ?? 0), (int) ($row->submitted ?? 0)];
        }

        $approved = (clone $base)->where("pe.{$draftCol}", 2)->count();
        $submitted = (clone $base)->where("pe.{$draftCol}", 0)->count();

        return [$approved, $submitted];
    }

    protected function averageApprovalDays(
        PerformancePhase $phase,
        string $period,
        ?int $divisionId,
        ?int $restrictStaffId,
        ?int $funderId,
    ): float {
        $trailTable = $phase->trailTable();
        if (! Schema::hasTable($trailTable)) {
            return 0.0;
        }

        $draftCol = $phase->draftStatusColumn();
        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        $approvedTrail = DB::table($trailTable.' as pat1')
            ->joinSub(
                DB::table($trailTable)
                    ->where('action', 'Approved')
                    ->selectRaw('entry_id, MAX(id) as max_id')
                    ->groupBy('entry_id'),
                'latest',
                'pat1.id',
                '=',
                'latest.max_id'
            )
            ->select('pat1.entry_id', 'pat1.created_at as approved_at');

        $rows = DB::table('ppa_entries as pe')
            ->joinSub($approvedTrail, 'trail', function ($join): void {
                $join->whereRaw('trail.entry_id COLLATE utf8mb4_unicode_ci = pe.entry_id COLLATE utf8mb4_unicode_ci');
            })
            ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                $j->on('sc.staff_id', '=', 'pe.staff_id')
                    ->whereIn('sc.staff_contract_id', $latestContractSub);
            })
            ->where('pe.performance_period', $period)
            ->where("pe.{$draftCol}", '!=', 1)
            ->when($divisionId, fn ($q) => $q->where('sc.division_id', $divisionId))
            ->when($funderId && $phase === PerformancePhase::Endterm, fn ($q) => $q->where('sc.funder_id', $funderId))
            ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId))
            ->get(['pe.created_at', 'trail.approved_at']);

        $total = 0.0;
        $count = 0;
        foreach ($rows as $row) {
            if (! $row->created_at || ! $row->approved_at) {
                continue;
            }
            $days = (strtotime((string) $row->approved_at) - strtotime((string) $row->created_at)) / 86400;
            if ($days >= 0) {
                $total += $days;
                $count++;
            }
        }

        return $count ? round($total / $count, 1) : 0.0;
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function eligibleStaffIds(?int $divisionId, ?int $funderId, ?int $restrictStaffId, bool $withFunder): \Illuminate\Support\Collection
    {
        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        return DB::table('staff as s')
            ->join('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
            ->whereIn('sc.staff_contract_id', $latestContractSub)
            ->whereIn('sc.status_id', [1, 2, 7])
            ->whereNotIn('sc.contract_type_id', [1, 5, 3, 7])
            ->when($divisionId, fn ($q) => $q->where('sc.division_id', $divisionId))
            ->when($withFunder && $funderId, fn ($q) => $q->where('sc.funder_id', $funderId))
            ->when($restrictStaffId, fn ($q) => $q->where('s.staff_id', $restrictStaffId))
            ->distinct()
            ->pluck('s.staff_id');
    }

    protected function pdpCount(
        PerformancePhase $phase,
        string $period,
        ?int $divisionId,
        ?int $restrictStaffId,
    ): int {
        $draftCol = $phase->draftStatusColumn();
        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        $q = DB::table('ppa_entries as pe')
            ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                $j->on('sc.staff_id', '=', 'pe.staff_id')
                    ->whereIn('sc.staff_contract_id', $latestContractSub);
            })
            ->where('pe.performance_period', $period)
            ->where("pe.{$draftCol}", '!=', 1)
            ->when($divisionId, fn ($q) => $q->where('sc.division_id', $divisionId))
            ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId));

        if ($phase === PerformancePhase::Ppa) {
            $q->where('pe.training_recommended', 'Yes');
        } else {
            $q->whereNotNull('pe.midterm_recommended_skills')
                ->where('pe.midterm_recommended_skills', '!=', '')
                ->where('pe.midterm_recommended_skills', '!=', '[]')
                ->where('pe.midterm_recommended_skills', '!=', 'null');
        }

        return (int) $q->distinct()->count('pe.staff_id');
    }

    /**
     * @return list<array{name: string, y: int}>
     */
    protected function trainingCategories(string $period, ?int $divisionId, ?int $restrictStaffId, string $draftCol): array
    {
        if (! Schema::hasTable('training_skills') || ! Schema::hasTable('training_categories')) {
            return [];
        }

        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        try {
            return DB::table('ppa_entries as pe')
                ->join('training_skills as ts', function ($join): void {
                    $join->whereRaw("JSON_CONTAINS(pe.required_skills, JSON_QUOTE(CAST(ts.id AS CHAR)), '$')");
                })
                ->leftJoin('training_categories as tc', 'tc.id', '=', 'ts.category_id')
                ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                    $j->on('sc.staff_id', '=', 'pe.staff_id')
                        ->whereIn('sc.staff_contract_id', $latestContractSub);
                })
                ->where('pe.performance_period', $period)
                ->where("pe.{$draftCol}", '!=', 1)
                ->when($divisionId, fn ($q) => $q->where('sc.division_id', $divisionId))
                ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId))
                ->selectRaw('COALESCE(tc.category_name, \'Uncategorised\') as name, COUNT(*) as y')
                ->groupBy('ts.category_id', 'tc.category_name')
                ->orderByDesc('y')
                ->get()
                ->map(fn ($r) => ['name' => (string) $r->name, 'y' => (int) $r->y])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{name: string, y: int}>
     */
    protected function trainingSkills(string $period, ?int $divisionId, ?int $restrictStaffId, string $draftCol): array
    {
        if (! Schema::hasTable('training_skills')) {
            return [];
        }

        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        try {
            return DB::table('ppa_entries as pe')
                ->join('training_skills as ts', function ($join): void {
                    $join->whereRaw("JSON_CONTAINS(pe.required_skills, JSON_QUOTE(CAST(ts.id AS CHAR)), '$')");
                })
                ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                    $j->on('sc.staff_id', '=', 'pe.staff_id')
                        ->whereIn('sc.staff_contract_id', $latestContractSub);
                })
                ->where('pe.performance_period', $period)
                ->where("pe.{$draftCol}", '!=', 1)
                ->when($divisionId, fn ($q) => $q->where('sc.division_id', $divisionId))
                ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId))
                ->selectRaw('ts.skill as name, COUNT(*) as y')
                ->groupBy('ts.id', 'ts.skill')
                ->orderByDesc('y')
                ->limit(10)
                ->get()
                ->map(fn ($r) => ['name' => (string) $r->name, 'y' => (int) $r->y])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{
     *   avg_score: float,
     *   score_bands: array{outstanding: int, satisfactory: int, poor: int, not_rated: int},
     *   division_averages: list<array{name: string, avg_score: float}>,
     *   funder_averages: list<array{name: string, avg_score: float}>,
     *   require_calibration: int
     * }
     */
    protected function endtermScores(string $period, ?int $divisionId, ?int $funderId, ?int $restrictStaffId): array
    {
        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        $entries = DB::table('ppa_entries as pe')
            ->leftJoin('staff_contracts as sc', function ($j) use ($latestContractSub): void {
                $j->on('sc.staff_id', '=', 'pe.staff_id')
                    ->whereIn('sc.staff_contract_id', $latestContractSub);
            })
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->where('pe.performance_period', $period)
            ->where('pe.endterm_draft_status', '!=', 1)
            ->whereNotNull('pe.endterm_updated_at')
            ->when($divisionId, fn ($q) => $q->where('sc.division_id', $divisionId))
            ->when($funderId, fn ($q) => $q->where('sc.funder_id', $funderId))
            ->when($restrictStaffId, fn ($q) => $q->where('pe.staff_id', $restrictStaffId))
            ->get([
                'pe.endterm_objectives',
                'pe.midterm_objectives',
                'pe.objectives',
                'd.division_name',
                'f.funder',
            ]);

        $bands = ['outstanding' => 0, 'satisfactory' => 0, 'poor' => 0, 'not_rated' => 0];
        $totalScore = 0.0;
        $scoreCount = 0;
        $divisionScores = [];
        $funderScores = [];
        $calibration = 0;
        foreach ($entries as $entry) {
            $objectives = $entry->endterm_objectives ?: ($entry->midterm_objectives ?: $entry->objectives);
            $rating = EndtermScore::fromObjectives($objectives);
            $score = $rating['score'];
            $bands[$rating['category']]++;
            if ($score > 0) {
                $totalScore += $score;
                $scoreCount++;
            }
            if ($score > 0 && $score < 51) {
                $calibration++;
            }
            $div = (string) ($entry->division_name ?: 'Unknown');
            $funder = (string) ($entry->funder ?: 'Unknown');
            if (! isset($divisionScores[$div])) {
                $divisionScores[$div] = ['total' => 0.0, 'count' => 0];
            }
            if (! isset($funderScores[$funder])) {
                $funderScores[$funder] = ['total' => 0.0, 'count' => 0];
            }
            if ($score > 0) {
                $divisionScores[$div]['total'] += $score;
                $divisionScores[$div]['count']++;
                $funderScores[$funder]['total'] += $score;
                $funderScores[$funder]['count']++;
            }
        }

        $divisionAverages = [];
        foreach ($divisionScores as $name => $data) {
            if ($data['count'] > 0) {
                $divisionAverages[] = [
                    'name' => $name,
                    'avg_score' => round($data['total'] / $data['count'], 2),
                ];
            }
        }
        usort($divisionAverages, fn ($a, $b) => $b['avg_score'] <=> $a['avg_score']);

        $funderAverages = [];
        foreach ($funderScores as $name => $data) {
            if ($data['count'] > 0) {
                $funderAverages[] = [
                    'name' => $name,
                    'avg_score' => round($data['total'] / $data['count'], 2),
                ];
            }
        }
        usort($funderAverages, fn ($a, $b) => $b['avg_score'] <=> $a['avg_score']);

        return [
            'avg_score' => $scoreCount ? round($totalScore / $scoreCount, 2) : 0.0,
            'score_bands' => $bands,
            'division_averages' => $divisionAverages,
            'funder_averages' => $funderAverages,
            'require_calibration' => $calibration,
        ];
    }
}
