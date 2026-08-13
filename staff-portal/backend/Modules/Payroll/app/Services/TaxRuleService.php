<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\PayrollTaxBand;
use Modules\Payroll\Models\PayrollTaxRule;
use Modules\Payroll\Models\PayrollWageType;

class TaxRuleService
{
    public function __construct(private PayrollAuditService $audit) {}

    public function list(): Collection
    {
        return PayrollTaxRule::query()->with('bands')->orderBy('code')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PayrollTaxRule
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->normalizeRule($data);
            if (PayrollTaxRule::query()->where('code', $payload['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'Tax rule code already exists.']);
            }

            $rule = PayrollTaxRule::query()->create($payload);
            $this->replaceBands($rule, $data['bands'] ?? []);
            $this->audit->log('tax_rule.create', 'payroll_tax_rules', (int) $rule->id, null, $rule->load('bands')->toArray());

            return $rule->load('bands');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PayrollTaxRule $rule, array $data): PayrollTaxRule
    {
        return DB::transaction(function () use ($rule, $data) {
            $before = $rule->load('bands')->toArray();
            $rule->update($this->normalizeRule($data, $rule));
            if (array_key_exists('bands', $data)) {
                $this->replaceBands($rule, $data['bands'] ?? []);
            }
            $fresh = $rule->fresh()->load('bands');
            $this->audit->log('tax_rule.update', 'payroll_tax_rules', (int) $rule->id, $before, $fresh->toArray());

            return $fresh;
        });
    }

    /**
     * Progressive band tax: for each band, tax the overlap of taxable amount with [from, to].
     */
    public function computeTax(float $taxable, Collection $bands): float
    {
        $tax = 0.0;
        foreach ($bands as $band) {
            $from = (float) $band->from_amount;
            $to = $band->to_amount !== null ? (float) $band->to_amount : null;
            if ($taxable <= $from) {
                continue;
            }
            $upper = $to === null ? $taxable : min($taxable, $to);
            $slice = max(0.0, $upper - $from);
            $tax += (float) $band->fixed_amount;
            $tax += $slice * ((float) $band->rate_percent / 100.0);
        }

        return round($tax, 2);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRule(array $data, ?PayrollTaxRule $existing = null): array
    {
        $applies = (string) ($data['applies_to'] ?? $existing?->applies_to ?? 'employee');
        if (! in_array($applies, ['employee', 'employer'], true)) {
            throw ValidationException::withMessages(['applies_to' => 'Invalid applies_to.']);
        }

        $wageTypeId = $data['wage_type_id'] ?? $existing?->wage_type_id;
        if ($wageTypeId && ! PayrollWageType::query()->whereKey($wageTypeId)->exists()) {
            throw ValidationException::withMessages(['wage_type_id' => 'Wage type not found.']);
        }

        return [
            'code' => strtoupper(trim((string) ($data['code'] ?? $existing?->code))),
            'name' => trim((string) ($data['name'] ?? $existing?->name)),
            'jurisdiction_code' => $data['jurisdiction_code'] ?? $existing?->jurisdiction_code,
            'effective_from' => $data['effective_from'] ?? $existing?->effective_from?->toDateString(),
            'effective_to' => $data['effective_to'] ?? $existing?->effective_to?->toDateString(),
            'applies_to' => $applies,
            'wage_type_id' => $wageTypeId,
            'is_active' => (bool) ($data['is_active'] ?? $existing?->is_active ?? true),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     */
    private function replaceBands(PayrollTaxRule $rule, array $bands): void
    {
        PayrollTaxBand::query()->where('tax_rule_id', $rule->id)->delete();
        $sort = 0;
        $prevFrom = null;
        foreach ($bands as $band) {
            $from = (float) ($band['from_amount'] ?? 0);
            if ($prevFrom !== null && $from < $prevFrom) {
                throw ValidationException::withMessages(['bands' => 'Bands must be ordered by ascending from_amount.']);
            }
            $prevFrom = $from;
            PayrollTaxBand::query()->create([
                'tax_rule_id' => $rule->id,
                'from_amount' => $from,
                'to_amount' => $band['to_amount'] ?? null,
                'rate_percent' => (float) ($band['rate_percent'] ?? 0),
                'fixed_amount' => (float) ($band['fixed_amount'] ?? 0),
                'sort_order' => (int) ($band['sort_order'] ?? $sort),
            ]);
            $sort++;
        }
    }
}
