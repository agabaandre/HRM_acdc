<?php

namespace Modules\Dashboard\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @var list<int>|null */
    private ?array $latestContractIdsCache = null;

    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(?int $divisionId = null, ?int $dutyStationId = null, ?int $funderId = null, ?int $jobId = null): array
    {
        $latestCids = $this->latestContractIds();
        if ($latestCids === []) {
            return $this->emptyPayload();
        }

        $active = $this->contractsByIds($latestCids)
            ->whereIn('sc.status_id', [1, 2]);
        $this->applyFilters($active, $divisionId, $dutyStationId, $funderId, $jobId);

        $staffCount = (int) (clone $active)->distinct()->count('s.staff_id');
        if ($staffCount === 0) {
            return $this->emptyPayload();
        }

        $statusCounts = $this->statusCounts($latestCids, $divisionId, $dutyStationId, $funderId, $jobId);
        $gender = $this->chartGender($active);

        return [
            'staff' => $staffCount,
            'two_months' => (int) ($statusCounts[2] ?? 0),
            'staff_renewal' => (int) ($statusCounts[7] ?? 0),
            'expired' => (int) ($statusCounts[3] ?? 0),
            'data_points' => $gender,
            'staff_by_gender' => $gender,
            'staff_by_contract' => $this->chartContractType($active),
            'staff_by_division' => $this->chartDivision($active),
            'staff_by_member_state' => $this->chartMemberState($active),
            'staff_by_funder' => $this->chartFunder($latestCids, $divisionId, $dutyStationId, $funderId, $jobId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyPayload(): array
    {
        return [
            'staff' => 0,
            'two_months' => 0,
            'staff_renewal' => 0,
            'expired' => 0,
            'data_points' => [],
            'staff_by_gender' => [],
            'staff_by_contract' => ['contract_type' => [], 'value' => []],
            'staff_by_division' => ['division' => [], 'value' => []],
            'staff_by_member_state' => ['member_states' => [], 'value' => []],
            'staff_by_funder' => ['funder' => [], 'value' => []],
        ];
    }

    /**
     * Materialize latest contract ids once — avoids repeating MAX()/GROUP BY per chart.
     *
     * @return list<int>
     */
    protected function latestContractIds(): array
    {
        if ($this->latestContractIdsCache !== null) {
            return $this->latestContractIdsCache;
        }

        $this->latestContractIdsCache = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id) as cid')
            ->groupBy('staff_id')
            ->pluck('cid')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->latestContractIdsCache;
    }

    /**
     * @param  list<int>  $contractIds
     */
    protected function contractsByIds(array $contractIds): Builder
    {
        return DB::table('staff_contracts as sc')
            ->join('staff as s', 's.staff_id', '=', 'sc.staff_id')
            ->whereIn('sc.staff_contract_id', $contractIds);
    }

    protected function applyFilters(
        Builder $query,
        ?int $divisionId,
        ?int $dutyStationId,
        ?int $funderId,
        ?int $jobId,
    ): void {
        if ($divisionId) {
            $query->where('sc.division_id', $divisionId);
        }
        if ($dutyStationId) {
            $query->where('sc.duty_station_id', $dutyStationId);
        }
        if ($funderId) {
            $query->where('sc.funder_id', $funderId);
        }
        if ($jobId) {
            $query->where('sc.job_id', $jobId);
        }
    }

    /**
     * @param  list<int>  $latestCids
     * @return array<int, int>
     */
    protected function statusCounts(
        array $latestCids,
        ?int $divisionId,
        ?int $dutyStationId,
        ?int $funderId,
        ?int $jobId,
    ): array {
        $q = $this->contractsByIds($latestCids)
            ->whereIn('sc.status_id', [2, 3, 7])
            ->selectRaw('sc.status_id, COUNT(DISTINCT s.staff_id) as n')
            ->groupBy('sc.status_id');

        $this->applyFilters($q, $divisionId, $dutyStationId, $funderId, $jobId);

        $out = [];
        foreach ($q->get() as $row) {
            $out[(int) $row->status_id] = (int) $row->n;
        }

        return $out;
    }

    /**
     * @return list<object>
     */
    protected function chartGender(Builder $active): array
    {
        return (clone $active)
            ->selectRaw('s.gender as name, COUNT(DISTINCT s.staff_id) as y')
            ->groupBy('s.gender')
            ->get()
            ->all();
    }

    /**
     * @return array{contract_type: list<string>, value: list<int>}
     */
    protected function chartContractType(Builder $active): array
    {
        $rows = (clone $active)
            ->join('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->selectRaw('ct.contract_type, COUNT(DISTINCT s.staff_id) as no')
            ->groupBy('sc.contract_type_id', 'ct.contract_type')
            ->get();

        return [
            'contract_type' => $rows->pluck('contract_type')->all(),
            'value' => $rows->pluck('no')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @return array{division: list<string>, value: list<int>}
     */
    protected function chartDivision(Builder $active): array
    {
        $rows = (clone $active)
            ->join('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->selectRaw('d.division_name, COUNT(DISTINCT s.staff_id) as no')
            ->groupBy('sc.division_id', 'd.division_name')
            ->get();

        return [
            'division' => $rows->pluck('division_name')->all(),
            'value' => $rows->pluck('no')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @return array{member_states: list<string>, value: list<int>}
     */
    protected function chartMemberState(Builder $active): array
    {
        $rows = (clone $active)
            ->join('nationalities as n', 'n.nationality_id', '=', 's.nationality_id')
            ->selectRaw('n.nationality, COUNT(DISTINCT s.staff_id) as tt')
            ->groupBy('s.nationality_id', 'n.nationality')
            ->get();

        return [
            'member_states' => $rows->pluck('nationality')->all(),
            'value' => $rows->pluck('tt')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * Active staff by funder (status 1, 2, 7) — matches CI3 dashboard.
     *
     * @param  list<int>  $latestCids
     * @return array{funder: list<string>, value: list<int>}
     */
    protected function chartFunder(
        array $latestCids,
        ?int $divisionId,
        ?int $dutyStationId,
        ?int $funderId,
        ?int $jobId
    ): array {
        $q = $this->contractsByIds($latestCids)
            ->join('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->whereIn('sc.status_id', [1, 2, 7]);

        $this->applyFilters($q, $divisionId, $dutyStationId, $funderId, $jobId);

        $rows = $q->selectRaw('f.funder, COUNT(DISTINCT s.staff_id) as no')
            ->groupBy('sc.funder_id', 'f.funder')
            ->orderByDesc('no')
            ->get();

        return [
            'funder' => $rows->pluck('funder')->all(),
            'value' => $rows->pluck('no')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * Upcoming birthdays (CI3 FullCalendar events shape).
     *
     * @return list<array<string, mixed>>
     */
    public function birthdayEvents(
        ?int $divisionId = null,
        ?int $dutyStationId = null,
        ?int $funderId = null,
        ?int $jobId = null
    ): array {
        $month = (int) date('n');
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $latestCids = $this->latestContractIds();
        if ($latestCids === []) {
            return [];
        }

        $q = $this->contractsByIds($latestCids)
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('duty_stations as ds', 'ds.duty_station_id', '=', 'sc.duty_station_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->whereIn('sc.status_id', [1, 2])
            ->whereNotNull('s.date_of_birth')
            ->where('s.date_of_birth', '!=', '0000-00-00')
            ->where('s.date_of_birth', 'not like', '0000-00-00%')
            ->where(function ($w) use ($month, $nextMonth): void {
                $w->whereRaw('MONTH(s.date_of_birth) = ?', [$month])
                    ->orWhereRaw('MONTH(s.date_of_birth) = ?', [$nextMonth]);
            });

        $this->applyFilters($q, $divisionId, $dutyStationId, $funderId, $jobId);

        $rows = $q->get([
            's.staff_id',
            's.fname',
            's.lname',
            's.title',
            's.date_of_birth',
            'g.grade',
            'j.job_name',
            'ds.duty_station_name',
            'd.division_name',
        ]);

        $today = new \DateTimeImmutable('today');
        $events = [];
        foreach ($rows as $staff) {
            $dob = (string) ($staff->date_of_birth ?? '');
            if ($dob === '' || strtotime($dob) === false) {
                continue;
            }
            try {
                $dobObj = new \DateTimeImmutable($dob);
            } catch (\Throwable) {
                continue;
            }
            $next = $dobObj->setDate((int) $today->format('Y'), (int) $dobObj->format('m'), (int) $dobObj->format('d'));
            if ($next < $today) {
                $next = $next->modify('+1 year');
            }
            $age = (int) $today->format('Y') - (int) $dobObj->format('Y');
            if ($next->format('Y') > $today->format('Y')) {
                $age++;
            }
            $name = trim(($staff->title ? $staff->title.' ' : '').$staff->fname.' '.$staff->lname);
            $events[] = [
                'id' => (int) $staff->staff_id,
                'title' => $name,
                'start' => $next->format('Y-m-d'),
                'age' => $age,
                'job_name' => $staff->job_name,
                'grade' => $staff->grade,
                'division_name' => $staff->division_name,
                'duty_station_name' => $staff->duty_station_name,
            ];
        }

        usort($events, static fn ($a, $b) => strcmp((string) $a['start'], (string) $b['start']));

        return array_slice($events, 0, 60);
    }
}
