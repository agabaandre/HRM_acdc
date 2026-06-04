<?php

namespace Modules\Leave\Services;

use Modules\Leave\Models\LeavePolicySetting;

class LeavePolicyService
{
    /**
     * Default Africa CDC leave policy (from HR leave module specification).
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'annual_accrual_per_month' => 2.33,
            'annual_min_days_per_year' => 15,
            'annual_max_carry_forward' => 45,
            'annual_max_per_calendar_year' => 45,
            'annual_prorate_mid_year_join' => true,
            'annual_forfeit_unused_minimum' => true,
            'deduct_compensatory_first' => true,
            'compensatory_expiry_months' => 3,
            'compensatory_weekend_travel_months' => 3,
            'compensatory_public_holiday_months' => 3,
            'sick_full_pay_months' => 3,
            'sick_half_pay_months' => 3,
            'sick_unpaid_max_months' => 6,
            'sick_medical_certificate_required' => true,
            'sick_medical_report_after_days' => 10,
            'maternity_calendar_days' => 98,
            'maternity_max_instances' => 4,
            'paternity_working_days' => 10,
            'paternity_max_periods' => 4,
            'calendar_year_start_month' => 1,
            'working_days_per_week' => 5,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = LeavePolicySetting::query()
            ->pluck('setting_value', 'setting_key')
            ->map(fn ($v) => is_array($v) ? $v : [])
            ->all();

        $flat = [];
        foreach ($stored as $key => $value) {
            if (is_array($value) && array_key_exists('value', $value)) {
                $flat[$key] = $value['value'];
            } else {
                $flat[$key] = $value;
            }
        }

        return array_merge($this->defaults(), $flat);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            LeavePolicySetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => ['value' => $value]]
            );
        }
    }
}
