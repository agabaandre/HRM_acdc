<?php

namespace App\Support;

use App\Models\Staff;

class TravelCashCarriers
{
    /**
     * @return array<int, int>
     */
    public static function normalizeIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === 'null') {
                return [];
            }
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return self::normalizeIds($decoded);
            }
            if (is_numeric($trimmed)) {
                return self::normalizeIds([(int) $trimmed]);
            }

            return [];
        }

        if (is_numeric($value)) {
            $id = (int) $value;

            return $id > 0 ? [$id] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $sid = (int) ($item['staff_id'] ?? $item['id'] ?? 0);
                if ($sid > 0) {
                    $ids[] = $sid;
                }
                continue;
            }
            if (is_numeric($item)) {
                $sid = (int) $item;
                if ($sid > 0) {
                    $ids[] = $sid;
                }
                continue;
            }
            if (is_numeric($key) && (string) $item !== '') {
                $sid = (int) $key;
                if ($sid > 0) {
                    $ids[] = $sid;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, int>
     */
    public static function resolveIds(?object $model): array
    {
        if ($model === null) {
            return [];
        }

        $ids = self::normalizeIds($model->cash_carrier_staff_ids ?? null);
        if ($ids !== []) {
            return $ids;
        }

        return self::normalizeIds($model->cash_carrier_staff_id ?? null);
    }

    /**
     * @return array<int, int>
     */
    public static function fromRequest(mixed $input): array
    {
        return self::normalizeIds($input);
    }

    /**
     * @param  array<int, int>  $staffIds
     */
    public static function displayNames(array $staffIds): string
    {
        $staffIds = self::normalizeIds($staffIds);
        if ($staffIds === []) {
            return 'N/A';
        }

        $staff = Staff::query()
            ->whereIn('staff_id', $staffIds)
            ->get(['staff_id', 'title', 'fname', 'lname']);

        $byId = $staff->keyBy('staff_id');
        $labels = [];
        foreach ($staffIds as $id) {
            $member = $byId->get($id);
            if (! $member) {
                continue;
            }
            $labels[] = trim(($member->title ? $member->title.' ' : '').$member->fname.' '.$member->lname);
        }

        return $labels !== [] ? implode('; ', $labels) : 'N/A';
    }

    /**
     * @return array<string, string>
     */
    public static function cashCarrierValidationRules(): array
    {
        return [
            'cash_carrier_staff_ids' => 'required|array|min:1',
            'cash_carrier_staff_ids.*' => 'integer|exists:staff,staff_id',
            'cash_bank_transfer_unavailable_reason' => 'required|string|min:15|max:5000',
        ];
    }
}
