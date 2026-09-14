<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\Division;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finds draft memos older than the configured age that still hold budget lines.
 */
class StaleDraftMemosService
{
    public function __construct(
        private readonly BudgetCommitmentSettings $settings = new BudgetCommitmentSettings()
    ) {}

    /**
     * @return list<array{
     *   type: string,
     *   type_label: string,
     *   id: int,
     *   title: string,
     *   document_number: string|null,
     *   updated_at: string,
     *   budget_total: float,
     *   edit_url: string|null
     * }>
     */
    public function getStaleDraftsForStaff(int $staffId): array
    {
        $cutoff = $this->settings->draftBudgetCutoff();
        if ($cutoff === null || ! $this->settings->staleDraftRemindersEnabled()) {
            return [];
        }

        $items = array_merge(
            $this->staleActivities($cutoff, $staffId),
            $this->staleSpecialMemos($cutoff, $staffId),
            $this->staleNonTravelMemos($cutoff, $staffId),
            $this->staleChangeRequests($cutoff, $staffId)
        );

        usort($items, static fn (array $a, array $b): int => strcmp($b['updated_at'], $a['updated_at']));

        return $items;
    }

    /**
     * @return list<int>
     */
    public function staffIdsWithStaleDrafts(): array
    {
        $cutoff = $this->settings->draftBudgetCutoff();
        if ($cutoff === null || ! $this->settings->staleDraftRemindersEnabled()) {
            return [];
        }

        $ids = collect()
            ->merge($this->staleActivityStaffIds($cutoff))
            ->merge($this->staleSpecialMemoStaffIds($cutoff))
            ->merge($this->staleNonTravelMemoStaffIds($cutoff))
            ->merge($this->staleChangeRequestStaffIds($cutoff))
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    /**
     * All stale draft memos system-wide (for admin archive review).
     *
     * @return list<array<string, mixed>>
     */
    public function getAllStaleDrafts(): array
    {
        $cutoff = $this->settings->draftBudgetCutoff();
        if ($cutoff === null) {
            return [];
        }

        $items = array_merge(
            $this->staleActivities($cutoff),
            $this->staleSpecialMemos($cutoff),
            $this->staleNonTravelMemos($cutoff),
            $this->staleChangeRequests($cutoff)
        );

        usort($items, static fn (array $a, array $b): int => strcmp($b['updated_at'], $a['updated_at']));

        return $items;
    }

    /**
     * Stale drafts visible to owner, responsible person, division HOD, or focal person.
     *
     * @return list<array<string, mixed>>
     */
    public function getStaleDraftsVisibleToUser(int $staffId): array
    {
        $cutoff = $this->settings->draftBudgetCutoff();
        if ($cutoff === null || $staffId <= 0) {
            return [];
        }

        $divisionIds = $this->divisionIdsForStaffOversight($staffId);
        $items = array_merge(
            $this->staleActivities($cutoff, $staffId),
            $this->staleSpecialMemos($cutoff, $staffId),
            $this->staleNonTravelMemos($cutoff, $staffId),
            $this->staleChangeRequests($cutoff, $staffId)
        );

        if ($divisionIds !== []) {
            $items = array_merge(
                $items,
                $this->staleActivities($cutoff, null, $divisionIds),
                $this->staleSpecialMemos($cutoff, null, $divisionIds),
                $this->staleNonTravelMemos($cutoff, null, $divisionIds),
                $this->staleChangeRequests($cutoff, null, $divisionIds)
            );
        }

        return $this->dedupeStaleItems($items);
    }

    public function findStaleDraftItem(string $type, int $id): ?array
    {
        foreach ($this->getAllStaleDrafts() as $item) {
            if (($item['type'] ?? '') === $type && (int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function divisionIdsForStaffOversight(int $staffId): array
    {
        return Division::query()
            ->get(['id', 'division_head', 'focal_person', 'head_oic_id', 'head_oic_start_date', 'head_oic_end_date'])
            ->filter(function (Division $division) use ($staffId): bool {
                if ((int) ($division->focal_person ?? 0) === $staffId) {
                    return true;
                }

                if (function_exists('effective_division_head_staff_id')) {
                    $headId = effective_division_head_staff_id($division);

                    return $headId !== null && (int) $headId === $staffId;
                }

                return (int) ($division->division_head ?? 0) === $staffId;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dedupeStaleItems(array $items): array
    {
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $key = ($item['type'] ?? '') . ':' . ($item['id'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        usort($unique, static fn (array $a, array $b): int => strcmp($b['updated_at'], $a['updated_at']));

        return $unique;
    }

    public function memoHoldsBudget(object $memo): bool
    {
        if ($memo instanceof Activity) {
            return (float) DB::table('activity_budgets')
                ->where('activity_id', $memo->id)
                ->sum('total') > 0;
        }

        if ($memo instanceof SpecialMemo || $memo instanceof NonTravelMemo || $memo instanceof ChangeRequest) {
            return $this->breakdownTotal($memo->budget_breakdown ?? null) > 0;
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staleActivities(Carbon $cutoff, ?int $staffId = null, ?array $divisionIds = null): array
    {
        if (! in_array('draft', $this->settings->committedActivityStatuses(), true)) {
            return [];
        }

        $rows = Activity::query()
            ->with('matrix:id,division_id')
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->when($staffId !== null || ($divisionIds !== null && $divisionIds !== []), function ($q) use ($staffId, $divisionIds) {
                $q->where(function ($outer) use ($staffId, $divisionIds) {
                    if ($staffId !== null) {
                        $outer->where(function ($inner) use ($staffId) {
                            $inner->where('staff_id', $staffId)
                                ->orWhere('responsible_person_id', $staffId);
                        });
                    }
                    if ($divisionIds !== null && $divisionIds !== []) {
                        $outer->orWhere(function ($inner) use ($divisionIds) {
                            $inner->whereIn('division_id', $divisionIds)
                                ->orWhereHas('matrix', fn ($matrix) => $matrix->whereIn('division_id', $divisionIds));
                        });
                    }
                });
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('activity_budgets')
                    ->whereColumn('activity_budgets.activity_id', 'activities.id')
                    ->where('activity_budgets.total', '>', 0);
            })
            ->get(['id', 'activity_title', 'document_number', 'updated_at', 'matrix_id', 'is_single_memo', 'staff_id', 'responsible_person_id', 'division_id']);

        return $rows->map(function (Activity $activity) {
            $budgetTotal = (float) DB::table('activity_budgets')
                ->where('activity_id', $activity->id)
                ->sum('total');

            return [
                'type' => (int) ($activity->is_single_memo ?? 0) === 1 ? 'single_memo' : 'activity',
                'type_label' => (int) ($activity->is_single_memo ?? 0) === 1 ? 'Single memo' : 'Activity',
                'id' => (int) $activity->id,
                'title' => (string) ($activity->activity_title ?? 'Untitled'),
                'document_number' => $activity->document_number,
                'updated_at' => $activity->updated_at?->toDateTimeString() ?? '',
                'budget_total' => round($budgetTotal, 2),
                'staff_id' => (int) ($activity->staff_id ?? 0),
                'responsible_person_id' => (int) ($activity->responsible_person_id ?? 0),
                'division_id' => (int) ($activity->division_id ?: $activity->matrix?->division_id ?: 0),
                'edit_url' => $this->activityEditUrl($activity),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staleSpecialMemos(Carbon $cutoff, ?int $staffId = null, ?array $divisionIds = null): array
    {
        if (! in_array('draft', $this->settings->committedMemoStatuses(), true)) {
            return [];
        }

        return SpecialMemo::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->when($staffId !== null || ($divisionIds !== null && $divisionIds !== []), function ($q) use ($staffId, $divisionIds) {
                $q->where(function ($outer) use ($staffId, $divisionIds) {
                    if ($staffId !== null) {
                        $outer->where(function ($inner) use ($staffId) {
                            $inner->where('staff_id', $staffId)
                                ->orWhere('responsible_person_id', $staffId);
                        });
                    }
                    if ($divisionIds !== null && $divisionIds !== []) {
                        $outer->orWhereIn('division_id', $divisionIds);
                    }
                });
            })
            ->get(['id', 'activity_title', 'document_number', 'updated_at', 'budget_breakdown', 'staff_id', 'responsible_person_id', 'division_id'])
            ->filter(fn (SpecialMemo $memo) => $this->breakdownTotal($memo->budget_breakdown) > 0)
            ->map(fn (SpecialMemo $memo) => [
                'type' => 'special_memo',
                'type_label' => 'Special memo',
                'id' => (int) $memo->id,
                'title' => (string) ($memo->activity_title ?? 'Untitled'),
                'document_number' => $memo->document_number,
                'updated_at' => $memo->updated_at?->toDateTimeString() ?? '',
                'budget_total' => $this->breakdownTotal($memo->budget_breakdown),
                'staff_id' => (int) ($memo->staff_id ?? 0),
                'responsible_person_id' => (int) ($memo->responsible_person_id ?? 0),
                'division_id' => (int) ($memo->division_id ?? 0),
                'edit_url' => route('special-memo.edit', $memo->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staleNonTravelMemos(Carbon $cutoff, ?int $staffId = null, ?array $divisionIds = null): array
    {
        if (! in_array('draft', $this->settings->committedMemoStatuses(), true)) {
            return [];
        }

        return NonTravelMemo::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->when($staffId !== null || ($divisionIds !== null && $divisionIds !== []), function ($q) use ($staffId, $divisionIds) {
                $q->where(function ($outer) use ($staffId, $divisionIds) {
                    if ($staffId !== null) {
                        $outer->where('staff_id', $staffId);
                    }
                    if ($divisionIds !== null && $divisionIds !== []) {
                        $outer->orWhereIn('division_id', $divisionIds);
                    }
                });
            })
            ->get(['id', 'activity_title', 'document_number', 'updated_at', 'budget_breakdown', 'staff_id', 'division_id'])
            ->filter(fn (NonTravelMemo $memo) => $this->breakdownTotal($memo->budget_breakdown) > 0)
            ->map(fn (NonTravelMemo $memo) => [
                'type' => 'non_travel_memo',
                'type_label' => 'Non-travel memo',
                'id' => (int) $memo->id,
                'title' => (string) ($memo->activity_title ?? 'Untitled'),
                'document_number' => $memo->document_number,
                'updated_at' => $memo->updated_at?->toDateTimeString() ?? '',
                'budget_total' => $this->breakdownTotal($memo->budget_breakdown),
                'staff_id' => (int) ($memo->staff_id ?? 0),
                'responsible_person_id' => 0,
                'division_id' => (int) ($memo->division_id ?? 0),
                'edit_url' => route('non-travel.edit', $memo->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staleChangeRequests(Carbon $cutoff, ?int $staffId = null, ?array $divisionIds = null): array
    {
        if (! in_array('draft', $this->settings->committedChangeRequestStatuses(), true)) {
            return [];
        }

        return ChangeRequest::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->when($staffId !== null || ($divisionIds !== null && $divisionIds !== []), function ($q) use ($staffId, $divisionIds) {
                $q->where(function ($outer) use ($staffId, $divisionIds) {
                    if ($staffId !== null) {
                        $outer->where(function ($inner) use ($staffId) {
                            $inner->where('staff_id', $staffId)
                                ->orWhere('responsible_person_id', $staffId);
                        });
                    }
                    if ($divisionIds !== null && $divisionIds !== []) {
                        $outer->orWhereIn('division_id', $divisionIds);
                    }
                });
            })
            ->get(['id', 'activity_title', 'document_number', 'updated_at', 'budget_breakdown', 'staff_id', 'responsible_person_id', 'division_id'])
            ->filter(fn (ChangeRequest $cr) => $this->breakdownTotal($cr->budget_breakdown) > 0)
            ->map(fn (ChangeRequest $cr) => [
                'type' => 'change_request',
                'type_label' => 'Change request',
                'id' => (int) $cr->id,
                'title' => (string) ($cr->activity_title ?? 'Untitled'),
                'document_number' => $cr->document_number,
                'updated_at' => $cr->updated_at?->toDateTimeString() ?? '',
                'budget_total' => $this->breakdownTotal($cr->budget_breakdown),
                'staff_id' => (int) ($cr->staff_id ?? 0),
                'responsible_person_id' => (int) ($cr->responsible_person_id ?? 0),
                'division_id' => (int) ($cr->division_id ?? 0),
                'edit_url' => route('change-requests.edit', $cr->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function staleActivityStaffIds(Carbon $cutoff): array
    {
        if (! in_array('draft', $this->settings->committedActivityStatuses(), true)) {
            return [];
        }

        return Activity::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('activity_budgets')
                    ->whereColumn('activity_budgets.activity_id', 'activities.id')
                    ->where('activity_budgets.total', '>', 0);
            })
            ->get(['staff_id', 'responsible_person_id'])
            ->flatMap(fn (Activity $a) => [(int) $a->staff_id, (int) ($a->responsible_person_id ?? 0)])
            ->all();
    }

    /**
     * @return list<int>
     */
    private function staleSpecialMemoStaffIds(Carbon $cutoff): array
    {
        return $this->memoStaffIds(SpecialMemo::query(), $cutoff, $this->settings->committedMemoStatuses(), true);
    }

    /**
     * @return list<int>
     */
    private function staleNonTravelMemoStaffIds(Carbon $cutoff): array
    {
        if (! in_array('draft', $this->settings->committedMemoStatuses(), true)) {
            return [];
        }

        return NonTravelMemo::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->get(['staff_id', 'budget_breakdown'])
            ->filter(fn (NonTravelMemo $memo) => $this->breakdownTotal($memo->budget_breakdown) > 0)
            ->pluck('staff_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function staleChangeRequestStaffIds(Carbon $cutoff): array
    {
        if (! in_array('draft', $this->settings->committedChangeRequestStatuses(), true)) {
            return [];
        }

        return ChangeRequest::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->get(['staff_id', 'responsible_person_id', 'budget_breakdown'])
            ->filter(fn (ChangeRequest $cr) => $this->breakdownTotal($cr->budget_breakdown) > 0)
            ->flatMap(fn (ChangeRequest $cr) => [(int) $cr->staff_id, (int) ($cr->responsible_person_id ?? 0)])
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<SpecialMemo>  $query
     * @param  list<string>  $statuses
     * @return list<int>
     */
    private function memoStaffIds($query, Carbon $cutoff, array $statuses, bool $includeResponsible = false): array
    {
        if (! in_array('draft', $statuses, true)) {
            return [];
        }

        $columns = $includeResponsible
            ? ['staff_id', 'responsible_person_id', 'budget_breakdown']
            : ['staff_id', 'budget_breakdown'];

        return $query
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->get($columns)
            ->filter(fn ($memo) => $this->breakdownTotal($memo->budget_breakdown) > 0)
            ->flatMap(function ($memo) use ($includeResponsible) {
                $ids = [(int) $memo->staff_id];
                if ($includeResponsible && ! empty($memo->responsible_person_id)) {
                    $ids[] = (int) $memo->responsible_person_id;
                }

                return $ids;
            })
            ->all();
    }

    private function activityEditUrl(Activity $activity): ?string
    {
        $matrixId = (int) ($activity->matrix_id ?? 0);
        if ($matrixId <= 0) {
            return null;
        }

        if ((int) ($activity->is_single_memo ?? 0) === 1) {
            return route('activities.single-memos.edit', ['matrix' => $matrixId, 'activity' => $activity->id]);
        }

        return route('matrices.activities.edit', ['matrix' => $matrixId, 'activity' => $activity->id]);
    }

    private function breakdownTotal(mixed $breakdown): float
    {
        if (is_string($breakdown) && $breakdown !== '') {
            $breakdown = json_decode($breakdown, true);
        }
        if (! is_array($breakdown)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($breakdown as $codeKey => $items) {
            if ($codeKey === 'grand_total' || ! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (! is_array($item) && ! is_object($item)) {
                    continue;
                }
                $row = (array) $item;
                $unitCost = (float) ($row['unit_cost'] ?? 0);
                $units = (float) ($row['units'] ?? $row['quantity'] ?? $row['qty'] ?? 1);
                $days = (float) ($row['days'] ?? 1);
                $total += $unitCost * $units * max(1, $days);
            }
        }

        return round($total, 2);
    }
}
