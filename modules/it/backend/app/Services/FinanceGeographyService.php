<?php

namespace App\Services;

use App\Support\RrSettingsBag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Geography standard lists from Staff Portal tables (shared staff DB):
 * regions, nationalities (countries), duty_stations (locations).
 */
final class FinanceGeographyService
{
    public const KEY_REGION_ALIASES = 'finance_geo_region_aliases';

    /** Workbook region labels → Staff Portal region_name fragments. */
    private const REGION_HINTS = [
        'East Africa' => ['AU_Eastern', 'Eastern'],
        'Eastern Africa' => ['AU_Eastern', 'Eastern'],
        'Southern Africa' => ['AU_Southern', 'Southern'],
        'Central Africa' => ['AU_Central', 'Central'],
        'North Africa' => ['AU_Northern', 'Northern'],
        'Northern Africa' => ['AU_Northern', 'Northern'],
        'West Africa' => ['AU_Western', 'Western'],
        'Western Africa' => ['AU_Western', 'Western'],
        'East/Central Africa' => ['AU_Central', 'Central'],
        'AU_Eastern' => ['AU_Eastern'],
        'AU_Southern' => ['AU_Southern'],
        'AU_Central' => ['AU_Central'],
        'AU_Northern' => ['AU_Northern'],
        'AU_Western' => ['AU_Western'],
    ];

    public function __construct(private readonly RrSettingsBag $bag = new RrSettingsBag) {}

    /**
     * @return array{
     *   regions: list<array<string, mixed>>,
     *   countries: list<array<string, mixed>>,
     *   locations: list<array<string, mixed>>,
     *   region_aliases: list<array{label: string, region_id: int|null, match_source: string}>
     * }
     */
    public function snapshot(): array
    {
        return [
            'regions' => $this->regions(),
            'countries' => $this->countries(),
            'locations' => $this->locations(),
            'region_aliases' => $this->regionAliases(),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function regions(): array
    {
        if (! Schema::hasTable('regions')) {
            return [];
        }
        $idCol = Schema::hasColumn('regions', 'region_id') ? 'region_id' : 'id';
        $nameCol = Schema::hasColumn('regions', 'region_name') ? 'region_name' : 'name';

        return DB::table('regions')
            ->orderBy($nameCol)
            ->get([$idCol.' as id', $nameCol.' as name'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => trim((string) $r->name)])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, region_id: int|null, iso2: string|null}>
     */
    public function countries(): array
    {
        if (! Schema::hasTable('nationalities')) {
            return [];
        }

        return DB::table('nationalities')
            ->orderBy('nationality')
            ->get(['nationality_id', 'nationality', 'region_id', 'iso2'])
            ->map(fn ($r) => [
                'id' => (int) $r->nationality_id,
                'name' => trim((string) $r->nationality),
                'region_id' => $r->region_id !== null ? (int) $r->region_id : null,
                'iso2' => $r->iso2 ? trim((string) $r->iso2) : null,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, country: string|null, country_iso2: string|null, city: string|null}>
     */
    public function locations(): array
    {
        if (! Schema::hasTable('duty_stations')) {
            return [];
        }

        $cols = ['duty_station_id', 'duty_station_name', 'country', 'city'];
        $hasIso = Schema::hasColumn('duty_stations', 'country_iso2');
        if ($hasIso) {
            $cols[] = 'country_iso2';
        }

        return DB::table('duty_stations')
            ->orderBy('duty_station_name')
            ->get($cols)
            ->map(fn ($r) => [
                'id' => (int) $r->duty_station_id,
                'name' => trim((string) $r->duty_station_name),
                'country' => $r->country ? trim((string) $r->country) : null,
                'country_iso2' => ($hasIso && ! empty($r->country_iso2))
                    ? trim((string) $r->country_iso2)
                    : null,
                'city' => $r->city ? trim((string) $r->city) : null,
            ])
            ->all();
    }

    /**
     * @return list<array{label: string, region_id: int|null, match_source: string}>
     */
    public function regionAliases(): array
    {
        $stored = $this->decodeList($this->bag->get(self::KEY_REGION_ALIASES));
        if ($stored !== []) {
            return $stored;
        }

        return $this->suggestRegionAliases();
    }

    /**
     * @param  list<array{label?: string, region_id?: int|null, match_source?: string}>  $rows
     * @return list<array{label: string, region_id: int|null, match_source: string}>
     */
    public function saveRegionAliases(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $id = isset($row['region_id']) && $row['region_id'] !== '' && $row['region_id'] !== null
                ? (int) $row['region_id']
                : null;
            $clean[] = [
                'label' => $label,
                'region_id' => $id,
                'match_source' => (string) ($row['match_source'] ?? ($id ? 'manual' : 'unmatched')),
            ];
        }
        $this->bag->set(self::KEY_REGION_ALIASES, json_encode($clean, JSON_THROW_ON_ERROR));

        return $clean;
    }

    public function resolveRegionId(?string $label): ?int
    {
        $label = trim((string) $label);
        if ($label === '') {
            return null;
        }
        foreach ($this->regionAliases() as $row) {
            if (strcasecmp((string) $row['label'], $label) === 0 && ! empty($row['region_id'])) {
                return (int) $row['region_id'];
            }
        }
        foreach ($this->regions() as $r) {
            if (strcasecmp($r['name'], $label) === 0) {
                return $r['id'];
            }
        }

        return null;
    }

    public function resolveCountryId(?string $label): ?int
    {
        $label = trim((string) $label);
        if ($label === '') {
            return null;
        }
        $aliases = [
            'DRC' => ['DR Congo', 'Democratic Republic of Congo', 'Congo DRC', 'DRC'],
            'Egypt' => ['Egypt'],
            'Congo' => ['DR Congo', 'Congo Republic'],
        ];
        $needles = $aliases[$label] ?? [$label];
        // Prefer exact name matches before fuzzy contains.
        foreach ($this->countries() as $c) {
            foreach ($needles as $needle) {
                if (strcasecmp($c['name'], $needle) === 0) {
                    return $c['id'];
                }
            }
        }
        foreach ($this->countries() as $c) {
            foreach ($needles as $needle) {
                if (stripos($c['name'], $needle) !== false || stripos($needle, $c['name']) !== false) {
                    return $c['id'];
                }
            }
        }

        return null;
    }

    public function resolveLocationId(?string $countryLabel, ?string $locationLabel = null): ?int
    {
        $country = trim((string) $countryLabel);
        $location = trim((string) $locationLabel);
        $locs = $this->locations();
        if ($location !== '' && strcasecmp($location, 'Africa') !== 0) {
            foreach ($locs as $l) {
                if (strcasecmp($l['name'], $location) === 0) {
                    return $l['id'];
                }
            }
        }
        if ($country === '') {
            return null;
        }
        $countryAliases = [
            'DRC' => ['DRC', 'DR Congo', 'Democratic Republic of Congo'],
            'DR Congo' => ['DRC', 'DR Congo'],
            'Egypt' => ['Egypt'],
            'South Africa' => ['South Africa'],
        ];
        $needles = $countryAliases[$country] ?? [$country];
        foreach ($locs as $l) {
            $locCountry = (string) ($l['country'] ?? '');
            $locName = (string) ($l['name'] ?? '');
            foreach ($needles as $needle) {
                if (
                    ($locCountry !== '' && strcasecmp($locCountry, $needle) === 0)
                    || ($locName !== '' && strcasecmp($locName, $needle) === 0)
                ) {
                    return $l['id'];
                }
            }
        }

        return null;
    }

    /**
     * Ensure AU regions exist — only inserts when the regions table is empty.
     *
     * @return array{seeded: bool, count: int}
     */
    public function seedRegionsIfEmpty(): array
    {
        if (! Schema::hasTable('regions')) {
            return ['seeded' => false, 'count' => 0];
        }
        if (DB::table('regions')->exists()) {
            return ['seeded' => false, 'count' => DB::table('regions')->count()];
        }
        $idCol = Schema::hasColumn('regions', 'region_id') ? 'region_id' : 'id';
        $nameCol = Schema::hasColumn('regions', 'region_name') ? 'region_name' : 'name';
        $rows = [
            [$idCol => 1, $nameCol => 'AU_Central'],
            [$idCol => 2, $nameCol => 'AU_Eastern'],
            [$idCol => 3, $nameCol => 'AU_Northern'],
            [$idCol => 4, $nameCol => 'AU_Southern'],
            [$idCol => 5, $nameCol => 'AU_Western'],
        ];
        DB::table('regions')->insert($rows);

        return ['seeded' => true, 'count' => 5];
    }

    /**
     * Persist suggested aliases when none stored yet.
     */
    public function ensureRegionAliases(): void
    {
        $stored = $this->decodeList($this->bag->get(self::KEY_REGION_ALIASES));
        if ($stored !== []) {
            return;
        }
        $this->saveRegionAliases($this->suggestRegionAliases());
    }

    /**
     * @return list<array{label: string, region_id: int|null, match_source: string}>
     */
    private function suggestRegionAliases(): array
    {
        $regions = $this->regions();
        $out = [];
        foreach (self::REGION_HINTS as $label => $needles) {
            $match = null;
            foreach ($regions as $r) {
                foreach ($needles as $needle) {
                    if (strcasecmp($r['name'], $needle) === 0 || stripos($r['name'], $needle) !== false) {
                        $match = $r;
                        break 2;
                    }
                }
            }
            $out[] = [
                'label' => $label,
                'region_id' => $match['id'] ?? null,
                'match_source' => $match ? 'auto' : 'unmatched',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeList(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? array_values($decoded) : [];
        } catch (Throwable) {
            return [];
        }
    }
}
