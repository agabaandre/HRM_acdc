<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\PayrollWageType;

class WageTypeService
{
    public function __construct(private PayrollAuditService $audit) {}

    public function list(?bool $activeOnly = null): Collection
    {
        $q = PayrollWageType::query()->orderBy('sort_order')->orderBy('name');
        if ($activeOnly === true) {
            $q->where('is_active', true);
        }

        return $q->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PayrollWageType
    {
        $payload = $this->normalize($data);
        if (PayrollWageType::query()->where('code', $payload['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Wage type code already exists.']);
        }

        $row = PayrollWageType::query()->create($payload);
        $this->audit->log('wage_type.create', 'payroll_wage_types', (int) $row->id, null, $row->toArray());

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PayrollWageType $type, array $data): PayrollWageType
    {
        $before = $type->toArray();
        $payload = $this->normalize($data, $type);
        if ($type->is_system) {
            unset($payload['code'], $payload['is_system'], $payload['category']);
        }
        $type->update($payload);
        $this->audit->log('wage_type.update', 'payroll_wage_types', (int) $type->id, $before, $type->fresh()->toArray());

        return $type->fresh();
    }

    public function deactivate(PayrollWageType $type): PayrollWageType
    {
        if ($type->is_system) {
            throw ValidationException::withMessages(['code' => 'System wage types cannot be deleted.']);
        }

        $before = $type->toArray();
        $type->update(['is_active' => false]);
        $this->audit->log('wage_type.deactivate', 'payroll_wage_types', (int) $type->id, $before, $type->fresh()->toArray());

        return $type->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?PayrollWageType $existing = null): array
    {
        $categories = ['earning', 'benefit', 'tax', 'deduction', 'employer_contrib'];
        $methods = ['fixed', 'percent_of_base', 'percent_of_gross', 'manual', 'formula'];

        $category = (string) ($data['category'] ?? $existing?->category ?? 'earning');
        $method = (string) ($data['calc_method'] ?? $existing?->calc_method ?? 'fixed');
        if (! in_array($category, $categories, true)) {
            throw ValidationException::withMessages(['category' => 'Invalid category.']);
        }
        if (! in_array($method, $methods, true)) {
            throw ValidationException::withMessages(['calc_method' => 'Invalid calc method.']);
        }

        return [
            'code' => strtoupper(trim((string) ($data['code'] ?? $existing?->code))),
            'name' => trim((string) ($data['name'] ?? $existing?->name)),
            'category' => $category,
            'calc_method' => $method,
            'percent_base' => $data['percent_base'] ?? $existing?->percent_base,
            'default_amount' => $data['default_amount'] ?? $existing?->default_amount,
            'taxable' => (bool) ($data['taxable'] ?? $existing?->taxable ?? true),
            'pre_tax' => (bool) ($data['pre_tax'] ?? $existing?->pre_tax ?? false),
            'is_system' => (bool) ($data['is_system'] ?? $existing?->is_system ?? false),
            'is_active' => (bool) ($data['is_active'] ?? $existing?->is_active ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? $existing?->sort_order ?? 0),
        ];
    }
}
