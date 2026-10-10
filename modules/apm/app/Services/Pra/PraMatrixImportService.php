<?php

namespace App\Services\Pra;

use App\Models\Activity;
use App\Models\Matrix;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PraMatrixImportService
{
    public function __construct(
        protected PraActivitiesCacheService $cache,
    ) {}

    /**
     * Create a draft matrix and draft activity stubs from selected PRA activities.
     *
     * @param  list<int>  $praActivityIds
     * @return array{matrix: Matrix, activities: list<Activity>, key_result_areas: list<array{description: string}>}
     */
    public function createMatrixWithActivities(
        int $divisionId,
        int $focalPersonId,
        int $staffId,
        int $year,
        string $quarter,
        array $praActivityIds,
    ): array {
        $quarter = strtoupper(trim($quarter));
        if (! in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
            throw new RuntimeException('Invalid quarter.');
        }

        if (Matrix::existsForDivisionYearQuarter($divisionId, $year, $quarter)) {
            throw new RuntimeException("A matrix already exists for this division in {$year} {$quarter}.");
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $praActivityIds))));
        if ($ids === []) {
            throw new RuntimeException('Select at least one PRA activity.');
        }

        $available = $this->cache->activitiesForDivision($divisionId, $year, null);
        $byId = [];
        foreach ($available as $row) {
            $byId[(int) $row['pra_activity_id']] = $row;
        }

        $selected = [];
        foreach ($ids as $id) {
            if (! isset($byId[$id])) {
                throw new RuntimeException("PRA activity {$id} is not available for this division.");
            }
            $selected[] = $byId[$id];
        }

        $kras = $this->buildKras($selected);
        $bounds = $this->cache->quarterBounds($year, $quarter);

        return DB::transaction(function () use (
            $divisionId,
            $focalPersonId,
            $staffId,
            $year,
            $quarter,
            $selected,
            $kras,
            $bounds
        ) {
            $matrix = Matrix::create([
                'division_id' => $divisionId,
                'focal_person_id' => $focalPersonId,
                'year' => $year,
                'quarter' => $quarter,
                'key_result_area' => json_encode($kras),
                'staff_id' => $staffId,
                'forward_workflow_id' => null,
                'overall_status' => 'draft',
            ]);

            $approvalService = new ApprovalService;
            $approvalService->updateApprovalOrderMap($matrix);

            $activities = [];
            foreach ($selected as $row) {
                $kraIndex = $this->kraIndexFor($kras, (string) ($row['outcome_area'] ?? ''));
                $dateFrom = $this->clampDate($row['start_date'] ?? null, $bounds['start'], $bounds['end']);
                $dateTo = $this->clampDate($row['end_date'] ?? null, $bounds['start'], $bounds['end'], true);
                if ($dateTo->lt($dateFrom)) {
                    $dateTo = $dateFrom->copy();
                }

                $background = trim((string) ($row['outcome_area'] ?? ''));
                if ($background === '') {
                    $background = (string) ($row['indicator_title'] ?? $row['title'] ?? 'Imported from PRA');
                }

                $activity = $matrix->activities()->create([
                    'staff_id' => $staffId,
                    'pra_activity_id' => (int) $row['pra_activity_id'],
                    'workplan_activity_code' => (string) ($row['code'] ?? ''),
                    'responsible_person_id' => $focalPersonId,
                    'date_from' => $dateFrom->toDateString(),
                    'date_to' => $dateTo->toDateString(),
                    'total_participants' => 1,
                    'total_external_participants' => 0,
                    'key_result_area' => (string) $kraIndex,
                    'request_type_id' => 1,
                    'activity_title' => mb_substr((string) ($row['title'] ?? 'PRA activity'), 0, 200),
                    'background' => $background,
                    'activity_request_remarks' => 'Imported from PRA — complete participants, location and budget before submission.',
                    'forward_workflow_id' => null,
                    'reverse_workflow_id' => 1,
                    'status' => Activity::STATUS_DRAFT,
                    'fund_type_id' => 1,
                    'location_id' => [],
                    'internal_participants' => [],
                    'budget_id' => [],
                    'budget_breakdown' => [],
                    'attachment' => [],
                    'is_single_memo' => 0,
                    'approval_level' => 0,
                    'division_id' => $divisionId,
                    'overall_status' => Activity::STATUS_DRAFT,
                ]);
                $activities[] = $activity;
            }

            return [
                'matrix' => $matrix->fresh(),
                'activities' => $activities,
                'key_result_areas' => $kras,
            ];
        });
    }

    /**
     * Prefill payload for the activity create form.
     *
     * @return array<string, mixed>
     */
    public function prefillForForm(array $praRow, Matrix $matrix): array
    {
        $bounds = $this->cache->quarterBounds((int) $matrix->year, (string) $matrix->quarter);
        $dateFrom = $this->clampDate($praRow['start_date'] ?? null, $bounds['start'], $bounds['end']);
        $dateTo = $this->clampDate($praRow['end_date'] ?? null, $bounds['start'], $bounds['end'], true);
        if ($dateTo->lt($dateFrom)) {
            $dateTo = $dateFrom->copy();
        }

        $kras = is_array($matrix->key_result_area)
            ? $matrix->key_result_area
            : (json_decode((string) $matrix->key_result_area, true) ?: []);
        $kraIndex = $this->kraIndexFor(
            array_map(fn ($k) => ['description' => $k['description'] ?? ''], is_array($kras) ? $kras : []),
            (string) ($praRow['outcome_area'] ?? '')
        );

        $background = trim((string) ($praRow['outcome_area'] ?? ''));
        if ($background === '') {
            $background = (string) ($praRow['indicator_title'] ?? $praRow['title'] ?? '');
        }

        return [
            'pra_activity_id' => (int) ($praRow['pra_activity_id'] ?? 0),
            'activity_title' => mb_substr((string) ($praRow['title'] ?? ''), 0, 200),
            'activity_code' => (string) ($praRow['code'] ?? ''),
            'workplan_activity_code' => (string) ($praRow['code'] ?? ''),
            'key_result_area' => (string) $kraIndex,
            'outcome_area' => (string) ($praRow['outcome_area'] ?? ''),
            'background' => $background,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'q1_budget' => $praRow['q1_budget'] ?? 0,
            'q2_budget' => $praRow['q2_budget'] ?? 0,
            'q3_budget' => $praRow['q3_budget'] ?? 0,
            'q4_budget' => $praRow['q4_budget'] ?? 0,
            'location' => $praRow['location'] ?? null,
        ];
    }

    /**
     * Prefill payload for non-travel / special memo create forms (no matrix context).
     *
     * @return array<string, mixed>
     */
    public function prefillForMemo(array $praRow, ?int $year = null, ?string $quarter = null): array
    {
        $year = $year ?: (int) ($praRow['fiscal_year'] ?? now()->year);
        $quarter = $quarter ? strtoupper(trim($quarter)) : null;
        if ($quarter && in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
            $bounds = $this->cache->quarterBounds($year, $quarter);
            $dateFrom = $this->clampDate($praRow['start_date'] ?? null, $bounds['start'], $bounds['end']);
            $dateTo = $this->clampDate($praRow['end_date'] ?? null, $bounds['start'], $bounds['end'], true);
        } else {
            $dateFrom = $this->parseOrDefault($praRow['start_date'] ?? null, Carbon::create($year, 1, 1));
            $dateTo = $this->parseOrDefault($praRow['end_date'] ?? null, Carbon::create($year, 12, 31), true);
        }
        if ($dateTo->lt($dateFrom)) {
            $dateTo = $dateFrom->copy();
        }

        $background = trim((string) ($praRow['outcome_area'] ?? ''));
        if ($background === '') {
            $background = (string) ($praRow['indicator_title'] ?? $praRow['title'] ?? '');
        }

        $title = mb_substr((string) ($praRow['title'] ?? ''), 0, 200);

        return [
            'pra_activity_id' => (int) ($praRow['pra_activity_id'] ?? 0),
            'activity_title' => $title,
            'title' => $title,
            'activity_code' => (string) ($praRow['code'] ?? ''),
            'workplan_activity_code' => (string) ($praRow['code'] ?? ''),
            'outcome_area' => (string) ($praRow['outcome_area'] ?? ''),
            'background' => $background,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'memo_date' => $dateFrom->format('Y-m-d'),
            'q1_budget' => $praRow['q1_budget'] ?? 0,
            'q2_budget' => $praRow['q2_budget'] ?? 0,
            'q3_budget' => $praRow['q3_budget'] ?? 0,
            'q4_budget' => $praRow['q4_budget'] ?? 0,
        ];
    }

    protected function parseOrDefault(mixed $value, Carbon $fallback, bool $endOfDay = false): Carbon
    {
        if ($value === null || $value === '') {
            return $endOfDay ? $fallback->copy()->endOfDay() : $fallback->copy()->startOfDay();
        }
        try {
            $d = Carbon::parse((string) $value);

            return $endOfDay ? $d->endOfDay() : $d->startOfDay();
        } catch (\Throwable) {
            return $endOfDay ? $fallback->copy()->endOfDay() : $fallback->copy()->startOfDay();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $selected
     * @return list<array{description: string}>
     */
    protected function buildKras(array $selected): array
    {
        $seen = [];
        $kras = [];
        foreach ($selected as $row) {
            $desc = trim((string) ($row['outcome_area'] ?? ''));
            if ($desc === '') {
                $desc = trim((string) ($row['indicator_title'] ?? 'Outcome area'));
            }
            $key = mb_strtolower($desc);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $kras[] = ['description' => $desc];
        }
        if ($kras === []) {
            $kras[] = ['description' => 'Quarterly workplan outcomes'];
        }

        return $kras;
    }

    /**
     * @param  list<array{description: string}>  $kras
     */
    protected function kraIndexFor(array $kras, string $outcomeArea): int
    {
        $needle = mb_strtolower(trim($outcomeArea));
        foreach ($kras as $i => $kra) {
            if (mb_strtolower(trim((string) ($kra['description'] ?? ''))) === $needle) {
                return (int) $i;
            }
        }

        return 0;
    }

    protected function clampDate(mixed $value, Carbon $qStart, Carbon $qEnd, bool $preferEnd = false): Carbon
    {
        try {
            if ($value) {
                $d = Carbon::parse((string) $value)->startOfDay();
                if ($d->lt($qStart)) {
                    return $qStart->copy();
                }
                if ($d->gt($qEnd)) {
                    return $qEnd->copy()->startOfDay();
                }

                return $d;
            }
        } catch (\Throwable) {
            // fall through
        }

        return $preferEnd ? $qEnd->copy()->startOfDay() : $qStart->copy();
    }
}
