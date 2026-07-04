<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChangeRequest;
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
            $this->staleActivities($staffId, $cutoff),
            $this->staleSpecialMemos($staffId, $cutoff),
            $this->staleNonTravelMemos($staffId, $cutoff),
            $this->staleChangeRequests($staffId, $cutoff)
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
     * @return list<array<string, mixed>>
     */
    private function staleActivities(int $staffId, Carbon $cutoff): array
    {
        if (! in_array('draft', $this->settings->committedActivityStatuses(), true)) {
            return [];
        }

        $rows = Activity::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->where(function ($q) use ($staffId) {
                $q->where('staff_id', $staffId)
                    ->orWhere('responsible_person_id', $staffId);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('activity_budgets')
                    ->whereColumn('activity_budgets.activity_id', 'activities.id')
                    ->where('activity_budgets.total', '>', 0);
            })
            ->get(['id', 'activity_title', 'document_number', 'updated_at', 'matrix_id', 'is_single_memo']);

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
                'edit_url' => $this->activityEditUrl($activity),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staleSpecialMemos(int $staffId, Carbon $cutoff): array
    {
        if (! in_array('draft', $this->settings->committedMemoStatuses(), true)) {
            return [];
        }

        return SpecialMemo::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->where(function ($q) use ($staffId) {
                $q->where('staff_id', $staffId)
                    ->orWhere('responsible_person_id', $staffId);
            })
            ->get(['id', 'activity_title', 'document_number', 'updated_at', 'budget_breakdown'])
            ->filter(fn (SpecialMemo $memo) => $this->breakdownTotal($memo->budget_breakdown) > 0)
            ->map(fn (SpecialMemo $memo) => [
                'type' => 'special_memo',
                'type_label' => 'Special memo',
                'id' => (int) $memo->id,
                'title' => (string) ($memo->activity_title ?? 'Untitled'),
                'document_number' => $memo->document_number,
                'updated_at' => $memo->updated_at?->toDateTimeString() ?? '',
                'budget_total' => $this->breakdownTotal($memo->budget_breakdown),
                'edit_url' => route('special-memo.edit', $memo->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staleNonTravelMemos(int $staffId, Carbon $cutoff): array
    {
        if (! in_array('draft', $this->settings->committedMemoStatuses(), true)) {
            return [];
        }

        return NonTravelMemo::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->where('staff_id', $staffId)
            ->get(['id', 'activity_title', 'document_number', 'updated_at', 'budget_breakdown'])
            ->filter(fn (NonTravelMemo $memo) => $this->breakdownTotal($memo->budget_breakdown) > 0)
            ->map(fn (NonTravelMemo $memo) => [
                'type' => 'non_travel_memo',
                'type_label' => 'Non-travel memo',
                'id' => (int) $memo->id,
                'title' => (string) ($memo->activity_title ?? 'Untitled'),
                'document_number' => $memo->document_number,
                'updated_at' => $memo->updated_at?->toDateTimeString() ?? '',
                'budget_total' => $this->breakdownTotal($memo->budget_breakdown),
                'edit_url' => route('non-travel.edit', $memo->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staleChangeRequests(int $staffId, Carbon $cutoff): array
    {
        if (! in_array('draft', $this->settings->committedChangeRequestStatuses(), true)) {
            return [];
        }

        return ChangeRequest::query()
            ->where('overall_status', 'draft')
            ->where('updated_at', '<', $cutoff)
            ->where(function ($q) use ($staffId) {
                $q->where('staff_id', $staffId)
                    ->orWhere('responsible_person_id', $staffId);
            })
            ->get(['id', 'activity_title', 'document_number', 'updated_at', 'budget_breakdown'])
            ->filter(fn (ChangeRequest $cr) => $this->breakdownTotal($cr->budget_breakdown) > 0)
            ->map(fn (ChangeRequest $cr) => [
                'type' => 'change_request',
                'type_label' => 'Change request',
                'id' => (int) $cr->id,
                'title' => (string) ($cr->activity_title ?? 'Untitled'),
                'document_number' => $cr->document_number,
                'updated_at' => $cr->updated_at?->toDateTimeString() ?? '',
                'budget_total' => $this->breakdownTotal($cr->budget_breakdown),
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
