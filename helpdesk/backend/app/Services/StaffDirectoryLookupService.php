<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
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

        $this->ensureStaffCacheWarm();

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
     * @return array{staff_id:int,name:string,work_email:string,division_id:?int,directorate_id:?int,duty_station_name:?string}|null
     */
    public function resolveByWorkEmail(string $email): ?array
    {
        $needle = strtolower(trim($email));
        if ($needle === '' || ! filter_var($needle, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $this->ensureStaffCacheWarm();

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
            $workEmail = strtolower(trim((string) ($row['work_email'] ?? '')));
            if ($workEmail === '' || $workEmail !== $needle) {
                continue;
            }
            $staffId = (int) ($row['id'] ?? 0);
            if ($staffId < 1) {
                continue;
            }
            $div = $divisions->get((int) ($row['division_id'] ?? 0));

            return [
                'staff_id' => $staffId,
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

        $this->ensureStaffCacheWarm();

        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);
        $cacheKey = 'helpdesk_reference_staff_v1_'.$limit;
        $staffRows = Cache::get($cacheKey);
        if (! is_array($staffRows)) {
            return $this->dutyStationMapCache = [];
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
     * Latest non-empty requester_duty_station stored on tickets (new tickets persist this on create).
     *
     * @return array<int, string>
     */
    public function dutyStationMetaFromTickets(): array
    {
        $out = [];
        HelpdeskTicket::query()
            ->where('requester_staff_id', '>', 0)
            ->whereNotNull('meta')
            ->orderByDesc('id')
            ->select(['requester_staff_id', 'meta'])
            ->chunk(500, function ($rows) use (&$out) {
                foreach ($rows as $ticket) {
                    $staffId = (int) $ticket->requester_staff_id;
                    if ($staffId < 1 || isset($out[$staffId])) {
                        continue;
                    }
                    $meta = is_array($ticket->meta) ? $ticket->meta : [];
                    $station = trim((string) ($meta['requester_duty_station'] ?? ''));
                    if ($station !== '') {
                        $out[$staffId] = $station;
                    }
                }
            });

        return $out;
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
     * Resolve duty station for screen/reports: ticket meta, then staff directory, then profile.
     */
    public function dutyStationLabelForStaffIdWithMeta(?int $staffId, array $metaByStaff): string
    {
        if ($staffId !== null && $staffId > 0) {
            $fromMeta = trim((string) ($metaByStaff[$staffId] ?? ''));
            if ($fromMeta !== '') {
                return $fromMeta;
            }
        }

        return $this->dutyStationLabelForStaffId($staffId);
    }

    /**
     * Populate staff reference cache on demand (e.g. public screen before directory sync was run).
     */
    public function ensureStaffCacheWarm(): void
    {
        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);
        $cacheKey = 'helpdesk_reference_staff_v1_'.$limit;
        $existing = Cache::get($cacheKey);
        if (is_array($existing) && $existing !== []) {
            return;
        }

        $client = app(StaffPortalReferenceClient::class);
        if (! $client->isConfigured()) {
            return;
        }

        try {
            app(ReferenceDataSyncService::class)->warmCaches($client);
        } catch (\Throwable $e) {
            report($e);
        }
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
