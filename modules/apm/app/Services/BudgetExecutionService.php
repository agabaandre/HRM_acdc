<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityBudget;
use App\Models\Division;
use App\Models\FundCode;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\RequestARF;
use App\Models\ServiceRequest;
use App\Models\SpecialMemo;
use App\Support\BudgetBreakdownTotal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Budget execution = approved APM initiatives vs funds requested via approved SR/ARF.
 * 100% executed when requested amount reaches the initiative budget.
 */
class BudgetExecutionService
{
    private const EXECUTED_STATUSES = ['approved'];

    /**
     * @param  list<int>|null  $divisionIds  null = all divisions
     * @return array{
     *   summary: array<string, mixed>,
     *   by_division: list<array<string, mixed>>,
     *   divisions: list<array<string, mixed>>,
     *   initiatives: list<array<string, mixed>>,
     *   filters: array<string, mixed>,
     * }
     */
    public function buildDashboard(
        ?array $divisionIds,
        ?int $year,
        ?string $quarter,
        string $periodMode = 'quarterly'
    ): array {
        $initiatives = $this->collectInitiatives($divisionIds, $year, $quarter, $periodMode);
        $executionDocs = $this->loadExecutionDocuments($initiatives);
        $this->attachExecutionMetrics($initiatives, $executionDocs);
        $this->attachFundCodeDetails($initiatives, $executionDocs);

        $byDivision = $this->aggregateByDivision($initiatives);
        $divisions = $this->buildDivisionsDetail($initiatives, $byDivision);
        $summary = $this->aggregateSummary($initiatives);

        return [
            'summary' => $summary,
            'by_division' => $byDivision,
            'divisions' => $divisions,
            'initiatives' => $initiatives->values()->all(),
            'filters' => [
                'year' => $year,
                'quarter' => $quarter,
                'period_mode' => $periodMode,
                'division_ids' => $divisionIds,
            ],
        ];
    }

    /**
     * @param  list<int>|null  $divisionIds
     * @return Collection<int, array<string, mixed>>
     */
    private function collectInitiatives(?array $divisionIds, ?int $year, ?string $quarter, string $periodMode): Collection
    {
        $rows = collect();

        $activities = Activity::query()
            ->with(['matrix:id,year,quarter,division_id'])
            ->where('overall_status', 'approved')
            ->whereHas('matrix', function ($q) {
                $q->where('overall_status', 'approved');
            });

        if ($divisionIds !== null) {
            $activities->where(function ($q) use ($divisionIds) {
                $q->whereIn('division_id', $divisionIds)
                    ->orWhere(function ($q2) use ($divisionIds) {
                        $q2->whereNull('division_id')
                            ->whereHas('matrix', fn ($m) => $m->whereIn('division_id', $divisionIds));
                    });
            });
        }

        if ($year !== null) {
            $activities->whereHas('matrix', fn ($q) => $q->where('year', $year));
        }

        if ($periodMode === 'quarterly' && $quarter !== null && $quarter !== '') {
            $activities->whereHas('matrix', fn ($q) => $q->where('quarter', $quarter));
        }

        $activityList = $activities->get();
        $activityBudgetsByActivity = ActivityBudget::query()
            ->whereIn('activity_id', $activityList->pluck('id'))
            ->get()
            ->groupBy('activity_id');

        foreach ($activityList as $activity) {
            $planned = $this->plannedBudgetForActivity($activity, $activityBudgetsByActivity);
            if ($planned <= 0) {
                continue;
            }

            $divisionId = (int) ($activity->division_id ?: ($activity->matrix?->division_id ?? 0));
            $rows->push($this->blankInitiativeRow([
                'source_type' => (int) ($activity->is_single_memo ?? 0) === 1 ? 'single_memo' : 'matrix_activity',
                'source_id' => (int) $activity->id,
                'title' => (string) $activity->activity_title,
                'document_number' => (string) ($activity->document_number ?? ''),
                'division_id' => $divisionId,
                'division_name' => $this->divisionName($divisionId),
                'year' => (int) ($activity->matrix?->year ?? 0),
                'quarter' => (string) ($activity->matrix?->quarter ?? ''),
                'planned_budget' => $planned,
                'budget_breakdown' => $activity->budget_breakdown,
                'activity_budgets' => $activityBudgetsByActivity->get($activity->id, collect())->all(),
            ]));
        }

        $specialMemos = SpecialMemo::query()->where('overall_status', 'approved');
        if ($divisionIds !== null) {
            $specialMemos->whereIn('division_id', $divisionIds);
        }
        $this->applyCalendarPeriod($specialMemos, 'date_from', $year, $quarter, $periodMode);

        foreach ($specialMemos->get() as $memo) {
            $planned = BudgetBreakdownTotal::fromFundCodeBreakdown($memo->budget_breakdown);
            if ($planned <= 0) {
                continue;
            }
            $divisionId = (int) ($memo->division_id ?? 0);
            $period = $this->periodFromDate($memo->date_from);
            $rows->push($this->blankInitiativeRow([
                'source_type' => 'special_memo',
                'source_id' => (int) $memo->id,
                'title' => (string) $memo->activity_title,
                'document_number' => (string) ($memo->document_number ?? ''),
                'division_id' => $divisionId,
                'division_name' => $this->divisionName($divisionId),
                'year' => $period['year'],
                'quarter' => $period['quarter'],
                'planned_budget' => $planned,
                'budget_breakdown' => $memo->budget_breakdown,
            ]));
        }

        $nonTravel = NonTravelMemo::query()->where('overall_status', 'approved');
        if ($divisionIds !== null) {
            $nonTravel->whereIn('division_id', $divisionIds);
        }
        $this->applyCalendarPeriod($nonTravel, 'memo_date', $year, $quarter, $periodMode);

        foreach ($nonTravel->get() as $memo) {
            $planned = BudgetBreakdownTotal::fromNonTravelBreakdown($memo->budget_breakdown);
            if ($planned <= 0) {
                continue;
            }
            $divisionId = (int) ($memo->division_id ?? 0);
            $period = $this->periodFromDate($memo->memo_date);
            $rows->push($this->blankInitiativeRow([
                'source_type' => 'non_travel_memo',
                'source_id' => (int) $memo->id,
                'title' => (string) $memo->activity_title,
                'document_number' => (string) ($memo->document_number ?? ''),
                'division_id' => $divisionId,
                'division_name' => $this->divisionName($divisionId),
                'year' => $period['year'],
                'quarter' => $period['quarter'],
                'planned_budget' => $planned,
                'budget_breakdown' => $memo->budget_breakdown,
            ]));
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function blankInitiativeRow(array $fields): array
    {
        return array_merge([
            'executed_budget' => 0.0,
            'execution_pct' => 0.0,
            'has_sr_or_arf' => false,
            'fully_executed' => false,
            'sr_count' => 0,
            'arf_count' => 0,
            'fund_codes' => [],
        ], $fields);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $initiatives
     * @return array{
     *   sr_by_key: array<string, list<ServiceRequest>>,
     *   arf_by_key: array<string, list<RequestARF>>,
     * }
     */
    private function loadExecutionDocuments(Collection $initiatives): array
    {
        $activityIds = $initiatives->whereIn('source_type', ['matrix_activity', 'single_memo'])->pluck('source_id')->all();
        $specialIds = $initiatives->where('source_type', 'special_memo')->pluck('source_id')->all();
        $nonTravelIds = $initiatives->where('source_type', 'non_travel_memo')->pluck('source_id')->all();

        $srByKey = [];
        if ($activityIds !== [] || $specialIds !== [] || $nonTravelIds !== []) {
            $srQuery = ServiceRequest::query()
                ->whereIn('overall_status', self::EXECUTED_STATUSES)
                ->where(function ($q) use ($activityIds, $specialIds, $nonTravelIds) {
                    if ($activityIds !== []) {
                        $q->orWhere(function ($q2) use ($activityIds) {
                            $q2->where('source_type', 'activity')->whereIn('source_id', $activityIds);
                        });
                        $q->orWhereIn('activity_id', $activityIds);
                    }
                    if ($specialIds !== []) {
                        $q->orWhere(function ($q2) use ($specialIds) {
                            $q2->where('source_type', 'special_memo')->whereIn('source_id', $specialIds);
                        });
                    }
                    if ($nonTravelIds !== []) {
                        $q->orWhere(function ($q2) use ($nonTravelIds) {
                            $q2->where('source_type', 'non_travel_memo')->whereIn('source_id', $nonTravelIds);
                        });
                    }
                });

            foreach ($srQuery->get(['id', 'source_type', 'source_id', 'activity_id', 'new_total_budget', 'estimated_cost', 'budget_breakdown']) as $sr) {
                if ($sr->source_type && $sr->source_id) {
                    $srByKey[$this->initiativeKey((string) $sr->source_type, (int) $sr->source_id)][] = $sr;
                }
                if ((int) ($sr->activity_id ?? 0) > 0) {
                    $srByKey[$this->initiativeKey('activity', (int) $sr->activity_id)][] = $sr;
                }
            }
        }

        $arfByKey = [];
        $arfPairs = [
            [Activity::class, $activityIds],
            [SpecialMemo::class, $specialIds],
            [NonTravelMemo::class, $nonTravelIds],
        ];
        foreach ($arfPairs as [$modelClass, $ids]) {
            if ($ids === []) {
                continue;
            }
            foreach (RequestARF::query()
                ->whereIn('overall_status', self::EXECUTED_STATUSES)
                ->where('model_type', $modelClass)
                ->whereIn('source_id', $ids)
                ->get(['id', 'model_type', 'source_id', 'requested_amount', 'total_amount', 'budget_breakdown']) as $arf) {
                $arfByKey[$this->arfInitiativeKey((string) $arf->model_type, (int) $arf->source_id)][] = $arf;
            }
        }

        return ['sr_by_key' => $srByKey, 'arf_by_key' => $arfByKey];
    }

    private function initiativeKey(string $srSourceType, int $sourceId): string
    {
        $type = match ($srSourceType) {
            'activity' => 'matrix_activity',
            default => $srSourceType,
        };

        return $type . ':' . $sourceId;
    }

    private function arfInitiativeKey(string $modelType, int $sourceId): string
    {
        $type = match ($modelType) {
            Activity::class => 'matrix_activity',
            default => class_basename($modelType),
        };
        $type = match ($type) {
            'SpecialMemo' => 'special_memo',
            'NonTravelMemo' => 'non_travel_memo',
            default => strtolower($type),
        };

        return $type . ':' . $sourceId;
    }

    private function rowInitiativeKey(array $row): string
    {
        $type = $row['source_type'];
        if ($type === 'single_memo') {
            $type = 'matrix_activity';
        }

        return $type . ':' . (int) $row['source_id'];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $initiatives
     * @param  array{sr_by_key: array<string, list<ServiceRequest>>, arf_by_key: array<string, list<RequestARF>>}  $executionDocs
     */
    private function attachExecutionMetrics(Collection $initiatives, array $executionDocs): void
    {
        $srByKey = $executionDocs['sr_by_key'];
        $arfByKey = $executionDocs['arf_by_key'];

        $initiatives->transform(function (array $row) use ($srByKey, $arfByKey) {
            $key = $this->rowInitiativeKey($row);
            $executed = 0.0;
            $srCount = 0;
            $arfCount = 0;

            foreach ($srByKey[$key] ?? [] as $sr) {
                $executed += (float) ($sr->new_total_budget ?? $sr->estimated_cost ?? 0);
                $srCount++;
            }
            foreach ($arfByKey[$key] ?? [] as $arf) {
                $executed += (float) ($arf->requested_amount ?? $arf->total_amount ?? 0);
                $arfCount++;
            }

            $planned = (float) $row['planned_budget'];
            $executed = round($executed, 2);
            $cappedExecuted = $planned > 0 ? min($planned, $executed) : 0.0;
            $pct = $planned > 0 ? round(min(100, ($cappedExecuted / $planned) * 100), 1) : 0.0;

            $row['executed_budget'] = $cappedExecuted;
            $row['execution_pct'] = $pct;
            $row['has_sr_or_arf'] = $executed > 0;
            $row['fully_executed'] = $planned > 0 && $cappedExecuted >= ($planned - 0.01);
            $row['sr_count'] = $srCount;
            $row['arf_count'] = $arfCount;

            return $row;
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $initiatives
     * @param  array{sr_by_key: array<string, list<ServiceRequest>>, arf_by_key: array<string, list<RequestARF>>}  $executionDocs
     */
    private function attachFundCodeDetails(Collection $initiatives, array $executionDocs): void
    {
        $fundCodeMeta = [];
        $balanceService = app(FundCodeWorkingBalanceService::class);
        $allFundCodeIds = [];

        $initiatives->transform(function (array $row) use ($executionDocs, &$fundCodeMeta, &$allFundCodeIds) {
            $plannedByCode = $this->plannedFundCodesForRow($row);
            $executedByCode = $this->executedFundCodesForRow($row, $executionDocs);
            $fundCodes = [];

            foreach (array_unique(array_merge(array_keys($plannedByCode), array_keys($executedByCode))) as $fundCodeId) {
                $fundCodeId = (int) $fundCodeId;
                if ($fundCodeId <= 0) {
                    continue;
                }
                $allFundCodeIds[] = $fundCodeId;
                $planned = round((float) ($plannedByCode[$fundCodeId] ?? 0), 2);
                $executed = round((float) ($executedByCode[$fundCodeId] ?? 0), 2);
                $cappedExecuted = $planned > 0 ? min($planned, $executed) : $executed;
                $remaining = round(max(0, $planned - $cappedExecuted), 2);

                if (! isset($fundCodeMeta[$fundCodeId])) {
                    $fc = FundCode::query()->find($fundCodeId, ['id', 'code', 'activity']);
                    $fundCodeMeta[$fundCodeId] = [
                        'code' => (string) ($fc->code ?? ('#' . $fundCodeId)),
                        'activity' => (string) ($fc->activity ?? ''),
                    ];
                }

                $fundCodes[] = [
                    'fund_code_id' => $fundCodeId,
                    'code' => $fundCodeMeta[$fundCodeId]['code'],
                    'activity' => $fundCodeMeta[$fundCodeId]['activity'],
                    'planned' => $planned,
                    'executed' => $cappedExecuted,
                    'remaining' => $remaining,
                    'working_balance' => 0.0,
                ];
            }

            usort($fundCodes, fn ($a, $b) => strcmp($a['code'], $b['code']));
            $row['fund_codes'] = $fundCodes;
            unset($row['budget_breakdown'], $row['activity_budgets']);

            return $row;
        });

        $allFundCodeIds = array_values(array_unique($allFundCodeIds));
        if ($allFundCodeIds === []) {
            return;
        }

        $snapshots = $balanceService->snapshotsFor($allFundCodeIds);
        $initiatives->transform(function (array $row) use ($snapshots) {
            $row['fund_codes'] = array_map(function (array $fc) use ($snapshots) {
                $fc['working_balance'] = (float) ($snapshots[$fc['fund_code_id']]['working_balance'] ?? 0);

                return $fc;
            }, $row['fund_codes']);

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, float>
     */
    private function plannedFundCodesForRow(array $row): array
    {
        if (! empty($row['activity_budgets'])) {
            $totals = [];
            foreach ($row['activity_budgets'] as $budget) {
                $fundCodeId = (int) ($budget->fund_code ?? 0);
                if ($fundCodeId <= 0) {
                    continue;
                }
                $totals[$fundCodeId] = round(($totals[$fundCodeId] ?? 0) + (float) ($budget->total ?? 0), 2);
            }
            if ($totals !== []) {
                return $totals;
            }
        }

        $nonTravel = $row['source_type'] === 'non_travel_memo';

        return BudgetBreakdownTotal::fundCodeTotalsFromExecutionBreakdown(
            $row['budget_breakdown'] ?? null,
            $nonTravel
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{sr_by_key: array<string, list<ServiceRequest>>, arf_by_key: array<string, list<RequestARF>>}  $executionDocs
     * @return array<int, float>
     */
    private function executedFundCodesForRow(array $row, array $executionDocs): array
    {
        $key = $this->rowInitiativeKey($row);
        $nonTravel = $row['source_type'] === 'non_travel_memo';
        $totals = [];

        foreach ($executionDocs['sr_by_key'][$key] ?? [] as $sr) {
            $part = BudgetBreakdownTotal::fundCodeTotalsFromExecutionBreakdown($sr->budget_breakdown, $nonTravel);
            foreach ($part as $fundCodeId => $amount) {
                $totals[$fundCodeId] = round(($totals[$fundCodeId] ?? 0) + $amount, 2);
            }
            if ($part === [] && (float) ($sr->new_total_budget ?? 0) > 0) {
                // SR without breakdown: cannot allocate to fund codes
            }
        }

        foreach ($executionDocs['arf_by_key'][$key] ?? [] as $arf) {
            $part = BudgetBreakdownTotal::fundCodeTotalsFromExecutionBreakdown($arf->budget_breakdown, $nonTravel);
            foreach ($part as $fundCodeId => $amount) {
                $totals[$fundCodeId] = round(($totals[$fundCodeId] ?? 0) + $amount, 2);
            }
        }

        return $totals;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $initiatives
     * @param  list<array<string, mixed>>  $byDivision
     * @return list<array<string, mixed>>
     */
    private function buildDivisionsDetail(Collection $initiatives, array $byDivision): array
    {
        $grouped = $initiatives->groupBy('division_id');

        return collect($byDivision)->map(function (array $summary) use ($grouped) {
            $divisionId = (int) $summary['division_id'];
            $items = $grouped->get($divisionId, collect())->sortBy('title')->values()->all();
            $fundCodeRollup = $this->rollupFundCodes($items);
            $totalWorkingBalance = round(collect($fundCodeRollup)->sum('working_balance'), 2);

            return array_merge($summary, [
                'initiatives' => $items,
                'fund_codes' => $fundCodeRollup,
                'fund_code_count' => count($fundCodeRollup),
                'remaining_budget' => round(max(0, (float) $summary['planned_budget'] - (float) $summary['executed_budget']), 2),
                'total_working_balance' => $totalWorkingBalance,
                'not_started_count' => (int) $summary['not_started_count'],
                'partial_count' => (int) $summary['partial_count'],
                'sr_count' => (int) $summary['sr_count'],
                'arf_count' => (int) $summary['arf_count'],
            ]);
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $initiatives
     * @return list<array<string, mixed>>
     */
    private function rollupFundCodes(array $initiatives): array
    {
        $rollup = [];
        foreach ($initiatives as $initiative) {
            foreach ($initiative['fund_codes'] ?? [] as $fc) {
                $id = (int) $fc['fund_code_id'];
                if (! isset($rollup[$id])) {
                    $rollup[$id] = [
                        'fund_code_id' => $id,
                        'code' => $fc['code'],
                        'activity' => $fc['activity'],
                        'planned' => 0.0,
                        'executed' => 0.0,
                        'remaining' => 0.0,
                        'working_balance' => (float) ($fc['working_balance'] ?? 0),
                    ];
                }
                $rollup[$id]['planned'] = round($rollup[$id]['planned'] + (float) $fc['planned'], 2);
                $rollup[$id]['executed'] = round($rollup[$id]['executed'] + (float) $fc['executed'], 2);
                $rollup[$id]['remaining'] = round($rollup[$id]['remaining'] + (float) $fc['remaining'], 2);
            }
        }

        return collect($rollup)->sortBy('code')->values()->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, ActivityBudget>>|null  $activityBudgetsByActivity
     */
    private function plannedBudgetForActivity(Activity $activity, $activityBudgetsByActivity = null): float
    {
        $budgets = $activityBudgetsByActivity?->get($activity->id) ?? ActivityBudget::query()
            ->where('activity_id', $activity->id)
            ->get();

        $fromRows = (float) $budgets->sum('total');

        if ($fromRows > 0) {
            return round($fromRows, 2);
        }

        return BudgetBreakdownTotal::fromFundCodeBreakdown($activity->budget_breakdown);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $initiatives
     * @return list<array<string, mixed>>
     */
    private function aggregateByDivision(Collection $initiatives): array
    {
        return $initiatives
            ->groupBy('division_id')
            ->map(function (Collection $group, $divisionId) {
                $planned = round($group->sum('planned_budget'), 2);
                $executed = round($group->sum('executed_budget'), 2);
                $pct = $planned > 0 ? round(min(100, ($executed / $planned) * 100), 1) : 0.0;
                $fullyExecuted = $group->where('fully_executed', true)->count();
                $withSrOrArf = $group->where('has_sr_or_arf', true)->count();

                return [
                    'division_id' => (int) $divisionId,
                    'division_name' => (string) ($group->first()['division_name'] ?? 'Unknown'),
                    'initiative_count' => $group->count(),
                    'with_sr_or_arf' => $withSrOrArf,
                    'fully_executed_count' => $fullyExecuted,
                    'not_started_count' => $group->count() - $withSrOrArf,
                    'partial_count' => max(0, $withSrOrArf - $fullyExecuted),
                    'sr_count' => (int) $group->sum('sr_count'),
                    'arf_count' => (int) $group->sum('arf_count'),
                    'planned_budget' => $planned,
                    'executed_budget' => $executed,
                    'remaining_budget' => round(max(0, $planned - $executed), 2),
                    'execution_pct' => $pct,
                ];
            })
            ->sortBy('division_name')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $initiatives
     * @return array<string, mixed>
     */
    private function aggregateSummary(Collection $initiatives): array
    {
        $planned = round($initiatives->sum('planned_budget'), 2);
        $executed = round($initiatives->sum('executed_budget'), 2);
        $pct = $planned > 0 ? round(min(100, ($executed / $planned) * 100), 1) : 0.0;
        $withSrOrArf = $initiatives->where('has_sr_or_arf', true)->count();
        $fullyExecuted = $initiatives->where('fully_executed', true)->count();

        return [
            'initiative_count' => $initiatives->count(),
            'division_count' => $initiatives->pluck('division_id')->unique()->count(),
            'with_sr_or_arf' => $withSrOrArf,
            'fully_executed_count' => $fullyExecuted,
            'not_started_count' => $initiatives->count() - $withSrOrArf,
            'partial_count' => max(0, $withSrOrArf - $fullyExecuted),
            'sr_count' => (int) $initiatives->sum('sr_count'),
            'arf_count' => (int) $initiatives->sum('arf_count'),
            'planned_budget' => $planned,
            'executed_budget' => $executed,
            'remaining_budget' => round(max(0, $planned - $executed), 2),
            'execution_pct' => $pct,
        ];
    }

    private function divisionName(int $divisionId): string
    {
        if ($divisionId <= 0) {
            return 'Unassigned';
        }

        static $cache = [];
        if (! isset($cache[$divisionId])) {
            $cache[$divisionId] = (string) (Division::query()->whereKey($divisionId)->value('division_name') ?? 'Division #' . $divisionId);
        }

        return $cache[$divisionId];
    }

    /**
     * @return array{year: int, quarter: string}
     */
    private function periodFromDate(mixed $date): array
    {
        if (! $date) {
            return ['year' => 0, 'quarter' => ''];
        }

        $c = Carbon::parse($date);

        return [
            'year' => (int) $c->year,
            'quarter' => 'Q' . $c->quarter,
        ];
    }

    private function applyCalendarPeriod($query, string $dateColumn, ?int $year, ?string $quarter, string $periodMode): void
    {
        if ($year !== null) {
            $query->whereYear($dateColumn, $year);
        }

        if ($periodMode === 'quarterly' && $quarter !== null && $quarter !== '') {
            $qNum = (int) preg_replace('/\D/', '', $quarter);
            if ($qNum >= 1 && $qNum <= 4) {
                $query->whereRaw('QUARTER(' . $dateColumn . ') = ?', [$qNum]);
            }
        }
    }
}
