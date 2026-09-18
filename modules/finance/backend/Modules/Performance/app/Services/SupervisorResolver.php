<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves supervisors from the staff member's latest contract (CI3 parity).
 */
class SupervisorResolver
{
    /**
     * @return array{contract_id: int|null, supervisor_1: int|null, supervisor_2: int|null, supervisor_1_name: string, supervisor_2_name: string}
     */
    public function fromLatestContract(int $staffId): array
    {
        $contract = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->orderByDesc('staff_contract_id')
            ->first(['staff_contract_id', 'first_supervisor', 'second_supervisor']);

        if (! $contract) {
            return [
                'contract_id' => null,
                'supervisor_1' => null,
                'supervisor_2' => null,
                'supervisor_1_name' => '—',
                'supervisor_2_name' => '—',
            ];
        }

        $s1 = (int) ($contract->first_supervisor ?? 0) ?: null;
        $s2 = (int) ($contract->second_supervisor ?? 0) ?: null;

        return [
            'contract_id' => (int) $contract->staff_contract_id,
            'supervisor_1' => $s1,
            'supervisor_2' => $s2,
            'supervisor_1_name' => $this->staffName($s1),
            'supervisor_2_name' => $this->staffName($s2),
        ];
    }

    public function staffName(?int $staffId): string
    {
        if (! $staffId) {
            return '—';
        }

        $row = DB::table('staff')
            ->where('staff_id', $staffId)
            ->first(['fname', 'lname']);

        return $row ? trim($row->fname.' '.$row->lname) : 'Staff #'.$staffId;
    }

    /**
     * Active staff for supervisor pickers (CI3: latest contract status Active/Due).
     *
     * @param  list<int|null>  $alwaysIncludeIds
     * @return list<array{staff_id: int, name: string}>
     */
    public function activeStaffOptions(array $alwaysIncludeIds = []): array
    {
        $query = DB::table('staff as s')
            ->select(['s.staff_id', 's.fname', 's.lname']);

        if (Schema::hasColumn('staff_contracts', 'status_id')) {
            $latest = DB::table('staff_contracts')
                ->selectRaw('MAX(staff_contract_id)')
                ->groupBy('staff_id');
            $query->join('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
                ->whereIn('sc.staff_contract_id', $latest)
                ->whereIn('sc.status_id', [1, 2])
                ->groupBy('s.staff_id', 's.fname', 's.lname');
        }

        $rows = $query->orderBy('s.fname')->orderBy('s.lname')->get();
        $options = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = (int) $row->staff_id;
            $seen[$id] = true;
            $options[] = [
                'staff_id' => $id,
                'name' => trim(($row->fname ?? '').' '.($row->lname ?? '')) ?: ('Staff #'.$id),
            ];
        }

        foreach (array_unique(array_filter($alwaysIncludeIds)) as $id) {
            $id = (int) $id;
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $options[] = [
                'staff_id' => $id,
                'name' => $this->staffName($id),
            ];
        }

        return $options;
    }
}
