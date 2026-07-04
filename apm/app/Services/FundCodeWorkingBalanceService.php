<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\FundCode;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Working balance = approved budget − commitments (draft, pending, submitted, approved).
 * Returned, archived, cancelled, and other non-committing statuses do not reduce working balance.
 * Cached in Redis/default store; busted on budget writes and archive/unarchive.
 */
class FundCodeWorkingBalanceService
{
    /** @var list<string> */
    public const COMMITTED_ACTIVITY_STATUSES = ['draft', 'pending', 'submitted', 'approved'];

    /** @var list<string> */
    public const COMMITTED_MEMO_STATUSES = ['draft', 'pending', 'approved'];

    /** @var list<string> */
    public const ACTIVE_CHANGE_REQUEST_STATUSES = ['draft', 'pending', 'submitted'];

    /**
     * Never counted toward working-balance commitments (explicit exclusion for clarity).
     *
     * @var list<string>
     */
    public const NON_COMMITTING_STATUSES = [
        'returned',
        'archived',
        'cancelled',
        'rejected',
        'onhold',
    ];

    private const CACHE_TTL_SECONDS = 45;

    /**
     * @param  array{
     *   activity_id?: int|null,
     *   non_travel_memo_id?: int|null,
     *   special_memo_id?: int|null,
     *   change_request_id?: int|null,
     * }  $exclude
     * @return array{approved_budget: float, committed_total: float, working_balance: float}
     */
    public function snapshot(int $fundCodeId, array $exclude = []): array
    {
        $fundCode = FundCode::query()->find($fundCodeId);
        if (! $fundCode) {
            return [
                'approved_budget' => 0.0,
                'committed_total' => 0.0,
                'working_balance' => 0.0,
            ];
        }

        $excludeKey = md5(json_encode($exclude, JSON_THROW_ON_ERROR));
        $version = $this->version($fundCodeId);
        $cacheKey = "apm:fc:{$fundCodeId}:snap:v{$version}:{$excludeKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($fundCode, $exclude) {
            $approved = $this->resolveApprovedBudget($fundCode);
            $committed = $this->computeCommittedTotal($fundCode, $exclude);

            return [
                'approved_budget' => round($approved, 2),
                'committed_total' => round($committed, 2),
                'working_balance' => round(max(0, $approved - $committed), 2),
            ];
        });
    }

    public function getWorkingBalance(int $fundCodeId, array $exclude = []): float
    {
        return $this->snapshot($fundCodeId, $exclude)['working_balance'];
    }

    /**
     * @param  list<int>  $fundCodeIds
     * @param  array<string, int|null>  $exclude
     * @return array<int, array{approved_budget: float, committed_total: float, working_balance: float}>
     */
    public function snapshotsFor(array $fundCodeIds, array $exclude = []): array
    {
        $out = [];
        foreach (array_unique(array_map('intval', $fundCodeIds)) as $id) {
            if ($id > 0) {
                $out[$id] = $this->snapshot($id, $exclude);
            }
        }

        return $out;
    }

    public function bust(int|array $fundCodeIds): void
    {
        $store = Cache::getStore();
        foreach ((array) $fundCodeIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $versionKey = $this->versionKey($id);
            $store->put($versionKey, (string) microtime(true), 86400 * 7);
        }
    }

    /**
     * Refresh working-balance cache after archive/unarchive (status no longer commits funds).
     */
    public function bustForArchiveStatusChange(object $model): void
    {
        if ($model instanceof Activity) {
            $this->bustForActivityId((int) $model->id);

            return;
        }

        if ($model instanceof SpecialMemo || $model instanceof NonTravelMemo) {
            $this->bustForMemoBudgetFields($model->budget_breakdown ?? null, $model->budget_id ?? null);

            return;
        }

        if ($model instanceof ChangeRequest) {
            $this->bustForMemoBudgetFields($model->budget_breakdown ?? null, null);

            return;
        }

        if ($model instanceof Matrix) {
            $this->bustForMatrixId((int) $model->id);
        }
    }

    public function bustForActivityId(int $activityId): void
    {
        if ($activityId <= 0) {
            return;
        }

        $refs = DB::table('activity_budgets')
            ->where('activity_id', $activityId)
            ->pluck('fund_code');

        $this->bust($this->resolveFundCodeIdsFromStoredReferences($refs));
    }

    public function bustForMatrixId(int $matrixId): void
    {
        if ($matrixId <= 0) {
            return;
        }

        $activityIds = Activity::query()
            ->where('matrix_id', $matrixId)
            ->pluck('id');

        foreach ($activityIds as $activityId) {
            $this->bustForActivityId((int) $activityId);
        }
    }

    /**
     * @param  mixed  $budgetBreakdown
     * @param  mixed  $budgetIds
     */
    public function bustForMemoBudgetFields(mixed $budgetBreakdown, mixed $budgetIds = null): void
    {
        $ids = [];

        if (is_string($budgetIds) && $budgetIds !== '') {
            $decoded = json_decode($budgetIds, true);
            $budgetIds = is_array($decoded) ? $decoded : [];
        }
        if (is_array($budgetIds)) {
            foreach ($budgetIds as $id) {
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
        }

        $breakdown = $this->decodeBreakdown($budgetBreakdown);
        foreach (array_keys($breakdown) as $key) {
            if (is_numeric($key)) {
                $ids[] = (int) $key;
            }
        }

        $this->bust(array_values(array_unique($ids)));
    }

    /**
     * @param  iterable<int|string, mixed>  $refs
     * @return list<int>
     */
    private function resolveFundCodeIdsFromStoredReferences(iterable $refs): array
    {
        $ids = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '') {
                continue;
            }
            if (ctype_digit($ref)) {
                $ids[] = (int) $ref;

                continue;
            }
            $id = FundCode::query()->where('code', $ref)->value('id');
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function resolveApprovedBudget(FundCode $fundCode): float
    {
        $approved = $this->parseMoney($fundCode->approved_budget);
        if ($approved > 0) {
            return $approved;
        }

        $uploaded = $this->parseMoney($fundCode->uploaded_budget);
        if ($uploaded > 0) {
            return $uploaded;
        }

        return $this->parseMoney($fundCode->budget_balance);
    }

    /**
     * @param  array<string, int|null>  $exclude
     */
    public function computeCommittedTotal(FundCode $fundCode, array $exclude = []): float
    {
        $fundCodeId = (int) $fundCode->id;
        $activeCrs = $this->activeChangeRequests($exclude);

        $total = 0.0;
        $total += $this->committedFromActivityBudgets($fundCode, $exclude, $activeCrs);
        $total += $this->committedFromNonTravelMemos($fundCodeId, $exclude, $activeCrs);
        $total += $this->committedFromSpecialMemos($fundCodeId, $exclude, $activeCrs);
        $total += $this->committedFromChangeRequests($fundCodeId, $exclude, $activeCrs);

        return $total;
    }

    /**
     * @param  array<string, int|null>  $exclude
     * @return array{
     *   activity_ids: list<int>,
     *   special_memo_ids: list<int>,
     *   non_travel_memo_ids: list<int>,
     *   by_id: array<int, ChangeRequest>
     * }
     */
    private function activeChangeRequests(array $exclude): array
    {
        $query = ChangeRequest::query()
            ->whereIn('overall_status', self::ACTIVE_CHANGE_REQUEST_STATUSES)
            ->whereNotIn('overall_status', self::NON_COMMITTING_STATUSES);

        if (! empty($exclude['change_request_id'])) {
            $query->where('id', '!=', (int) $exclude['change_request_id']);
        }

        $rows = $query->get(['id', 'activity_id', 'special_memo_id', 'non_travel_memo_id', 'budget_breakdown']);

        return [
            'activity_ids' => $rows->pluck('activity_id')->filter()->map(fn ($v) => (int) $v)->values()->all(),
            'special_memo_ids' => $rows->pluck('special_memo_id')->filter()->map(fn ($v) => (int) $v)->values()->all(),
            'non_travel_memo_ids' => $rows->pluck('non_travel_memo_id')->filter()->map(fn ($v) => (int) $v)->values()->all(),
            'by_id' => $rows->keyBy('id')->all(),
        ];
    }

    /**
     * @param  array<string, int|null>  $exclude
     * @param  array{
     *   activity_ids: list<int>,
     *   special_memo_ids: list<int>,
     *   non_travel_memo_ids: list<int>,
     *   by_id: array<int, ChangeRequest>
     * }  $activeCrs
     */
    private function committedFromActivityBudgets(FundCode $fundCode, array $exclude, array $activeCrs): float
    {
        $query = DB::table('activity_budgets')
            ->join('activities', 'activities.id', '=', 'activity_budgets.activity_id')
            ->join('matrices', 'matrices.id', '=', 'activities.matrix_id')
            ->whereIn('activities.overall_status', self::COMMITTED_ACTIVITY_STATUSES)
            ->whereNotIn('activities.overall_status', self::NON_COMMITTING_STATUSES)
            ->where('matrices.overall_status', '!=', 'archived')
            ->where(function ($q) use ($fundCode) {
                $q->where('activity_budgets.fund_code', (string) $fundCode->id)
                    ->orWhere('activity_budgets.fund_code', (string) $fundCode->code);
            });

        if (! empty($exclude['activity_id'])) {
            $query->where('activities.id', '!=', (int) $exclude['activity_id']);
        }

        if ($activeCrs['activity_ids'] !== []) {
            $query->whereNotIn('activities.id', $activeCrs['activity_ids']);
        }

        return (float) $query->sum('activity_budgets.total');
    }

    /**
     * @param  array<string, int|null>  $exclude
     * @param  array{
     *   activity_ids: list<int>,
     *   special_memo_ids: list<int>,
     *   non_travel_memo_ids: list<int>,
     *   by_id: array<int, ChangeRequest>
     * }  $activeCrs
     */
    private function committedFromNonTravelMemos(int $fundCodeId, array $exclude, array $activeCrs): float
    {
        $query = NonTravelMemo::query()
            ->whereIn('overall_status', self::COMMITTED_MEMO_STATUSES)
            ->whereNotIn('overall_status', self::NON_COMMITTING_STATUSES);

        if (! empty($exclude['non_travel_memo_id'])) {
            $query->where('id', '!=', (int) $exclude['non_travel_memo_id']);
        }

        if ($activeCrs['non_travel_memo_ids'] !== []) {
            $query->whereNotIn('id', $activeCrs['non_travel_memo_ids']);
        }

        return $this->sumBreakdownFromModels($query->get(['id', 'budget_breakdown']), $fundCodeId, useQuantity: true);
    }

    /**
     * @param  array<string, int|null>  $exclude
     * @param  array{
     *   activity_ids: list<int>,
     *   special_memo_ids: list<int>,
     *   non_travel_memo_ids: list<int>,
     *   by_id: array<int, ChangeRequest>
     * }  $activeCrs
     */
    private function committedFromSpecialMemos(int $fundCodeId, array $exclude, array $activeCrs): float
    {
        $query = SpecialMemo::query()
            ->whereIn('overall_status', self::COMMITTED_MEMO_STATUSES)
            ->whereNotIn('overall_status', self::NON_COMMITTING_STATUSES);

        if (! empty($exclude['special_memo_id'])) {
            $query->where('id', '!=', (int) $exclude['special_memo_id']);
        }

        if ($activeCrs['special_memo_ids'] !== []) {
            $query->whereNotIn('id', $activeCrs['special_memo_ids']);
        }

        return $this->sumBreakdownFromModels($query->get(['id', 'budget_breakdown']), $fundCodeId, useQuantity: false);
    }

    /**
     * @param  array{
     *   activity_ids: list<int>,
     *   special_memo_ids: list<int>,
     *   non_travel_memo_ids: list<int>,
     *   by_id: array<int, ChangeRequest>
     * }  $activeCrs
     * @param  array<string, int|null>  $exclude
     */
    private function committedFromChangeRequests(int $fundCodeId, array $exclude, array $activeCrs): float
    {
        $total = 0.0;
        foreach ($activeCrs['by_id'] as $cr) {
            if (! empty($exclude['change_request_id']) && (int) $cr->id === (int) $exclude['change_request_id']) {
                continue;
            }
            $breakdown = $this->decodeBreakdown($cr->budget_breakdown);
            $total += $this->sumBreakdownForFundCode($breakdown, $fundCodeId, $this->changeRequestUsesQuantity($cr));
        }

        return $total;
    }

    private function changeRequestUsesQuantity(ChangeRequest $cr): bool
    {
        return $cr->non_travel_memo_id !== null;
    }

    /**
     * @param  iterable<int, object{id: mixed, budget_breakdown: mixed}>  $models
     */
    private function sumBreakdownFromModels(iterable $models, int $fundCodeId, bool $useQuantity): float
    {
        $total = 0.0;
        foreach ($models as $model) {
            $breakdown = $this->decodeBreakdown($model->budget_breakdown);
            $total += $this->sumBreakdownForFundCode($breakdown, $fundCodeId, $useQuantity);
        }

        return $total;
    }

    /**
     * @param  array<mixed, mixed>  $breakdown
     */
    public function sumBreakdownForFundCode(array $breakdown, int $fundCodeId, bool $useQuantity = false, bool $useDays = false): float
    {
        $sum = 0.0;
        foreach ($breakdown as $codeKey => $items) {
            if (! is_numeric($codeKey) || (int) $codeKey !== $fundCodeId || ! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (! is_array($item) && ! is_object($item)) {
                    continue;
                }
                $row = (array) $item;
                $unitCost = (float) ($row['unit_cost'] ?? 0);
                if ($useDays) {
                    $units = (float) ($row['units'] ?? $row['quantity'] ?? $row['qty'] ?? 1);
                    $days = (float) ($row['days'] ?? 1);
                    $sum += $unitCost * $units * $days;
                } elseif ($useQuantity) {
                    $qty = (float) ($row['quantity'] ?? $row['qty'] ?? $row['units'] ?? 1);
                    $sum += $qty * $unitCost;
                } else {
                    $units = (float) ($row['units'] ?? $row['quantity'] ?? $row['qty'] ?? 1);
                    $sum += $units * $unitCost;
                }
            }
        }

        return round($sum, 2);
    }

    /**
     * @param  array<int, float>  $totalsPerFundCodeId
     * @param  array<string, int|null>  $exclude
     * @return list<string>
     */
    public function validateTotals(array $totalsPerFundCodeId, int $fundTypeId, array $exclude = []): array
    {
        if ($fundTypeId === 3) {
            return [];
        }

        $errors = [];
        foreach ($totalsPerFundCodeId as $fundCodeId => $requested) {
            $fundCodeId = (int) $fundCodeId;
            $requested = round((float) $requested, 2);
            if ($requested <= 0) {
                continue;
            }
            $available = $this->getWorkingBalance($fundCodeId, $exclude);
            if ($requested > $available + 0.009) {
                $code = FundCode::query()->whereKey($fundCodeId)->value('code') ?? (string) $fundCodeId;
                $errors[] = sprintf(
                    'Budget exceeded for fund code %s. Available: $%s',
                    $code,
                    number_format($available, 2)
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<mixed, mixed>  $budgetItems
     * @return array<int, float>
     */
    public function activityBudgetTotalsPerCode(array $budgetItems, bool $withDays = true): array
    {
        $totals = [];
        foreach ($budgetItems as $codeKey => $items) {
            if (! is_numeric($codeKey) || ! is_array($items)) {
                continue;
            }
            $codeId = (int) $codeKey;
            $totals[$codeId] = $this->sumBreakdownForFundCode([$codeId => $items], $codeId, useQuantity: false, useDays: $withDays);
        }

        return $totals;
    }

    /**
     * Change requests: only net increases over the parent memo must fit in working balance.
     *
     * @param  array<mixed, mixed>  $newBreakdown
     * @param  array<mixed, mixed>  $parentBreakdown
     * @param  array<string, int|null>  $exclude
     * @return list<string>
     */
    public function validateChangeRequestIncreases(
        array $newBreakdown,
        array $parentBreakdown,
        int $fundTypeId,
        array $exclude = [],
        bool $useQuantity = false,
        bool $useDays = true
    ): array {
        if ($fundTypeId === 3) {
            return [];
        }

        $newTotals = $this->breakdownTotalsPerCode($newBreakdown, $useQuantity, $useDays);
        $errors = [];

        foreach ($newTotals as $fundCodeId => $newTotal) {
            $parentTotal = $this->sumBreakdownForFundCode($parentBreakdown, (int) $fundCodeId, $useQuantity, $useDays);
            $delta = round(max(0, $newTotal - $parentTotal), 2);
            if ($delta <= 0) {
                continue;
            }
            $available = $this->getWorkingBalance((int) $fundCodeId, $exclude);
            if ($delta > $available + 0.009) {
                $code = FundCode::query()->whereKey($fundCodeId)->value('code') ?? (string) $fundCodeId;
                $errors[] = sprintf(
                    'Budget increase for fund code %s exceeds available balance. Additional needed: $%s · Available: $%s',
                    $code,
                    number_format($delta, 2),
                    number_format($available, 2)
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<mixed, mixed>  $breakdown
     * @return array<int, float>
     */
    public function breakdownTotalsPerCode(array $breakdown, bool $useQuantity = false, bool $useDays = false): array
    {
        $totals = [];
        foreach ($breakdown as $codeKey => $items) {
            if (! is_numeric($codeKey)) {
                continue;
            }
            $codeId = (int) $codeKey;
            $totals[$codeId] = $this->sumBreakdownForFundCode($breakdown, $codeId, $useQuantity, $useDays);
        }

        return $totals;
    }

    private function decodeBreakdown(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^\d.-]/', '', (string) $value);

        return $clean === '' || $clean === '-' ? 0.0 : (float) $clean;
    }

    private function versionKey(int $fundCodeId): string
    {
        return "apm:fc:{$fundCodeId}:ver";
    }

    private function version(int $fundCodeId): string
    {
        return (string) Cache::get($this->versionKey($fundCodeId), '1');
    }
}
