<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\PayrollFxRate;
use Modules\Payroll\Models\PayrollPeriod;

class PayrollPeriodService
{
    public function __construct(
        private PayrollSettingsService $settings,
        private PayrollAuditService $audit,
    ) {}

    public function list(): Collection
    {
        return PayrollPeriod::query()->with('fxRates')->orderByDesc('year')->orderByDesc('month')->get();
    }

    /**
     * @param  array{year:int,month:int,label?:string}  $data
     */
    public function create(array $data): PayrollPeriod
    {
        $year = (int) $data['year'];
        $month = (int) $data['month'];
        if ($month < 1 || $month > 12) {
            throw ValidationException::withMessages(['month' => 'Month must be 1–12.']);
        }

        if (PayrollPeriod::query()->where(['year' => $year, 'month' => $month])->exists()) {
            throw ValidationException::withMessages(['month' => 'Period already exists.']);
        }

        $label = $data['label'] ?? sprintf('%04d-%02d', $year, $month);
        $period = PayrollPeriod::query()->create([
            'year' => $year,
            'month' => $month,
            'label' => $label,
            'status' => 'open',
        ]);

        $default = $this->settings->current()->default_currency;
        PayrollFxRate::query()->create([
            'period_id' => $period->id,
            'currency' => $default,
            'rate_to_default' => 1,
        ]);

        $this->audit->log('period.create', 'payroll_periods', (int) $period->id, null, $period->toArray());

        return $period->load('fxRates');
    }

    public function close(PayrollPeriod $period): PayrollPeriod
    {
        $before = $period->toArray();
        $period->update(['status' => 'closed']);
        $this->audit->log('period.close', 'payroll_periods', (int) $period->id, $before, $period->fresh()->toArray());

        return $period->fresh()->load('fxRates');
    }

    /**
     * @param  list<array{currency:string,rate_to_default:float|int|string}>  $rates
     */
    public function upsertFx(PayrollPeriod $period, array $rates): PayrollPeriod
    {
        $default = $this->settings->current()->default_currency;
        $before = $period->fxRates()->get()->toArray();

        foreach ($rates as $row) {
            $currency = strtoupper((string) $row['currency']);
            $rate = (float) $row['rate_to_default'];
            if ($currency === $default) {
                $rate = 1.0;
            }
            if ($rate <= 0) {
                throw ValidationException::withMessages(['rates' => "FX rate for {$currency} must be > 0."]);
            }

            PayrollFxRate::query()->updateOrCreate(
                ['period_id' => $period->id, 'currency' => $currency],
                ['rate_to_default' => $rate],
            );
        }

        PayrollFxRate::query()->updateOrCreate(
            ['period_id' => $period->id, 'currency' => $default],
            ['rate_to_default' => 1],
        );

        $fresh = $period->fresh()->load('fxRates');
        $this->audit->log('period.fx', 'payroll_periods', (int) $period->id, $before, $fresh->fxRates->toArray());

        return $fresh;
    }

    public function rateFor(PayrollPeriod $period, string $currency): float
    {
        $currency = strtoupper($currency);
        $default = $this->settings->current()->default_currency;
        if ($currency === $default) {
            return 1.0;
        }

        $row = PayrollFxRate::query()
            ->where('period_id', $period->id)
            ->where('currency', $currency)
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'fx' => "Missing FX rate for {$currency} in period {$period->label}.",
            ]);
        }

        return (float) $row->rate_to_default;
    }
}
