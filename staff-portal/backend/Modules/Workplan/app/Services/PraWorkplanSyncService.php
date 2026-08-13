<?php

namespace Modules\Workplan\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PraWorkplanSyncService
{
    public function __construct(
        protected PraWorkplanClient $client,
    ) {}

    /**
     * Sync PRA indicators/activities into local workplan tables.
     *
     * @param  list<string>|null  $divisionCodes  PRA codes (e.g. MIS). Null = all local short names (+ aliases).
     * @return array{
     *   fiscal_year: int,
     *   divisions: list<array<string, mixed>>,
     *   indicators_upserted: int,
     *   activities_upserted: int,
     *   skipped: list<string>,
     *   errors: list<string>
     * }
     */
    public function sync(?int $fiscalYear = null, ?array $divisionCodes = null): array
    {
        if (! Schema::hasTable('workplan_tasks')) {
            throw new \RuntimeException('workplan_tasks table is missing.');
        }

        $year = $fiscalYear
            ?? (int) (config('workplan.pra.fiscal_year') ?: now()->year);

        $codes = $divisionCodes !== null && $divisionCodes !== []
            ? array_values(array_unique(array_map(fn ($c) => strtoupper(trim((string) $c)), $divisionCodes)))
            : $this->defaultDivisionCodes();

        $result = [
            'fiscal_year' => $year,
            'divisions' => [],
            'indicators_upserted' => 0,
            'activities_upserted' => 0,
            'skipped' => [],
            'errors' => [],
        ];

        foreach ($codes as $praCode) {
            $divisionId = $this->resolveDivisionId($praCode);
            if ($divisionId === null) {
                $msg = "No local division for PRA code {$praCode} (set division_short_name or PRA_WORKPLAN_DIVISION_ALIASES).";
                $result['skipped'][] = $msg;
                Log::warning('workplan.pra_sync.skipped', ['code' => $praCode, 'message' => $msg]);

                continue;
            }

            try {
                $payload = $this->client->fetch($praCode, $year);
                $counts = $this->ingestDivision($payload['data'], $divisionId, $praCode, $year);
                $result['indicators_upserted'] += $counts['indicators'];
                $result['activities_upserted'] += $counts['activities'];
                $result['divisions'][] = [
                    'pra_code' => $praCode,
                    'division_id' => $divisionId,
                    'indicators' => $counts['indicators'],
                    'activities' => $counts['activities'],
                    'api_indicators' => count($payload['data']),
                ];
            } catch (\Throwable $e) {
                $result['errors'][] = "{$praCode}: ".$e->getMessage();
                Log::error('workplan.pra_sync.error', [
                    'code' => $praCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function defaultDivisionCodes(): array
    {
        $configured = (array) config('workplan.pra.divisions', []);
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($c) => strtoupper(trim((string) $c)),
            $configured,
        ))));

        if ($codes !== []) {
            return $codes;
        }

        // All local division short names → PRA fetch codes (apply reverse aliases, e.g. DHIS→MIS).
        $aliases = (array) config('workplan.pra.division_aliases', []);
        $localToPra = [];
        foreach ($aliases as $praCode => $localShort) {
            $localToPra[strtoupper((string) $localShort)] = strtoupper((string) $praCode);
        }

        $localShorts = DB::table('divisions')
            ->whereNotNull('division_short_name')
            ->where('division_short_name', '!=', '')
            ->pluck('division_short_name')
            ->map(fn ($s) => strtoupper(trim((string) $s)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $praCodes = [];
        foreach ($localShorts as $short) {
            $praCodes[] = $localToPra[$short] ?? $short;
        }

        // Include alias sources even if the local target short is missing.
        foreach (array_keys($aliases) as $praCode) {
            $praCodes[] = strtoupper((string) $praCode);
        }

        return array_values(array_unique(array_filter($praCodes)));
    }

    public function resolveDivisionId(string $praCode): ?int
    {
        $praCode = strtoupper(trim($praCode));
        $aliases = (array) config('workplan.pra.division_aliases', []);
        $localShort = strtoupper((string) ($aliases[$praCode] ?? $praCode));

        $id = DB::table('divisions')
            ->whereRaw('UPPER(TRIM(division_short_name)) = ?', [$localShort])
            ->value('division_id');

        if ($id) {
            return (int) $id;
        }

        // Fallback: PRA code equals local short name.
        if ($localShort !== $praCode) {
            $id = DB::table('divisions')
                ->whereRaw('UPPER(TRIM(division_short_name)) = ?', [$praCode])
                ->value('division_id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $indicators
     * @return array{indicators: int, activities: int}
     */
    protected function ingestDivision(array $indicators, int $divisionId, string $praCode, int $year): array
    {
        $indicatorCount = 0;
        $activityCount = 0;
        $hasPraIndicator = Schema::hasColumn('workplan_tasks', 'pra_indicator_id');
        $hasPraActivity = Schema::hasTable('work_planner_tasks')
            && Schema::hasColumn('work_planner_tasks', 'pra_activity_id');

        foreach ($indicators as $indicator) {
            if (! is_array($indicator)) {
                continue;
            }
            $praIndicatorId = (int) ($indicator['id'] ?? 0);
            if ($praIndicatorId < 1) {
                continue;
            }

            $specific = is_array($indicator['specific_activities'] ?? null)
                ? $indicator['specific_activities']
                : [];

            $broadTitles = [];
            foreach ($specific as $act) {
                if (! is_array($act)) {
                    continue;
                }
                $broad = is_array($act['broad_activity'] ?? null) ? $act['broad_activity'] : [];
                $title = trim((string) ($broad['title'] ?? ''));
                if ($title !== '') {
                    $broadTitles[$title] = true;
                }
            }

            $targets = is_array($indicator['targets'] ?? null) ? $indicator['targets'] : [];
            $targetBits = [];
            foreach (['y1', 'y2', 'y3'] as $yk) {
                if (isset($targets[$yk]) && $targets[$yk] !== null && $targets[$yk] !== '') {
                    $targetBits[] = strtoupper($yk).':'.$targets[$yk];
                }
            }
            if (isset($indicator['baseline']) && $indicator['baseline'] !== null && $indicator['baseline'] !== '') {
                array_unshift($targetBits, 'baseline:'.$indicator['baseline']);
            }

            $pillar = is_array($indicator['pillar'] ?? null) ? $indicator['pillar'] : [];
            $code = trim((string) ($indicator['code'] ?? ''));
            $title = trim((string) ($indicator['title'] ?? 'Untitled indicator'));

            $row = [
                'division_id' => $divisionId,
                'year' => (string) ($indicator['fiscal_year'] ?? $year),
                'activity_name' => $title,
                'broad_activity' => implode("\n", array_keys($broadTitles)) ?: null,
                'intermediate_outcome' => trim((string) ($pillar['name'] ?? '')) ?: null,
                'output_indicator' => trim($code !== '' ? "{$code} — {$title}" : $title),
                'cumulative_target' => $targetBits !== [] ? implode('; ', $targetBits) : null,
                'has_budget' => $this->indicatorHasBudget($specific) ? 1 : 0,
            ];

            if ($hasPraIndicator) {
                $row['pra_indicator_id'] = $praIndicatorId;
                $row['pra_division_code'] = strtoupper($praCode);
            }

            $existingId = null;
            if ($hasPraIndicator) {
                $existingId = DB::table('workplan_tasks')
                    ->where('pra_indicator_id', $praIndicatorId)
                    ->value('id');
            }

            if ($existingId) {
                DB::table('workplan_tasks')->where('id', $existingId)->update($row);
                $workplanId = (int) $existingId;
            } else {
                $row['created_at'] = now();
                $workplanId = (int) DB::table('workplan_tasks')->insertGetId($row);
            }
            $indicatorCount++;

            if (! $hasPraActivity || ! Schema::hasTable('work_planner_tasks')) {
                continue;
            }

            foreach ($specific as $act) {
                if (! is_array($act)) {
                    continue;
                }
                $praActivityId = (int) ($act['id'] ?? 0);
                if ($praActivityId < 1) {
                    continue;
                }

                $start = $this->nullableDate($act['start_date'] ?? null)
                    ?: sprintf('%d-01-01', $year);
                $end = $this->nullableDate($act['end_date'] ?? null)
                    ?: sprintf('%d-12-31', $year);

                $actRow = [
                    'workplan_id' => $workplanId,
                    'activity_name' => mb_substr(trim((string) ($act['title'] ?? 'Activity')), 0, 255),
                    'start_date' => $start,
                    'end_date' => $end,
                    'priority' => 'Medium',
                    'comments' => $this->activityComments($act),
                    'status' => strtoupper((string) ($act['status'] ?? '')) === 'APPROVED' ? 1 : 0,
                    'pra_activity_id' => $praActivityId,
                ];

                $existingAct = DB::table('work_planner_tasks')
                    ->where('pra_activity_id', $praActivityId)
                    ->value('activity_id');

                if ($existingAct) {
                    DB::table('work_planner_tasks')->where('activity_id', $existingAct)->update($actRow);
                } else {
                    $actRow['created_by'] = 0;
                    $actRow['created_at'] = now();
                    DB::table('work_planner_tasks')->insert($actRow);
                }
                $activityCount++;
            }
        }

        return ['indicators' => $indicatorCount, 'activities' => $activityCount];
    }

    /**
     * @param  list<array<string, mixed>>  $specific
     */
    protected function indicatorHasBudget(array $specific): bool
    {
        foreach ($specific as $act) {
            if (! is_array($act)) {
                continue;
            }
            if ((float) ($act['budget_main'] ?? 0) > 0) {
                return true;
            }
            $broad = is_array($act['broad_activity'] ?? null) ? $act['broad_activity'] : [];
            if ((float) ($broad['budget_main'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $act
     */
    protected function activityComments(array $act): ?string
    {
        $bits = [];
        $code = trim((string) ($act['code'] ?? ''));
        if ($code !== '') {
            $bits[] = 'Code: '.$code;
        }
        $status = trim((string) ($act['status'] ?? ''));
        if ($status !== '') {
            $bits[] = 'Status: '.$status;
        }
        $broad = is_array($act['broad_activity'] ?? null) ? $act['broad_activity'] : [];
        $broadCode = trim((string) ($broad['code'] ?? ''));
        if ($broadCode !== '') {
            $bits[] = 'Broad: '.$broadCode;
        }

        return $bits !== [] ? implode(' | ', $bits) : null;
    }

    protected function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = (string) $value;
        $ts = strtotime($raw);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
