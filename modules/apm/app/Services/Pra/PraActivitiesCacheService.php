<?php

namespace App\Services\Pra;

use App\Models\Division;
use App\Models\PraOrgUnitMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Flattened PRA specific activities (+ outcome areas) cached in Redis for matrix creation.
 */
class PraActivitiesCacheService
{
    public const CACHE_PREFIX = 'apm:pra:activities:fy:';

    public const TTL_SECONDS = 3600;

    public function __construct(
        protected PraClient $client,
        protected PraSettingsService $settings,
        protected PraOrgUnitMappingService $mappings,
    ) {}

    /**
     * Refresh Redis cache for a fiscal year (all divisions from workplan API).
     *
     * @return array{fiscal_year: int, activities: int, cached_at: string}
     */
    public function refresh(?int $fiscalYear = null): array
    {
        $year = $fiscalYear
            ?? (int) ($this->settings->resolved()['fiscal_year'] ?: now()->year);

        $payload = $this->client->fetchWorkplan(null, $year);
        $activities = $this->flatten($payload['data'] ?? [], $year);
        $cachedAt = now()->toIso8601String();

        $this->cachePut($this->key($year), [
            'fiscal_year' => $year,
            'cached_at' => $cachedAt,
            'activities' => $activities,
        ], self::TTL_SECONDS);

        Log::info('pra.activities_cache.refreshed', [
            'fiscal_year' => $year,
            'count' => count($activities),
        ]);

        return [
            'fiscal_year' => $year,
            'activities' => count($activities),
            'cached_at' => $cachedAt,
        ];
    }

    /**
     * All PRA activities for a local APM division (no date/quarter exclusion).
     * When $quarter is set, each row includes matches_quarter for client-side filtering.
     *
     * @return list<array<string, mixed>>
     */
    public function activitiesForDivision(int $divisionId, int $year, ?string $quarter = null, bool $refreshIfMissing = true): array
    {
        $bundle = $this->bundle($year, $refreshIfMissing);
        $praCodes = $this->praCodesForLocalDivision($divisionId);
        if ($praCodes === []) {
            return [];
        }

        $codeSet = array_fill_keys($praCodes, true);
        $q = $quarter !== null && $quarter !== '' ? strtoupper(trim($quarter)) : null;
        $out = [];
        foreach ($bundle['activities'] as $row) {
            $divCode = strtoupper((string) ($row['division_code'] ?? ''));
            if ($divCode === '' || ! isset($codeSet[$divCode])) {
                continue;
            }
            if ($q !== null) {
                $row['matches_quarter'] = $this->matchesQuarter($row, $year, $q);
                $row['filter_quarter'] = $q;
            }
            $out[] = $row;
        }

        usort($out, fn ($a, $b) => strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? '')));

        return $out;
    }

    /**
     * @deprecated Prefer activitiesForDivision(); kept for callers that still pass a quarter.
     *
     * @return list<array<string, mixed>>
     */
    public function activitiesForDivisionQuarter(int $divisionId, int $year, string $quarter, bool $refreshIfMissing = true): array
    {
        return $this->activitiesForDivision($divisionId, $year, $quarter, $refreshIfMissing);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActivity(int $praActivityId, ?int $fiscalYear = null): ?array
    {
        $year = $fiscalYear
            ?? (int) ($this->settings->resolved()['fiscal_year'] ?: now()->year);
        $bundle = $this->bundle($year, true);
        foreach ($bundle['activities'] as $row) {
            if ((int) ($row['pra_activity_id'] ?? 0) === $praActivityId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * PRA division codes that map to a local APM division.
     *
     * @return list<string>
     */
    public function praCodesForLocalDivision(int $divisionId): array
    {
        $codes = [];

        if (Schema::hasTable('pra_org_unit_mappings')) {
            foreach (PraOrgUnitMapping::query()->where('local_division_id', $divisionId)->get() as $row) {
                $codes[] = strtoupper(trim((string) $row->pra_code));
            }
        }

        $division = Division::query()->find($divisionId);
        $short = $division ? strtoupper(trim((string) ($division->division_short_name ?? ''))) : '';
        if ($short !== '') {
            $codes[] = $short;
            // Reverse aliases: local short → PRA codes that alias to it
            foreach ($this->settings->resolved()['division_aliases'] as $praCode => $localShort) {
                if (strtoupper((string) $localShort) === $short) {
                    $codes[] = strtoupper((string) $praCode);
                }
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function quarterBounds(int $year, string $quarter): array
    {
        $q = strtoupper(trim($quarter));
        $startMonth = match ($q) {
            'Q2' => 4,
            'Q3' => 7,
            'Q4' => 10,
            default => 1,
        };

        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = (clone $start)->addMonths(3)->subDay()->endOfDay();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function matchesQuarter(array $row, int $year, string $quarter): bool
    {
        $bounds = $this->quarterBounds($year, $quarter);
        $start = $this->parseDate($row['start_date'] ?? null);
        $end = $this->parseDate($row['end_date'] ?? null);

        if ($start || $end) {
            $actStart = $start ?? Carbon::create($year, 1, 1)->startOfDay();
            $actEnd = $end ?? Carbon::create($year, 12, 31)->endOfDay();

            return $actStart->lte($bounds['end']) && $actEnd->gte($bounds['start']);
        }

        $qKey = strtolower($quarter).'_budget';
        $budget = (float) ($row[$qKey] ?? 0);
        if ($budget > 0) {
            return true;
        }

        // No dates / quarter budgets — still show so focal persons can pick
        $anyBudget = ((float) ($row['q1_budget'] ?? 0))
            + ((float) ($row['q2_budget'] ?? 0))
            + ((float) ($row['q3_budget'] ?? 0))
            + ((float) ($row['q4_budget'] ?? 0));

        return $anyBudget <= 0;
    }

    /**
     * @return array{fiscal_year: int, cached_at: ?string, activities: list<array<string, mixed>>}
     */
    protected function bundle(int $year, bool $refreshIfMissing): array
    {
        $cached = $this->cacheGet($this->key($year));
        if (is_array($cached) && isset($cached['activities']) && is_array($cached['activities'])) {
            return $cached;
        }

        if (! $refreshIfMissing) {
            return ['fiscal_year' => $year, 'cached_at' => null, 'activities' => []];
        }

        if (! $this->settings->isConfigured()) {
            return ['fiscal_year' => $year, 'cached_at' => null, 'activities' => []];
        }

        try {
            $this->refresh($year);
        } catch (Throwable $e) {
            Log::error('pra.activities_cache.refresh_failed', ['error' => $e->getMessage()]);

            return ['fiscal_year' => $year, 'cached_at' => null, 'activities' => []];
        }

        $cached = $this->cacheGet($this->key($year));

        return is_array($cached) ? $cached : ['fiscal_year' => $year, 'cached_at' => null, 'activities' => []];
    }

    /**
     * @param  list<array<string, mixed>>  $indicators
     * @return list<array<string, mixed>>
     */
    protected function flatten(array $indicators, int $year): array
    {
        $out = [];
        foreach ($indicators as $indicator) {
            if (! is_array($indicator)) {
                continue;
            }
            $outcome = trim((string) ($indicator['title'] ?? ''));
            $indicatorCode = (string) ($indicator['code'] ?? '');
            $indicatorId = (int) ($indicator['id'] ?? 0);
            $acts = is_array($indicator['specific_activities'] ?? null)
                ? $indicator['specific_activities']
                : [];

            foreach ($acts as $act) {
                if (! is_array($act)) {
                    continue;
                }
                $praId = (int) ($act['id'] ?? 0);
                if ($praId < 1) {
                    continue;
                }
                $division = is_array($act['division'] ?? null) ? $act['division'] : [];
                $broad = is_array($act['broad_activity'] ?? null) ? $act['broad_activity'] : [];
                $outcomeArea = trim((string) ($broad['title'] ?? '')) ?: $outcome;

                $out[] = [
                    'pra_activity_id' => $praId,
                    'code' => (string) ($act['code'] ?? ''),
                    'title' => trim((string) ($act['title'] ?? '')),
                    'status' => (string) ($act['status'] ?? ''),
                    'division_code' => strtoupper(trim((string) ($division['code'] ?? ''))),
                    'division_name' => (string) ($division['name'] ?? ''),
                    'outcome_area' => $outcomeArea,
                    'indicator_id' => $indicatorId,
                    'indicator_code' => $indicatorCode,
                    'indicator_title' => $outcome,
                    'broad_activity_id' => (int) ($broad['id'] ?? 0) ?: null,
                    'broad_activity_code' => (string) ($broad['code'] ?? ''),
                    'start_date' => $act['start_date'] ?? null,
                    'end_date' => $act['end_date'] ?? null,
                    'q1_budget' => (float) ($act['q1_budget'] ?? 0),
                    'q2_budget' => (float) ($act['q2_budget'] ?? 0),
                    'q3_budget' => (float) ($act['q3_budget'] ?? 0),
                    'q4_budget' => (float) ($act['q4_budget'] ?? 0),
                    'budget_main' => (float) ($act['budget_main'] ?? 0),
                    'budget_2027' => (float) ($act['budget_2027'] ?? 0),
                    'budget_2028' => (float) ($act['budget_2028'] ?? 0),
                    'primary_funding_source' => trim((string) ($act['primary_funding_source'] ?? '')),
                    'other_funding_source' => trim((string) ($act['other_funding_source'] ?? '')),
                    'location' => $act['location'] ?? null,
                    'focal_person' => $act['focal_person'] ?? null,
                    'fiscal_year' => $year,
                ];
            }
        }

        return $out;
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function key(int $year): string
    {
        return self::CACHE_PREFIX.$year;
    }

    protected function cachePut(string $key, mixed $value, int $ttl): void
    {
        try {
            Cache::store('redis')->put($key, $value, $ttl);
        } catch (Throwable $e) {
            Log::warning('pra.activities_cache.redis_unavailable', ['error' => $e->getMessage()]);
            Cache::store(config('cache.default', 'file'))->put($key, $value, $ttl);
        }
    }

    protected function cacheGet(string $key): mixed
    {
        try {
            return Cache::store('redis')->get($key);
        } catch (Throwable $e) {
            Log::warning('pra.activities_cache.redis_unavailable', ['error' => $e->getMessage()]);

            return Cache::store(config('cache.default', 'file'))->get($key);
        }
    }
}
