<?php

namespace Modules\Performance\Services;

use Illuminate\Support\Facades\DB;

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
}
