<?php

namespace Modules\Payroll\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Staff\Services\StaffContractService;

class PayrollStaffEligibility
{
    /**
     * Latest-contract staff whose current contract is Active / Due / Under Renewal.
     */
    public function activeStaffIdSubquery(): Builder
    {
        $latest = DB::table('staff_contracts')
            ->select('staff_id', DB::raw('MAX(staff_contract_id) as staff_contract_id'))
            ->groupBy('staff_id');

        return DB::table('staff_contracts as sc')
            ->joinSub($latest, 'latest', function ($join): void {
                $join->on('latest.staff_contract_id', '=', 'sc.staff_contract_id');
            })
            ->whereIn('sc.status_id', StaffContractService::CURRENT_STATUSES)
            ->select('sc.staff_id');
    }

    /**
     * @return list<int>
     */
    public function activeStaffIds(): array
    {
        return $this->activeStaffIdSubquery()
            ->pluck('staff_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function isActiveStaff(int $staffId): bool
    {
        if ($staffId <= 0) {
            return false;
        }

        return $this->activeStaffIdSubquery()
            ->where('sc.staff_id', $staffId)
            ->exists();
    }

    public function assertActiveStaff(int $staffId): void
    {
        if (! $this->isActiveStaff($staffId)) {
            throw ValidationException::withMessages([
                'staff_id' => 'Payroll only applies to active staff (current contract).',
            ]);
        }
    }

    public function activeStaffCount(): int
    {
        return (int) $this->activeStaffIdSubquery()->count();
    }
}
