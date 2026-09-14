<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\FundCode;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Support\BudgetBreakdownTotal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Working balance = approved budget − commitments (configurable statuses; see App settings → Budget).
 * Stale drafts older than budget_draft_max_age_months do not commit funds.
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
            return $this->emptySnapshot();
        }

        $excludeKey = md5(json_encode($exclude, JSON_THROW_ON_ERROR));
        $version = $this->version($fundCodeId);
        $settingsToken = $this->commitmentSettings()->cacheToken();
        $cacheKey = "apm:fc:{$fundCodeId}:snap:v{$version}:cfg{$settingsToken}:{$excludeKey}";

        return $this->cacheRemember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($fundCode, $exclude) {
            return $this->buildSnapshot($fundCode, $exclude);
        });
    }

    /**
     * @return array{approved_budget: float, committed_total: float, working_balance: float}
     */
    public function buildSnapshot(FundCode $fundCode, array $exclude = []): array
    {
        $approved = $this->resolveApprovedBudget($fundCode);
        $committed = $this->computeCommittedTotal($fundCode, $exclude);

        return [
            'approved_budget' => round($approved, 2),
            'committed_total' => round($committed, 2),
            'working_balance' => round(max(0, $approved - $committed), 2),
        ];
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

    /**
     * @param  array<string, int|null>  $exclude
     * @return array{
     *   activity_ids: list<int>,
     *   special_memo_ids: list<int>,
     *   non_travel_memo_ids: list<int>,
     *   by_id: array<int, ChangeRequest>
     * }
     */
    public function getActiveChangeRequests(array $exclude = []): array
    {
        return $this->activeChangeRequests($exclude);
    }

    public function cacheVersion(int $fundCodeId): string
    {
        return $this->version($fundCodeId);
    }

    public function bust(int|array $fundCodeIds): void
    {
        try {
            $store = Cache::getStore();
            foreach ((array) $fundCodeIds as $id) {
                $id = (int) $id;
                if ($id <= 0) {
                    continue;
                }
                $versionKey = $this->versionKey($id);
                $store->put($versionKey, (string) microtime(true), 86400 * 7);
            }
        } catch (\Throwable) {
            // Cache store unavailable — balances are computed live on the next read.
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
     * Active change requests that commit budget — at most one (the latest) per parent memo.
     *
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
        $statuses = $this->commitmentSettings()->committedChangeRequestStatuses();
        $query = ChangeRequest::query()
            ->whereIn('overall_status', $statuses)
            ->whereNotIn('overall_status', self::NON_COMMITTING_STATUSES);

        if (! empty($exclude['change_request_id'])) {
            $query->where('id', '!=', (int) $exclude['change_request_id']);
        }

        $this->commitmentSettings()->applyDraftAgeFilter($query, (new ChangeRequest)->getTable(), $statuses);

        $rows = $query->get([
            'id',
            'activity_id',
            'special_memo_id',
            'non_travel_memo_id',
            'parent_memo_id',
            'parent_memo_model',
            'budget_breakdown',
            'updated_at',
        ]);

        $latestByParent = $this->selectLatestChangeRequestsPerParent($rows);

        if (! empty($exclude['change_request_id'])) {
            $excludedCr = ChangeRequest::query()->find((int) $exclude['change_request_id']);
            if ($excludedCr) {
                $this->ensureParentSuppressedForExcludedChangeRequest($excludedCr, $latestByParent);
            }
        }

        $latest = array_values($latestByParent);

        return [
            'activity_ids' => $this->parentIdsFromChangeRequests($latest, 'activity_id'),
            'special_memo_ids' => $this->parentIdsFromChangeRequests($latest, 'special_memo_id'),
            'non_travel_memo_ids' => $this->parentIdsFromChangeRequests($latest, 'non_travel_memo_id'),
            'by_id' => collect($latest)->keyBy('id')->all(),
        ];
    }

    /**
     * @param  iterable<int, ChangeRequest>  $rows
     * @return array<string, ChangeRequest>
     */
    public function selectLatestChangeRequestsPerParent(iterable $rows): array
    {
        $latestByParent = [];

        foreach ($rows as $cr) {
            if (! $cr instanceof ChangeRequest) {
                continue;
            }
            $key = $this->changeRequestParentKey($cr) ?? ('cr:' . (int) $cr->id);
            if (
                ! isset($latestByParent[$key])
                || $this->isNewerChangeRequest($cr, $latestByParent[$key])
            ) {
                $latestByParent[$key] = $cr;
            }
        }

        return $latestByParent;
    }

    /**
     * When editing a CR that was excluded from the query, keep its parent memo out of parent-side commitment.
     *
     * @param  array<string, ChangeRequest>  $latestByParent
     */
    private function ensureParentSuppressedForExcludedChangeRequest(ChangeRequest $excludedCr, array &$latestByParent): void
    {
        $key = $this->changeRequestParentKey($excludedCr);
        if ($key === null) {
            return;
        }

        if (isset($latestByParent[$key])) {
            return;
        }

        $latestByParent[$key] = $excludedCr;
    }

    private function changeRequestParentKey(ChangeRequest $cr): ?string
    {
        if ((int) ($cr->activity_id ?? 0) > 0) {
            return 'activity:' . (int) $cr->activity_id;
        }
        if ((int) ($cr->special_memo_id ?? 0) > 0) {
            return 'special_memo:' . (int) $cr->special_memo_id;
        }
        if ((int) ($cr->non_travel_memo_id ?? 0) > 0) {
            return 'non_travel:' . (int) $cr->non_travel_memo_id;
        }

        $parentId = (int) ($cr->parent_memo_id ?? 0);
        $parentModel = strtolower(str_replace('\\', '', (string) ($cr->parent_memo_model ?? '')));
        if ($parentId <= 0 || $parentModel === '') {
            return null;
        }

        if (str_contains($parentModel, 'activity')) {
            return 'activity:' . $parentId;
        }
        if (str_contains($parentModel, 'specialmemo')) {
            return 'special_memo:' . $parentId;
        }
        if (str_contains($parentModel, 'nontravelmemo')) {
            return 'non_travel:' . $parentId;
        }

        return null;
    }

    private function isNewerChangeRequest(ChangeRequest $candidate, ChangeRequest $incumbent): bool
    {
        $candidateTs = $candidate->updated_at ?? null;
        $incumbentTs = $incumbent->updated_at ?? null;
        if ($candidateTs && $incumbentTs && $candidateTs != $incumbentTs) {
            return $candidateTs->greaterThan($incumbentTs);
        }

        return (int) $candidate->id > (int) $incumbent->id;
    }

    /**
     * @param  list<ChangeRequest>  $changeRequests
     * @return list<int>
     */
    private function parentIdsFromChangeRequests(array $changeRequests, string $column): array
    {
        $ids = [];
        foreach ($changeRequests as $cr) {
            $id = (int) ($cr->{$column} ?? 0);
            if ($id > 0) {
                $ids[] = $id;
                continue;
            }

            $parentId = (int) ($cr->parent_memo_id ?? 0);
            $parentModel = strtolower(str_replace('\\', '', (string) ($cr->parent_memo_model ?? '')));
            if ($parentId <= 0) {
                continue;
            }

            $matches = match ($column) {
                'activity_id' => str_contains($parentModel, 'activity'),
                'special_memo_id' => str_contains($parentModel, 'specialmemo'),
                'non_travel_memo_id' => str_contains($parentModel, 'nontravelmemo'),
                default => false,
            };

            if ($matches) {
                $ids[] = $parentId;
            }
        }

        return array_values(array_unique($ids));
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
        return $this->committedFromActivities($fundCode, $exclude, $activeCrs);
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
    private function committedFromActivities(FundCode $fundCode, array $exclude, array $activeCrs): float
    {
        $fundCodeId = (int) $fundCode->id;
        $statuses = $this->commitmentSettings()->committedActivityStatuses();

        $budgetActivityIds = DB::table('activity_budgets')
            ->where(function ($q) use ($fundCode) {
                $q->where('fund_code', (string) $fundCode->id)
                    ->orWhere('fund_code', (string) $fundCode->code)
                    ->orWhereRaw('UPPER(TRIM(fund_code)) = UPPER(?)', [(string) $fundCode->code]);
            })
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $breakdownActivityIds = BudgetBreakdownTotal::constrainFundCodeId(
            Activity::query(),
            'budget_breakdown',
            $fundCodeId
        )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activityIds = array_values(array_unique(array_merge($budgetActivityIds, $breakdownActivityIds)));
        if ($activityIds === []) {
            return 0.0;
        }

        $query = Activity::query()
            ->whereIn('activities.id', $activityIds)
            ->join('matrices', 'matrices.id', '=', 'activities.matrix_id')
            ->whereIn('activities.overall_status', $statuses)
            ->whereNotIn('activities.overall_status', self::NON_COMMITTING_STATUSES)
            ->where('matrices.overall_status', '!=', 'archived');

        $this->commitmentSettings()->applyDraftAgeFilter($query, 'activities', $statuses);

        if (! empty($exclude['activity_id'])) {
            $query->where('activities.id', '!=', (int) $exclude['activity_id']);
        }

        if ($activeCrs['activity_ids'] !== []) {
            $query->whereNotIn('activities.id', $activeCrs['activity_ids']);
        }

        $activities = $query->get(['activities.id', 'activities.budget_breakdown']);

        $budgetSums = DB::table('activity_budgets')
            ->whereIn('activity_id', $activities->pluck('id')->all())
            ->where(function ($q) use ($fundCode) {
                $q->where('fund_code', (string) $fundCode->id)
                    ->orWhere('fund_code', (string) $fundCode->code)
                    ->orWhereRaw('UPPER(TRIM(fund_code)) = UPPER(?)', [(string) $fundCode->code]);
            })
            ->selectRaw('activity_id, SUM(total) as budget_sum')
            ->groupBy('activity_id')
            ->pluck('budget_sum', 'activity_id');

        $total = 0.0;
        foreach ($activities as $activity) {
            $total += BudgetBreakdownTotal::activityAmountForFundCode(
                $activity->budget_breakdown,
                $fundCodeId,
                (float) ($budgetSums[(int) $activity->id] ?? 0)
            );
        }

        return $total;
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
        $statuses = $this->commitmentSettings()->committedMemoStatuses();
        $query = BudgetBreakdownTotal::constrainFundCodeId(
            NonTravelMemo::query(),
            'budget_breakdown',
            $fundCodeId
        )
            ->whereIn('overall_status', $statuses)
            ->whereNotIn('overall_status', self::NON_COMMITTING_STATUSES);

        $this->commitmentSettings()->applyDraftAgeFilter($query, 'non_travel_memos', $statuses);

        if (! empty($exclude['non_travel_memo_id'])) {
            $query->where('id', '!=', (int) $exclude['non_travel_memo_id']);
        }

        if ($activeCrs['non_travel_memo_ids'] !== []) {
            $query->whereNotIn('id', $activeCrs['non_travel_memo_ids']);
        }

        return $this->sumBreakdownFromModels(
            $query->get(['id', 'budget_breakdown']),
            $fundCodeId,
            BudgetBreakdownTotal::STYLE_NON_TRAVEL
        );
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
        $statuses = $this->commitmentSettings()->committedMemoStatuses();
        $query = BudgetBreakdownTotal::constrainFundCodeId(
            SpecialMemo::query(),
            'budget_breakdown',
            $fundCodeId
        )
            ->whereIn('overall_status', $statuses)
            ->whereNotIn('overall_status', self::NON_COMMITTING_STATUSES);

        $this->commitmentSettings()->applyDraftAgeFilter($query, 'special_memos', $statuses);

        if (! empty($exclude['special_memo_id'])) {
            $query->where('id', '!=', (int) $exclude['special_memo_id']);
        }

        if ($activeCrs['special_memo_ids'] !== []) {
            $query->whereNotIn('id', $activeCrs['special_memo_ids']);
        }

        return $this->sumBreakdownFromModels(
            $query->get(['id', 'budget_breakdown']),
            $fundCodeId,
            BudgetBreakdownTotal::STYLE_TRAVEL_LENIENT
        );
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
            $style = $cr->non_travel_memo_id !== null
                ? BudgetBreakdownTotal::STYLE_NON_TRAVEL
                : BudgetBreakdownTotal::STYLE_CHANGE_REQUEST;
            $total += $this->sumBreakdownForFundCode($breakdown, $fundCodeId, $style);
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
    private function sumBreakdownFromModels(iterable $models, int $fundCodeId, string $style): float
    {
        $total = 0.0;
        foreach ($models as $model) {
            $breakdown = $this->decodeBreakdown($model->budget_breakdown);
            $total += $this->sumBreakdownForFundCode($breakdown, $fundCodeId, $style);
        }

        return $total;
    }

    /**
     * @param  array<mixed, mixed>  $breakdown
     */
    public function sumBreakdownForFundCode(
        array $breakdown,
        int $fundCodeId,
        string $style = BudgetBreakdownTotal::STYLE_TRAVEL_STRICT
    ): float {
        return BudgetBreakdownTotal::forFundCode($breakdown, $fundCodeId, $style);
    }

    /**
     * Budget overage is advisory only (frontend warning). Finance validates on review.
     *
     * @param  array<int, float>  $totalsPerFundCodeId
     * @param  array<string, int|null>  $exclude
     * @return list<string>
     */
    public function validateTotals(array $totalsPerFundCodeId, int $fundTypeId, array $exclude = []): array
    {
        return [];
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
            $totals[$codeId] = $this->sumBreakdownForFundCode(
                [$codeId => $items],
                $codeId,
                $withDays ? BudgetBreakdownTotal::STYLE_TRAVEL_STRICT : BudgetBreakdownTotal::STYLE_NON_TRAVEL
            );
        }

        return $totals;
    }

    /**
     * Change-request budget increases are advisory only (frontend warning).
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
        return [];
    }

    /**
     * @param  array<mixed, mixed>  $breakdown
     * @return array<int, float>
     */
    public function breakdownTotalsPerCode(array $breakdown, bool $useQuantity = false, bool $useDays = false): array
    {
        $style = $useQuantity
            ? BudgetBreakdownTotal::STYLE_NON_TRAVEL
            : ($useDays ? BudgetBreakdownTotal::STYLE_TRAVEL_STRICT : BudgetBreakdownTotal::STYLE_CHANGE_REQUEST);

        return BudgetBreakdownTotal::fundCodeTotals($breakdown, $style);
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
        return (string) $this->cacheGet($this->versionKey($fundCodeId), '1');
    }

    /**
     * @return array{approved_budget: float, committed_total: float, working_balance: float}
     */
    private function emptySnapshot(): array
    {
        return [
            'approved_budget' => 0.0,
            'committed_total' => 0.0,
            'working_balance' => 0.0,
        ];
    }

    private function cacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function cacheRemember(string $key, int $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }

    private function commitmentSettings(): BudgetCommitmentSettings
    {
        return app(BudgetCommitmentSettings::class);
    }
}
