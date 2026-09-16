<?php

namespace App\Services;

use App\Support\XlsxSheetReader;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ExcelRiskImportService
{
    public function __construct(
        private readonly StaffPortalOrgClient $orgClient,
        private readonly ?XlsxSheetReader $reader = null,
        private readonly ?ResidualRiskCalculator $calculator = null,
    ) {}

    /**
     * @param  array{divisions?: list<array<string, mixed>>, directorates?: list<array<string, mixed>>}|null  $orgOverride
     * @return array{imported:int,unmatched:int,owners_defaulted:int,skipped:int}
     */
    public function import(string $path, ?array $orgOverride = null): array
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

        $imported = 0;
        $unmatched = 0;
        $ownersDefaulted = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $rows,
            $headerRow,
            $colMap,
            $matcher,
            $calculator,
            $lookups,
            &$imported,
            &$unmatched,
            &$ownersDefaulted,
            &$skipped
        ) {
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
                if ($match['unmapped'] !== null) {
                    $unmatched++;
                }

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

                $scores = $calculator->compute(
                    $likelihood ?? 1,
                    $impact ?? 1,
                    $reduction,
                    $assessed
                );

                $themeName = trim((string) ($cells[$colMap['theme']] ?? ''));
                $typeName = trim((string) ($cells[$colMap['risk_type']] ?? ''));
                $statusName = trim((string) ($cells[$colMap['status']] ?? ''));

                $riskId = DB::table('rr_risks')->insertGetId([
                    'import_row_number' => $entry['row'],
                    'source_business_unit' => $bu !== '' ? $bu : null,
                    'division_id' => $match['division_id'],
                    'directorate_id' => $match['directorate_id'],
                    'unmapped_business_unit' => $match['unmapped'],
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
                    'workflow_state' => 'active_imported',
                    'imported' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($match['division_head'] !== null) {
                    DB::table('rr_risk_owners')->insert([
                        'risk_id' => $riskId,
                        'staff_id' => $match['division_head'],
                        'is_default_hod' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $ownersDefaulted++;
                }

                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'unmatched' => $unmatched,
            'owners_defaulted' => $ownersDefaulted,
            'skipped' => $skipped,
        ];
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
            $n = (int) $scoreCell;

            return max(1, min(5, $n));
        }
        $label = trim((string) ($labelCell ?? ''));
        if ($label === '') {
            return null;
        }

        return $labelToScore[$this->norm($label)] ?? null;
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
            $date = $this->excelSerialToDate((float) $t);

            return $date ?? $t;
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
        // Excel 1900 date system
        $unix = (int) (($serial - 25569) * 86400);

        return gmdate('Y-m-d', $unix);
    }

    private function norm(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }
}
