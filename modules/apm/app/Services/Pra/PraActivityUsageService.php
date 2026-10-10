<?php

namespace App\Services\Pra;

use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve APM records already linked to PRA specific activity IDs.
 */
class PraActivityUsageService
{
    /**
     * @param  list<int>  $praActivityIds
     * @return array<int, list<array{
     *     type: string,
     *     id: int,
     *     label: string,
     *     status: string,
     *     url: string,
     *     matrix_id: ?int,
     *     year: ?int,
     *     quarter: ?string,
     *     is_submitted: bool
     * }>>
     */
    public function usagesByPraIds(array $praActivityIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $praActivityIds))));
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [];
        }

        if (Schema::hasColumn('activities', 'pra_activity_id')) {
            // activities has no is_draft column — draft is overall_status = 'draft'
            $activities = Activity::query()
                ->with(['matrix:id,year,quarter,division_id'])
                ->whereIn('pra_activity_id', $ids)
                ->get(['id', 'pra_activity_id', 'matrix_id', 'activity_title', 'overall_status', 'is_single_memo']);

            foreach ($activities as $activity) {
                $praId = (int) $activity->pra_activity_id;
                $isSingle = (int) ($activity->is_single_memo ?? 0) === 1;
                $status = (string) ($activity->overall_status ?? '');
                $isDraft = strtolower($status) === 'draft' || $status === '';
                $url = $isSingle
                    ? route('activities.single-memos.show', $activity)
                    : route('matrices.activities.show', [$activity->matrix_id, $activity]);

                $out[$praId][] = [
                    'type' => $isSingle ? 'single_memo' : 'matrix_activity',
                    'id' => (int) $activity->id,
                    'label' => $isSingle
                        ? 'Single memo #'.$activity->id
                        : 'Matrix activity #'.$activity->id,
                    'status' => $status !== '' ? $status : 'draft',
                    'url' => $url,
                    'matrix_id' => $activity->matrix_id ? (int) $activity->matrix_id : null,
                    'year' => $activity->matrix?->year ? (int) $activity->matrix->year : null,
                    'quarter' => $activity->matrix?->quarter ? (string) $activity->matrix->quarter : null,
                    'is_submitted' => ! $isDraft,
                ];
            }
        }

        if (Schema::hasTable('non_travel_memos') && Schema::hasColumn('non_travel_memos', 'pra_activity_id')) {
            foreach (NonTravelMemo::query()->whereIn('pra_activity_id', $ids)->get(['id', 'pra_activity_id', 'activity_title', 'overall_status', 'is_draft']) as $memo) {
                $praId = (int) $memo->pra_activity_id;
                $status = (string) ($memo->overall_status ?? '');
                $isDraft = (bool) ($memo->is_draft ?? false) || strtolower($status) === 'draft';
                $out[$praId][] = [
                    'type' => 'non_travel',
                    'id' => (int) $memo->id,
                    'label' => 'Non-travel #'.$memo->id,
                    'status' => $status !== '' ? $status : ($isDraft ? 'draft' : 'unknown'),
                    'url' => route('non-travel.show', $memo),
                    'matrix_id' => null,
                    'year' => null,
                    'quarter' => null,
                    'is_submitted' => ! $isDraft && ! in_array(strtolower($status), ['draft', ''], true),
                ];
            }
        }

        if (Schema::hasTable('special_memos') && Schema::hasColumn('special_memos', 'pra_activity_id')) {
            foreach (SpecialMemo::query()->whereIn('pra_activity_id', $ids)->get(['id', 'pra_activity_id', 'activity_title', 'overall_status', 'is_draft']) as $memo) {
                $praId = (int) $memo->pra_activity_id;
                $status = (string) ($memo->overall_status ?? '');
                $isDraft = (bool) ($memo->is_draft ?? false) || strtolower($status) === 'draft';
                $out[$praId][] = [
                    'type' => 'special_memo',
                    'id' => (int) $memo->id,
                    'label' => 'Special memo #'.$memo->id,
                    'status' => $status !== '' ? $status : ($isDraft ? 'draft' : 'unknown'),
                    'url' => route('special-memo.show', $memo),
                    'matrix_id' => null,
                    'year' => null,
                    'quarter' => null,
                    'is_submitted' => ! $isDraft && ! in_array(strtolower($status), ['draft', ''], true),
                ];
            }
        }

        return $out;
    }
}
