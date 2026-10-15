<?php

namespace Modules\Payroll\Services;

use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\PayrollSetting;

class PayrollSettingsService
{
    public function __construct(private PayrollAuditService $audit) {}

    public function current(): PayrollSetting
    {
        $row = PayrollSetting::query()->first();
        if ($row) {
            return $row;
        }

        return PayrollSetting::query()->create([
            'default_currency' => 'USD',
            'enabled_currencies' => ['USD', 'ETB', 'EUR'],
            'period_close_day' => 25,
            'jurisdiction_default' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): PayrollSetting
    {
        $row = $this->current();
        $before = $row->toArray();

        $default = strtoupper((string) ($data['default_currency'] ?? $row->default_currency));
        $enabled = array_values(array_unique(array_map(
            fn ($c) => strtoupper((string) $c),
            $data['enabled_currencies'] ?? ($row->enabled_currencies ?? [$default])
        )));

        if (! in_array($default, $enabled, true)) {
            $enabled[] = $default;
        }

        if (! preg_match('/^[A-Z]{3}$/', $default)) {
            throw ValidationException::withMessages(['default_currency' => 'Currency must be a 3-letter ISO code.']);
        }

        $closeDay = (int) ($data['period_close_day'] ?? $row->period_close_day);
        if ($closeDay < 1 || $closeDay > 28) {
            throw ValidationException::withMessages(['period_close_day' => 'Period close day must be between 1 and 28.']);
        }

        $row->update([
            'default_currency' => $default,
            'enabled_currencies' => $enabled,
            'period_close_day' => $closeDay,
            'jurisdiction_default' => $data['jurisdiction_default'] ?? $row->jurisdiction_default,
        ]);

        $this->audit->log('settings.update', 'payroll_settings', (int) $row->id, $before, $row->fresh()->toArray());

        return $row->fresh();
    }
}
