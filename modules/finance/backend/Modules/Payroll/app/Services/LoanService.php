<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\PayrollLoan;
use Modules\Payroll\Models\PayrollLoanSchedule;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollStaffPay;
use Modules\Payroll\Models\PayrollWageType;
use Modules\Payroll\Support\PayrollAccess;

class LoanService
{
    public function __construct(
        private PayrollAuditService $audit,
        private PayrollSettingsService $settings,
        private PayrollStaffEligibility $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, PayrollLoan>
     */
    public function list(array $filters = [])
    {
        $q = PayrollLoan::query()->with(['schedules', 'wageType'])->orderByDesc('id');

        if (! empty($filters['staff_id'])) {
            $q->where('staff_id', (int) $filters['staff_id']);
        }
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['mine']) && PayrollAccess::staffId()) {
            $q->where('staff_id', PayrollAccess::staffId());
        }
        if (! empty($filters['pending_approval'])) {
            $q->where('status', 'pending_supervisor');
        }

        return $q->limit(500)->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(array $data, ?int $userId): PayrollLoan
    {
        $staffId = (int) ($data['staff_id'] ?? PayrollAccess::staffId());
        if (! $staffId) {
            throw ValidationException::withMessages(['staff_id' => 'Staff is required.']);
        }

        $this->eligibility->assertActiveStaff($staffId);

        if (! PayrollAccess::canManageLoans() && $staffId !== PayrollAccess::staffId()) {
            abort(403, 'You can only request loans for yourself.');
        }

        $settings = $this->settings->current();
        $currency = strtoupper((string) ($data['currency'] ?? PayrollStaffPay::query()->where('staff_id', $staffId)->value('currency') ?? $settings->default_currency));
        $type = (string) ($data['type'] ?? 'loan');
        if (! in_array($type, ['loan', 'advance'], true)) {
            throw ValidationException::withMessages(['type' => 'Type must be loan or advance.']);
        }

        $loan = PayrollLoan::query()->create([
            'staff_id' => $staffId,
            'type' => $type,
            'currency' => $currency,
            'principal' => (float) $data['principal'],
            'interest_rate' => (float) ($data['interest_rate'] ?? 0),
            'installment_amount' => $data['installment_amount'] ?? null,
            'installment_count' => $data['installment_count'] ?? null,
            'status' => 'pending_supervisor',
            'requested_by_user_id' => $userId,
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'wage_type_id' => PayrollWageType::query()->where('code', 'LOAN_DED')->value('id'),
        ]);

        $this->audit->log('loan.request', 'payroll_loans', (int) $loan->id, null, $loan->toArray());

        return $loan->load('schedules');
    }

    /**
     * @param  array{decision: string, reason?: string|null}  $data
     */
    public function decide(PayrollLoan $loan, array $data, ?int $userId): PayrollLoan
    {
        if ($loan->status !== 'pending_supervisor') {
            abort(409, 'Loan is not awaiting supervisor approval.');
        }

        $decision = (string) $data['decision'];
        $before = $loan->toArray();

        if ($decision === 'reject') {
            $loan->update([
                'status' => 'rejected',
                'rejected_reason' => $data['reason'] ?? 'Rejected',
                'approved_by_user_id' => $userId,
            ]);
        } elseif ($decision === 'approve') {
            $loan->update([
                'status' => 'pending_payroll',
                'approved_by_user_id' => $userId,
            ]);
        } else {
            throw ValidationException::withMessages(['decision' => 'decision must be approve or reject.']);
        }

        $this->audit->log('loan.decide', 'payroll_loans', (int) $loan->id, $before, $loan->fresh()->toArray());

        return $loan->fresh()->load('schedules');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function disburse(PayrollLoan $loan, array $data): PayrollLoan
    {
        if ($loan->status !== 'pending_payroll') {
            abort(409, 'Loan must be pending payroll disbursement.');
        }

        $period = PayrollPeriod::query()->findOrFail((int) $data['start_period_id']);
        $count = (int) ($data['installment_count'] ?? $loan->installment_count ?? 1);
        if ($count < 1) {
            throw ValidationException::withMessages(['installment_count' => 'At least one installment required.']);
        }

        $principal = (float) $loan->principal;
        $interest = round($principal * ((float) $loan->interest_rate / 100.0), 2);
        $total = $principal + $interest;

        $installmentAmount = isset($data['installment_amount'])
            ? (float) $data['installment_amount']
            : round($total / $count, 2);

        return DB::transaction(function () use ($loan, $period, $count, $installmentAmount, $total, $data) {
            $before = $loan->load('schedules')->toArray();
            $loan->schedules()->delete();

            $allocated = 0.0;
            for ($i = 1; $i <= $count; $i++) {
                $amt = ($i === $count)
                    ? round($total - $allocated, 2)
                    : $installmentAmount;
                $allocated += $amt;

                $duePeriodId = $this->periodIdOffset((int) $period->id, $i - 1);

                PayrollLoanSchedule::query()->create([
                    'loan_id' => $loan->id,
                    'sequence' => $i,
                    'due_period_id' => $duePeriodId,
                    'amount' => $amt,
                    'status' => 'pending',
                ]);
            }

            $loan->update([
                'status' => 'active',
                'disbursed_at' => now(),
                'start_period_id' => $period->id,
                'installment_count' => $count,
                'installment_amount' => $installmentAmount,
            ]);

            $fresh = $loan->fresh()->load('schedules');
            $this->audit->log('loan.disburse', 'payroll_loans', (int) $loan->id, $before, $fresh->toArray());

            return $fresh;
        });
    }

    public function waiveSchedule(PayrollLoanSchedule $schedule): PayrollLoanSchedule
    {
        if ($schedule->status !== 'pending') {
            abort(409, 'Only pending schedules can be waived.');
        }
        $before = $schedule->toArray();
        $schedule->update(['status' => 'waived']);
        $this->audit->log('loan.schedule.waive', 'payroll_loan_schedules', (int) $schedule->id, $before, $schedule->fresh()->toArray());

        return $schedule->fresh();
    }

    private function periodIdOffset(int $startPeriodId, int $offsetMonths): int
    {
        $start = PayrollPeriod::query()->findOrFail($startPeriodId);
        $year = (int) $start->year;
        $month = (int) $start->month + $offsetMonths;
        while ($month > 12) {
            $month -= 12;
            $year++;
        }

        $period = PayrollPeriod::query()->where(['year' => $year, 'month' => $month])->first();
        if ($period) {
            return (int) $period->id;
        }

        // Create open period shell for schedule targeting
        $created = PayrollPeriod::query()->create([
            'year' => $year,
            'month' => $month,
            'label' => sprintf('%04d-%02d', $year, $month),
            'status' => 'open',
        ]);

        return (int) $created->id;
    }
}
