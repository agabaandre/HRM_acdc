<?php

namespace Modules\Dashboard\Services;

use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(?int $divisionId = null, ?int $dutyStationId = null, ?int $funderId = null, ?int $jobId = null): array
    {
        $subquery = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        $base = DB::table('staff as s')
            ->join('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
            ->whereIn('sc.staff_contract_id', $subquery)
            ->whereIn('sc.status_id', [1, 2]);

        $applyFilters = function ($query) use ($divisionId, $dutyStationId, $funderId, $jobId): void {
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
        };

        $staffIds = (clone $base)->when(true, $applyFilters)->distinct()->pluck('s.staff_id')->all();

        if ($staffIds === []) {
            return $this->emptyPayload();
        }

        $countWith = function (int $statusId) use ($subquery, $staffIds, $divisionId, $dutyStationId, $funderId, $jobId): int {
            $q = DB::table('staff_contracts as sc')
                ->whereIn('sc.staff_contract_id', $subquery)
                ->whereIn('sc.staff_id', $staffIds)
                ->where('sc.status_id', $statusId);
            if ($divisionId) {
                $q->where('sc.division_id', $divisionId);
            }
            if ($dutyStationId) {
                $q->where('sc.duty_station_id', $dutyStationId);
            }
            if ($funderId) {
                $q->where('sc.funder_id', $funderId);
            }
            if ($jobId) {
                $q->where('sc.job_id', $jobId);
            }

            return $q->distinct('sc.staff_id')->count('sc.staff_id');
        };

        $gender = $this->chartGender($staffIds);

        return [
            'staff' => count($staffIds),
            'two_months' => $countWith(2),
            'staff_renewal' => $countWith(7),
            'expired' => $countWith(3),
            'data_points' => $gender,
            'staff_by_gender' => $gender,
            'staff_by_contract' => $this->chartContractType($staffIds),
            'staff_by_division' => $this->chartDivision($staffIds),
            'staff_by_member_state' => $this->chartMemberState($staffIds),
            'staff_by_funder' => $this->chartFunder($divisionId, $dutyStationId, $funderId, $jobId),
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
     * @param  list<int>  $staffIds
     * @return list<object>
     */
    protected function chartGender(array $staffIds): array
    {
        return DB::table('staff as s')
            ->join('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
            ->whereIn('s.staff_id', $staffIds)
            ->whereIn('sc.status_id', [1, 2])
            ->selectRaw('s.gender as name, COUNT(s.staff_id) as y')
            ->groupBy('s.gender')
            ->get()
            ->all();
    }

    /**
     * @param  list<int>  $staffIds
     * @return array{contract_type: list<string>, value: list<int>}
     */
    protected function chartContractType(array $staffIds): array
    {
        $rows = DB::table('staff as s')
            ->join('staff_contracts as sc', 's.staff_id', '=', 'sc.staff_id')
            ->join('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->whereIn('s.staff_id', $staffIds)
            ->whereIn('sc.status_id', [1, 2])
            ->selectRaw('ct.contract_type, COUNT(s.staff_id) as no')
            ->groupBy('sc.contract_type_id', 'ct.contract_type')
            ->get();

        return [
            'contract_type' => $rows->pluck('contract_type')->all(),
            'value' => $rows->pluck('no')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @param  list<int>  $staffIds
     * @return array{division: list<string>, value: list<int>}
     */
    protected function chartDivision(array $staffIds): array
    {
        $rows = DB::table('staff as s')
            ->join('staff_contracts as sc', 's.staff_id', '=', 'sc.staff_id')
            ->join('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->whereIn('s.staff_id', $staffIds)
            ->whereIn('sc.status_id', [1, 2])
            ->selectRaw('d.division_name, COUNT(s.staff_id) as no')
            ->groupBy('sc.division_id', 'd.division_name')
            ->get();

        return [
            'division' => $rows->pluck('division_name')->all(),
            'value' => $rows->pluck('no')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @param  list<int>  $staffIds
     * @return array{member_states: list<string>, value: list<int>}
     */
    protected function chartMemberState(array $staffIds): array
    {
        $rows = DB::table('staff as s')
            ->join('staff_contracts as sc', 's.staff_id', '=', 'sc.staff_id')
            ->join('nationalities as n', 'n.nationality_id', '=', 's.nationality_id')
            ->whereIn('s.staff_id', $staffIds)
            ->whereIn('sc.status_id', [1, 2])
            ->selectRaw('n.nationality, COUNT(s.staff_id) as tt')
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
     * @return array{funder: list<string>, value: list<int>}
     */
    protected function chartFunder(
        ?int $divisionId,
        ?int $dutyStationId,
        ?int $funderId,
        ?int $jobId
    ): array {
        $subquery = DB::table('staff_contracts')
            ->selectRaw('MAX(staff_contract_id)')
            ->groupBy('staff_id');

        $q = DB::table('staff as s')
            ->join('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
            ->join('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->whereIn('sc.staff_contract_id', $subquery)
            ->whereIn('sc.status_id', [1, 2, 7]);

        if ($divisionId) {
            $q->where('sc.division_id', $divisionId);
        }
        if ($dutyStationId) {
            $q->where('sc.duty_station_id', $dutyStationId);
        }
        if ($funderId) {
            $q->where('sc.funder_id', $funderId);
        }
        if ($jobId) {
            $q->where('sc.job_id', $jobId);
        }

        $rows = $q->selectRaw('f.funder, COUNT(DISTINCT s.staff_id) as no')
            ->groupBy('sc.funder_id', 'f.funder')
            ->orderByDesc('no')
            ->get();

        return [
            'funder' => $rows->pluck('funder')->all(),
            'value' => $rows->pluck('no')->map(fn ($v) => (int) $v)->all(),
        ];
    }
}
