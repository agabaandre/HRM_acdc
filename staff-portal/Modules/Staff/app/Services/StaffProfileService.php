<?php

namespace Modules\Staff\Services;

use Illuminate\Support\Facades\DB;

class StaffProfileService
{
    public function find(int $staffId): ?object
    {
        return DB::table('staff as s')
            ->leftJoin('nationalities as n', 'n.nationality_id', '=', 's.nationality_id')
            ->leftJoin('regions as reg', 'reg.id', '=', 'n.region_id')
            ->where('s.staff_id', $staffId)
            ->select('s.*', 'n.nationality', 'reg.region_name')
            ->first();
    }

    /**
     * @return list<object>
     */
    public function contracts(int $staffId): array
    {
        return DB::table('staff_contracts as sc')
            ->leftJoin('duty_stations as ds', 'ds.duty_station_id', '=', 'sc.duty_station_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('jobs_acting as ja', 'ja.job_acting_id', '=', 'sc.job_acting_id')
            ->leftJoin('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->leftJoin('contracting_institutions as ci', 'ci.contracting_institution_id', '=', 'sc.contracting_institution_id')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->leftJoin('status as st', 'st.status_id', '=', 'sc.status_id')
            ->leftJoin('staff as sup1', 'sup1.staff_id', '=', 'sc.first_supervisor')
            ->leftJoin('staff as sup2', 'sup2.staff_id', '=', 'sc.second_supervisor')
            ->where('sc.staff_id', $staffId)
            ->orderByDesc('sc.staff_contract_id')
            ->select(
                'sc.*',
                'ds.duty_station_name',
                'd.division_name',
                'j.job_name',
                'ja.job_acting',
                'f.funder',
                'ci.contracting_institution',
                'ct.contract_type',
                'g.grade',
                'st.status as status_label',
                DB::raw("TRIM(CONCAT(COALESCE(sup1.fname,''), ' ', COALESCE(sup1.lname,''))) as first_supervisor_name"),
                DB::raw("TRIM(CONCAT(COALESCE(sup2.fname,''), ' ', COALESCE(sup2.lname,''))) as second_supervisor_name")
            )
            ->get()
            ->all();
    }
}
