<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\PayrollStaffPay;
use Modules\Payroll\Models\PayrollStaffWageItem;
use Modules\Payroll\Models\PayrollWageType;

class StaffPayService
{
    public function __construct(
        private PayrollSettingsService $settings,
        private PayrollAuditService $audit,
        private PayrollStaffEligibility $eligibility,
    ) {}

    public function directory(): Collection
    {
        return DB::table('payroll_staff_pay as p')
            ->join('staff as s', 's.staff_id', '=', 'p.staff_id')
            ->whereIn('p.staff_id', $this->eligibility->activeStaffIdSubquery())
            ->select([
                'p.*',
                's.SAPNO as sap_number',
                's.work_email',
                DB::raw("TRIM(CONCAT(COALESCE(s.title,''), ' ', COALESCE(s.fname,''), ' ', COALESCE(s.oname,''), ' ', COALESCE(s.lname,''))) as staff_name"),
            ])
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->orderBy('p.staff_id')
            ->get()
            ->map(function ($row) {
                $row->staff_name = trim(preg_replace('/\s+/', ' ', (string) $row->staff_name)) ?: null;

                return $row;
            });
    }

    public function get(int $staffId): ?PayrollStaffPay
    {
        return PayrollStaffPay::query()->where('staff_id', $staffId)->first();
    }

    /**
     * @return array{staff_id: int, staff_name: ?string, sap_number: ?string, work_email: ?string}|null
     */
    public function staffIdentity(int $staffId): ?array
    {
        $row = DB::table('staff')
            ->where('staff_id', $staffId)
            ->select([
                'staff_id',
                'SAPNO as sap_number',
                'work_email',
                DB::raw("TRIM(CONCAT(COALESCE(title,''), ' ', COALESCE(fname,''), ' ', COALESCE(oname,''), ' ', COALESCE(lname,''))) as staff_name"),
            ])
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'staff_id' => (int) $row->staff_id,
            'staff_name' => trim(preg_replace('/\s+/', ' ', (string) $row->staff_name)) ?: null,
            'sap_number' => $row->sap_number !== null ? (string) $row->sap_number : null,
            'work_email' => $row->work_email !== null ? (string) $row->work_email : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(int $staffId, array $data): PayrollStaffPay
    {
        if (! DB::table('staff')->where('staff_id', $staffId)->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'Staff record not found.']);
        }

        $this->eligibility->assertActiveStaff($staffId);

        $settings = $this->settings->current();
        $currency = strtoupper((string) ($data['currency'] ?? $settings->default_currency));
        $enabled = $settings->enabled_currencies ?? [$settings->default_currency];
        if (! in_array($currency, $enabled, true)) {
            throw ValidationException::withMessages(['currency' => 'Currency is not enabled in payroll settings.']);
        }

        $status = (string) ($data['pay_status'] ?? 'active');
        if (! in_array($status, ['active', 'held', 'terminated'], true)) {
            throw ValidationException::withMessages(['pay_status' => 'Invalid pay status.']);
        }

        $payload = [
            'staff_id' => $staffId,
            'currency' => $currency,
            'basic_salary' => (float) ($data['basic_salary'] ?? 0),
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
            'tax_identifier' => $data['tax_identifier'] ?? null,
            'pay_status' => $status,
            'notes' => $data['notes'] ?? null,
        ];

        $existing = $this->get($staffId);
        if ($existing) {
            $before = $existing->toArray();
            $existing->update($payload);
            $this->audit->log('staff_pay.update', 'payroll_staff_pay', (int) $existing->id, $before, $existing->fresh()->toArray());

            return $existing->fresh();
        }

        $row = PayrollStaffPay::query()->create($payload);
        $this->audit->log('staff_pay.create', 'payroll_staff_pay', (int) $row->id, null, $row->toArray());

        return $row;
    }

    public function wageItems(int $staffId): Collection
    {
        return PayrollStaffWageItem::query()
            ->with('wageType')
            ->where('staff_id', $staffId)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWageItem(int $staffId, array $data): PayrollStaffWageItem
    {
        if (! DB::table('staff')->where('staff_id', $staffId)->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'Staff record not found.']);
        }

        $this->eligibility->assertActiveStaff($staffId);

        $type = PayrollWageType::query()->findOrFail((int) $data['wage_type_id']);
        if (! $type->is_active) {
            throw ValidationException::withMessages(['wage_type_id' => 'Wage type is inactive.']);
        }

        $item = PayrollStaffWageItem::query()->create([
            'staff_id' => $staffId,
            'wage_type_id' => $type->id,
            'amount' => $data['amount'] ?? null,
            'percent' => $data['percent'] ?? null,
            'currency' => isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $this->audit->log('staff_wage_item.create', 'payroll_staff_wage_items', (int) $item->id, null, $item->toArray());

        return $item->load('wageType');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateWageItem(PayrollStaffWageItem $item, array $data): PayrollStaffWageItem
    {
        $before = $item->toArray();
        $item->update([
            'wage_type_id' => $data['wage_type_id'] ?? $item->wage_type_id,
            'amount' => array_key_exists('amount', $data) ? $data['amount'] : $item->amount,
            'percent' => array_key_exists('percent', $data) ? $data['percent'] : $item->percent,
            'currency' => array_key_exists('currency', $data)
                ? ($data['currency'] ? strtoupper((string) $data['currency']) : null)
                : $item->currency,
            'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : $item->start_date,
            'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $item->end_date,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $item->is_active,
        ]);
        $this->audit->log('staff_wage_item.update', 'payroll_staff_wage_items', (int) $item->id, $before, $item->fresh()->toArray());

        return $item->fresh()->load('wageType');
    }

    public function deleteWageItem(PayrollStaffWageItem $item): void
    {
        $before = $item->toArray();
        $id = (int) $item->id;
        $item->delete();
        $this->audit->log('staff_wage_item.delete', 'payroll_staff_wage_items', $id, $before, null);
    }
}
