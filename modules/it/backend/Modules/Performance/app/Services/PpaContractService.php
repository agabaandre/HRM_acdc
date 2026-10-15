<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;

class PpaContractService
{
    public function forStaff(int $staffId): ?object
    {
        $contractId = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->max('staff_contract_id');

        if (! $contractId) {
            return null;
        }

        return $this->forContract((int) $contractId);
    }

    public function forContract(int $contractId): ?object
    {
        return DB::table('staff_contracts as sc')
            ->join('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->join('staff as s', 's.staff_id', '=', 'sc.staff_id')
            ->leftJoin('jobs_acting as ja', 'ja.job_acting_id', '=', 'sc.job_acting_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->where('sc.staff_contract_id', $contractId)
            ->select(
                'sc.*',
                's.fname',
                's.lname',
                's.SAPNO',
                'j.job_name',
                'ja.job_acting as job_acting_name',
                'd.division_name',
                'f.funder',
                'ct.contract_type',
            )
            ->first();
    }

    public function divisionLabel(?int $divisionId): string
    {
        if (! $divisionId) {
            return '—';
        }

        $row = DB::table('divisions')->where('division_id', $divisionId)->value('division_name');

        return $row ? (string) $row : '—';
    }

    public function emptyContractStub(int $staffContractId = 0): object
    {
        return (object) [
            'staff_contract_id' => $staffContractId,
            'fname' => '',
            'lname' => '',
            'SAPNO' => '',
            'job_name' => '',
            'initiation_date' => '',
            'division_id' => null,
            'division_name' => '—',
            'first_supervisor' => null,
            'second_supervisor' => null,
            'funder_id' => null,
            'funder' => '—',
            'contract_type_id' => null,
            'contract_type' => '—',
        ];
    }
}
