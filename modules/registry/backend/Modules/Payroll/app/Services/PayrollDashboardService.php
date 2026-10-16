<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\Models\PayrollLoan;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\PayrollStaffPay;

class PayrollDashboardService
{
    public function __construct(
        private PayrollStaffEligibility $eligibility,
    ) {}

    public function summary(): array
    {
        $openPeriod = PayrollPeriod::query()->where('status', 'open')->orderByDesc('year')->orderByDesc('month')->first();
        $lastRun = PayrollRun::query()->with('period')->orderByDesc('id')->first();
        $pendingLoans = PayrollLoan::query()->where('status', 'pending_supervisor')->count();

        $activeStaffCount = $this->eligibility->activeStaffCount();
        $withPay = PayrollStaffPay::query()
            ->where('pay_status', 'active')
            ->whereIn('staff_id', $this->eligibility->activeStaffIdSubquery())
            ->count();
        $missingPay = max(0, $activeStaffCount - $withPay);

        return [
            'open_period' => $openPeriod,
            'last_run' => $lastRun,
            'pending_loan_approvals' => $pendingLoans,
            'staff_missing_pay_master' => $missingPay,
            'active_staff_count' => $activeStaffCount,
            'staff_with_pay_count' => $withPay,
        ];
    }
}
