<?php

namespace App\Services;

use App\Support\XlsxSheetReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ExcelRiskImportService
{
    public function __construct(
        private readonly StaffPortalOrgClient $orgClient,
        private readonly ?XlsxSheetReader $reader = null,
        private readonly ?ResidualRiskCalculator $calculator = null,
    ) {}

    /**
     * CLI / full import (matched + unmatched with unmapped_business_unit).
     *
     * @param  array{divisions?: list<array<string, mixed>>, directorates?: list<array<string, mixed>>}|null  $orgOverride
     * @return array{imported:int,unmatched:int,owners_defaulted:int,skipped:int}
     */
    public function import(string $path, ?array $orgOverride = null): array
    {
        $parsed = $this->parseWorkbook($path, $orgOverride);
        $imported = 0;
        $unmatched = 0;
        $ownersDefaulted = 0;

        DB::transaction(function () use ($parsed, &$imported, &$unmatched, &$ownersDefaulted) {
            foreach ($parsed['rows'] as $row) {
                if ($row['match']['unmapped'] !== null) {
                    $unmatched++;
                }
                $riskId = $this->insertRiskRow($row['payload'], $row['match']);
                if (($row['match']['division_head'] ?? null) !== null) {
                    $this->attachHodOwner($riskId, (int) $row['match']['division_head']);
                    $ownersDefaulted++;
                }
                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'unmatched' => $unmatched,
            'owners_defaulted' => $ownersDefaulted,
            'skipped' => $parsed['skipped'],
        ];
    }

    /**
     * @return array{
     *   batch_id:int,
     *   matched:int,
     *   unmatched:int,
     *   skipped:int,
     *   unmatched_bus: list<array{business_unit:string,count:int}>
     * }
     */
    public function previewUpload(string $absolutePath, string $originalFilename, int $actorStaffId, ?array $orgOverride = null): array
    {
        $parsed = $this->parseWorkbook($absolutePath, $orgOverride);
        $disk = Storage::disk('local');
        $relative = 'risk-imports/'.uniqid('batch_', true).'.xlsx';
        $disk->put($relative, file_get_contents($absolutePath));

        $unmatchedBus = [];
        foreach ($parsed['rows'] as $row) {
            if ($row['match']['unmapped'] === null) {
                continue;
            }
            $label = (string) $row['match']['unmapped'];
            $unmatchedBus[$label] = ($unmatchedBus[$label] ?? 0) + 1;
        }
        $summary = [];
        foreach ($unmatchedBus as $bu => $count) {
            $summary[] = ['business_unit' => $bu, 'count' => $count];
        }
        usort($summary, static fn ($a, $b) => $b['count'] <=> $a['count']);

        $matched = count(array_filter($parsed['rows'], static fn ($r) => $r['match']['unmapped'] === null));
        $unmatched = count($parsed['rows']) - $matched;

        $batchId = (int) DB::table('rr_import_batches')->insertGetId([
            'original_filename' => mb_substr($originalFilename, 0, 255),
            'stored_path' => $relative,
            'status' => 'preview',
            'matched_count' => $matched,
            'unmatched_count' => $unmatched,
            'skipped_count' => $parsed['skipped'],
            'imported_count' => 0,
            'staged_count' => 0,
            'mapped_imported_count' => 0,
            'unmatched_summary' => json_encode($summary),
            'created_by_staff_id' => $actorStaffId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cache parsed rows on disk as json next to file for commit step
        $disk->put($this->parsedCachePath($relative), json_encode($parsed['rows']));

        return [
            'batch_id' => $batchId,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'skipped' => $parsed['skipped'],
            'unmatched_bus' => $summary,
        ];
    }

    /**
     * @return array{imported:int,staged:int,owners_defaulted:int}
     */
    public function commitMatched(int $batchId): array
    {
        $batch = DB::table('rr_import_batches')->where('id', $batchId)->first();
        if (! $batch || $batch->status !== 'preview') {
            throw new RuntimeException('Import batch not in preview state.');
        }

        $disk = Storage::disk('local');
        $cachePath = $this->parsedCachePath((string) $batch->stored_path);
        $raw = $disk->get($cachePath);
        $rows = json_decode((string) $raw, true);
        if (! is_array($rows)) {
            throw new RuntimeException('Preview cache missing; re-upload the workbook.');
        }

        $imported = 0;
        $staged = 0;
        $ownersDefaulted = 0;

        DB::transaction(function () use ($rows, $batchId, &$imported, &$staged, &$ownersDefaulted) {
            foreach ($rows as $row) {
                $match = $row['match'];
                $payload = $row['payload'];
                if (($match['unmapped'] ?? null) === null) {
                    $riskId = $this->insertRiskRow($payload, $match);
                    if (($match['division_head'] ?? null) !== null) {
                        $this->attachHodOwner($riskId, (int) $match['division_head']);
                        $ownersDefaulted++;
                    }
                    $imported++;
                } else {
                    DB::table('rr_import_staged_rows')->insert([
                        'batch_id' => $batchId,
                        'excel_row' => $payload['import_row_number'] ?? null,
                        'business_unit' => $match['unmapped'],
                        'payload' => json_encode($payload),
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $staged++;
                }
            }

            DB::table('rr_import_batches')->where('id', $batchId)->update([
                'status' => $staged > 0 ? 'matched_committed' : 'completed',
                'imported_count' => $imported,
                'staged_count' => $staged,
                'updated_at' => now(),
            ]);
        });

        return [
            'imported' => $imported,
            'staged' => $staged,
            'owners_defaulted' => $ownersDefaulted,
        ];
    }

    /**
     * @param  list<array{business_unit:string,division_id:int,directorate_id?:?int}>  $mappings
     * @return array{imported:int,remaining:int}
     */
    public function applyMappings(int $batchId, array $mappings, ?array $orgOverride = null): array
    {
        $batch = DB::table('rr_import_batches')->where('id', $batchId)->first();
        if (! $batch || $batch->status !== 'matched_committed') {
            throw new RuntimeException('Import batch is not ready for mapping.');
        }

        $org = $orgOverride ?? $this->orgClient->fetchOrg();
        $heads = [];
        foreach ($org['divisions'] ?? [] as $div) {
            $heads[(int) $div['division_id']] = isset($div['division_head']) ? (int) $div['division_head'] : null;
        }

        $mapByBu = [];
        foreach ($mappings as $m) {
            $bu = trim((string) ($m['business_unit'] ?? ''));
            if ($bu === '' || empty($m['division_id'])) {
                continue;
            }
            $mapByBu[$bu] = [
                'division_id' => (int) $m['division_id'],
                'directorate_id' => isset($m['directorate_id']) && $m['directorate_id'] !== '' && $m['directorate_id'] !== null
                    ? (int) $m['directorate_id']
                    : null,
            ];
        }

        $imported = 0;
        DB::transaction(function () use ($batchId, $mapByBu, $heads, &$imported) {
            $pending = DB::table('rr_import_staged_rows')
                ->where('batch_id', $batchId)
                ->where('status', 'pending')
                ->get();

            foreach ($pending as $staged) {
                $bu = (string) $staged->business_unit;
                if (! isset($mapByBu[$bu])) {
                    continue;
                }
                $payload = json_decode((string) $staged->payload, true);
                if (! is_array($payload)) {
                    continue;
                }
                $divisionId = $mapByBu[$bu]['division_id'];
                $match = [
                    'division_id' => $divisionId,
                    'directorate_id' => $mapByBu[$bu]['directorate_id'],
                    'unmapped' => null,
                    'division_head' => $heads[$divisionId] ?? null,
                ];
                $payload['source_business_unit'] = $bu;
                $riskId = $this->insertRiskRow($payload, $match);
                if ($match['division_head'] !== null) {
                    $this->attachHodOwner($riskId, (int) $match['division_head']);
                }
                DB::table('rr_import_staged_rows')->where('id', $staged->id)->update([
                    'status' => 'imported',
                    'mapped_division_id' => $divisionId,
                    'mapped_directorate_id' => $match['directorate_id'],
                    'imported_risk_id' => $riskId,
                    'updated_at' => now(),
                ]);
                $imported++;
            }

            $remaining = DB::table('rr_import_staged_rows')
                ->where('batch_id', $batchId)
                ->where('status', 'pending')
                ->count();

            DB::table('rr_import_batches')->where('id', $batchId)->update([
                'mapped_imported_count' => DB::raw('mapped_imported_count + '.$imported),
                'status' => $remaining === 0 ? 'completed' : 'matched_committed',
                'updated_at' => now(),
            ]);
        });

        $remaining = (int) DB::table('rr_import_staged_rows')
            ->where('batch_id', $batchId)
            ->where('status', 'pending')
            ->count();

        return ['imported' => $imported, 'remaining' => $remaining];
    }

    /**
     * @return array{skipped:int,rows:list<array{match:array,payload:array}>}
     */
    private function parseWorkbook(string $path, ?array $orgOverride = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Excel file not found: '.$path);
        }

        $org = $orgOverride ?? $this->orgClient->fetchOrg();
        $matcher = new BusinessUnitMatcher($org);
        $calculator = $this->calculator ?? new ResidualRiskCalculator(
            ResidualRiskCalculator::defaultRatingForScore(...)
        );
        $reader = $this->reader ?? new XlsxSheetReader;
        $lookups = $this->loadLookups();
        $rows = $reader->readSheetRows($path, 'Risk Register');

        $headerRow = null;
        $colMap = null;
        foreach ($rows as $entry) {
            $cells = $entry['cells'];
            $joined = mb_strtolower(implode('|', $cells));
            if (str_contains($joined, 'business unit') && str_contains($joined, 'risk name')) {
                $headerRow = $entry['row'];
                $colMap = $this->mapHeaderColumns($cells);
                break;
            }
        }
        if ($colMap === null) {
            throw new RuntimeException('Could not find Risk Register header row.');
        }

        $out = [];
        $skipped = 0;
        foreach ($rows as $entry) {
            if ($entry['row'] <= $headerRow) {
                continue;
            }
            $cells = $entry['cells'];
            $name = trim((string) ($cells[$colMap['name']] ?? ''));
            if ($name === '') {
                $skipped++;

                continue;
            }

            $bu = trim((string) ($cells[$colMap['business_unit']] ?? ''));
            $match = $matcher->match($bu);

            $likelihood = $this->scoreFromCell(
                $cells[$colMap['likelihood_score']] ?? null,
                $cells[$colMap['likelihood_label']] ?? null,
                $lookups['likelihoods']
            );
            $impact = $this->scoreFromCell(
                $cells[$colMap['impact_score']] ?? null,
                $cells[$colMap['impact_label']] ?? null,
                $lookups['impacts']
            );

            $effName = trim((string) ($cells[$colMap['effectiveness']] ?? ''));
            $eff = $lookups['effectiveness'][$this->norm($effName)] ?? $lookups['effectiveness']['not assessed'] ?? null;
            $reduction = (int) ($eff['likelihood_reduction'] ?? 0);
            $assessed = (bool) ($eff['is_assessed'] ?? false);
            $scores = $calculator->compute($likelihood ?? 1, $impact ?? 1, $reduction, $assessed);

            $themeName = trim((string) ($cells[$colMap['theme']] ?? ''));
            $typeName = trim((string) ($cells[$colMap['risk_type']] ?? ''));
            $statusName = trim((string) ($cells[$colMap['status']] ?? ''));

            $payload = [
                'import_row_number' => $entry['row'],
                'source_business_unit' => $bu !== '' ? $bu : null,
                'enterprise_theme_id' => $lookups['themes'][$this->norm($themeName)] ?? null,
                'name' => mb_substr($name, 0, 512),
                'consequence' => $this->nullableText($cells[$colMap['consequence']] ?? null),
                'root_causes' => $this->nullableText($cells[$colMap['root_causes']] ?? null),
                'risk_type_id' => $lookups['types'][$this->norm($typeName)] ?? null,
                'inherent_likelihood' => $likelihood,
                'inherent_impact' => $impact,
                'inherent_score' => $scores['inherent_score'],
                'inherent_rating' => $scores['inherent_rating'],
                'mitigation' => $this->nullableText($cells[$colMap['mitigation']] ?? null),
                'management_response' => $this->nullableText($cells[$colMap['management_response']] ?? null),
                'timeline' => $this->formatTimeline($cells[$colMap['timeline']] ?? null),
                'status_id' => $lookups['statuses'][$this->norm($statusName)] ?? null,
                'action_update' => $this->nullableText($cells[$colMap['action_update']] ?? null),
                'date_of_update' => $this->excelDate($cells[$colMap['date_of_update']] ?? null),
                'oio_verification_notes' => $this->nullableText($cells[$colMap['oio_notes']] ?? null),
                'mitigation_effectiveness_id' => $eff['id'] ?? null,
                'residual_likelihood' => $scores['residual_likelihood'],
                'residual_impact' => $scores['residual_impact'],
                'residual_score' => $scores['residual_score'],
                'residual_rating' => $scores['residual_rating'],
                'risk_movement' => $scores['movement'],
            ];

            $out[] = ['match' => $match, 'payload' => $payload];
        }

        return ['skipped' => $skipped, 'rows' => $out];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{division_id:?int,directorate_id:?int,unmapped:?string,division_head:?int}  $match
     */
    private function insertRiskRow(array $payload, array $match): int
    {
        return (int) DB::table('rr_risks')->insertGetId([
            'import_row_number' => $payload['import_row_number'] ?? null,
            'source_business_unit' => $payload['source_business_unit'] ?? null,
            'division_id' => $match['division_id'],
            'directorate_id' => $match['directorate_id'],
            'unmapped_business_unit' => $match['unmapped'],
            'enterprise_theme_id' => $payload['enterprise_theme_id'] ?? null,
            'name' => $payload['name'],
            'consequence' => $payload['consequence'] ?? null,
            'root_causes' => $payload['root_causes'] ?? null,
            'risk_type_id' => $payload['risk_type_id'] ?? null,
            'inherent_likelihood' => $payload['inherent_likelihood'] ?? null,
            'inherent_impact' => $payload['inherent_impact'] ?? null,
            'inherent_score' => $payload['inherent_score'] ?? null,
            'inherent_rating' => $payload['inherent_rating'] ?? null,
            'mitigation' => $payload['mitigation'] ?? null,
            'management_response' => $payload['management_response'] ?? null,
            'timeline' => $payload['timeline'] ?? null,
            'status_id' => $payload['status_id'] ?? null,
            'action_update' => $payload['action_update'] ?? null,
            'date_of_update' => $payload['date_of_update'] ?? null,
            'oio_verification_notes' => $payload['oio_verification_notes'] ?? null,
            'mitigation_effectiveness_id' => $payload['mitigation_effectiveness_id'] ?? null,
            'residual_likelihood' => $payload['residual_likelihood'] ?? null,
            'residual_impact' => $payload['residual_impact'] ?? null,
            'residual_score' => $payload['residual_score'] ?? null,
            'residual_rating' => $payload['residual_rating'] ?? null,
            'risk_movement' => $payload['risk_movement'] ?? null,
            'workflow_state' => 'active_imported',
            'imported' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachHodOwner(int $riskId, int $staffId): void
    {
        DB::table('rr_risk_owners')->insert([
            'risk_id' => $riskId,
            'staff_id' => $staffId,
            'is_default_hod' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function parsedCachePath(string $storedPath): string
    {
        return $storedPath.'.parsed.json';
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<string, int>
     */
    private function mapHeaderColumns(array $cells): array
    {
        $map = [];
        foreach ($cells as $col => $label) {
            $n = $this->norm($label);
            $key = match (true) {
                str_contains($n, 'business unit') => 'business_unit',
                str_contains($n, 'enterprise risk theme') => 'theme',
                str_starts_with($n, 'risk name') => 'name',
                str_contains($n, 'risk consequence') => 'consequence',
                str_contains($n, 'root cause') => 'root_causes',
                $n === 'risk type' => 'risk_type',
                str_contains($n, 'likelihood (l)') => 'likelihood_label',
                str_contains($n, 'score of likelihood') => 'likelihood_score',
                str_contains($n, 'impact (i)') => 'impact_label',
                str_contains($n, 'score of impact') => 'impact_score',
                str_contains($n, 'inherent risk score') => 'inherent_score_excel',
                $n === 'mitigation' => 'mitigation',
                str_contains($n, 'management response') => 'management_response',
                $n === 'timeline' => 'timeline',
                str_starts_with($n, 'status update') && ! str_contains($n, 'verified') => 'status',
                str_contains($n, 'responsible owner') => 'owners_text',
                str_contains($n, 'action update') => 'action_update',
                str_contains($n, 'date of update') => 'date_of_update',
                str_contains($n, 'verified by') => 'oio_notes',
                str_contains($n, 'mitigation effectiveness') => 'effectiveness',
                default => null,
            };
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = $col;
            }
        }

        foreach (['business_unit', 'name'] as $required) {
            if (! isset($map[$required])) {
                throw new RuntimeException('Missing required Excel column: '.$required);
            }
        }

        return $map;
    }

    /**
     * @return array{
     *   likelihoods: array<string,int>,
     *   impacts: array<string,int>,
     *   themes: array<string,int>,
     *   types: array<string,int>,
     *   statuses: array<string,int>,
     *   effectiveness: array<string,array{id:int,likelihood_reduction:int,is_assessed:bool}>
     * }
     */
    private function loadLookups(): array
    {
        $likelihoods = [];
        foreach (DB::table('rr_likelihoods')->get(['id', 'label', 'score']) as $row) {
            $likelihoods[$this->norm((string) $row->label)] = (int) $row->score;
        }
        $impacts = [];
        foreach (DB::table('rr_impacts')->get(['id', 'label', 'score']) as $row) {
            $impacts[$this->norm((string) $row->label)] = (int) $row->score;
        }
        $themes = [];
        foreach (DB::table('rr_enterprise_themes')->get(['id', 'name']) as $row) {
            $themes[$this->norm((string) $row->name)] = (int) $row->id;
        }
        $types = [];
        foreach (DB::table('rr_risk_types')->get(['id', 'name']) as $row) {
            $types[$this->norm((string) $row->name)] = (int) $row->id;
        }
        $statuses = [];
        foreach (DB::table('rr_statuses')->get(['id', 'name']) as $row) {
            $statuses[$this->norm((string) $row->name)] = (int) $row->id;
        }
        $effectiveness = [];
        foreach (DB::table('rr_mitigation_effectiveness')->get(['id', 'name', 'likelihood_reduction', 'is_assessed']) as $row) {
            $effectiveness[$this->norm((string) $row->name)] = [
                'id' => (int) $row->id,
                'likelihood_reduction' => (int) $row->likelihood_reduction,
                'is_assessed' => (bool) $row->is_assessed,
            ];
        }

        return compact('likelihoods', 'impacts', 'themes', 'types', 'statuses', 'effectiveness');
    }

    /**
     * @param  array<string, int>  $labelToScore
     */
    private function scoreFromCell(mixed $scoreCell, mixed $labelCell, array $labelToScore): ?int
    {
        if ($scoreCell !== null && $scoreCell !== '' && is_numeric($scoreCell)) {
            return max(1, min(5, (int) $scoreCell));
        }
        $label = trim((string) ($labelCell ?? ''));

        return $label === '' ? null : ($labelToScore[$this->norm($label)] ?? null);
    }

    private function nullableText(mixed $value): ?string
    {
        $t = trim((string) ($value ?? ''));

        return $t === '' ? null : $t;
    }

    private function formatTimeline(mixed $value): ?string
    {
        $t = trim((string) ($value ?? ''));
        if ($t === '') {
            return null;
        }
        if (is_numeric($t)) {
            return $this->excelSerialToDate((float) $t) ?? $t;
        }

        return mb_substr($t, 0, 255);
    }

    private function excelDate(mixed $value): ?string
    {
        $t = trim((string) ($value ?? ''));
        if ($t === '') {
            return null;
        }
        if (is_numeric($t)) {
            return $this->excelSerialToDate((float) $t);
        }
        $ts = strtotime($t);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function excelSerialToDate(float $serial): ?string
    {
        if ($serial < 1) {
            return null;
        }

        return gmdate('Y-m-d', (int) (($serial - 25569) * 86400));
    }

    private function norm(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
