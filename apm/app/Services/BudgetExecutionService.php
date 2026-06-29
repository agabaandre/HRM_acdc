<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityBudget;
use App\Models\Division;
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
    $this->attachExecutionMetrics($initiatives);

    $byDivision = $this->aggregateByDivision($initiatives);
    $summary = $this->aggregateSummary($initiatives);

    return [
      'summary' => $summary,
      'by_division' => $byDivision,
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
      ->with(['matrix:id,year,quarter,division_id,division_name'])
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

    foreach ($activities->get() as $activity) {
      $planned = $this->plannedBudgetForActivity($activity);
      if ($planned <= 0) {
        continue;
      }

      $divisionId = (int) ($activity->division_id ?: ($activity->matrix?->division_id ?? 0));
      $rows->push([
        'source_type' => (int) ($activity->is_single_memo ?? 0) === 1 ? 'single_memo' : 'matrix_activity',
        'source_id' => (int) $activity->id,
        'title' => (string) $activity->activity_title,
        'document_number' => (string) ($activity->document_number ?? ''),
        'division_id' => $divisionId,
        'division_name' => $this->divisionName($divisionId),
        'year' => (int) ($activity->matrix?->year ?? 0),
        'quarter' => (string) ($activity->matrix?->quarter ?? ''),
        'planned_budget' => $planned,
        'executed_budget' => 0.0,
        'execution_pct' => 0.0,
        'has_sr_or_arf' => false,
        'fully_executed' => false,
        'sr_count' => 0,
        'arf_count' => 0,
      ]);
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
      $rows->push([
        'source_type' => 'special_memo',
        'source_id' => (int) $memo->id,
        'title' => (string) $memo->activity_title,
        'document_number' => (string) ($memo->document_number ?? ''),
        'division_id' => $divisionId,
        'division_name' => $this->divisionName($divisionId),
        'year' => $period['year'],
        'quarter' => $period['quarter'],
        'planned_budget' => $planned,
        'executed_budget' => 0.0,
        'execution_pct' => 0.0,
        'has_sr_or_arf' => false,
        'fully_executed' => false,
        'sr_count' => 0,
        'arf_count' => 0,
      ]);
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
      $rows->push([
        'source_type' => 'non_travel_memo',
        'source_id' => (int) $memo->id,
        'title' => (string) $memo->activity_title,
        'document_number' => (string) ($memo->document_number ?? ''),
        'division_id' => $divisionId,
        'division_name' => $this->divisionName($divisionId),
        'year' => $period['year'],
        'quarter' => $period['quarter'],
        'planned_budget' => $planned,
        'executed_budget' => 0.0,
        'execution_pct' => 0.0,
        'has_sr_or_arf' => false,
        'fully_executed' => false,
        'sr_count' => 0,
        'arf_count' => 0,
      ]);
    }

    return $rows;
  }

  /**
   * @param  Collection<int, array<string, mixed>>  $initiatives
   */
  private function attachExecutionMetrics(Collection $initiatives): void
  {
    if ($initiatives->isEmpty()) {
      return;
    }

    $activityIds = $initiatives->whereIn('source_type', ['matrix_activity', 'single_memo'])->pluck('source_id')->all();
    $specialIds = $initiatives->where('source_type', 'special_memo')->pluck('source_id')->all();
    $nonTravelIds = $initiatives->where('source_type', 'non_travel_memo')->pluck('source_id')->all();

    $srByActivity = $this->sumServiceRequestsFor('activity', $activityIds);
    $srByActivityId = $this->sumServiceRequestsByActivityId($activityIds);
    $srBySpecial = $this->sumServiceRequestsFor('special_memo', $specialIds);
    $srByNonTravel = $this->sumServiceRequestsFor('non_travel_memo', $nonTravelIds);

    $arfByActivity = $this->sumArfsFor(Activity::class, $activityIds);
    $arfBySpecial = $this->sumArfsFor(SpecialMemo::class, $specialIds);
    $arfByNonTravel = $this->sumArfsFor(NonTravelMemo::class, $nonTravelIds);

    $initiatives->transform(function (array $row) use (
      $srByActivity,
      $srByActivityId,
      $srBySpecial,
      $srByNonTravel,
      $arfByActivity,
      $arfBySpecial,
      $arfByNonTravel
    ) {
      $id = (int) $row['source_id'];
      $executed = 0.0;
      $srCount = 0;
      $arfCount = 0;

      match ($row['source_type']) {
        'matrix_activity', 'single_memo' => (function () use (&$executed, &$srCount, &$arfCount, $id, $srByActivity, $srByActivityId, $arfByActivity) {
          $sr = ($srByActivity[$id] ?? 0) + ($srByActivityId[$id] ?? 0);
          $arf = $arfByActivity[$id] ?? 0;
          $executed = $sr + $arf;
          $srCount = ($sr > 0 ? 1 : 0);
          $arfCount = ($arf > 0 ? 1 : 0);
        })(),
        'special_memo' => (function () use (&$executed, &$srCount, &$arfCount, $id, $srBySpecial, $arfBySpecial) {
          $executed = ($srBySpecial[$id] ?? 0) + ($arfBySpecial[$id] ?? 0);
          $srCount = (($srBySpecial[$id] ?? 0) > 0 ? 1 : 0);
          $arfCount = (($arfBySpecial[$id] ?? 0) > 0 ? 1 : 0);
        })(),
        'non_travel_memo' => (function () use (&$executed, &$srCount, &$arfCount, $id, $srByNonTravel, $arfByNonTravel) {
          $executed = ($srByNonTravel[$id] ?? 0) + ($arfByNonTravel[$id] ?? 0);
          $srCount = (($srByNonTravel[$id] ?? 0) > 0 ? 1 : 0);
          $arfCount = (($arfByNonTravel[$id] ?? 0) > 0 ? 1 : 0);
        })(),
        default => null,
      };

      $planned = (float) $row['planned_budget'];
      $cappedExecuted = $planned > 0 ? min($planned, round($executed, 2)) : 0.0;
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
   * @param  list<int>  $sourceIds
   * @return array<int, float>
   */
  private function sumServiceRequestsFor(string $sourceType, array $sourceIds): array
  {
    if ($sourceIds === []) {
      return [];
    }

    return ServiceRequest::query()
      ->whereIn('overall_status', self::EXECUTED_STATUSES)
      ->where('source_type', $sourceType)
      ->whereIn('source_id', $sourceIds)
      ->selectRaw('source_id, SUM(COALESCE(new_total_budget, estimated_cost, 0)) as total')
      ->groupBy('source_id')
      ->pluck('total', 'source_id')
      ->map(fn ($v) => round((float) $v, 2))
      ->all();
  }

  /**
   * @param  list<int>  $activityIds
   * @return array<int, float>
   */
  private function sumServiceRequestsByActivityId(array $activityIds): array
  {
    if ($activityIds === []) {
      return [];
    }

    return ServiceRequest::query()
      ->whereIn('overall_status', self::EXECUTED_STATUSES)
      ->whereIn('activity_id', $activityIds)
      ->selectRaw('activity_id, SUM(COALESCE(new_total_budget, estimated_cost, 0)) as total')
      ->groupBy('activity_id')
      ->pluck('total', 'activity_id')
      ->map(fn ($v) => round((float) $v, 2))
      ->all();
  }

  /**
   * @param  list<int>  $sourceIds
   * @return array<int, float>
   */
  private function sumArfsFor(string $modelClass, array $sourceIds): array
  {
    if ($sourceIds === []) {
      return [];
    }

    return RequestARF::query()
      ->whereIn('overall_status', self::EXECUTED_STATUSES)
      ->where('model_type', $modelClass)
      ->whereIn('source_id', $sourceIds)
      ->selectRaw('source_id, SUM(COALESCE(requested_amount, total_amount, 0)) as total')
      ->groupBy('source_id')
      ->pluck('total', 'source_id')
      ->map(fn ($v) => round((float) $v, 2))
      ->all();
  }

  private function plannedBudgetForActivity(Activity $activity): float
  {
    $fromRows = (float) ActivityBudget::query()
      ->where('activity_id', $activity->id)
      ->sum('total');

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

        return [
          'division_id' => (int) $divisionId,
          'division_name' => (string) ($group->first()['division_name'] ?? 'Unknown'),
          'initiative_count' => $group->count(),
          'with_sr_or_arf' => $group->where('has_sr_or_arf', true)->count(),
          'fully_executed_count' => $group->where('fully_executed', true)->count(),
          'planned_budget' => $planned,
          'executed_budget' => $executed,
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

    return [
      'initiative_count' => $initiatives->count(),
      'with_sr_or_arf' => $initiatives->where('has_sr_or_arf', true)->count(),
      'fully_executed_count' => $initiatives->where('fully_executed', true)->count(),
      'planned_budget' => $planned,
      'executed_budget' => $executed,
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
