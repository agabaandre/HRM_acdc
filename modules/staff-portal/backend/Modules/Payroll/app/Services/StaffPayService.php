<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\PayrollStaffPay;
use Modules\Payroll\Models\PayrollStaffWageItem;
use Modules\Payroll\Models\PayrollWageType;
use Modules\Staff\Services\StaffContractService;

class StaffPayService
{
    public function __construct(
        private PayrollSettingsService $settings,
        private PayrollAuditService $audit,
        private PayrollStaffEligibility $eligibility,
    ) {}

    public function directory(): Collection
    {
        $rows = DB::table('payroll_staff_pay as p')
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
            ->get();

        return $rows
            ->groupBy('staff_id')
            ->map(function (Collection $group) {
                $staffId = (int) $group->first()->staff_id;
                $currentId = $this->currentContractId($staffId);
                $row = null;
                if ($currentId) {
                    $row = $group->firstWhere('staff_contract_id', $currentId);
                }
                if (! $row) {
                    $row = $group->sortByDesc('id')->first();
                }
                $row->staff_name = trim(preg_replace('/\s+/', ' ', (string) $row->staff_name)) ?: null;

                return $row;
            })
            ->values();
    }

    public function currentContractId(int $staffId): ?int
    {
        if ($staffId < 1 || ! Schema::hasTable('staff_contracts')) {
            return null;
        }

        $row = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->whereIn('status_id', StaffContractService::CURRENT_STATUSES)
            ->orderByDesc('staff_contract_id')
            ->first();

        if ($row) {
            return (int) $row->staff_contract_id;
        }

        $latest = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->orderByDesc('staff_contract_id')
            ->value('staff_contract_id');

        return $latest ? (int) $latest : null;
    }

    public function previousContractId(int $staffId, int $exceptContractId): ?int
    {
        if ($staffId < 1 || ! Schema::hasTable('staff_contracts')) {
            return null;
        }

        $id = DB::table('staff_contracts')
            ->where('staff_id', $staffId)
            ->where('staff_contract_id', '!=', $exceptContractId)
            ->orderByDesc('staff_contract_id')
            ->value('staff_contract_id');

        return $id ? (int) $id : null;
    }

    public function getForContract(int $staffId, int $contractId): ?PayrollStaffPay
    {
        return PayrollStaffPay::query()
            ->where('staff_id', $staffId)
            ->where('staff_contract_id', $contractId)
            ->first();
    }

    public function get(int $staffId): ?PayrollStaffPay
    {
        $contractId = $this->currentContractId($staffId);
        if ($contractId) {
            $match = $this->getForContract($staffId, $contractId);
            if ($match) {
                return $match;
            }
        }

        return PayrollStaffPay::query()
            ->where('staff_id', $staffId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *   staff: array{staff_id: int, staff_name: ?string, sap_number: ?string, work_email: ?string}|null,
     *   pay: ?PayrollStaffPay,
     *   wage_items: Collection,
     *   staff_contract_id: ?int,
     *   inherited_from_contract_id: ?int,
     *   needs_verification: bool
     * }
     */
    public function bundle(int $staffId): array
    {
        $contractId = $this->currentContractId($staffId);
        $pay = $this->get($staffId);
        $needsVerification = (bool) ($pay?->inherited_unverified);
        $inheritedFrom = null;
        if ($needsVerification && $pay?->staff_contract_id) {
            $inheritedFrom = $this->previousContractId($staffId, (int) $pay->staff_contract_id);
        }

        return [
            'staff' => $this->staffIdentity($staffId),
            'pay' => $pay,
            'wage_items' => $this->wageItems($staffId, $contractId ?? ($pay?->staff_contract_id ? (int) $pay->staff_contract_id : null)),
            'staff_contract_id' => $contractId ?? ($pay?->staff_contract_id ? (int) $pay->staff_contract_id : null),
            'inherited_from_contract_id' => $inheritedFrom,
            'needs_verification' => $needsVerification,
        ];
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
    public function upsert(int $staffId, array $data, ?int $contractId = null): PayrollStaffPay
    {
        if (! DB::table('staff')->where('staff_id', $staffId)->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'Staff record not found.']);
        }

        $this->eligibility->assertActiveStaff($staffId);

        $contractId = $contractId
            ?? (isset($data['staff_contract_id']) ? (int) $data['staff_contract_id'] : null)
            ?? $this->currentContractId($staffId);

        if (! $contractId) {
            throw ValidationException::withMessages([
                'staff_contract_id' => 'Payroll must be linked to a staff contract.',
            ]);
        }

        $settings = $this->settings->current();
        $currency = strtoupper((string) ($data['currency'] ?? $settings->default_currency));
        $enabled = $settings->enabled_currencies ?? [$settings->default_currency];
        if (! in_array($currency, $enabled, true)) {
            throw ValidationException::withMessages(['currency' => 'Currency is not enabled in payroll settings.']);
        }

        if (! array_key_exists('basic_salary', $data) || $data['basic_salary'] === null || $data['basic_salary'] === '') {
            throw ValidationException::withMessages(['basic_salary' => 'Basic salary is required.']);
        }

        $status = (string) ($data['pay_status'] ?? 'active');
        if (! in_array($status, ['active', 'held', 'terminated'], true)) {
            throw ValidationException::withMessages(['pay_status' => 'Invalid pay status.']);
        }

        $payload = [
            'staff_id' => $staffId,
            'staff_contract_id' => $contractId,
            'currency' => $currency,
            'basic_salary' => (float) $data['basic_salary'],
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
            'tax_identifier' => $data['tax_identifier'] ?? null,
            'pay_status' => $status,
            'notes' => $data['notes'] ?? null,
            'inherited_unverified' => false,
        ];

        $existing = $this->getForContract($staffId, $contractId);
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

    public function wageItems(int $staffId, ?int $contractId = null): Collection
    {
        $contractId ??= $this->currentContractId($staffId);
        $query = PayrollStaffWageItem::query()
            ->with('wageType')
            ->where('staff_id', $staffId);

        if ($contractId) {
            $hasScoped = PayrollStaffWageItem::query()
                ->where('staff_id', $staffId)
                ->where('staff_contract_id', $contractId)
                ->exists();

            if ($hasScoped) {
                $query->where('staff_contract_id', $contractId);
            } else {
                $query->where(function ($q) use ($contractId): void {
                    $q->where('staff_contract_id', $contractId)
                        ->orWhereNull('staff_contract_id');
                });
            }
        }

        return $query->orderByDesc('is_active')->orderBy('id')->get();
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

        $contractId = isset($data['staff_contract_id'])
            ? (int) $data['staff_contract_id']
            : $this->currentContractId($staffId);

        if (! $contractId) {
            throw ValidationException::withMessages([
                'staff_contract_id' => 'Wage items must be linked to a staff contract.',
            ]);
        }

        $type = PayrollWageType::query()->findOrFail((int) $data['wage_type_id']);
        if (! $type->is_active) {
            throw ValidationException::withMessages(['wage_type_id' => 'Wage type is inactive.']);
        }

        $item = PayrollStaffWageItem::query()->create([
            'staff_id' => $staffId,
            'staff_contract_id' => $contractId,
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

    public function inheritFromPreviousContract(int $staffId, int $newContractId): ?PayrollStaffPay
    {
        if (! Schema::hasTable('payroll_staff_pay') || $staffId < 1 || $newContractId < 1) {
            return null;
        }

        if ($this->getForContract($staffId, $newContractId)) {
            return null;
        }

        $previousContractId = $this->previousContractId($staffId, $newContractId);
        if (! $previousContractId) {
            return null;
        }

        $source = $this->getForContract($staffId, $previousContractId)
            ?? PayrollStaffPay::query()->where('staff_id', $staffId)->orderByDesc('id')->first();

        if (! $source) {
            return null;
        }

        return DB::transaction(function () use ($staffId, $newContractId, $previousContractId, $source) {
            $pay = PayrollStaffPay::query()->create([
                'staff_id' => $staffId,
                'staff_contract_id' => $newContractId,
                'currency' => $source->currency,
                'basic_salary' => $source->basic_salary,
                'bank_name' => $source->bank_name,
                'bank_account' => $source->bank_account,
                'bank_branch' => $source->bank_branch,
                'tax_identifier' => $source->tax_identifier,
                'pay_status' => $source->pay_status,
                'notes' => $source->notes,
                'inherited_unverified' => true,
            ]);

            $items = PayrollStaffWageItem::query()
                ->where('staff_id', $staffId)
                ->get();

            $scoped = $items->where('staff_contract_id', $previousContractId);
            $toClone = $scoped->isNotEmpty()
                ? $scoped
                : $items->whereNull('staff_contract_id');

            foreach ($toClone as $item) {
                PayrollStaffWageItem::query()->create([
                    'staff_id' => $staffId,
                    'staff_contract_id' => $newContractId,
                    'wage_type_id' => $item->wage_type_id,
                    'amount' => $item->amount,
                    'percent' => $item->percent,
                    'currency' => $item->currency,
                    'start_date' => $item->start_date,
                    'end_date' => $item->end_date,
                    'is_active' => $item->is_active,
                ]);
            }

            $this->audit->log('staff_pay.inherit', 'payroll_staff_pay', (int) $pay->id, [
                'from_contract_id' => $previousContractId,
            ], $pay->toArray());

            return $pay;
        });
    }
}
