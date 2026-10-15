# Payroll Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a SAP-aligned Payroll module (`Modules/Payroll` + SPA `/payroll`) with configurable wage types/taxes, multi-currency monthly runs, payslip PDF, and loans/advances.

**Architecture:** Single nwidart module mirroring Leave: Sanctum APIs under `/api/v1/payroll/*`, `PayrollAccess` + permissions 110–117, Vue hub with Runs / Payslips / Loans / Setup. Run engine simulates then posts immutably; payslips via `Modules\Core\Services\PdfService` (mPDF).

**Tech Stack:** Laravel modules, Sanctum, Vue 3 + Vuetify 3, mPDF via `PdfService`, existing PortalPermission / HR override patterns.

**Spec:** `docs/superpowers/specs/2026-08-13-payroll-management-design.md`

## Global Constraints

- Permissions **110–117** only; Admin (group 10 / perm 17) + HR role `20` override all payroll caps.
- Generic tax/benefits via rule tables — no country hardcode.
- Monthly periods; multi-currency per staff + `payroll_settings.default_currency`; FX on period for reporting aggregates.
- Posted runs immutable; corrections = new/off-cycle run.
- Net floor default **0**; block post unless override flag on post request.
- PDF via `Modules\Core\Services\PdfService`; store under portal storage (e.g. `storage/app/payroll/payslips/`).
- Compact outlined fields; toned-down Leave/Performance visual language.
- Do not commit unless the user explicitly asks.
- Frontend build: `cd staff-portal/frontend && npm run build` → `dist-build`.

## File map

| Path | Responsibility |
|------|----------------|
| `backend/Modules/Payroll/` | Full module (scaffold, migrations, models, services, controllers, seeders) |
| `backend/modules_statuses.json` | Enable `Payroll: true` |
| `frontend/src/lib/payrollApi.ts` | API client |
| `frontend/src/lib/payrollPermissions.ts` | Perm constants 110–117 |
| `frontend/src/pages/payroll/*.vue` | Hub, Runs, Run detail, Payslips, Loans, Setup |
| `frontend/src/router/index.ts` | `/payroll*` routes |
| `frontend/src/lib/portalNav.ts` | Nav item + page icon |

---

### Task 1: Module scaffold + permissions 110–117

**Files:**
- Create: `backend/Modules/Payroll/module.json`
- Create: `backend/Modules/Payroll/composer.json`
- Create: `backend/Modules/Payroll/config/config.php` → `['name' => 'Payroll']`
- Create: `backend/Modules/Payroll/app/Providers/PayrollServiceProvider.php`
- Create: `backend/Modules/Payroll/app/Providers/RouteServiceProvider.php`
- Create: `backend/Modules/Payroll/app/Providers/EventServiceProvider.php`
- Create: `backend/Modules/Payroll/routes/api.php`, `routes/web.php`
- Create: `backend/Modules/Payroll/app/Support/PayrollPermissions.php`
- Create: `backend/Modules/Payroll/app/Support/PayrollAccess.php`
- Create: `backend/Modules/Payroll/database/seeders/PayrollPermissionsSeeder.php`
- Create: `backend/Modules/Payroll/database/seeders/PayrollDatabaseSeeder.php`
- Create: `backend/Modules/Payroll/database/migrations/2026_08_13_230000_seed_payroll_permissions.php`
- Modify: `backend/modules_statuses.json` → `"Payroll": true`

**Produces:**
- Constants: `VIEW_HUB=110`, `MANAGE_SETUP=111`, `MANAGE_STAFF_PAY=112`, `RUN_PAYROLL=113`, `MANAGE_LOANS=114`, `APPROVE_LOANS=115`, `REQUEST_LOAN=116`, `VIEW_OWN_PAYSLIPS=117`
- `PayrollAccess::{canViewHub,canManageSetup,canManageStaffPay,canRunPayroll,canManageLoans,canApproveLoans,canRequestLoan,canViewOwnPayslips,isHr,isAdmin,staffId,authorize*}`
- Seeder assigns **110–117** to group `10`; **110–114** to groups `20,22`; upsert catalog like Leave

- [ ] **Step 1:** Copy Leave provider/route/composer patterns; rename to Payroll.
- [ ] **Step 2:** Implement `PayrollPermissions::catalog()` and `moduleAccessIds()` returning 110–117.
- [ ] **Step 3:** Implement `PayrollAccess` with HR (`role === 20`) and admin (`PortalPermission::can(17)`) overrides granting all.
- [ ] **Step 4:** Permissions seeder + migration that calls seeder in `up()`.
- [ ] **Step 5:** Enable module; `composer dump-autoload` in backend if needed; `php artisan migrate` for seed migration.
- [ ] **Step 6:** Smoke: permissions rows 110–117 exist; groups 10/20/22 have expected grants.

---

### Task 2: Schema migrations + Eloquent models

**Files:**
- Create: `backend/Modules/Payroll/database/migrations/2026_08_13_230100_create_payroll_tables.php` (single migration or split by domain)
- Create models under `backend/Modules/Payroll/app/Models/`:
  - `PayrollSetting`, `PayrollWageType`, `PayrollTaxRule`, `PayrollTaxBand`
  - `PayrollStaffPay`, `PayrollStaffWageItem`
  - `PayrollPeriod`, `PayrollFxRate`, `PayrollRun`, `PayrollRunLine`, `PayrollRunLineItem`
  - `PayrollPayslip`, `PayrollLoan`, `PayrollLoanSchedule`, `PayrollAuditLog`

**Produces:** Tables exactly as spec §3 (charset/collation `utf8mb4_unicode_ci` or match sibling modules).

- [ ] **Step 1:** Create all tables with FKs where safe (`staff_id` → existing staff table if present; otherwise unsignedBigInteger index only — match Leave pattern).
- [ ] **Step 2:** Seed system wage types in migration or `PayrollCatalogSeeder`: `BASIC`, `TAX`, `LOAN_DED` (`is_system=true`).
- [ ] **Step 3:** Insert default `payroll_settings` row: `default_currency=USD`, `enabled_currencies=["USD","ETB","EUR"]`, `period_close_day=25`.
- [ ] **Step 4:** Models with `$fillable` / casts for JSON/decimals/dates; relationships (`taxRule.bands`, `run.lines`, `loan.schedules`).

---

### Task 3: Settings, wage types, tax rules APIs

**Files:**
- Create: `Services/PayrollSettingsService.php`, `WageTypeService.php`, `TaxRuleService.php`, `PayrollAuditService.php`
- Create: controllers `PayrollSettingsController`, `PayrollWageTypeController`, `PayrollTaxRuleController`
- Modify: `routes/api.php`

**Produces:**
- `GET/PUT /api/v1/payroll/settings` — GET: VIEW_HUB or MANAGE_SETUP; PUT: MANAGE_SETUP
- `GET/POST /api/v1/payroll/wage-types`, `PUT/DELETE .../{id}` — MANAGE_SETUP (DELETE soft-deactivate if `is_system`)
- `GET/POST /api/v1/payroll/tax-rules`, `PUT .../{id}` with nested `bands[]` — MANAGE_SETUP
- Audit log on every mutating settings/catalog change

- [ ] **Step 1:** Settings get-or-create singleton; validate currency ISO-3, `period_close_day` 1–28, `enabled_currencies` includes default.
- [ ] **Step 2:** Wage type CRUD; reject delete of system codes; validate category/calc_method enums.
- [ ] **Step 3:** Tax rule + bands replace-on-update (transaction); validate band ordering `from_amount` ascending, rates ≥ 0.
- [ ] **Step 4:** Feature test or manual: unauthorized → 403; HR can PUT settings.

---

### Task 4: Staff pay master + wage items APIs

**Files:**
- Create: `Services/StaffPayService.php`
- Create: `PayrollStaffPayController.php`
- Modify: `routes/api.php`

**Produces:**
- `GET/PUT /api/v1/payroll/staff/{staffId}/pay`
- `GET/POST /api/v1/payroll/staff/{staffId}/wage-items`
- `PUT/DELETE /api/v1/payroll/staff/{staffId}/wage-items/{id}`
- Optional list: `GET /api/v1/payroll/staff-pay` (directory for admin UI)

- [ ] **Step 1:** Upsert `payroll_staff_pay`; currency must be in `enabled_currencies`.
- [ ] **Step 2:** Wage items validate wage_type exists/active; amount/percent per `calc_method`.
- [ ] **Step 3:** Gate with `MANAGE_STAFF_PAY` (+ HR/admin override).

---

### Task 5: Periods, FX, run simulate/post engine

**Files:**
- Create: `Services/PayrollPeriodService.php`, `PayrollRunService.php`
- Create: `PayrollPeriodController.php`, `PayrollRunController.php`
- Modify: `routes/api.php`

**Produces:**
- `GET/POST /api/v1/payroll/periods`
- `PUT /api/v1/payroll/periods/{id}/close`
- `PUT /api/v1/payroll/periods/{id}/fx` body `{ rates: [{ currency, rate_to_default }] }`
- `GET/POST /api/v1/payroll/runs`
- `GET /api/v1/payroll/runs/{id}`, `GET .../lines`
- `POST /api/v1/payroll/runs/{id}/simulate`
- `POST /api/v1/payroll/runs/{id}/post` body optional `{ allow_negative_net: bool }`

**Calc order (staff currency)** — implement in `PayrollRunService::calculateStaff(int $staffId, PayrollPeriod $period, ?PayrollRun $run): array`:
1. Basic from `payroll_staff_pay.basic_salary`
2. Active wage items (date-overlap with period month): fixed / percent_of_base / percent_of_gross / manual
3. Gross; track taxable
4. Pre-tax deductions
5. Apply tax bands for active rules matching jurisdiction (settings default or staff override later — use settings `jurisdiction_default`)
6. Post-tax deductions + pending loan schedules for this period
7. Net; FX → `net_default` via period rate (missing rate → abort simulate with clear error for non-default currencies)

- [ ] **Step 1:** Create period unique(year,month); status open|closed.
- [ ] **Step 2:** FX upsert; always ensure default_currency rate = 1.
- [ ] **Step 3:** Simulate replaces prior draft/simulated lines for that run; status → `simulated`; does not touch loan schedules.
- [ ] **Step 4:** Post only from `simulated`; write payslips (Task 6 hook), mark loan installments deducted, status `posted`, set aggregates; reject if any net < 0 unless `allow_negative_net`.
- [ ] **Step 5:** Posted run rejects re-simulate/re-post (409).
- [ ] **Step 6:** Unit/feature test: basic 1000 + 10% allowance + band tax + loan deduction → expected net.

---

### Task 6: Payslip PDF + self-service list/download

**Files:**
- Create: `Services/PayslipService.php`
- Create: `PayrollPayslipController.php`
- Create: `resources/views/payroll/payslip.blade.php` (or HTML string in service)
- Modify: `PayrollRunService` post path to call `PayslipService::generateForRun(PayrollRun $run)`
- Modify: `routes/api.php`

**Produces:**
- On post: one `payroll_payslips` row per run line; PDF bytes via `PdfService`; `ytd` JSON from prior posted slips in year + current
- `GET /api/v1/payroll/payslips` — own if only VIEW_OWN; filtered if RUN/VIEW_HUB admin
- `GET /api/v1/payroll/payslips/{id}/pdf` — owner or RUN_PAYROLL/VIEW_HUB

- [ ] **Step 1:** YTD = sum of posted payslip line totals for staff in calendar year of period through current period.
- [ ] **Step 2:** PDF sections: org header, staff, period, earnings, benefits, tax, deductions, net, YTD.
- [ ] **Step 3:** Stream PDF response with `Content-Type: application/pdf`.

---

### Task 7: Loans & advances

**Files:**
- Create: `Services/LoanService.php`
- Create: `PayrollLoanController.php`
- Modify: `PayrollRunService` to include pending schedules in calc
- Modify: `routes/api.php`

**Produces:**
- `GET/POST /api/v1/payroll/loans`
- `POST /api/v1/payroll/loans/{id}/decide` `{ decision: approve|reject, reason? }`
- `POST /api/v1/payroll/loans/{id}/disburse` `{ start_period_id, installment_amount?, installment_count? }`
- `POST /api/v1/payroll/loans/{id}/schedules/{scheduleId}/waive` (MANAGE_LOANS)

**Status machine:** `draft|pending_supervisor|pending_payroll|active|completed|rejected|cancelled`

- [ ] **Step 1:** Staff creates → `pending_supervisor` (REQUEST_LOAN).
- [ ] **Step 2:** Supervisor decide → approve → `pending_payroll`; reject → `rejected` (APPROVE_LOANS).
- [ ] **Step 3:** Disburse generates N schedules; simple interest = principal * rate * (even split); wage_type LOAN_DED; status `active`.
- [ ] **Step 4:** Simulate includes pending schedules for period; post marks them `deducted` + links `run_line_item_id`.
- [ ] **Step 5:** When all schedules deducted → loan `completed`.

---

### Task 8: Dashboard + SPA shell

**Files:**
- Create: `Services/PayrollDashboardService.php`, `PayrollDashboardController.php`
- Create: `frontend/src/lib/payrollPermissions.ts`
- Create: `frontend/src/lib/payrollApi.ts`
- Create: `frontend/src/pages/payroll/PayrollHubPage.vue`
- Create: `frontend/src/pages/payroll/PayrollRunsPage.vue`
- Create: `frontend/src/pages/payroll/PayrollRunDetailPage.vue`
- Create: `frontend/src/pages/payroll/PayrollPayslipsPage.vue`
- Create: `frontend/src/pages/payroll/PayrollLoansPage.vue`
- Create: `frontend/src/pages/payroll/PayrollSetupPage.vue`
- Modify: `frontend/src/router/index.ts`, `frontend/src/lib/portalNav.ts`
- Create: `GET /api/v1/payroll/dashboard` → open period, last run, pending loan approvals, staff missing pay master count

- [ ] **Step 1:** Wire routes with `anyPermission: [110,111,112,113,114,115,116,117]`.
- [ ] **Step 2:** Nav Payroll primary; icon `fa-money-check-dollar`.
- [ ] **Step 3:** Hub KPIs + links; Setup tabs: Settings, Wage types, Tax rules, Staff pay.
- [ ] **Step 4:** Runs list + detail simulate/post; Payslips download; Loans Mine/Approvals/Admin tabs.
- [ ] **Step 5:** `npm run build` in frontend; manual smoke against local API.

---

### Task 9: Feature tests (critical paths)

**Files:**
- Create: `backend/Modules/Payroll/tests/Feature/PayrollPermissionsTest.php`
- Create: `backend/Modules/Payroll/tests/Feature/PayrollRunEngineTest.php`
- Create: `backend/Modules/Payroll/tests/Feature/PayrollLoanFlowTest.php`

- [ ] **Step 1:** Unauthorized settings PUT → 403; admin OK.
- [ ] **Step 2:** Simulate math fixture: basic + percent earning + tax band + loan → assert net.
- [ ] **Step 3:** Post immutability + loan schedule deducted.
- [ ] **Step 4:** Multi-currency aggregate uses FX.

---

## Spec coverage checklist

| Spec area | Task |
|-----------|------|
| Module + perms 110–117 | 1 |
| Settings / wage types / tax | 2–3 |
| Staff pay master | 4 |
| Periods / FX / simulate / post | 5 |
| Payslips PDF + YTD | 6 |
| Loans/advances | 7 |
| Hub dashboard + SPA | 8 |
| Tests | 9 |
| Audit log | 3–7 (via PayrollAuditService) |
| Net floor 0 | 5 |
| Admin/HR override | 1 (`PayrollAccess`) |

## Self-review notes

- No country tax packs; jurisdiction + bands only.
- Attendance OT left as non-implemented stub (no API).
- GL/bank/email out of scope.
