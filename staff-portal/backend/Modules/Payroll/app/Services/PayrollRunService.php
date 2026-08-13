<?php

namespace Modules\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\PayrollLoanSchedule;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\PayrollRunLine;
use Modules\Payroll\Models\PayrollRunLineItem;
use Modules\Payroll\Models\PayrollStaffPay;
use Modules\Payroll\Models\PayrollStaffWageItem;
use Modules\Payroll\Models\PayrollTaxRule;
use Modules\Payroll\Models\PayrollWageType;

class PayrollRunService
{
    public function __construct(
        private PayrollSettingsService $settings,
        private PayrollPeriodService $periods,
        private TaxRuleService $taxRules,
        private PayslipService $payslips,
        private PayrollAuditService $audit,
        private PayrollStaffEligibility $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PayrollRun
    {
        $period = PayrollPeriod::query()->findOrFail((int) $data['period_id']);
        if ($period->status === 'closed' && empty($data['off_cycle'])) {
            throw ValidationException::withMessages(['period_id' => 'Period is closed; use an off-cycle run.']);
        }

        $run = PayrollRun::query()->create([
            'period_id' => $period->id,
            'status' => 'draft',
            'off_cycle' => (bool) ($data['off_cycle'] ?? false),
            'title' => $data['title'] ?? ($period->label.' payroll'),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->log('run.create', 'payroll_runs', (int) $run->id, null, $run->toArray());

        return $run->load('period');
    }

    public function simulate(PayrollRun $run): PayrollRun
    {
        if (in_array($run->status, ['posted', 'cancelled'], true)) {
            abort(409, 'Posted or cancelled runs cannot be simulated.');
        }

        $period = $run->period()->firstOrFail();
        $settings = $this->settings->current();

        $staffPays = PayrollStaffPay::query()
            ->where('pay_status', 'active')
            ->whereIn('staff_id', $this->eligibility->activeStaffIdSubquery())
            ->get();
        $taxWageTypeId = PayrollWageType::query()->where('code', 'TAX')->value('id');
        $loanWageTypeId = PayrollWageType::query()->where('code', 'LOAN_DED')->value('id');
        $basicWageTypeId = PayrollWageType::query()->where('code', 'BASIC')->value('id');

        $periodStart = Carbon::create($period->year, $period->month, 1)->startOfDay();
        $periodEnd = (clone $periodStart)->endOfMonth();

        $taxRuleQuery = PayrollTaxRule::query()
            ->with('bands')
            ->where('is_active', true)
            ->where('applies_to', 'employee')
            ->whereDate('effective_from', '<=', $periodEnd->toDateString())
            ->where(function ($q) use ($periodStart): void {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart->toDateString());
            });

        if ($settings->jurisdiction_default) {
            $taxRuleQuery->where(function ($q) use ($settings): void {
                $q->whereNull('jurisdiction_code')
                    ->orWhere('jurisdiction_code', $settings->jurisdiction_default);
            });
        }

        $activeTaxRules = $taxRuleQuery->get();

        return DB::transaction(function () use (
            $run, $period, $staffPays, $taxWageTypeId, $loanWageTypeId, $basicWageTypeId,
            $periodStart, $periodEnd, $activeTaxRules, $settings
        ) {
            $run->lines()->each(function (PayrollRunLine $line): void {
                $line->items()->delete();
                $line->delete();
            });

            $totalGrossDefault = 0.0;
            $totalNetDefault = 0.0;
            $count = 0;

            foreach ($staffPays as $pay) {
                $calc = $this->calculateStaff(
                    $pay,
                    $period,
                    $periodStart,
                    $periodEnd,
                    $activeTaxRules,
                    (int) $basicWageTypeId,
                    $taxWageTypeId ? (int) $taxWageTypeId : null,
                    $loanWageTypeId ? (int) $loanWageTypeId : null,
                );

                $line = PayrollRunLine::query()->create([
                    'run_id' => $run->id,
                    'staff_id' => $pay->staff_id,
                    'currency' => $calc['currency'],
                    'basic' => $calc['basic'],
                    'gross' => $calc['gross'],
                    'taxable' => $calc['taxable'],
                    'tax' => $calc['tax'],
                    'deductions' => $calc['deductions'],
                    'benefits' => $calc['benefits'],
                    'net' => $calc['net'],
                    'fx_rate_to_default' => $calc['fx_rate_to_default'],
                    'net_default' => $calc['net_default'],
                ]);

                foreach ($calc['items'] as $item) {
                    PayrollRunLineItem::query()->create([
                        'run_line_id' => $line->id,
                        'wage_type_id' => $item['wage_type_id'],
                        'category' => $item['category'],
                        'amount' => $item['amount'],
                        'meta' => $item['meta'] ?? null,
                    ]);
                }

                $totalGrossDefault += $calc['gross'] * $calc['fx_rate_to_default'];
                $totalNetDefault += $calc['net_default'];
                $count++;
            }

            $before = $run->toArray();
            $run->update([
                'status' => 'simulated',
                'simulated_at' => now(),
                'staff_count' => $count,
                'total_gross_default' => round($totalGrossDefault, 2),
                'total_net_default' => round($totalNetDefault, 2),
            ]);

            $fresh = $run->fresh()->load('period');
            $this->audit->log('run.simulate', 'payroll_runs', (int) $run->id, $before, $fresh->toArray());

            return $fresh;
        });
    }

    public function post(PayrollRun $run, bool $allowNegativeNet = false): PayrollRun
    {
        if ($run->status !== 'simulated') {
            abort(409, 'Only simulated runs can be posted.');
        }

        $netFloor = (float) config('payroll.net_floor', 0);
        $negatives = $run->lines()->where('net', '<', $netFloor)->count();
        if ($negatives > 0 && ! $allowNegativeNet) {
            throw ValidationException::withMessages([
                'net' => "{$negatives} staff line(s) are below the net floor ({$netFloor}). Pass allow_negative_net to override.",
            ]);
        }

        return DB::transaction(function () use ($run) {
            $before = $run->toArray();

            foreach ($run->lines()->with('items')->get() as $line) {
                foreach ($line->items as $item) {
                    $meta = $item->meta ?? [];
                    if (($meta['loan_schedule_id'] ?? null)) {
                        $scheduleId = (int) $meta['loan_schedule_id'];
                        PayrollLoanSchedule::query()->whereKey($scheduleId)->where('status', 'pending')->update([
                            'status' => 'deducted',
                            'run_line_item_id' => $item->id,
                        ]);

                        $loan = PayrollLoanSchedule::query()->find($scheduleId)?->loan;
                        if ($loan) {
                            $pending = $loan->schedules()->where('status', 'pending')->count();
                            if ($pending === 0) {
                                $loan->update(['status' => 'completed']);
                            }
                        }
                    }
                }
            }

            $this->payslips->generateForRun($run);

            $run->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by_user_id' => auth()->id() ? (int) auth()->id() : null,
            ]);

            $fresh = $run->fresh()->load('period');
            $this->audit->log('run.post', 'payroll_runs', (int) $run->id, $before, $fresh->toArray());

            return $fresh;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollTaxRule>  $taxRules
     * @return array<string, mixed>
     */
    public function calculateStaff(
        PayrollStaffPay $pay,
        PayrollPeriod $period,
        Carbon $periodStart,
        Carbon $periodEnd,
        $taxRules,
        int $basicWageTypeId,
        ?int $taxWageTypeId,
        ?int $loanWageTypeId,
    ): array {
        $currency = strtoupper((string) $pay->currency);
        $basic = round((float) $pay->basic_salary, 2);
        $items = [];
        $items[] = [
            'wage_type_id' => $basicWageTypeId,
            'category' => 'earning',
            'amount' => $basic,
            'meta' => ['code' => 'BASIC'],
        ];

        $earnings = $basic;
        $taxable = $basic;
        $benefits = 0.0;
        $preTaxDeductions = 0.0;
        $postTaxDeductions = 0.0;

        $wageItems = PayrollStaffWageItem::query()
            ->with('wageType')
            ->where('staff_id', $pay->staff_id)
            ->where('is_active', true)
            ->where(function ($q) use ($periodStart): void {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $periodStart->copy()->endOfMonth()->toDateString());
            })
            ->where(function ($q) use ($periodStart): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $periodStart->toDateString());
            })
            ->get();

        foreach ($wageItems as $wi) {
            $wt = $wi->wageType;
            if (! $wt || ! $wt->is_active || $wt->code === 'BASIC') {
                continue;
            }

            $amount = $this->resolveItemAmount($wi, $basic, $earnings);
            if ($amount == 0.0) {
                continue;
            }

            $category = $wt->category;
            $items[] = [
                'wage_type_id' => $wt->id,
                'category' => $category,
                'amount' => $amount,
                'meta' => ['code' => $wt->code, 'staff_wage_item_id' => $wi->id],
            ];

            if ($category === 'earning') {
                $earnings += $amount;
                if ($wt->taxable) {
                    $taxable += $amount;
                }
            } elseif ($category === 'benefit') {
                $benefits += $amount;
                if ($wt->taxable) {
                    $taxable += $amount;
                }
            } elseif ($category === 'deduction') {
                if ($wt->pre_tax) {
                    $preTaxDeductions += $amount;
                    $taxable -= $amount;
                } else {
                    $postTaxDeductions += $amount;
                }
            }
        }

        $taxable = max(0.0, round($taxable, 2));

        $tax = 0.0;
        foreach ($taxRules as $rule) {
            $tax += $this->taxRules->computeTax($taxable, $rule->bands);
        }
        $tax = round($tax, 2);

        if ($tax > 0 && $taxWageTypeId) {
            $items[] = [
                'wage_type_id' => $taxWageTypeId,
                'category' => 'tax',
                'amount' => $tax,
                'meta' => ['code' => 'TAX'],
            ];
        }

        // Loan schedules due this period
        $schedules = PayrollLoanSchedule::query()
            ->where('status', 'pending')
            ->where('due_period_id', $period->id)
            ->whereHas('loan', function ($q) use ($pay): void {
                $q->where('staff_id', $pay->staff_id)->where('status', 'active');
            })
            ->get();

        $loanTotal = 0.0;
        foreach ($schedules as $schedule) {
            $amt = round((float) $schedule->amount, 2);
            $loanTotal += $amt;
            $items[] = [
                'wage_type_id' => $loanWageTypeId,
                'category' => 'deduction',
                'amount' => $amt,
                'meta' => [
                    'code' => 'LOAN_DED',
                    'loan_schedule_id' => $schedule->id,
                    'loan_id' => $schedule->loan_id,
                ],
            ];
        }
        $postTaxDeductions += $loanTotal;

        $gross = round($earnings, 2);
        $deductions = round($preTaxDeductions + $postTaxDeductions + $tax, 2);
        $net = round($gross - $preTaxDeductions - $postTaxDeductions - $tax, 2);

        $fx = $this->periods->rateFor($period, $currency);

        return [
            'currency' => $currency,
            'basic' => $basic,
            'gross' => $gross,
            'taxable' => $taxable,
            'tax' => $tax,
            'deductions' => round($preTaxDeductions + $postTaxDeductions, 2),
            'benefits' => round($benefits, 2),
            'net' => $net,
            'fx_rate_to_default' => $fx,
            'net_default' => round($net * $fx, 2),
            'items' => $items,
        ];
    }

    private function resolveItemAmount(PayrollStaffWageItem $wi, float $basic, float $currentGross): float
    {
        $method = $wi->wageType?->calc_method ?? 'fixed';

        return match ($method) {
            'percent_of_base' => round($basic * ((float) ($wi->percent ?? 0) / 100.0), 2),
            'percent_of_gross' => round($currentGross * ((float) ($wi->percent ?? 0) / 100.0), 2),
            'fixed', 'manual', 'formula' => round((float) ($wi->amount ?? $wi->wageType?->default_amount ?? 0), 2),
            default => round((float) ($wi->amount ?? 0), 2),
        };
    }
}
