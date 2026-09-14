<?php

namespace App\Services;

use App\Support\StaffShareNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves effective Head of Division from Staff Share division payloads
 * (same rules as APM effective_division_head_staff_id).
 */
final class DivisionHeadResolver
{
    public const CACHE_KEY = 'helpdesk_reference_bundle_v1';

    /**
     * @param  array<string, mixed>|null  $division  Normalised or raw Share row
     */
    public function effectiveHeadStaffId(?array $division, ?Carbon $on = null): ?int
    {
        if ($division === null) {
            return null;
        }

        $today = ($on ?? Carbon::today())->startOfDay();

        $oicId = (int) ($division['head_oic_id'] ?? 0);
        if ($oicId > 0) {
            $start = $this->parseDate($division['head_oic_start_date'] ?? null);
            $end = $this->parseDate($division['head_oic_end_date'] ?? null);
            $active = true;
            if ($start !== null) {
                $active = $active && $start->lte($today);
            }
            if ($end !== null) {
                $active = $active && $end->gte($today);
            }
            if ($active) {
                return $oicId;
            }
        }

        $head = (int) ($division['division_head'] ?? 0);

        return $head > 0 ? $head : null;
    }

    public function effectiveHeadStaffIdForDivision(int $divisionId, ?Carbon $on = null): ?int
    {
        $division = $this->divisionById($divisionId);

        return $this->effectiveHeadStaffId($division, $on);
    }

    /**
     * @return array{staff_id:?int,name:?string,division:?array<string,mixed>}
     */
    public function resolveForDivision(int $divisionId, ?Carbon $on = null): array
    {
        $division = $this->divisionById($divisionId);
        $staffId = $this->effectiveHeadStaffId($division, $on);
        $name = null;
        if ($division !== null) {
            $name = isset($division['name']) ? (string) $division['name'] : null;
        }

        return [
            'staff_id' => $staffId,
            'name' => $name,
            'division' => $division,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function divisionById(int $divisionId): ?array
    {
        if ($divisionId <= 0) {
            return null;
        }

        $bundle = Cache::get(self::CACHE_KEY);
        if (! is_array($bundle) || empty($bundle['divisions']) || ! is_array($bundle['divisions'])) {
            return null;
        }

        foreach ($bundle['divisions'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $first = $row;
            if (array_key_exists('division_id', $first) && ! array_key_exists('id', $first)) {
                $row = StaffShareNormalizer::division($row);
            }
            $id = (int) ($row['id'] ?? $row['division_id'] ?? 0);
            if ($id === $divisionId) {
                return $row;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
