<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\FundCode;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Support\BudgetBreakdownTotal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds a fund-code budget ledger from actual memos (not legacy fund_code_transactions).
 * Cached via Redis/default store with live fallback when cache is unavailable.
 */
class FundCodeBudgetLedgerService
{
    private const CACHE_TTL_SECONDS = 45;

    public function __construct(
        private FundCodeWorkingBalanceService $balanceService,
        private BudgetCommitmentSettings $settings,
    ) {}

    /**
     * @return array{
     *   fund_code: array{id: int, code: string, name: string|null},
     *   snapshot: array{approved_budget: float, committed_total: float, working_balance: float},
     *   settings: array{draft_max_age_months: int, activity_statuses: list<string>, memo_statuses: list<string>, change_request_statuses: list<string>},
     *   committed: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   totals: array{committed_count: int, skipped_count: int, committed_sum: float}
     * }
     */
    public function ledger(int $fundCodeId): array
    {
        $fundCode = FundCode::query()->find($fundCodeId);
        if (! $fundCode) {
            return $this->emptyLedger($fundCodeId);
        }

        $version = $this->balanceService->cacheVersion($fundCodeId);
        $settingsToken = $this->settings->cacheToken();
        $cacheKey = "apm:fc:{$fundCodeId}:ledger:v{$version}:cfg{$settingsToken}";

        return $this->cacheRemember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($fundCode) {
            return $this->buildLedger($fundCode);
        });
    }

    /**
     * @return array{
     *   fund_code: array{id: int, code: string, name: string|null},
     *   snapshot: array{approved_budget: float, committed_total: float, working_balance: float},
     *   settings: array{draft_max_age_months: int, activity_statuses: list<string>, memo_statuses: list<string>, change_request_statuses: list<string>},
     *   committed: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   totals: array{committed_count: int, skipped_count: int, committed_sum: float}
     * }
     */
    public function buildLedger(FundCode $fundCode): array
    {
        $fundCodeId = (int) $fundCode->id;
        $activeCrs = $this->balanceService->getActiveChangeRequests([]);
        $lines = [];

        $lines = array_merge($lines, $this->linesFromActivities($fundCode, $activeCrs));
        $lines = array_merge($lines, $this->linesFromSpecialMemos($fundCode, $activeCrs));
        $lines = array_merge($lines, $this->linesFromNonTravelMemos($fundCode, $activeCrs));
        $lines = array_merge($lines, $this->linesFromChangeRequests($fundCode, $activeCrs));

        $lines = $this->dedupeLines($lines);

        $committed = [];
        $skipped = [];
        foreach ($lines as $line) {
            if ($line['committed']) {
                $committed[] = $line;
            } else {
                $skipped[] = $line;
            }
        }

        usort($committed, fn (array $a, array $b): int => strcmp((string) $b['updated_at'], (string) $a['updated_at']));
        usort($skipped, fn (array $a, array $b): int => strcmp((string) $b['updated_at'], (string) $a['updated_at']));

        $snapshot = $this->balanceService->buildSnapshot($fundCode, []);
        $committedSum = round(array_sum(array_column($committed, 'amount')), 2);

        return [
            'fund_code' => [
                'id' => $fundCodeId,
                'code' => (string) $fundCode->code,
                'name' => $fundCode->name,
            ],
            'snapshot' => $snapshot,
            'settings' => [
                'draft_max_age_months' => $this->settings->draftMaxAgeMonths(),
                'activity_statuses' => $this->settings->committedActivityStatuses(),
                'memo_statuses' => $this->settings->committedMemoStatuses(),
                'change_request_statuses' => $this->settings->committedChangeRequestStatuses(),
            ],
            'committed' => $committed,
            'skipped' => $skipped,
            'totals' => [
                'committed_count' => count($committed),
                'skipped_count' => count($skipped),
                'committed_sum' => $committedSum,
            ],
        ];
    }

    /**
     * @param  array{
     *   activity_ids: list<int>,
     *   special_memo_ids: list<int>,
     *   non_travel_memo_ids: list<int>,
     *   by_id: array<int, ChangeRequest>
     * }  $activeCrs
     * @return list<array<string, mixed>>
     */
    private function linesFromActivities(FundCode $fundCode, array $activeCrs): array
    {
        $fundCodeId = (int) $fundCode->id;

        $budgetActivityIds = DB::table('activity_budgets')
            ->where(function ($q) use ($fundCode) {
                $q->where('fund_code', (string) $fundCode->id)
                    ->orWhere('fund_code', (string) $fundCode->code)
                    ->orWhereRaw('UPPER(TRIM(fund_code)) = UPPER(?)', [(string) $fundCode->code]);
            })
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $breakdownActivityIds = Activity::query()
            ->whereNotNull('budget_breakdown')
            ->where('budget_breakdown', '!=', '')
            ->where('budget_breakdown', '!=', '[]')
            ->where('budget_breakdown', '!=', '{}')
            ->get(['id', 'budget_breakdown'])
            ->filter(fn ($activity) => BudgetBreakdownTotal::hasFundCodeEntries($activity->budget_breakdown, $fundCodeId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activityIds = array_values(array_unique(array_merge($budgetActivityIds, $breakdownActivityIds)));
        if ($activityIds === []) {
            return [];
        }

        $budgetSums = DB::table('activity_budgets')
            ->whereIn('activity_id', $activityIds)
            ->where(function ($q) use ($fundCode) {
                $q->where('fund_code', (string) $fundCode->id)
                    ->orWhere('fund_code', (string) $fundCode->code)
                    ->orWhereRaw('UPPER(TRIM(fund_code)) = UPPER(?)', [(string) $fundCode->code]);
            })
            ->selectRaw('activity_id, SUM(total) as budget_sum')
            ->groupBy('activity_id')
            ->pluck('budget_sum', 'activity_id');

        $rows = DB::table('activities')
            ->leftJoin('matrices', 'matrices.id', '=', 'activities.matrix_id')
            ->whereIn('activities.id', $activityIds)
            ->get([
                'activities.id as activity_id',
                'activities.activity_title',
                'activities.document_number',
                'activities.overall_status',
                'activities.updated_at',
                'activities.matrix_id',
                'activities.is_single_memo',
                'activities.budget_breakdown',
                'matrices.overall_status as matrix_status',
            ]);

        $lines = [];
        foreach ($rows as $row) {
            $amount = BudgetBreakdownTotal::activityAmountForFundCode(
                $row->budget_breakdown,
                $fundCodeId,
                (float) ($budgetSums[(int) $row->activity_id] ?? 0)
            );
            if ($amount <= 0) {
                continue;
            }

            $type = (int) ($row->is_single_memo ?? 0) === 1 ? 'single_memo' : 'activity';
            [$committed, $skipCode, $skipReason] = $this->classifyActivity(
                (string) $row->overall_status,
                $row->updated_at,
                (string) ($row->matrix_status ?? ''),
                (int) $row->activity_id,
                $activeCrs,
                'activity'
            );

            $breakdown = $this->decodeBreakdown($row->budget_breakdown);

            $lines[] = $this->makeLine(
                type: $type,
                id: (int) $row->activity_id,
                title: (string) $row->activity_title,
                documentNumber: $row->document_number,
                status: (string) $row->overall_status,
                amount: $amount,
                committed: $committed,
                skipCode: $skipCode,
                skipReason: $skipReason,
                updatedAt: $row->updated_at,
                matrixId: (int) $row->matrix_id,
                memoGrandTotal: BudgetBreakdownTotal::memoGrandTotal($breakdown, BudgetBreakdownTotal::STYLE_TRAVEL_STRICT),
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $activeCrs
     * @return list<array<string, mixed>>
     */
    private function linesFromSpecialMemos(FundCode $fundCode, array $activeCrs): array
    {
        return $this->linesFromBreakdownMemos(
            SpecialMemo::query()
                ->whereNotNull('budget_breakdown')
                ->where('budget_breakdown', '!=', '')
                ->where('budget_breakdown', '!=', '[]')
                ->where('budget_breakdown', '!=', '{}'),
            $fundCode,
            $activeCrs,
            'special_memo',
            BudgetBreakdownTotal::STYLE_TRAVEL_LENIENT
        );
    }

    /**
     * @param  array<string, mixed>  $activeCrs
     * @return list<array<string, mixed>>
     */
    private function linesFromNonTravelMemos(FundCode $fundCode, array $activeCrs): array
    {
        return $this->linesFromBreakdownMemos(
            NonTravelMemo::query()
                ->whereNotNull('budget_breakdown')
                ->where('budget_breakdown', '!=', '')
                ->where('budget_breakdown', '!=', '[]')
                ->where('budget_breakdown', '!=', '{}'),
            $fundCode,
            $activeCrs,
            'non_travel',
            BudgetBreakdownTotal::STYLE_NON_TRAVEL
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<SpecialMemo|NonTravelMemo>  $query
     * @param  array<string, mixed>  $activeCrs
     * @return list<array<string, mixed>>
     */
    private function linesFromBreakdownMemos($query, FundCode $fundCode, array $activeCrs, string $type, string $style): array
    {
        $fundCodeId = (int) $fundCode->id;
        $models = $query->get([
            'id',
            'activity_title',
            'document_number',
            'overall_status',
            'updated_at',
            'budget_breakdown',
        ]);

        $lines = [];
        foreach ($models as $model) {
            $breakdown = $this->decodeBreakdown($model->budget_breakdown);
            $amount = BudgetBreakdownTotal::forFundCode($breakdown, $fundCodeId, $style);
            if ($amount <= 0) {
                continue;
            }

            [$committed, $skipCode, $skipReason] = $this->classifyMemo(
                (string) $model->overall_status,
                $model->updated_at,
                (int) $model->id,
                $type,
                $activeCrs
            );

            $lines[] = $this->makeLine(
                type: $type,
                id: (int) $model->id,
                title: (string) $model->activity_title,
                documentNumber: $model->document_number,
                status: (string) $model->overall_status,
                amount: $amount,
                committed: $committed,
                skipCode: $skipCode,
                skipReason: $skipReason,
                updatedAt: $model->updated_at,
                memoGrandTotal: BudgetBreakdownTotal::memoGrandTotal($breakdown, $style),
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $activeCrs
     * @return list<array<string, mixed>>
     */
    private function linesFromChangeRequests(FundCode $fundCode, array $activeCrs): array
    {
        $fundCodeId = (int) $fundCode->id;
        $latestIds = array_map('intval', array_keys($activeCrs['by_id'] ?? []));

        $models = ChangeRequest::query()
            ->whereNotNull('budget_breakdown')
            ->where('budget_breakdown', '!=', '')
            ->where('budget_breakdown', '!=', '[]')
            ->where('budget_breakdown', '!=', '{}')
            ->get([
            'id',
            'activity_title',
            'document_number',
            'overall_status',
            'updated_at',
            'budget_breakdown',
            'non_travel_memo_id',
            'activity_id',
            'special_memo_id',
        ]);

        $lines = [];
        foreach ($models as $cr) {
            $breakdown = $this->decodeBreakdown($cr->budget_breakdown);
            $style = $cr->non_travel_memo_id !== null
                ? BudgetBreakdownTotal::STYLE_NON_TRAVEL
                : BudgetBreakdownTotal::STYLE_CHANGE_REQUEST;
            $amount = BudgetBreakdownTotal::forFundCode($breakdown, $fundCodeId, $style);
            if ($amount <= 0) {
                continue;
            }

            [$committed, $skipCode, $skipReason] = $this->classifyChangeRequest(
                $cr,
                $activeCrs,
                $latestIds
            );

            $lines[] = $this->makeLine(
                type: 'change_request',
                id: (int) $cr->id,
                title: (string) ($cr->activity_title ?: 'Change Request #' . $cr->id),
                documentNumber: $cr->document_number,
                status: (string) $cr->overall_status,
                amount: $amount,
                committed: $committed,
                skipCode: $skipCode,
                skipReason: $skipReason,
                updatedAt: $cr->updated_at,
                memoGrandTotal: BudgetBreakdownTotal::memoGrandTotal($breakdown, $style),
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $activeCrs
     * @return array{0: bool, 1: string|null, 2: string|null}
     */
    private function classifyActivity(
        string $status,
        mixed $updatedAt,
        string $matrixStatus,
        int $activityId,
        array $activeCrs,
        string $kind
    ): array {
        $status = strtolower(trim($status));
        $activityStatuses = $this->settings->committedActivityStatuses();

        if ($this->isNonCommitting($status, $activityStatuses)) {
            return [false, 'non_committing_status', $this->skipReasonForStatus($status, $activityStatuses)];
        }

        if ($this->isStaleDraft($status, $updatedAt, $activityStatuses)) {
            return [false, 'stale_draft', $this->staleDraftReason($updatedAt)];
        }

        if (strtolower($matrixStatus) === 'archived') {
            return [false, 'archived_matrix', 'Parent matrix is archived'];
        }

        if (in_array($activityId, $activeCrs['activity_ids'] ?? [], true)) {
            $crId = $this->supersedingChangeRequestId($activeCrs, 'activity', $activityId);

            return [false, 'superseded_by_change_request', 'Superseded by change request' . ($crId ? " #{$crId}" : '')];
        }

        return [true, null, null];
    }

    /**
     * @param  array<string, mixed>  $activeCrs
     * @return array{0: bool, 1: string|null, 2: string|null}
     */
    private function classifyMemo(
        string $status,
        mixed $updatedAt,
        int $memoId,
        string $type,
        array $activeCrs
    ): array {
        $status = strtolower(trim($status));
        $memoStatuses = $this->settings->committedMemoStatuses();

        if ($this->isNonCommitting($status, $memoStatuses)) {
            return [false, 'non_committing_status', $this->skipReasonForStatus($status, $memoStatuses)];
        }

        if ($this->isStaleDraft($status, $updatedAt, $memoStatuses)) {
            return [false, 'stale_draft', $this->staleDraftReason($updatedAt)];
        }

        $idColumn = match ($type) {
            'special_memo' => 'special_memo_ids',
            'non_travel' => 'non_travel_memo_ids',
            default => null,
        };

        if ($idColumn && in_array($memoId, $activeCrs[$idColumn] ?? [], true)) {
            $crId = $this->supersedingChangeRequestId($activeCrs, $type, $memoId);

            return [false, 'superseded_by_change_request', 'Superseded by change request' . ($crId ? " #{$crId}" : '')];
        }

        return [true, null, null];
    }

    /**
     * @param  array<string, mixed>  $activeCrs
     * @param  list<int>  $latestIds
     * @return array{0: bool, 1: string|null, 2: string|null}
     */
    private function classifyChangeRequest(ChangeRequest $cr, array $activeCrs, array $latestIds): array
    {
        $status = strtolower(trim((string) $cr->overall_status));
        $crStatuses = $this->settings->committedChangeRequestStatuses();

        if ($this->isNonCommitting($status, $crStatuses)) {
            return [false, 'non_committing_status', $this->skipReasonForStatus($status, $crStatuses)];
        }

        if ($this->isStaleDraft($status, $cr->updated_at, $crStatuses)) {
            return [false, 'stale_draft', $this->staleDraftReason($cr->updated_at)];
        }

        if (! in_array((int) $cr->id, $latestIds, true)) {
            return [false, 'not_latest_change_request', 'A newer change request exists for the same parent memo'];
        }

        return [true, null, null];
    }

    /**
     * @param  list<string>  $committedStatuses
     */
    private function isNonCommitting(string $status, array $committedStatuses): bool
    {
        if (in_array($status, FundCodeWorkingBalanceService::NON_COMMITTING_STATUSES, true)) {
            return true;
        }

        return ! in_array($status, $committedStatuses, true);
    }

    /**
     * @param  list<string>  $committedStatuses
     */
    private function isStaleDraft(string $status, mixed $updatedAt, array $committedStatuses): bool
    {
        if ($status !== 'draft' || ! in_array('draft', $committedStatuses, true)) {
            return false;
        }

        $cutoff = $this->settings->draftBudgetCutoff();
        if ($cutoff === null || $updatedAt === null) {
            return false;
        }

        $ts = $updatedAt instanceof Carbon ? $updatedAt : Carbon::parse((string) $updatedAt);

        return $ts->lt($cutoff);
    }

    /**
     * @param  list<string>  $committedStatuses
     */
    private function skipReasonForStatus(string $status, array $committedStatuses): string
    {
        if (in_array($status, FundCodeWorkingBalanceService::NON_COMMITTING_STATUSES, true)) {
            return 'Status "' . $status . '" does not commit budget';
        }

        return 'Status "' . $status . '" is not in committed statuses (' . implode(', ', $committedStatuses) . ')';
    }

    private function staleDraftReason(mixed $updatedAt): string
    {
        $months = $this->settings->draftMaxAgeMonths();
        $cutoff = $this->settings->draftBudgetCutoff();
        $cutoffLabel = $cutoff instanceof Carbon ? $cutoff->format('M j, Y') : 'cutoff';
        $updatedLabel = $updatedAt instanceof Carbon
            ? $updatedAt->format('M j, Y')
            : Carbon::parse((string) $updatedAt)->format('M j, Y');

        return "Draft last updated {$updatedLabel} is older than {$months} month(s) (before {$cutoffLabel})";
    }

    /**
     * @param  array<string, mixed>  $activeCrs
     */
    private function supersedingChangeRequestId(array $activeCrs, string $type, int $memoId): ?int
    {
        foreach ($activeCrs['by_id'] ?? [] as $cr) {
            if (! $cr instanceof ChangeRequest) {
                continue;
            }
            $match = match ($type) {
                'activity' => (int) ($cr->activity_id ?? 0) === $memoId
                    || $this->crParentMatches($cr, 'activity', $memoId),
                'special_memo' => (int) ($cr->special_memo_id ?? 0) === $memoId
                    || $this->crParentMatches($cr, 'specialmemo', $memoId),
                'non_travel' => (int) ($cr->non_travel_memo_id ?? 0) === $memoId
                    || $this->crParentMatches($cr, 'nontravelmemo', $memoId),
                default => false,
            };
            if ($match) {
                return (int) $cr->id;
            }
        }

        return null;
    }

    private function crParentMatches(ChangeRequest $cr, string $parentModelFragment, int $memoId): bool
    {
        $parentId = (int) ($cr->parent_memo_id ?? 0);
        if ($parentId !== $memoId) {
            return false;
        }

        $parentModel = strtolower(str_replace('\\', '', (string) ($cr->parent_memo_model ?? '')));

        return str_contains($parentModel, $parentModelFragment);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeLine(
        string $type,
        int $id,
        string $title,
        ?string $documentNumber,
        string $status,
        float $amount,
        bool $committed,
        ?string $skipCode,
        ?string $skipReason,
        mixed $updatedAt,
        ?int $matrixId = null,
        ?float $memoGrandTotal = null,
    ): array {
        $updated = $updatedAt instanceof Carbon
            ? $updatedAt->toIso8601String()
            : ($updatedAt ? Carbon::parse((string) $updatedAt)->toIso8601String() : null);

        return [
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'id' => $id,
            'title' => $title,
            'document_number' => $documentNumber,
            'status' => strtolower($status),
            'amount' => round($amount, 2),
            'memo_grand_total' => $memoGrandTotal !== null ? round($memoGrandTotal, 2) : null,
            'committed' => $committed,
            'skip_code' => $skipCode,
            'skip_reason' => $skipReason,
            'updated_at' => $updated,
            'url' => $this->memoUrl($type, $id, $matrixId),
        ];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'activity' => 'Matrix Activity',
            'single_memo' => 'Single Memo',
            'special_memo' => 'Special Memo',
            'non_travel' => 'Non-Travel Memo',
            'change_request' => 'Change Request',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function dedupeLines(array $lines): array
    {
        $seen = [];
        $out = [];
        foreach ($lines as $line) {
            $key = ($line['type'] ?? '') . ':' . ($line['id'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $line;
        }

        return $out;
    }

    private function memoUrl(string $type, int $id, ?int $matrixId = null): ?string
    {
        try {
            return match ($type) {
                'activity' => $matrixId ? route('matrices.activities.show', [$matrixId, $id]) : null,
                'single_memo' => route('activities.single-memos.show', $id),
                'special_memo' => route('special-memo.show', $id),
                'non_travel' => route('non-travel.show', $id),
                'change_request' => route('change-requests.show', $id),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLedger(int $fundCodeId): array
    {
        return [
            'fund_code' => ['id' => $fundCodeId, 'code' => (string) $fundCodeId, 'name' => null],
            'snapshot' => [
                'approved_budget' => 0.0,
                'committed_total' => 0.0,
                'working_balance' => 0.0,
            ],
            'settings' => [
                'draft_max_age_months' => $this->settings->draftMaxAgeMonths(),
                'activity_statuses' => $this->settings->committedActivityStatuses(),
                'memo_statuses' => $this->settings->committedMemoStatuses(),
                'change_request_statuses' => $this->settings->committedChangeRequestStatuses(),
            ],
            'committed' => [],
            'skipped' => [],
            'totals' => ['committed_count' => 0, 'skipped_count' => 0, 'committed_sum' => 0.0],
        ];
    }

    /**
     * @return array<mixed, mixed>
     */
    private function decodeBreakdown(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function cacheRemember(string $key, int $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }
}
