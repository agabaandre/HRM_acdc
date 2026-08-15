# Contract-scoped staff payroll

**Date:** 2026-08-15  
**Status:** Approved (pending written-spec review)  
**Related:** [2026-08-13-payroll-management-design.md](./2026-08-13-payroll-management-design.md)

## Goal

Bind staff payroll (basic pay + wage items) to the **current staff contract instance**, so renewals get a fresh pay set (optionally inherited), create-staff can capture optional pay against the new contract, and HR always verifies inherited values before saving.

## Decisions

| Topic | Decision |
| --- | --- |
| Approach | Contract-scoped pay records (not a single staff-global row) |
| Create form | Optional payroll block when payroll module + manage-pay permission |
| Minimum when saving pay | Basic salary required; currency defaults from payroll settings |
| Contract id on create | Taken from the contract created in the same transaction/save |
| Renew / new contract | Auto-inherit previous contract pay + wage items; warn to verify before save |
| Edit same contract | Update that contract’s pay in place |
| Wage items | Per-contract (same scope as basic pay) |

## Data model

### `payroll_staff_pay`

- Add `staff_contract_id` (unsigned int, nullable during migration, then required for new rows).
- Unique on `staff_contract_id` (one pay master per contract).
- Remove uniqueness that forces one row per `staff_id` only (keep non-unique `staff_id` index for lookups).
- Existing columns unchanged: `currency`, `basic_salary`, `bank_*`, `tax_identifier`, `pay_status`, `notes`.

### `payroll_staff_wage_items`

- Add `staff_contract_id`.
- Index `(staff_id, staff_contract_id)`.
- Queries for a staff member’s active package always filter by the target contract.

### Backfill

1. For each existing `payroll_staff_pay` row, set `staff_contract_id` to that staff’s **latest** `staff_contracts.staff_contract_id`.
2. Copy the same `staff_contract_id` onto that staff’s `payroll_staff_wage_items`.
3. Rows that cannot resolve a contract remain nullable until HR opens pay and saves (or a follow-up cleanup job).

## Backend behaviour

### Resolve current contract

Reuse staff contract “current” semantics (`status_id` in current set, else latest by `staff_contract_id`).

### `StaffPayService`

- `get(staffId, ?contractId)` → pay for explicit contract, or current contract.
- `upsert(staffId, data)` → requires resolvable current (or explicit) `staff_contract_id`; validates `basic_salary` ≥ 0 and present; currency from payload or settings default.
- `wageItems` / create / update / delete → scoped to the same contract as the pay master.
- `inheritFromPreviousContract(staffId, newContractId)`:
  - Find previous contract’s pay + wage items.
  - If none, no-op.
  - Create new pay + cloned wage items for `newContractId`.
  - Return metadata: `inherited: true`, `inherited_from_contract_id`.

### Contract create hook

In `StaffContractService::create` (after insert + demote previous):

- If Payroll module tables exist and previous contract had pay, call `inheritFromPreviousContract`.
- Inheritance is automatic; UI must surface the verify warning when pay is shown next.

### Staff create

In `StaffCreateService::create` (same DB transaction after contract insert):

- Optional `pay` payload: `{ currency?, basic_salary, bank_*, tax_identifier?, pay_status?, notes?, wage_items?[] }`.
- If `pay` omitted or empty → skip.
- If `pay` present → `basic_salary` required; attach `staff_contract_id` from newly created contract; create wage items if provided.

### API responses

`GET /api/v1/payroll/staff/{staffId}/pay` includes:

- `pay` (nullable)
- `wage_items`
- `staff_contract_id` (current contract used)
- `inherited_from_contract_id` (nullable; set when this pay row was created via inheritance and not yet confirmed — see below)
- `needs_verification` (boolean)

**Verification flag:** store `inherited_unverified` (boolean, default false) on `payroll_staff_pay`. Set true on inherit; clear to false on successful `upsert` / explicit confirm. UI uses this for the warning banner.

### Payroll runs

When calculating a staff line, prefer pay (and wage items) for the contract that was current covering the period dates; fallback to latest pay for that staff if no contract-scoped match (migration safety).

## Frontend

### Create staff (`StaffNewPage`)

- When `auth.isModuleEnabled('payroll')` and user can manage staff pay, show payroll section (same fields as `StaffPayPanel` basic + optional wage items).
- Default currency from `fetchPayrollSettings().default_currency`.
- Skip entire section → no pay created.
- If user enters pay: client requires basic salary before submit; POST pay with staff create (or follow-up upsert using returned `contract_id` — prefer single create payload for atomicity).

### Staff show (`StaffShowPage` + `StaffPayPanel`)

- Smart panel:
  - Subtitle: payroll for **current contract #X**.
  - If `needs_verification` / inherited: warning alert — *Copied from previous contract — verify all entries before saving.*
  - Edit + Save clears verification flag.
- After “Add / renew contract”, reload pay panel so inherited draft appears with warning.
- Editing an existing contract does not clone pay.

### Permissions

Unchanged: payroll module enabled + manage staff pay (HR / admin / permission as today).

## Out of scope

- Changing tax engine / loan rules.
- Editing historical contract pay from the contract history table UI (can be a later enhancement; data will exist).
- Making pay mandatory on create.

## Acceptance criteria

1. Create staff without pay still works when payroll is enabled.
2. Create staff with basic salary creates `payroll_staff_pay` linked to the new `staff_contract_id`.
3. Renewing a contract clones previous pay + wage items onto the new contract and shows a verify warning until saved.
4. Staff show pay panel always reflects the current contract’s package.
5. Existing staff with legacy pay still load after backfill.
6. Payroll run still produces lines for staff with contract-scoped pay.

## Self-review notes

- No unresolved placeholders.
- Optional create vs required basic-when-saving are consistent.
- Wage items and basic pay share the same contract scope (no split model).
- Scope limited to staff create/show + pay services + migration; no unrelated modules.
