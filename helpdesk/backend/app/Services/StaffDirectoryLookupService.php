<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Support\StaffShareNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves requester identity and org fields from cached Staff Share reference data
 * (populated by directory sync / reference-data endpoints).
 */
class StaffDirectoryLookupService
{
    /** @var array<int, string>|null */
    private ?array $dutyStationMapCache = null;

    /**
     * @return array{name:string,work_email:string,division_id:?int,directorate_id:?int,duty_station_name:?string}|null
     */
    public function resolveByStaffId(int $staffId): ?array
    {
        if ($staffId < 1) {
            return null;
        }

        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);
        $cacheKey = 'helpdesk_reference_staff_v1_'.$limit;
        $staffRows = Cache::get($cacheKey);
        if (! is_array($staffRows)) {
            return null;
        }

        $divisions = $this->divisionsKeyedById();

        foreach ($staffRows as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $row = StaffShareNormalizer::staff($raw);
            if ($row['id'] !== $staffId) {
                continue;
            }
            $div = $divisions->get((int) ($row['division_id'] ?? 0));

            return [
                'name' => $row['name'],
                'work_email' => trim((string) ($row['work_email'] ?? '')),
                'division_id' => $row['division_id'],
                'directorate_id' => $div['directorate_id'] ?? null,
                'duty_station_name' => $row['duty_station_name'],
            ];
        }

        return null;
    }

    /**
     * Duty station label for routing (Staff directory first, then Helpdesk profile sync field).
     */
    public function dutyStationForStaffId(int $staffId): ?string
    {
        if ($staffId < 1) {
            return null;
        }

        $fromDirectory = $this->dutyStationMapByStaffId()[$staffId] ?? null;
        if ($fromDirectory !== null && $fromDirectory !== '') {
            return $fromDirectory;
        }

        $p = HelpdeskProfile::query()->where('staff_id', $staffId)->first();
        $ds = $p?->duty_station ? trim((string) $p->duty_station) : '';

        return $ds !== '' ? $ds : null;
    }

    /**
     * @return array<int, string> staff_id => duty station name
     */
    public function dutyStationMapByStaffId(): array
    {
        if ($this->dutyStationMapCache !== null) {
            return $this->dutyStationMapCache;
        }

        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);
        $cacheKey = 'helpdesk_reference_staff_v1_'.$limit;
        $staffRows = Cache::get($cacheKey);
        if (! is_array($staffRows)) {
            return [];
        }

        $map = [];
        foreach ($staffRows as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $row = StaffShareNormalizer::staff($raw);
            $id = (int) ($row['id'] ?? 0);
            $name = $row['duty_station_name'] ?? null;
            if ($id > 0 && is_string($name) && trim($name) !== '') {
                $map[$id] = trim($name);
            }
        }

        return $this->dutyStationMapCache = $map;
    }

    /**
     * Display label for a requester's duty station (never null).
     */
    public function dutyStationLabelForStaffId(?int $staffId): string
    {
        if ($staffId === null || $staffId < 1) {
            return 'Unspecified';
        }

        return $this->dutyStationForStaffId($staffId) ?? 'Unspecified';
    }

    /**
     * @return Collection<int, array{id:int,name:string,short_name:?string,directorate_id:?int}>
     */
    private function divisionsKeyedById(): Collection
    {
        $bundle = Cache::get('helpdesk_reference_bundle_v1');
        if (! is_array($bundle) || empty($bundle['divisions'])) {
            return collect();
        }

        /** @var list<array<string, mixed>> $rawDivs */
        $rawDivs = $bundle['divisions'];
        $first = $rawDivs[0] ?? null;
        if (is_array($first) && array_key_exists('division_id', $first)) {
            $divisions = array_map(fn (array $r) => StaffShareNormalizer::division($r), $rawDivs);

            return collect($divisions)->keyBy('id');
        }

        return collect($rawDivs)->keyBy('id');
    }
}
