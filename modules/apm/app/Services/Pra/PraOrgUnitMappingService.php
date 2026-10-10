<?php

namespace App\Services\Pra;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\PraOrgUnitMapping;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PraOrgUnitMappingService
{
    public function __construct(
        protected PraClient $client,
        protected PraSettingsService $settings,
    ) {}

    /**
     * Fetch PRA org units, persist new ones, keep existing matches intact.
     *
     * @return array{
     *   fiscal_year: int,
     *   fetched_at: string,
     *   units: list<array<string, mixed>>,
     *   summary: array{
     *     total: int,
     *     matched: int,
     *     unmatched: int,
     *     divisions: int,
     *     directorates: int,
     *     added: int,
     *     preserved: int,
     *     updated_meta: int
     *   },
     *   newly_added: list<array<string, mixed>>,
     *   unmatched_units: list<array<string, mixed>>,
     *   local_divisions: list<array{id: int, label: string, short_name: ?string}>,
     *   local_directorates: list<array{id: int, label: string}>
     * }
     */
    public function syncFromPra(?int $fiscalYear = null): array
    {
        if (! Schema::hasTable('pra_org_unit_mappings')) {
            throw new \RuntimeException('pra_org_unit_mappings table is missing. Run migrations.');
        }

        $year = $fiscalYear
            ?? (int) ($this->settings->resolved()['fiscal_year'] ?: now()->year);

        $payload = $this->client->fetchWorkplan(null, $year);
        $praUnits = $this->extractOrgUnits($payload['data']);
        $saved = $this->savedByCode();
        $localByShort = $this->localDivisionsByShortName();
        $aliases = $this->settings->resolved()['division_aliases'];
        $localDirectorates = $this->localDirectorates();

        $units = [];
        $newlyAdded = [];
        $matched = 0;
        $divCount = 0;
        $dirCount = 0;
        $added = 0;
        $preserved = 0;
        $updatedMeta = 0;
        $now = now();

        DB::transaction(function () use (
            $praUnits,
            $saved,
            $localByShort,
            $aliases,
            $localDirectorates,
            $now,
            &$units,
            &$newlyAdded,
            &$matched,
            &$divCount,
            &$dirCount,
            &$added,
            &$preserved,
            &$updatedMeta
        ) {
            foreach ($praUnits as $unit) {
                $code = $unit['pra_code'];
                $praDivisionId = $unit['pra_division_id'];
                $entityType = $unit['entity_type'];
                $entityType === 'directorate' ? $dirCount++ : $divCount++;

                $savedRow = $saved[$code] ?? null;

                if ($savedRow) {
                    // Preserve existing local matches; refresh PRA metadata only.
                    $payload = [
                        'pra_name' => $unit['pra_name'],
                        'pra_division_id' => $praDivisionId,
                        'entity_type' => $entityType,
                        'last_seen_at' => $now,
                    ];
                    $savedRow->fill($payload);
                    if ($savedRow->isDirty()) {
                        $savedRow->save();
                        $updatedMeta++;
                    } else {
                        $savedRow->forceFill(['last_seen_at' => $now])->save();
                    }

                    $row = $this->rowFromModel($savedRow);
                    if ($row['local_division_id'] || $row['local_directorate_id']) {
                        $matched++;
                        $preserved++;
                    }
                    $units[] = $row;

                    continue;
                }

                // New PRA unit only — try auto/alias mapping.
                $row = [
                    'pra_code' => $code,
                    'pra_division_id' => $praDivisionId,
                    'pra_name' => $unit['pra_name'],
                    'entity_type' => $entityType,
                    'local_division_id' => null,
                    'local_directorate_id' => null,
                    'match_source' => 'unmatched',
                    'match_label' => 'Unmatched',
                ];

                $resolved = $this->resolveLocalDivision($code, $aliases, $localByShort);
                if ($resolved !== null) {
                    $row['local_division_id'] = $resolved['id'];
                    $row['match_source'] = $resolved['source'];
                    $row['match_label'] = $resolved['source'] === 'alias' ? 'Alias' : 'Auto (code)';
                    $matched++;
                }

                if ($entityType === 'directorate' && ! $row['local_directorate_id']) {
                    $dirId = $this->resolveLocalDirectorate($unit['pra_name'], $localDirectorates);
                    if ($dirId !== null) {
                        $row['local_directorate_id'] = $dirId;
                        if ($row['match_source'] === 'unmatched') {
                            $row['match_source'] = 'auto';
                            $row['match_label'] = 'Auto (name)';
                            $matched++;
                        }
                    }
                }

                PraOrgUnitMapping::query()->create([
                    'pra_code' => $code,
                    'pra_division_id' => $praDivisionId,
                    'pra_name' => $unit['pra_name'],
                    'entity_type' => $entityType,
                    'local_division_id' => $row['local_division_id'],
                    'local_directorate_id' => $row['local_directorate_id'],
                    'match_source' => $row['match_source'] === 'unmatched' ? 'unmatched' : $row['match_source'],
                    'last_seen_at' => $now,
                ]);

                $added++;
                $newlyAdded[] = $row;
                $units[] = $row;
            }
        });

        usort($units, function ($a, $b) {
            $order = ['unmatched' => 0, 'manual' => 1, 'alias' => 2, 'auto' => 3];
            $oa = $order[$a['match_source']] ?? 9;
            $ob = $order[$b['match_source']] ?? 9;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strcmp((string) $a['pra_code'], (string) $b['pra_code']);
        });

        $unmatchedUnits = array_values(array_filter(
            $units,
            fn ($u) => ! $u['local_division_id'] && ! $u['local_directorate_id']
        ));

        return [
            'fiscal_year' => $year,
            'fetched_at' => $now->toIso8601String(),
            'units' => $units,
            'summary' => [
                'total' => count($units),
                'matched' => $matched,
                'unmatched' => count($unmatchedUnits),
                'divisions' => $divCount,
                'directorates' => $dirCount,
                'added' => $added,
                'preserved' => $preserved,
                'updated_meta' => $updatedMeta,
            ],
            'newly_added' => $newlyAdded,
            'unmatched_units' => $unmatchedUnits,
            'local_divisions' => $this->localDivisionOptions(),
            'local_directorates' => array_map(
                fn ($d) => ['id' => $d['id'], 'label' => $d['label']],
                $localDirectorates
            ),
        ];
    }

    /** @deprecated Use syncFromPra() — kept for callers expecting preview naming. */
    public function fetchAndPreview(?int $fiscalYear = null): array
    {
        return $this->syncFromPra($fiscalYear);
    }

    /**
     * Persist mapping rows. Existing matches are updated; unmatched rows are kept for follow-up.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function saveMappings(array $rows): int
    {
        if (! Schema::hasTable('pra_org_unit_mappings')) {
            throw new \RuntimeException('pra_org_unit_mappings table is missing. Run migrations.');
        }

        $saved = 0;
        DB::transaction(function () use ($rows, &$saved) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['pra_code'] ?? '')));
                if ($code === '') {
                    continue;
                }

                $praDivisionId = trim((string) ($row['pra_division_id'] ?? ''));
                if ($praDivisionId === '') {
                    $praDivisionId = $code;
                }

                $divisionId = $row['local_division_id'] ?? null;
                $divisionId = $divisionId === '' || $divisionId === null ? null : (int) $divisionId;
                $directorateId = $row['local_directorate_id'] ?? null;
                $directorateId = $directorateId === '' || $directorateId === null ? null : (int) $directorateId;

                $source = (string) ($row['match_source'] ?? 'manual');
                if (! in_array($source, ['auto', 'alias', 'manual', 'unmatched'], true)) {
                    $source = 'manual';
                }
                if ($divisionId || $directorateId) {
                    if (($row['user_set'] ?? false) || $source === 'manual') {
                        $source = 'manual';
                    }
                } else {
                    $source = 'unmatched';
                }

                PraOrgUnitMapping::query()->updateOrCreate(
                    ['pra_code' => $code],
                    [
                        'pra_division_id' => $praDivisionId,
                        'pra_name' => mb_substr(trim((string) ($row['pra_name'] ?? '')), 0, 255) ?: null,
                        'entity_type' => in_array(($row['entity_type'] ?? ''), ['division', 'directorate'], true)
                            ? $row['entity_type']
                            : 'division',
                        'local_division_id' => $divisionId,
                        'local_directorate_id' => $directorateId,
                        'match_source' => $source,
                    ]
                );
                $saved++;
            }
        });

        return $saved;
    }

    /**
     * @return array{units: list<array<string, mixed>>, local_divisions: list<array{id: int, label: string, short_name: ?string}>, local_directorates: list<array{id: int, label: string}>}
     */
    public function savedMappingsPayload(): array
    {
        $units = [];
        if (Schema::hasTable('pra_org_unit_mappings')) {
            foreach (PraOrgUnitMapping::query()->orderBy('pra_code')->get() as $row) {
                $units[] = $this->rowFromModel($row);
            }
        }

        return [
            'units' => $units,
            'local_divisions' => $this->localDivisionOptions(),
            'local_directorates' => array_map(
                fn ($d) => ['id' => $d['id'], 'label' => $d['label']],
                $this->localDirectorates()
            ),
        ];
    }

    /**
     * Resolve local division id for a PRA code or pra_division_id.
     */
    public function resolveDivisionId(string $praCodeOrId): ?int
    {
        $key = strtoupper(trim($praCodeOrId));
        if ($key === '') {
            return null;
        }

        if (Schema::hasTable('pra_org_unit_mappings')) {
            $saved = PraOrgUnitMapping::query()
                ->where('pra_code', $key)
                ->orWhereRaw('UPPER(TRIM(pra_division_id)) = ?', [$key])
                ->first();
            if ($saved && $saved->local_division_id) {
                return (int) $saved->local_division_id;
            }
        }

        $resolved = $this->resolveLocalDivision(
            $key,
            $this->settings->resolved()['division_aliases'],
            $this->localDivisionsByShortName()
        );

        return $resolved['id'] ?? null;
    }

    /**
     * Email support_email when there are unmatched PRA units.
     *
     * @param  array<string, mixed>  $syncResult
     */
    public function notifyAdminOfMismatches(array $syncResult): bool
    {
        $unmatched = $syncResult['unmatched_units'] ?? [];
        $newlyAdded = array_values(array_filter(
            $syncResult['newly_added'] ?? [],
            fn ($u) => empty($u['local_division_id']) && empty($u['local_directorate_id'])
        ));
        $newCodes = [];
        foreach ($newlyAdded as $u) {
            $newCodes[strtoupper((string) ($u['pra_code'] ?? ''))] = true;
        }

        if ($unmatched === [] && $newlyAdded === []) {
            return false;
        }

        $to = trim((string) SystemSetting::get('support_email', ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning('pra.org_units.mismatch_email_skipped', [
                'reason' => 'support_email missing or invalid',
                'unmatched' => count($unmatched),
            ]);

            return false;
        }

        $mapUrl = url('/divisions');
        $addedCount = count($newlyAdded);
        $unmatchedCount = count($unmatched);
        $fy = (int) ($syncResult['fiscal_year'] ?? now()->year);

        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rowsHtml = '';
        foreach ($unmatched as $u) {
            $code = strtoupper((string) ($u['pra_code'] ?? ''));
            $badge = isset($newCodes[$code]) ? ' <em>(new)</em>' : '';
            $rowsHtml .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $esc($u['pra_division_id'] ?? $u['pra_code'] ?? ''),
                $esc($u['pra_code'] ?? ''),
                $esc($u['pra_name'] ?? '').$badge,
                $esc($u['entity_type'] ?? 'division')
            );
        }

        $subject = sprintf(
            '[APM] PRA division mismatches need review (%d unmatched%s)',
            $unmatchedCount,
            $addedCount > 0 ? ", {$addedCount} new" : ''
        );

        $body = <<<HTML
<p>The daily PRA org-unit sync found division/directorate codes that are not mapped to APM.</p>
<p><strong>Fiscal year:</strong> {$fy}<br>
<strong>Unmatched:</strong> {$unmatchedCount}<br>
<strong>Newly added unmatched:</strong> {$addedCount}</p>
<p>Please resolve them on <a href="{$mapUrl}">Divisions → PRA integration</a>.</p>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px">
  <thead><tr><th>PRA division id</th><th>Code</th><th>Name</th><th>Type</th></tr></thead>
  <tbody>{$rowsHtml}</tbody>
</table>
HTML;

        if (! function_exists('sendEmail')) {
            require_once app_path('Helpers/MailingHelper.php');
        }

        try {
            $ok = (bool) sendEmail($to, $subject, $body);
        } catch (\Throwable $e) {
            Log::error('pra.org_units.mismatch_email_failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        Log::info('pra.org_units.mismatch_email', [
            'to' => $to,
            'unmatched' => $unmatchedCount,
            'new_unmatched' => $addedCount,
            'sent' => $ok,
        ]);

        return $ok;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowFromModel(PraOrgUnitMapping $row): array
    {
        $source = (string) ($row->match_source ?: 'unmatched');
        $hasMap = $row->local_division_id || $row->local_directorate_id;
        if (! $hasMap) {
            $source = 'unmatched';
        }

        $label = match ($source) {
            'manual' => 'Manual',
            'alias' => 'Alias',
            'auto' => 'Auto (code)',
            default => 'Unmatched',
        };

        return [
            'pra_code' => $row->pra_code,
            'pra_division_id' => $row->pra_division_id ?: $row->pra_code,
            'pra_name' => $row->pra_name,
            'entity_type' => $row->entity_type,
            'local_division_id' => $row->local_division_id,
            'local_directorate_id' => $row->local_directorate_id,
            'match_source' => $source,
            'match_label' => $label,
            'last_seen_at' => $row->last_seen_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $indicators
     * @return list<array{pra_code: string, pra_division_id: string, pra_name: string, entity_type: string}>
     */
    protected function extractOrgUnits(array $indicators): array
    {
        $units = [];
        foreach ($indicators as $row) {
            if (! is_array($row)) {
                continue;
            }
            $division = $row['division'] ?? null;
            if (! is_array($division)) {
                $broad = is_array($row['broad_activity'] ?? null) ? $row['broad_activity'] : null;
                $division = is_array($broad['division'] ?? null) ? $broad['division'] : null;
            }
            if (! is_array($division)) {
                continue;
            }
            $code = strtoupper(trim((string) ($division['code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $name = trim((string) ($division['name'] ?? $code));
            $rawId = $division['id'] ?? $division['division_id'] ?? $row['division_id'] ?? null;
            $praDivisionId = ($rawId !== null && trim((string) $rawId) !== '')
                ? trim((string) $rawId)
                : $code;

            if (! isset($units[$code])) {
                $units[$code] = [
                    'pra_code' => $code,
                    'pra_division_id' => $praDivisionId,
                    'pra_name' => $name,
                    'entity_type' => $this->inferEntityType($name),
                ];
            } elseif (($units[$code]['pra_division_id'] === $code) && $praDivisionId !== $code) {
                $units[$code]['pra_division_id'] = $praDivisionId;
            }
        }

        return array_values($units);
    }

    protected function inferEntityType(string $name): string
    {
        return stripos($name, 'directorate') !== false ? 'directorate' : 'division';
    }

    /**
     * @return array<string, PraOrgUnitMapping>
     */
    protected function savedByCode(): array
    {
        if (! Schema::hasTable('pra_org_unit_mappings')) {
            return [];
        }

        $out = [];
        foreach (PraOrgUnitMapping::query()->get() as $row) {
            $out[strtoupper((string) $row->pra_code)] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, array{id: int, name: string, short: string}>
     */
    protected function localDivisionsByShortName(): array
    {
        $out = [];
        $query = Division::query()
            ->whereNotNull('division_short_name')
            ->where('division_short_name', '!=', '');

        foreach ($query->get(['id', 'division_name', 'division_short_name']) as $row) {
            $short = strtoupper(trim((string) $row->division_short_name));
            if ($short === '') {
                continue;
            }
            $out[$short] = [
                'id' => (int) $row->id,
                'name' => (string) $row->division_name,
                'short' => $short,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $aliases
     * @param  array<string, array{id: int, name: string, short: string}>  $localByShort
     * @return array{id: int, source: string}|null
     */
    protected function resolveLocalDivision(string $praCode, array $aliases, array $localByShort): ?array
    {
        $praCode = strtoupper(trim($praCode));
        if ($praCode === '') {
            return null;
        }

        if (isset($aliases[$praCode])) {
            $localShort = strtoupper((string) $aliases[$praCode]);
            if (isset($localByShort[$localShort])) {
                return ['id' => $localByShort[$localShort]['id'], 'source' => 'alias'];
            }
        }

        if (isset($localByShort[$praCode])) {
            return ['id' => $localByShort[$praCode]['id'], 'source' => 'auto'];
        }

        return null;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    protected function localDirectorates(): array
    {
        if (! Schema::hasTable('directorates')) {
            return [];
        }

        return Directorate::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'label' => (string) $d->name,
            ])
            ->all();
    }

    /**
     * @param  list<array{id: int, label: string}>  $localDirectorates
     */
    protected function resolveLocalDirectorate(string $praName, array $localDirectorates): ?int
    {
        $needle = strtolower(trim($praName));
        if ($needle === '') {
            return null;
        }

        foreach ($localDirectorates as $dir) {
            $label = strtolower((string) $dir['label']);
            if ($label === $needle || str_contains($label, $needle) || str_contains($needle, $label)) {
                return (int) $dir['id'];
            }
        }

        $stripped = preg_replace('/^directorate\s+(of\s+)?/i', '', $praName) ?? $praName;
        $stripped = strtolower(trim($stripped));
        foreach ($localDirectorates as $dir) {
            $label = strtolower((string) $dir['label']);
            $labelStripped = preg_replace('/^directorate\s+(of\s+)?/i', '', $dir['label']) ?? $dir['label'];
            $labelStripped = strtolower(trim((string) $labelStripped));
            if ($stripped !== '' && ($labelStripped === $stripped || str_contains($label, $stripped))) {
                return (int) $dir['id'];
            }
        }

        return null;
    }

    /**
     * @return list<array{id: int, label: string, short_name: ?string}>
     */
    protected function localDivisionOptions(): array
    {
        return Division::query()
            ->orderBy('division_name')
            ->get(['id', 'division_name', 'division_short_name'])
            ->map(function ($d) {
                $short = $d->division_short_name ? trim((string) $d->division_short_name) : '';
                $label = (string) $d->division_name;
                if ($short !== '') {
                    $label .= ' ('.$short.')';
                }

                return [
                    'id' => (int) $d->id,
                    'label' => $label,
                    'short_name' => $short !== '' ? $short : null,
                ];
            })
            ->all();
    }
}
