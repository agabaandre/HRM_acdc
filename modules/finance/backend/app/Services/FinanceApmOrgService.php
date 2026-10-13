<?php

namespace App\Services;

use App\Support\RrSettingsBag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fetch APM divisions/funders and store Finance-side alias mappings (PRA-style).
 */
final class FinanceApmOrgService
{
    public const KEY_DIVISION_ALIASES = 'finance_apm_division_aliases';

    public const KEY_FUNDER_ALIASES = 'finance_apm_funder_aliases';

    /** Portfolio workbook BU labels (Intramural sheet) → likely APM short codes. */
    private const BU_HINTS = [
        'Administration' => ['DA'],
        'Policy and Health Diplomacy' => ['PHD'],
        'Management Information System' => ['DHIS'],
        'Knowledge Management' => ['KM'],
        'Project Inplementation Unit' => ['PIU'],
        'Project Implementation Unit' => ['PIU'],
        'LAB' => ['LAB'],
        'Office of the Director General' => ['ODG'],
        'Division of Internal Oversight' => ['OIO'],
        'Surveillance and Disease Intelligence' => ['SDI'],
        'Emergency Prepararedness and Response' => ['EPR'],
        'Emergency Preparedness and Response' => ['EPR'],
        'Human Resources Management & Development' => ['HRM'],
        'Communication' => ['DCPI'],
        'Supply Chain' => ['SCM'],
        'SRCC' => ['SRCC'],
        'Finance Directorate' => ['DFIN'],
        'ERCC' => ['ERCC'],
        'NRCC' => ['NRCC'],
        'Health Economic & Finance' => ['HEF'],
        'Primary Health Care' => ['CPH', 'CHSHP'],
        'PHI' => ['PHIR'],
        'Legal' => ['LADS'],
        'CRCC' => ['CRCC'],
        'Digital Health, Health Information Systems and Data Gov' => ['DHIS'],
        'Health Workforce Development' => ['HRM'],
        'R&D and Clinical Trials' => ['RD&CT'],
        'Local Manufacturing' => ['LMHC'],
        'Planning Reporting & Accountability' => ['PRA'],
        'Partnerships and International Cooperation' => ['DERSE'],
        'Youth Division' => ['YD'],
        'WRCC' => ['WRCC'],
    ];

    /** Portfolio / indirect-cost funder labels → APM funder name fragments. */
    private const FUNDER_HINTS = [
        'World Bank' => ['World Bank'],
        'WB Support Africa CDC' => ['World Bank'],
        'Gavi' => ['Gavi'],
        'Africa CDC-Gavi Joint Action Plan' => ['Gavi'],
        'CEPI' => ['CEPI'],
        'Africa CDC - CEPI JAP' => ['CEPI'],
        'CEPI – Africa CDC Funding Agreement' => ['CEPI'],
        'Susan Thompson Buffet Foundation (STBF)' => ['Susan Thompson', 'STBF'],
        'The Susan Thompson Buffett Foundati' => ['Susan Thompson', 'STBF'],
        'European CDC' => ['ECDC'],
        'European Centre for Disease Prevent' => ['ECDC'],
        'KDCA' => ['KDCA'],
        'FCDO' => ['FCDO'],
        'Foreign, Commonwealth & Development' => ['FCDO'],
        'World Diabetes Fund' => ['WDF', 'World Diabetes'],
        'The World Diabetes Foundation' => ['WDF', 'World Diabetes'],
        'US CDC Support to AfCDC' => ['US CDC'],
        'PMS US Support to Africa CDC' => ['US CDC'],
        'Member State' => ['Member State'],
        'AfDB' => ['AfDB'],
        'Mission of Japan' => ['Japan', 'JICA'],
        'European Union (HERA' => ['HERA', 'European Union'],
        'WASTEWISE EU HERA' => ['HERA', 'WASTEWISE'],
        'EDCTP' => ['EDCTP'],
        'Gates Foundation' => ['Gates'],
        'Global Fund' => ['Global Fund'],
        'UNICEF' => ['UNICEF'],
        'UNAIDS' => ['UNAIDS'],
        'Wellcome Trust' => ['Wellcome'],
        'MasterCard Foundation' => ['MasterCard', 'Mastercard'],
        'KOICA' => ['KOICA'],
    ];

    public function __construct(private readonly RrSettingsBag $bag = new RrSettingsBag) {}

    /**
     * @return list<array{division_id: int, division_name: string, division_short_name: string}>
     */
    public function apmDivisions(): array
    {
        if (! $this->apmReady() || ! Schema::connection('apm')->hasTable('divisions')) {
            return [];
        }
        $idCol = Schema::connection('apm')->hasColumn('divisions', 'id') ? 'id' : 'division_id';

        return DB::connection('apm')
            ->table('divisions')
            ->select([$idCol.' as division_id', 'division_name', 'division_short_name'])
            ->orderBy('division_name')
            ->get()
            ->map(fn ($r) => [
                'division_id' => (int) $r->division_id,
                'division_name' => trim((string) $r->division_name),
                'division_short_name' => trim((string) ($r->division_short_name ?? '')),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, is_active: bool}>
     */
    public function apmFunders(bool $activeOnly = true): array
    {
        if (! $this->apmReady() || ! Schema::connection('apm')->hasTable('funders')) {
            return [];
        }
        $q = DB::connection('apm')->table('funders')->select(['id', 'name', 'is_active']);
        if ($activeOnly && Schema::connection('apm')->hasColumn('funders', 'is_active')) {
            $q->where('is_active', 1);
        }
        if (Schema::connection('apm')->hasColumn('funders', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        return $q->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => trim((string) $r->name),
                'is_active' => (bool) ($r->is_active ?? true),
            ])
            ->all();
    }

    /**
     * @return array{
     *   divisions: list<array<string, mixed>>,
     *   funders: list<array<string, mixed>>,
     *   division_aliases: list<array{label: string, division_id: int|null, match_source: string}>,
     *   funder_aliases: list<array{label: string, funder_id: int|null, funder_name: string|null, match_source: string}>,
     *   apm_ready: bool
     * }
     */
    public function snapshot(): array
    {
        $divisions = $this->apmDivisions();
        $funders = $this->apmFunders(false);
        $divAliases = $this->loadDivisionAliases();
        $funderAliases = $this->loadFunderAliases();

        if ($divAliases === []) {
            $divAliases = $this->suggestDivisionAliases($divisions);
        }
        if ($funderAliases === []) {
            $funderAliases = $this->suggestFunderAliases($funders);
        }

        return [
            'apm_ready' => $this->apmReady(),
            'apm_database' => $this->apmDatabaseName(),
            'divisions' => $divisions,
            'funders' => $funders,
            'division_aliases' => $divAliases,
            'funder_aliases' => $funderAliases,
        ];
    }

    /**
     * Re-fetch APM org lists and refresh aliases with auto suggestions.
     * Manual funder/division picks are kept; unmatched and previous auto matches are rematched by name.
     *
     * @return array<string, mixed>
     */
    public function fetchFromApm(): array
    {
        $divisions = $this->apmDivisions();
        $funders = $this->apmFunders(false);
        if ($divisions === [] && $funders === []) {
            throw new \RuntimeException('Could not read APM divisions/funders. Check APM_DB_DATABASE connection.');
        }

        $existingDiv = $this->loadDivisionAliases();
        $existingFund = $this->loadFunderAliases();
        $suggestedDiv = $this->suggestDivisionAliases($divisions);
        $suggestedFund = $this->suggestFunderAliases($funders);

        $mergedDiv = $this->mergeAliases($existingDiv, $suggestedDiv, 'division_id', rematchAuto: true);
        $mergedFund = $this->mergeAliases($existingFund, $suggestedFund, 'funder_id', rematchAuto: true);

        $this->bag->set(self::KEY_DIVISION_ALIASES, json_encode($mergedDiv, JSON_THROW_ON_ERROR));
        $this->bag->set(self::KEY_FUNDER_ALIASES, json_encode($mergedFund, JSON_THROW_ON_ERROR));

        return $this->snapshot();
    }

    /**
     * @param  array{division_aliases?: list<array<string, mixed>>, funder_aliases?: list<array<string, mixed>>}  $input
     */
    public function save(array $input): array
    {
        if (array_key_exists('division_aliases', $input) && is_array($input['division_aliases'])) {
            $clean = [];
            foreach ($input['division_aliases'] as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $divId = isset($row['division_id']) && $row['division_id'] !== '' && $row['division_id'] !== null
                    ? (int) $row['division_id']
                    : null;
                $clean[] = [
                    'label' => $label,
                    'division_id' => $divId,
                    'match_source' => (string) ($row['match_source'] ?? ($divId ? 'manual' : 'unmatched')),
                ];
            }
            $this->bag->set(self::KEY_DIVISION_ALIASES, json_encode($clean, JSON_THROW_ON_ERROR));
        }

        if (array_key_exists('funder_aliases', $input) && is_array($input['funder_aliases'])) {
            $fundersById = [];
            foreach ($this->apmFunders(false) as $f) {
                $fundersById[$f['id']] = $f['name'];
            }
            $clean = [];
            foreach ($input['funder_aliases'] as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $fid = isset($row['funder_id']) && $row['funder_id'] !== '' && $row['funder_id'] !== null
                    ? (int) $row['funder_id']
                    : null;
                $clean[] = [
                    'label' => $label,
                    'funder_id' => $fid,
                    'funder_name' => $fid ? ($fundersById[$fid] ?? ($row['funder_name'] ?? null)) : null,
                    'match_source' => (string) ($row['match_source'] ?? ($fid ? 'manual' : 'unmatched')),
                ];
            }
            $this->bag->set(self::KEY_FUNDER_ALIASES, json_encode($clean, JSON_THROW_ON_ERROR));
        }

        return $this->snapshot();
    }

    public function resolveFunderId(?string $label): ?int
    {
        $label = trim((string) $label);
        if ($label === '') {
            return null;
        }
        foreach ($this->loadFunderAliases() as $row) {
            if (strcasecmp((string) $row['label'], $label) === 0 && ! empty($row['funder_id'])) {
                return (int) $row['funder_id'];
            }
        }
        foreach ($this->apmFunders(false) as $f) {
            if (strcasecmp($f['name'], $label) === 0) {
                return $f['id'];
            }
        }

        return null;
    }

    /**
     * @return list<array{label: string, division_id: int|null, match_source: string}>
     */
    private function loadDivisionAliases(): array
    {
        return $this->decodeList($this->bag->get(self::KEY_DIVISION_ALIASES));
    }

    /**
     * @return list<array{label: string, funder_id: int|null, funder_name: string|null, match_source: string}>
     */
    private function loadFunderAliases(): array
    {
        return $this->decodeList($this->bag->get(self::KEY_FUNDER_ALIASES));
    }

    /**
     * @param  list<array{division_id: int, division_name: string, division_short_name: string}>  $divisions
     * @return list<array{label: string, division_id: int|null, match_source: string}>
     */
    private function suggestDivisionAliases(array $divisions): array
    {
        $byShort = [];
        foreach ($divisions as $d) {
            $short = strtoupper(trim($d['division_short_name']));
            if ($short !== '') {
                $byShort[$short] = $d['division_id'];
            }
        }
        $out = [];
        foreach (self::BU_HINTS as $label => $shorts) {
            $id = null;
            $source = 'unmatched';
            foreach ($shorts as $short) {
                $key = strtoupper($short);
                if (isset($byShort[$key])) {
                    $id = $byShort[$key];
                    $source = 'auto';
                    break;
                }
            }
            $out[] = ['label' => $label, 'division_id' => $id, 'match_source' => $source];
        }

        return $out;
    }

    /**
     * @param  list<array{id: int, name: string}>  $funders
     * @return list<array{label: string, funder_id: int|null, funder_name: string|null, match_source: string}>
     */
    private function suggestFunderAliases(array $funders): array
    {
        $out = [];
        foreach (self::FUNDER_HINTS as $label => $needles) {
            $match = $this->matchFunderByName($label, $funders, $needles);
            $out[] = [
                'label' => $label,
                'funder_id' => $match['id'] ?? null,
                'funder_name' => $match['name'] ?? null,
                'match_source' => $match ? 'auto' : 'unmatched',
            ];
        }

        return $out;
    }

    /**
     * Prefer exact / normalised name, then containment, then explicit hint needles against APM names only.
     *
     * @param  list<array{id: int, name: string}>  $funders
     * @param  list<string>  $needles
     * @return array{id: int, name: string}|null
     */
    private function matchFunderByName(string $label, array $funders, array $needles = []): ?array
    {
        $labelNorm = $this->normalizeName($label);
        if ($labelNorm === '') {
            return null;
        }

        $token = null;
        $contains = null;
        $hint = null;
        $bestContainsLen = 0;
        $bestNeedleLen = 0;

        foreach ($funders as $f) {
            $name = trim((string) $f['name']);
            $nameNorm = $this->normalizeName($name);
            if ($nameNorm === '') {
                continue;
            }

            if ($nameNorm === $labelNorm) {
                return ['id' => (int) $f['id'], 'name' => $name];
            }

            // Label contains full APM name, or APM name contains full label (prefer longer APM name).
            if (
                str_contains($labelNorm, $nameNorm)
                || str_contains($nameNorm, $labelNorm)
            ) {
                $len = strlen($nameNorm);
                if ($len > $bestContainsLen) {
                    $bestContainsLen = $len;
                    $contains = ['id' => (int) $f['id'], 'name' => $name];
                }
            }

            foreach ($needles as $needle) {
                $needleNorm = $this->normalizeName($needle);
                if ($needleNorm === '' || strlen($needleNorm) < 2) {
                    continue;
                }
                // Prefer needles that appear in both the label and the APM name (e.g. HERA).
                $inName = str_contains($nameNorm, $needleNorm);
                $inLabel = str_contains($labelNorm, $needleNorm);
                if (! $inName) {
                    continue;
                }
                $score = strlen($needleNorm) + ($inLabel ? 100 : 0);
                if ($score > $bestNeedleLen) {
                    $bestNeedleLen = $score;
                    $hint = ['id' => (int) $f['id'], 'name' => $name];
                }
            }

            if ($token === null && $this->tokensOverlap($labelNorm, $nameNorm)) {
                $token = ['id' => (int) $f['id'], 'name' => $name];
            }
        }

        return $contains ?? $hint ?? $token;
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            '–' => '-',
            '—' => '-',
            '’' => "'",
            '“' => '"',
            '”' => '"',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function tokensOverlap(string $a, string $b): bool
    {
        $stop = ['the', 'and', 'of', 'to', 'for', 'in', 'a', 'an'];
        $tokens = static function (string $s) use ($stop): array {
            $parts = preg_split('/\s+/', $s) ?: [];

            return array_values(array_filter(
                $parts,
                static fn (string $t) => strlen($t) >= 3 && ! in_array($t, $stop, true)
            ));
        };
        $ta = $tokens($a);
        $tb = $tokens($b);
        if ($ta === [] || $tb === []) {
            return false;
        }
        $shared = array_intersect($ta, $tb);
        // Require a strong token (e.g. gavi, cepi) — at least one shared significant token
        // and either short acronym-like name or majority overlap.
        if ($shared === []) {
            return false;
        }
        $ratio = count($shared) / min(count($ta), count($tb));

        return $ratio >= 0.5;
    }

    /**
     * Preserve manual picks; fill blanks (and optionally rematch prior auto rows) from suggestions.
     *
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $suggested
     * @return list<array<string, mixed>>
     */
    private function mergeAliases(array $existing, array $suggested, string $idKey, bool $rematchAuto = false): array
    {
        $byLabel = [];
        foreach ($suggested as $row) {
            $byLabel[mb_strtolower((string) $row['label'])] = $row;
        }
        foreach ($existing as $row) {
            $key = mb_strtolower((string) ($row['label'] ?? ''));
            if ($key === '') {
                continue;
            }
            if (! isset($byLabel[$key])) {
                $byLabel[$key] = $row;
                continue;
            }
            $source = (string) ($row['match_source'] ?? '');
            $hasId = ! empty($row[$idKey]);
            // Keep only explicit manual mappings when rematching; otherwise keep any existing id.
            if ($hasId && ($source === 'manual' || (! $rematchAuto && $source !== 'unmatched'))) {
                $byLabel[$key] = array_merge($byLabel[$key], $row);
                $byLabel[$key]['match_source'] = $source === 'manual' ? 'manual' : ($source ?: 'auto');
            }
        }

        return array_values($byLabel);
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

    private function apmDatabaseName(): string
    {
        return trim((string) config('database.connections.apm.database', ''));
    }

    private function apmReady(): bool
    {
        return $this->apmDatabaseName() !== '';
    }
}
