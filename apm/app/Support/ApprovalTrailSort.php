<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Order approval trail rows for API: newest created_at first, then id descending.
 */
class ApprovalTrailSort
{
    public static function timestamp(object $t): int
    {
        $raw = $t->created_at ?? null;
        if ($raw instanceof \DateTimeInterface) {
            return $raw->getTimestamp();
        }
        if ($raw === null || $raw === '') {
            return 0;
        }
        $parsed = strtotime((string) $raw);

        return $parsed !== false ? $parsed : 0;
    }

    /**
     * @return Collection<int, mixed>
     */
    public static function latestFirst(Collection $trails): Collection
    {
        if ($trails->isEmpty()) {
            return $trails;
        }

        return $trails->sort(function ($a, $b) {
            $ta = self::timestamp($a);
            $tb = self::timestamp($b);
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }

            return (int) ($b->id ?? 0) <=> (int) ($a->id ?? 0);
        })->values();
    }
}
