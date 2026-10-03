<?php

namespace App\Support;

/**
 * Normalize associated division IDs from Staff Portal / share API payloads.
 */
final class AssociatedDivisions
{
    /**
     * @return list<int>
     */
    public static function normalize(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map(static function ($id) {
                return is_numeric($id) ? (int) $id : 0;
            }, $value), static fn (int $id): bool => $id > 0));
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? self::normalize($decoded) : [];
        }

        return [];
    }
}
