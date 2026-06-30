<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolve ARF / morph source models without dynamic $modelType::class autoload.
 *
 * Production ARF rows may store model_type with stray whitespace or legacy aliases;
 * calling class_exists($modelType) can re-include Activity.php and fatal.
 */
final class ArfSourceModelResolver
{
    /**
     * @return 'activity'|'non_travel_memo'|'special_memo'|null
     */
    public static function normalizeModelType(?string $modelType): ?string
    {
        if ($modelType === null || $modelType === '') {
            return null;
        }

        $raw = trim($modelType);
        $raw = str_replace('\\\\', '\\', $raw);
        $raw = preg_replace('/[\x00-\x1F\x7F]/', '', $raw) ?? $raw;

        return match ($raw) {
            'App\Models\Activity', 'Activity' => 'activity',
            'App\Models\NonTravelMemo', 'NonTravelMemo' => 'non_travel_memo',
            'App\Models\SpecialMemo', 'SpecialMemo' => 'special_memo',
            default => null,
        };
    }

    public static function find(?string $modelType, int|string|null $sourceId): ?Model
    {
        if ($sourceId === null || $sourceId === '') {
            return null;
        }

        $id = (int) $sourceId;
        if ($id <= 0) {
            return null;
        }

        return match (self::normalizeModelType($modelType)) {
            'activity' => self::findActivity($id),
            'non_travel_memo' => self::findNonTravelMemo($id),
            'special_memo' => self::findSpecialMemo($id),
            default => null,
        };
    }

    private static function findActivity(int $id): ?Activity
    {
        ActivityModelWarmup::run();

        return Activity::query()->withoutAppends()->find($id);
    }

    private static function findNonTravelMemo(int $id): ?NonTravelMemo
    {
        return NonTravelMemo::query()->withoutAppends()->find($id);
    }

    private static function findSpecialMemo(int $id): ?SpecialMemo
    {
        return SpecialMemo::query()->withoutAppends()->find($id);
    }
}
