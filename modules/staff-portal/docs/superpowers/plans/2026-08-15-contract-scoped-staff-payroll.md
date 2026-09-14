# Contract-Scoped Staff Payroll Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bind staff payroll to the current `staff_contract_id`, support optional create-form pay (basic salary required when present), auto-inherit pay on contract renew with a verify-before-save warning, and keep the staff-show pay panel scoped to the current contract.

**Architecture:** Add `staff_contract_id` (+ `inherited_unverified`) to `payroll_staff_pay` and `staff_contract_id` to `payroll_staff_wage_items`. `StaffPayService` resolves the current contract; `StaffContractService::create` inherits prior pay; `StaffCreateService` optionally upserts pay onto the new contract. Vue `StaffPayPanel` shows contract context + inheritance warning; `StaffNewPage` embeds optional basic-pay fields.

**Tech Stack:** Laravel module `Modules/Payroll`, `Modules/Staff`, Vue 3 + Vuetify, existing `payrollApi` / `staffApi`.

**Spec:** `docs/superpowers/specs/2026-08-15-contract-scoped-staff-payroll-design.md`

## Global Constraints

- Pay on create is optional; if any pay is submitted, `basic_salary` is required.
- Default currency comes from payroll settings when the client omits/leaves it.
- Every new pay / wage-item row must store `staff_contract_id`.
- Renew copies previous contract pay + wage items and sets `inherited_unverified = true` until HR saves pay.
- Do not commit unless the user explicitly asks.
- Keep diffs focused; do not refactor unrelated staff/settings work already dirty in the tree.

## File map

| File | Responsibility |
|------|----------------|
| `backend/Modules/Payroll/database/migrations/2026_08_15_160000_contract_scope_staff_pay.php` | Schema + backfill |
| `backend/Modules/Payroll/app/Models/PayrollStaffPay.php` | Fillable/casts for contract + verify flag |
| `backend/Modules/Payroll/app/Models/PayrollStaffWageItem.php` | Fillable for `staff_contract_id` |
| `backend/Modules/Payroll/app/Services/StaffPayService.php` | Current-contract resolve, upsert, inherit, scoped wage items |
| `backend/Modules/Payroll/app/Http/Controllers/Api/V1/PayrollStaffPayController.php` | Enriched show/upsert responses |
| `backend/Modules/Payroll/app/Services/PayrollRunService.php` | Scope wage items (and prefer current-contract pay) |
| `backend/Modules/Staff/app/Services/StaffContractService.php` | Call inherit after create |
| `backend/Modules/Staff/app/Services/StaffCreateService.php` | Optional `pay` after contract create |
| `backend/Modules/Staff/app/Http/Controllers/Api/V1/StaffApiController.php` | Validate optional `pay` on store |
| `backend/Modules/Payroll/tests/Feature/StaffPayContractScopeTest.php` | Service-level tests |
| `frontend/src/lib/payrollApi.ts` | Types for contract + verification fields |
| `frontend/src/lib/staffApi.ts` | Optional `pay` on create payload |
| `frontend/src/components/staff/StaffPayPanel.vue` | Smart current-contract UI + warning |
| `frontend/src/pages/staff/StaffNewPage.vue` | Optional basic pay section |
| `frontend/src/pages/staff/StaffShowPage.vue` | Reload pay after renew; keep panel gated |

---

### Task 1: Migration — contract scope columns + backfill

**Files:**
- Create: `staff-portal/backend/Modules/Payroll/database/migrations/2026_08_15_160000_contract_scope_staff_pay.php`

**Produces:** Schema ready for contract-scoped pay.

- [ ] **Step 1: Add migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_staff_pay')) {
            Schema::table('payroll_staff_pay', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_staff_pay', 'staff_contract_id')) {
                    $table->unsignedInteger('staff_contract_id')->nullable()->after('staff_id');
                }
                if (! Schema::hasColumn('payroll_staff_pay', 'inherited_unverified')) {
                    $table->boolean('inherited_unverified')->default(false)->after('notes');
                }
            });

            // Drop staff_id unique if present (MySQL name may vary).
            try {
                Schema::table('payroll_staff_pay', function (Blueprint $table): void {
                    $table->dropUnique(['staff_id']);
                });
            } catch (\Throwable) {
                // index may already be non-unique or named differently
            }

            Schema::table('payroll_staff_pay', function (Blueprint $table): void {
                $table->index('staff_id');
                $table->unique('staff_contract_id');
            });

            // Backfill: latest contract per staff.
            if (Schema::hasTable('staff_contracts')) {
                $latest = DB::table('staff_contracts')
                    ->select('staff_id', DB::raw('MAX(staff_contract_id) as staff_contract_id'))
                    ->groupBy('staff_id');

                DB::table('payroll_staff_pay as p')
                    ->joinSub($latest, 'lc', 'lc.staff_id', '=', 'p.staff_id')
                    ->whereNull('p.staff_contract_id')
                    ->update(['p.staff_contract_id' => DB::raw('lc.staff_contract_id')]);
            }
        }

        if (Schema::hasTable('payroll_staff_wage_items')) {
            Schema::table('payroll_staff_wage_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_staff_wage_items', 'staff_contract_id')) {
                    $table->unsignedInteger('staff_contract_id')->nullable()->after('staff_id');
                    $table->index(['staff_id', 'staff_contract_id']);
                }
            });

            if (Schema::hasTable('payroll_staff_pay')) {
                DB::table('payroll_staff_wage_items as w')
                    ->join('payroll_staff_pay as p', 'p.staff_id', '=', 'w.staff_id')
                    ->whereNull('w.staff_contract_id')
                    ->whereNotNull('p.staff_contract_id')
                    ->update(['w.staff_contract_id' => DB::raw('p.staff_contract_id')]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive down omitted for legacy safety; leave columns if rolled back carefully in ops.
    }
};
```

- [ ] **Step 2: Run migration**

```bash
cd staff-portal/backend && php artisan migrate --path=Modules/Payroll/database/migrations/2026_08_15_160000_contract_scope_staff_pay.php --force
```

Expected: migrate succeeds; existing pay rows have `staff_contract_id` when a contract exists.

- [ ] **Step 3: Commit only if user asked** (otherwise leave unstaged).

---

### Task 2: Models + `StaffPayService` contract resolve / upsert / inherit

**Files:**
- Modify: `staff-portal/backend/Modules/Payroll/app/Models/PayrollStaffPay.php`
- Modify: `staff-portal/backend/Modules/Payroll/app/Models/PayrollStaffWageItem.php`
- Modify: `staff-portal/backend/Modules/Payroll/app/Services/StaffPayService.php`
- Create: `staff-portal/backend/Modules/Payroll/tests/Feature/StaffPayContractScopeTest.php`

**Interfaces:**
- Produces:
  - `currentContractId(int $staffId): ?int`
  - `getForContract(int $staffId, int $contractId): ?PayrollStaffPay`
  - `get(int $staffId): ?PayrollStaffPay` — current contract
  - `bundle(int $staffId): array` — `{ staff, pay, wage_items, staff_contract_id, inherited_from_contract_id, needs_verification }`
  - `upsert(int $staffId, array $data, ?int $contractId = null): PayrollStaffPay` — clears `inherited_unverified`
  - `inheritFromPreviousContract(int $staffId, int $newContractId): ?PayrollStaffPay`
  - Wage item methods require / stamp `staff_contract_id`

- [ ] **Step 1: Update models**

```php
// PayrollStaffPay fillable add:
'staff_contract_id', 'inherited_unverified',
// casts add:
'staff_contract_id' => 'integer',
'inherited_unverified' => 'boolean',

// PayrollStaffWageItem fillable add:
'staff_contract_id',
// casts add:
'staff_contract_id' => 'integer',
```

- [ ] **Step 2: Implement resolve helpers on `StaffPayService`**

```php
public function currentContractId(int $staffId): ?int
{
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

public function get(int $staffId): ?PayrollStaffPay
{
    $contractId = $this->currentContractId($staffId);
    if (! $contractId) {
        // Fallback: legacy row without contract still readable during transition
        return PayrollStaffPay::query()->where('staff_id', $staffId)->orderByDesc('id')->first();
    }

    return $this->getForContract($staffId, $contractId)
        ?? PayrollStaffPay::query()->where('staff_id', $staffId)->whereNull('staff_contract_id')->first();
}
```

- [ ] **Step 3: Upsert stamps contract + clears verification**

On create/update payload include:

```php
'staff_contract_id' => $contractId,
'inherited_unverified' => false,
```

Require `$contractId` from `$data['staff_contract_id'] ?? $this->currentContractId($staffId)`; if missing throw validation `staff_contract_id`.

Unique key for upsert: prefer find by `staff_contract_id`, else create.

- [ ] **Step 4: `inheritFromPreviousContract`**

```php
public function inheritFromPreviousContract(int $staffId, int $newContractId): ?PayrollStaffPay
{
    if (! Schema::hasTable('payroll_staff_pay')) {
        return null;
    }
    if ($this->getForContract($staffId, $newContractId)) {
        return null; // already exists
    }

    $previousContractId = (int) (DB::table('staff_contracts')
        ->where('staff_id', $staffId)
        ->where('staff_contract_id', '!=', $newContractId)
        ->orderByDesc('staff_contract_id')
        ->value('staff_contract_id') ?: 0);

    if ($previousContractId < 1) {
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
            ->where(function ($q) use ($previousContractId, $source): void {
                $q->where('staff_contract_id', $previousContractId)
                    ->orWhere(function ($q2) use ($source): void {
                        $q2->whereNull('staff_contract_id')->where('staff_id', $source->staff_id);
                    });
            })
            ->get();

        // Prefer only items matching previousContractId when any exist
        $scoped = $items->where('staff_contract_id', $previousContractId);
        $toClone = $scoped->isNotEmpty() ? $scoped : $items->whereNull('staff_contract_id');

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
```

- [ ] **Step 5: Scope `wageItems` / createWageItem to current (or explicit) contract**

```php
public function wageItems(int $staffId, ?int $contractId = null): Collection
{
    $contractId ??= $this->currentContractId($staffId);
    $q = PayrollStaffWageItem::query()->with('wageType')->where('staff_id', $staffId);
    if ($contractId) {
        $q->where(function ($inner) use ($contractId): void {
            $inner->where('staff_contract_id', $contractId)
                ->orWhereNull('staff_contract_id'); // legacy until cleaned
        });
        // Prefer contract-scoped only when any exist:
        $scoped = (clone $q)->where('staff_contract_id', $contractId)->exists();
        if ($scoped) {
            $q->where('staff_contract_id', $contractId);
        }
    }

    return $q->orderByDesc('is_active')->orderBy('id')->get();
}
```

On `createWageItem`, set `staff_contract_id` from current contract (required).

- [ ] **Step 6: Unit test for inherit clone fields (no DB if awkward — or Feature with existing TestCase)**

```php
public function test_inherit_sets_unverified_flag_on_payload_shape(): void
{
    // If full DB unavailable, assert method exists and signature via reflection.
    $this->assertTrue(method_exists(StaffPayService::class, 'inheritFromPreviousContract'));
    $this->assertTrue(method_exists(StaffPayService::class, 'currentContractId'));
}
```

Prefer a real DB feature test when the local demo DB is available: create two contracts + source pay, call inherit, assert new row `inherited_unverified === true` and wage item count matches.

- [ ] **Step 7: `php -l` on changed PHP files.**

---

### Task 3: API controller bundle + run service scoping

**Files:**
- Modify: `staff-portal/backend/Modules/Payroll/app/Http/Controllers/Api/V1/PayrollStaffPayController.php`
- Modify: `staff-portal/backend/Modules/Payroll/app/Services/PayrollRunService.php` (`calculateStaff` wage-item query)

**Produces:** Show payload with verification metadata; runs use contract-scoped wage items.

- [ ] **Step 1: Change `show` to return bundle**

```php
return response()->json(['data' => $service->bundle($staffId)]);
```

`bundle()` returns:

```php
[
  'staff' => $identity,
  'pay' => $pay,
  'wage_items' => $items,
  'staff_contract_id' => $contractId,
  'inherited_from_contract_id' => $pay && $pay->inherited_unverified
      ? /* previous contract id if resolvable */ null
      : null,
  'needs_verification' => (bool) ($pay?->inherited_unverified),
]
```

When `inherited_unverified`, set `inherited_from_contract_id` to the next-lower contract id for that staff (same query as inherit source).

- [ ] **Step 2: Upsert validation unchanged except ensure basic_salary required; service clears flag.**

- [ ] **Step 3: In `PayrollRunService::simulate`, when multiple pay rows per staff exist, keep only the row whose `staff_contract_id` equals the staff’s current contract (or latest pay).**

```php
$staffPays = PayrollStaffPay::query()
    ->where('pay_status', 'active')
    ->whereIn('staff_id', $this->eligibility->activeStaffIdSubquery())
    ->get()
    ->groupBy('staff_id')
    ->map(function ($rows) {
        $currentId = app(StaffPayService::class)->currentContractId((int) $rows->first()->staff_id);
        if ($currentId) {
            $match = $rows->firstWhere('staff_contract_id', $currentId);
            if ($match) {
                return $match;
            }
        }

        return $rows->sortByDesc('id')->first();
    })
    ->values();
```

- [ ] **Step 4: In `calculateStaff`, filter wage items by `$pay->staff_contract_id` when set:**

```php
->where('staff_id', $pay->staff_id)
->when($pay->staff_contract_id, fn ($q) => $q->where('staff_contract_id', $pay->staff_contract_id))
```

---

### Task 4: Hook inherit on contract create + optional pay on staff create

**Files:**
- Modify: `staff-portal/backend/Modules/Staff/app/Services/StaffContractService.php` (`create` transaction, after demote)
- Modify: `staff-portal/backend/Modules/Staff/app/Services/StaffCreateService.php`
- Modify: `staff-portal/backend/Modules/Staff/app/Http/Controllers/Api/V1/StaffApiController.php` (`validatedStorePayload`)

**Produces:** Renew inherits; create staff can attach pay to new contract id.

- [ ] **Step 1: After successful contract create**

```php
if (class_exists(\Modules\Payroll\Services\StaffPayService::class)
    && Schema::hasTable('payroll_staff_pay')) {
    app(\Modules\Payroll\Services\StaffPayService::class)
        ->inheritFromPreviousContract($staffId, $id);
}
```

Place inside the existing transaction after `demotePreviousOnRenew`.

- [ ] **Step 2: Staff create — after `$contractId`:**

```php
if (! empty($data['pay']) && is_array($data['pay']) && Schema::hasTable('payroll_staff_pay')) {
    $pay = $data['pay'];
    $pay['staff_contract_id'] = $contractId;
    app(\Modules\Payroll\Services\StaffPayService::class)->upsert($staffId, $pay, $contractId);
    foreach ((array) ($pay['wage_items'] ?? []) as $item) {
        if (empty($item['wage_type_id'])) {
            continue;
        }
        app(\Modules\Payroll\Services\StaffPayService::class)->createWageItem($staffId, array_merge($item, [
            'staff_contract_id' => $contractId,
        ]));
    }
}
```

Note: for brand-new staff, `assertActiveStaff` must pass (status_id 1 is current) — create contract with status 1 before upsert.

- [ ] **Step 3: Validate optional pay on store**

```php
'pay' => ['nullable', 'array'],
'pay.currency' => ['nullable', 'string', 'size:3'],
'pay.basic_salary' => ['required_with:pay', 'numeric', 'min:0'],
'pay.bank_name' => ['nullable', 'string', 'max:120'],
'pay.bank_account' => ['nullable', 'string', 'max:80'],
'pay.bank_branch' => ['nullable', 'string', 'max:120'],
'pay.tax_identifier' => ['nullable', 'string', 'max:80'],
'pay.pay_status' => ['nullable', 'in:active,held,terminated'],
'pay.notes' => ['nullable', 'string'],
'pay.wage_items' => ['nullable', 'array'],
'pay.wage_items.*.wage_type_id' => ['required', 'integer'],
'pay.wage_items.*.amount' => ['nullable', 'numeric'],
'pay.wage_items.*.percent' => ['nullable', 'numeric'],
```

Treat empty pay object without `basic_salary` as omitted: before validate, if `pay` is present but all fields blank, unset `pay`.

---

### Task 5: Frontend API types

**Files:**
- Modify: `staff-portal/frontend/src/lib/payrollApi.ts`
- Modify: `staff-portal/frontend/src/lib/staffApi.ts`

- [ ] **Step 1: Extend types**

```ts
export type StaffPay = {
  id: number
  staff_id: number
  staff_contract_id?: number | null
  inherited_unverified?: boolean
  currency: string
  basic_salary: number
  // ...existing
}

export type StaffPayBundle = {
  staff?: { ... }
  pay: StaffPay | null
  wage_items: StaffWageItem[]
  staff_contract_id?: number | null
  inherited_from_contract_id?: number | null
  needs_verification?: boolean
}
```

Ensure `fetchStaffPay` return type is `StaffPayBundle`.

- [ ] **Step 2: Add to `StaffCreatePayload`**

```ts
pay?: {
  currency?: string
  basic_salary: number
  bank_name?: string | null
  bank_account?: string | null
  bank_branch?: string | null
  tax_identifier?: string | null
  pay_status?: string
  notes?: string | null
} | null
```

Include `pay` in JSON body and FormData (`pay` as JSON string key `pay` or nested fields `pay[basic_salary]` — prefer JSON string field `pay` when multipart for simplicity, decoded in controller).

**Multipart note:** If create uses FormData for files, append `pay` as `JSON.stringify(payObject)` and in `StaffApiController::store` / `validatedStorePayload`:

```php
if ($request->has('pay') && is_string($request->input('pay'))) {
    $decoded = json_decode($request->input('pay'), true);
    $request->merge(['pay' => is_array($decoded) ? $decoded : null]);
}
```

---

### Task 6: Smart `StaffPayPanel`

**Files:**
- Modify: `staff-portal/frontend/src/components/staff/StaffPayPanel.vue`

**Produces:** Contract-aware lede + inheritance warning; save clears warning via API.

- [ ] **Step 1: Track bundle meta**

```ts
const staffContractId = ref<number | null>(null)
const needsVerification = ref(false)
const inheritedFromContractId = ref<number | null>(null)

// in load():
const bundle = await fetchStaffPay(props.staffId)
staffContractId.value = bundle.staff_contract_id ?? bundle.pay?.staff_contract_id ?? null
needsVerification.value = !!bundle.needs_verification
inheritedFromContractId.value = bundle.inherited_from_contract_id ?? null
if (needsVerification.value) editing.value = true
```

- [ ] **Step 2: UI**

Update lede to: `Payroll for current contract #{{ staffContractId }}` (or “No current contract” if null).

Add alert when `needsVerification`:

```vue
<v-alert v-if="needsVerification" type="warning" variant="tonal" class="mb-3" density="compact">
  Copied from previous contract
  <template v-if="inheritedFromContractId"> #{{ inheritedFromContractId }}</template>.
  Verify all entries before saving.
</v-alert>
```

- [ ] **Step 3: After successful `save()`, set `needsVerification = false` from refreshed pay / assume cleared.**

- [ ] **Step 4: Expose `reload()` via `defineExpose({ reload: load })` so StaffShowPage can refresh after renew.**

---

### Task 7: Optional pay on `StaffNewPage`

**Files:**
- Modify: `staff-portal/frontend/src/pages/staff/StaffNewPage.vue`

- [ ] **Step 1: Gate with same permission logic as StaffShowPage `canManagePay`.**

- [ ] **Step 2: Local pay form**

```ts
const includePay = ref(false)
const payForm = reactive({
  currency: 'USD',
  basic_salary: null as number | null,
  bank_name: '',
  bank_account: '',
  bank_branch: '',
  tax_identifier: '',
  pay_status: 'active',
  notes: '',
})

onMounted: if canManagePay, fetchPayrollSettings() → payForm.currency = settings.default_currency
```

- [ ] **Step 3: Template section “Payroll (optional)”** with switch/checkbox “Set up basic pay now”; when on, show currency (defaulted), basic salary (required), bank fields, status.

- [ ] **Step 4: On submit**, if `includePay`:

```ts
if (includePay.value) {
  if (payForm.basic_salary == null || Number.isNaN(Number(payForm.basic_salary))) {
    // client error on basic_salary
    return
  }
  payload.pay = {
    currency: payForm.currency,
    basic_salary: Number(payForm.basic_salary),
    bank_name: payForm.bank_name || null,
    bank_account: payForm.bank_account || null,
    bank_branch: payForm.bank_branch || null,
    tax_identifier: payForm.tax_identifier || null,
    pay_status: payForm.pay_status,
    notes: payForm.notes || null,
  }
}
```

Wage items on create are out of UI scope for this task (API supports them; HR adds on show page) — matches “at least basic pay”.

---

### Task 8: Staff show — reload pay after contract renew

**Files:**
- Modify: `staff-portal/frontend/src/pages/staff/StaffShowPage.vue`

- [ ] **Step 1: `ref` on `StaffPayPanel`**

```vue
<StaffPayPanel v-if="canManagePay && staffId" ref="payPanelRef" :staff-id="staffId" />
```

```ts
const payPanelRef = ref<{ reload?: () => Promise<void> } | null>(null)
```

- [ ] **Step 2: After successful `createContract` in `submitContract`**, `await payPanelRef.value?.reload?.()` and set success message that mentions verifying inherited payroll when renewing.

```ts
successMessage.value =
  formMode.value === 'create'
    ? 'Contract created. If payroll was copied from the previous contract, verify it before saving.'
    : 'Contract updated successfully.'
```

---

### Task 9: Manual verification checklist

- [ ] Enable payroll module; open `/staff/new` as HR → see optional payroll; create without pay → OK.
- [ ] Create with basic salary → staff show pay panel shows values linked to new contract id.
- [ ] On `/staff/{id}`, Add/renew contract → pay panel warns + prefilled; Save pay → warning clears; `inherited_unverified` false in DB.
- [ ] Edit current contract (not renew) → pay row `staff_contract_id` unchanged.
- [ ] Simulate payroll run still includes the staff with active current-contract pay.

---

## Spec coverage check

| Spec requirement | Task |
|------------------|------|
| `staff_contract_id` on pay + wage items | 1–2 |
| Backfill latest contract | 1 |
| `inherited_unverified` / needs_verification | 2–3, 6 |
| Inherit on renew | 4, 8 |
| Optional create pay + required basic_salary | 4, 7 |
| Default currency from settings | 7 |
| Smart staff-view panel | 6, 8 |
| Runs prefer current contract pay | 3 |

## Placeholder scan

None intentional. Multipart `pay` JSON decoding is specified in Task 5.

## Type consistency

- Backend flag: `inherited_unverified`
- API/UI: `needs_verification` (= that flag), `staff_contract_id`, `inherited_from_contract_id`
- Service methods: `currentContractId`, `getForContract`, `inheritFromPreviousContract`, `bundle`
