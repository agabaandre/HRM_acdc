<?php

namespace App\Support;

use App\Models\Activity;

/**
 * Resolve show URLs for parent memos (activity, non-travel, etc.).
 * Matrix activities must use matrices.activities.show, not single-memos.show.
 */
class MemoShowUrl
{
    public static function forMemo(?string $modelClass, ?int $memoId): ?string
    {
        if (! $modelClass || ! $memoId) {
            return null;
        }

        $modelName = class_basename($modelClass);

        return match ($modelName) {
            'Activity' => self::activityShowUrl((int) $memoId),
            'SpecialMemo' => route('special-memo.show', $memoId),
            'NonTravelMemo' => route('non-travel.show', $memoId),
            'OtherMemo' => route('other-memos.show', $memoId),
            'RequestArf' => route('request-arf.show', $memoId),
            'ServiceRequest' => route('service-requests.show', $memoId),
            default => null,
        };
    }

    public static function activityShowUrl(int $activityId): ?string
    {
        $activity = Activity::query()->find($activityId);
        if (! $activity) {
            return null;
        }

        if ($activity->is_single_memo) {
            return route('activities.single-memos.show', $activity->id);
        }

        if ($activity->matrix_id) {
            return route('matrices.activities.show', [$activity->matrix_id, $activity->id]);
        }

        return null;
    }
}
