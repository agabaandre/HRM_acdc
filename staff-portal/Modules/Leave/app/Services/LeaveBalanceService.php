<?php

namespace Modules\Leave\Services;

use Carbon\Carbon;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Models\StaffLeaveCompensatoryCredit;
use Modules\Leave\Models\StaffLeaveOpeningBalance;
use Modules\Staff\Models\Staff;

class LeaveBalanceService
{
    public function __construct(
        protected LeavePolicyService $policy,
    ) {}

    /**
     * @return array{
     *     entitlement: float,
     *     accrued: float,
     *     opening: float,
     *     carried_forward: float,
     *     compensatory: float,
     *     used: float,
     *     pending: float,
     *     available: float,
     *     year: int
     * }
     */
    public function snapshot(int $staffId, int $leaveTypeId, ?int $year = null): array
    {
        $year = $year ?? (int) now()->year;
        $type = LeaveType::query()->findOrFail($leaveTypeId);
        $opening = $this->openingRecord($staffId, $leaveTypeId, $year);

        $openingDays = (float) ($opening?->opening_days ?? 0);
        $carriedForward = (float) ($opening?->carried_forward_days ?? 0);
        $compensatory = $this->compensatoryAvailable($staffId) + (float) ($opening?->compensatory_days ?? 0);

        $entitlement = $this->entitlementForType($type, $staffId, $year);
        $accrued = $type->is_accrued ? $this->accruedDays($staffId, $type, $year) : 0;
        $used = $this->usedDays($staffId, $leaveTypeId, $year);
        $pending = $this->pendingDays($staffId, $leaveTypeId, $year);

        $available = max(0, $openingDays + $carriedForward + $entitlement + $accrued - $used - $pending);

        return [
            'entitlement' => round($entitlement, 2),
            'accrued' => round($accrued, 2),
            'opening' => round($openingDays, 2),
            'carried_forward' => round($carriedForward, 2),
            'compensatory' => round($compensatory, 2),
            'used' => round($used, 2),
            'pending' => round($pending, 2),
            'available' => round($available, 2),
            'year' => $year,
        ];
    }

    /**
     * @return array<int, array{type: LeaveType, balance: array<string, float|int>}>
     */
    public function allTypesForStaff(int $staffId, ?int $year = null): array
    {
        $types = LeaveType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('leave_name')->get();
        $result = [];
        foreach ($types as $type) {
            $result[] = [
                'type' => $type,
                'balance' => $this->snapshot($staffId, (int) $type->leave_id, $year),
            ];
        }

        return $result;
    }

    public function compensatoryAvailable(int $staffId): float
    {
        return (float) StaffLeaveCompensatoryCredit::query()
            ->where('staff_id', $staffId)
            ->where(function ($q): void {
                $q->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString());
            })
            ->get()
            ->sum(fn (StaffLeaveCompensatoryCredit $c) => $c->remainingDays());
    }

    protected function openingRecord(int $staffId, int $leaveTypeId, int $year): ?StaffLeaveOpeningBalance
    {
        return StaffLeaveOpeningBalance::query()
            ->where('staff_id', $staffId)
            ->where('leave_id', $leaveTypeId)
            ->where('calendar_year', $year)
            ->first();
    }

    protected function entitlementForType(LeaveType $type, int $staffId, int $year): float
    {
        if ($type->is_accrued) {
            return 0;
        }

        if ($type->max_days_per_year !== null) {
            return (float) $type->max_days_per_year;
        }

        return (float) ($type->leave_days ?? 0);
    }

    protected function accruedDays(int $staffId, LeaveType $type, int $year): float
    {
        $rate = (float) ($type->accrual_rate ?: $this->policy->get('annual_accrual_per_month', 2.33));
        $months = $this->completedMonthsInYear($staffId, $year);

        if ($this->policy->get('annual_prorate_mid_year_join', true)) {
            return round($months * $rate, 2);
        }

        return round(12 * $rate, 2);
    }

    protected function completedMonthsInYear(int $staffId, int $year): int
    {
        $staff = Staff::query()->find($staffId);
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();
        $now = now();

        $employedRaw = $staff->initiation_date ?? $staff->date_of_birth ?? null;
        if ($staff && ! empty($employedRaw)) {
            try {
                $employed = Carbon::parse($employedRaw)->startOfDay();
                if ($employed->year === $year && $employed->gt($start)) {
                    $start = $employed;
                }
            } catch (\Throwable) {
                //
            }
        }

        if ($now->year < $year) {
            return 0;
        }

        $periodEnd = $now->year === $year ? $now : $end;

        return max(0, (int) $start->diffInMonths($periodEnd) + 1);
    }

    protected function usedDays(int $staffId, int $leaveTypeId, int $year): float
    {
        return (float) StaffLeave::query()
            ->where('staff_id', $staffId)
            ->where('leave_id', $leaveTypeId)
            ->where('overall_status', 'Approved')
            ->whereYear('start_date', $year)
            ->sum('requested_days');
    }

    protected function pendingDays(int $staffId, int $leaveTypeId, int $year): float
    {
        return (float) StaffLeave::query()
            ->where('staff_id', $staffId)
            ->where('leave_id', $leaveTypeId)
            ->where('overall_status', 'Pending')
            ->whereYear('start_date', $year)
            ->sum('requested_days');
    }

    /**
     * @param  array<string, float>  $rows  keyed by leave_id
     */
    public function saveOpeningBalances(int $staffId, int $year, array $rows, ?int $userId = null): void
    {
        foreach ($rows as $leaveId => $data) {
            StaffLeaveOpeningBalance::query()->updateOrCreate(
                [
                    'staff_id' => $staffId,
                    'leave_id' => (int) $leaveId,
                    'calendar_year' => $year,
                ],
                [
                    'opening_days' => (float) ($data['opening_days'] ?? 0),
                    'carried_forward_days' => (float) ($data['carried_forward_days'] ?? 0),
                    'compensatory_days' => (float) ($data['compensatory_days'] ?? 0),
                    'notes' => $data['notes'] ?? null,
                    'updated_by_user_id' => $userId,
                ]
            );
        }
    }
}
