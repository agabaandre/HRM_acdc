<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Order approval trail rows:
 * - {@see latestFirst} — API / feeds: newest first.
 * - {@see timelineAsc} — HTML timelines: workflow step, then chronological.
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

    /**
     * Timeline order for Blade partials: approval_order (nulls last), then created_at, then id.
     *
     * @return Collection<int, mixed>
     */
    public static function timelineAsc(Collection $trails): Collection
    {
        if ($trails->isEmpty()) {
            return $trails;
        }

        return $trails->sort(function ($a, $b) {
            $oa = $a->approval_order ?? null;
            $ob = $b->approval_order ?? null;
            $orderA = ($oa === null || $oa === '') ? PHP_INT_MAX : (int) $oa;
            $orderB = ($ob === null || $ob === '') ? PHP_INT_MAX : (int) $ob;
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            $ta = self::timestamp($a);
            $tb = self::timestamp($b);
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

            return (int) ($a->id ?? 0) <=> (int) ($b->id ?? 0);
        })->values();
    }
}
