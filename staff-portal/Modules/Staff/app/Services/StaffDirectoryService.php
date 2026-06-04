<?php

namespace Modules\Staff\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PortalTable;

class StaffDirectoryService
{
    /**
     * @param  int|list<int>|null  $statusId
     */
    public function paginate(
        string $search = '',
        int|array|null $statusId = null,
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = $this->baseQuery($search, $statusId)
            ->orderBy('s.lname')
            ->orderBy('s.fname');

        return PortalTable::paginateDistinct($query, 's.staff_id', $perPage, $page);
    }

    /**
     * @return array<string, int>
     */
    public function filterCounts(string $search = ''): array
    {
        return [
            'active' => $this->countForStatus($search, [1, 2]),
            'due' => $this->countForStatus($search, 2),
            'expired' => $this->countForStatus($search, 3),
            'former' => $this->countForStatus($search, 4),
            'renewal' => $this->countForStatus($search, 7),
            'all' => $this->countForStatus($search, null),
        ];
    }

    protected function countForStatus(string $search, int|array|null $statusId): int
    {
        return (int) $this->baseQuery($search, $statusId)->count(DB::raw('DISTINCT s.staff_id'));
    }

    /**
     * @param  int|list<int>|null  $statusId
     */
    protected function baseQuery(string $search, int|array|null $statusId): Builder
    {
        $sub = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        $q = DB::table('staff as s')
            ->joinSub($sub, 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->leftJoin('nationalities as n', 'n.nationality_id', '=', 's.nationality_id')
            ->leftJoin('regions as reg', 'reg.id', '=', 'n.region_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('duty_stations as ds', 'ds.duty_station_id', '=', 'sc.duty_station_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('jobs_acting as ja', 'ja.job_acting_id', '=', 'sc.job_acting_id')
            ->leftJoin('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->leftJoin('status as st', 'st.status_id', '=', 'sc.status_id')
            ->leftJoin('staff as sup1', 'sup1.staff_id', '=', 'sc.first_supervisor')
            ->leftJoin('staff as sup2', 'sup2.staff_id', '=', 'sc.second_supervisor')
            ->select([
                's.staff_id',
                's.SAPNO',
                's.title',
                's.fname',
                's.lname',
                's.oname',
                's.photo',
                's.gender',
                's.date_of_birth',
                's.initiation_date',
                's.work_email',
                's.tel_1',
                's.tel_2',
                's.whatsapp',
                'n.nationality',
                'reg.region_name',
                'g.grade',
                'j.job_name',
                'ja.job_acting',
                'ds.duty_station_name',
                'd.division_name',
                'sc.start_date',
                'sc.end_date',
                'sc.status_id',
                'st.status as contract_status',
                'f.funder',
                DB::raw("TRIM(CONCAT(COALESCE(sup1.fname,''), ' ', COALESCE(sup1.lname,''))) as first_supervisor_name"),
                DB::raw("TRIM(CONCAT(COALESCE(sup2.fname,''), ' ', COALESCE(sup2.lname,''))) as second_supervisor_name"),
            ]);

        if (is_array($statusId)) {
            $q->whereIn('sc.status_id', $statusId);
        } elseif ($statusId !== null) {
            $q->where('sc.status_id', $statusId);
        } else {
            $q->whereIn('sc.status_id', [1, 2, 3, 4, 7]);
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($term): void {
                $w->where('s.fname', 'like', $term)
                    ->orWhere('s.lname', 'like', $term)
                    ->orWhere('s.oname', 'like', $term)
                    ->orWhere('s.work_email', 'like', $term)
                    ->orWhere('s.SAPNO', 'like', $term);
            });
        }

        return $q;
    }
}
