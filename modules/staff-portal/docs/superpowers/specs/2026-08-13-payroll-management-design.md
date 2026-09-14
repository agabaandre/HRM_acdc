# Staff Portal: Payroll Management (SAP-aligned)

**Date:** 2026-08-13  
**Status:** Approved (design)  
**Approach:** Monolithic `Modules/Payroll` + SPA hub `/payroll`  
**Tax model:** Generic configurable engine (no country law hard-coded)

## Goals

1. Deliver a **SAP-style payroll** foundation: wage calculation (gross → net), configurable taxes/benefits, deductions, recordkeeping, and payslips with YTD.
2. Include **staff loans and advances** with request → approve → disburse → schedule → auto-deduct on payroll run.
3. Support **multi-currency per staff** with a **system default currency** in Settings.
4. Seed **payroll permissions**, assign defaults to Admin/HR groups, allow assignment via Permissions UI; Admin/HR overall override.
5. Employee self-service: own payslips + loan/advance requests; supervisors approve loans; payroll admins run payroll.

## Non-goals (v1)

- Hard-coded Ethiopia/other statutory tax packs (framework only via jurisdiction + rule tables).
- Full time & attendance / overtime costing (stub hooks only).
- Multi-company / multi-assignment concurrent pays.
- True retroactive recalculation engine (posted runs are immutable; corrections via new adjustment run).
- Finance/GL posting and bank file generation (export stubs optional later).
- Email distribution of payslips (portal download first).

## Decisions (locked)

| Topic | Decision |
|-------|----------|
| Module shape | Single `Payroll` module (Approach 1) |
| Pay cycle | Monthly periods; optional `off_cycle` flag on a run |
| Currency | Staff pay currency + org **default currency** in settings; period FX rate for reporting |
| Users | Payroll admin + supervisor (loan approve) + staff (payslips / loan request) |
| Permissions | New IDs **110–117**; Admin/HR full; assignable in Permissions UI |
| Payslips | PDF generated on post; stored under portal uploads; YTD JSON snapshot |
| Loans | Loan + Advance types; installment wage-type deduction |
| Audit | Append-only audit log for config, runs, loans |
| Integration | Read staff + active `staff_contracts` (grade/funder/division); no salary on contract today → `payroll_staff_pay` |

---

## 1. SAP feature compliance

| SAP-style capability | v1 | Later |
|----------------------|----|--------|
| Calculating wages (gross → net, deductions, adjustments) | Yes | Attendance OT formulas |
| Processing taxes (withholding via rules) | Configurable bands/rules | Remittance exports, jurisdiction packs |
| Recordkeeping / audit | Immutable posted lines + audit trail | Statutory report packs |
| Pay statements + YTD | PDF payslip + YTD snapshot | Email push |
| Integration HR / leave / finance | Staff + contract; leave hook stub | Time→payroll, GL export |
| Time & attendance | Stub only | Full |
| Benefits, bonuses, allowances, advanced deductions | Wage-type catalog + assignments | Commission engines |
| Multi-region / localization | `jurisdiction_code` on tax rules | Localized packs |
| Complex pay cycles / off-cycle / retro | Monthly + off-cycle run flag | Bi-weekly, retro engine |
| Multi-entity / multi-assignment | Single org, primary pay record | Multi-assignment |
| Employee self-service | Payslips, loan/advance request | Bank self-update |
| Reporting / analytics | Run KPIs, cost by division/funder | Forecasting |
| Dashboards & audit trails | Hub dashboard + audit log | Compliance packs |

---

## 2. Architecture

```
Modules/Payroll/
  module.json, composer.json, config/config.php
  routes/api.php, routes/web.php
  app/Providers/{Payroll,Route}ServiceProvider.php
  app/Support/PayrollPermissions.php
  app/Support/PayrollAccess.php
  app/Services/
    PayrollSettingsService.php
    WageTypeService.php
    TaxRuleService.php
    StaffPayService.php
    PayrollPeriodService.php
    PayrollRunService.php          # simulate + post
    PayslipService.php             # PDF + YTD
    LoanService.php
    PayrollAuditService.php
  app/Http/Controllers/Api/V1/...
  app/Models/...
  database/migrations/...
  database/seeders/PayrollPermissionsSeeder.php

frontend/
  src/lib/payrollApi.ts
  src/lib/payrollPermissions.ts
  src/pages/payroll/
    PayrollHubPage.vue
    PayrollRunsPage.vue
    PayrollRunDetailPage.vue
    PayrollPayslipsPage.vue
    PayrollLoansPage.vue
    PayrollSetupPage.vue
  router + portalNav.ts
```

**API prefix:** `/api/v1/payroll/...` (Sanctum).  
**Pattern:** Mirror Leave module (permissions seeder, Access helper, SPA pages).

---

## 3. Data model

### Settings & catalogs

- **`payroll_settings`**  
  - `default_currency` (CHAR 3)  
  - `enabled_currencies` (JSON array)  
  - `period_close_day` (1–28)  
  - `jurisdiction_default` (string, optional)  
  - timestamps  

- **`payroll_wage_types`** (SAP “wage types”)  
  - `code`, `name`, `category` enum: `earning|benefit|tax|deduction|employer_contrib`  
  - `calc_method` enum: `fixed|percent_of_base|percent_of_gross|manual|formula`  
  - `percent_base` nullable, `default_amount` nullable  
  - `taxable` bool, `pre_tax` bool (for deductions)  
  - `is_system` bool (e.g. BASIC, LOAN_DED, TAX)  
  - `is_active`, `sort_order`  

- **`payroll_tax_rules`**  
  - `code`, `name`, `jurisdiction_code`, `effective_from`, `effective_to`  
  - `applies_to` enum: `employee|employer`  
  - `wage_type_id` (output wage type for calculated tax)  

- **`payroll_tax_bands`**  
  - `tax_rule_id`, `from_amount`, `to_amount` nullable, `rate_percent`, `fixed_amount`  

### Staff pay master

- **`payroll_staff_pay`**  
  - `staff_id` unique  
  - `currency` (CHAR 3)  
  - `basic_salary` decimal  
  - `bank_name`, `bank_account`, `bank_branch` nullable  
  - `tax_identifier` nullable  
  - `pay_status` enum: `active|held|terminated`  
  - `notes`  

- **`payroll_staff_wage_items`**  
  - `staff_id`, `wage_type_id`  
  - `amount` / `percent` (per method)  
  - `currency` (defaults to staff pay currency)  
  - `start_date`, `end_date` nullable  
  - `is_active`  

### Periods, runs, results

- **`payroll_periods`**  
  - `year`, `month`, `label`, `status` enum: `open|closed`  
  - unique(year, month)  

- **`payroll_fx_rates`**  
  - `period_id`, `currency`, `rate_to_default` (1 default_currency = rate)  

- **`payroll_runs`**  
  - `period_id`  
  - `status` enum: `draft|simulated|posted|cancelled`  
  - `off_cycle` bool  
  - `title`, `notes`  
  - `simulated_at`, `posted_at`, `posted_by_user_id`  
  - `staff_count`, `total_gross_default`, `total_net_default` (reporting currency)  

- **`payroll_run_lines`**  
  - `run_id`, `staff_id`, `currency`  
  - `basic`, `gross`, `taxable`, `tax`, `deductions`, `benefits`, `net`  
  - `fx_rate_to_default`, `net_default`  

- **`payroll_run_line_items`**  
  - `run_line_id`, `wage_type_id`, `category`, `amount`, `meta` JSON  

- **`payroll_payslips`**  
  - `run_line_id` unique  
  - `staff_id`, `period_id`, `run_id`  
  - `pdf_path` nullable  
  - `ytd` JSON (`{gross, tax, net, ...}`)  
  - `generated_at`  

### Loans & advances

- **`payroll_loans`**  
  - `staff_id`, `type` enum: `loan|advance`  
  - `currency`, `principal`, `interest_rate` (0 for advances typically)  
  - `installment_amount`, `installment_count`  
  - `status` enum: `draft|pending_supervisor|pending_payroll|active|completed|rejected|cancelled`  
  - `requested_by_user_id`, `supervisor_id`, `approved_by_user_id`, `rejected_reason`  
  - `disbursed_at`, `start_period_id`  
  - `wage_type_id` (deduction type, default LOAN_DED)  

- **`payroll_loan_schedules`**  
  - `loan_id`, `sequence`, `due_period_id` nullable  
  - `amount`, `status` enum: `pending|deducted|waived|skipped`  
  - `run_line_item_id` nullable  

### Audit

- **`payroll_audit_logs`**  
  - `actor_user_id`, `action`, `entity_type`, `entity_id`, `before` JSON, `after` JSON, `created_at`  

---

## 4. Payroll run engine

### Status machine

```
period: open ──(admin close)──► closed
run:    draft → simulated → posted
                 ↘ cancelled
posted is immutable; corrections = new adjustment/off-cycle run
```

### Calculation order (per staff, staff currency)

1. **Basic** from `payroll_staff_pay.basic_salary`  
2. **Earnings / benefits** from active `payroll_staff_wage_items` + any run-level manual adjustments  
3. **Gross** = basic + taxable/non-taxable earnings (track taxable separately)  
4. **Pre-tax deductions** (benefits employee share if marked pre-tax)  
5. **Taxable base** → apply active `payroll_tax_rules` bands for jurisdiction  
6. **Post-tax deductions** including due `payroll_loan_schedules`  
7. **Net** = gross − all employee deductions − tax (+ non-cash benefits shown separately on slip)  
8. Convert totals to default currency via `payroll_fx_rates` for run aggregates  

### Simulate vs post

- **Simulate:** compute into run lines (replace prior simulation); no payslips; loan schedules untouched.  
- **Post:** freeze lines, generate payslips + YTD, mark loan installments `deducted`, set run `posted`, optionally lock period when all regular runs done (manual period close).

### Payslip

- PDF (server-side, same pattern as other portal PDFs).  
- Sections: employer header, staff identity, period, earnings, benefits, taxes, deductions (incl. loan), net, YTD.  
- Staff can list/download own; admins can download any in period/run.

---

## 5. Loans & advances flow

```
Staff request → Supervisor approve/reject → Payroll admin disburse
  → generate schedule → each open period run picks pending installment
  → mark deducted when run posted
```

- Advance: usually short schedule / fewer installments; interest typically 0.  
- Loan: principal + optional interest (simple interest split across installments in v1).  
- Admin may waive/skip an installment (audited).  
- Cannot post a run that would drive net below configurable floor (default 0) without override permission.

---

## 6. Permissions

| ID | Constant | Meaning |
|----|----------|---------|
| 110 | `VIEW_HUB` | Open payroll hub / dashboards |
| 111 | `MANAGE_SETUP` | Wage types, tax rules, settings, currencies |
| 112 | `MANAGE_STAFF_PAY` | Staff pay master + wage assignments |
| 113 | `RUN_PAYROLL` | Create/simulate/post runs, manage periods/FX |
| 114 | `MANAGE_LOANS` | Disburse, waive, admin loan ops |
| 115 | `APPROVE_LOANS` | Supervisor approve/reject |
| 116 | `REQUEST_LOAN` | Staff request loan/advance |
| 117 | `VIEW_OWN_PAYSLIPS` | Staff view/download own payslips |

**Overrides:** Portal Admin (permission 17 or existing admin pattern) and HR role `20` grant all payroll capabilities (same spirit as Leave).  
**Seeding:** Upsert catalog; assign **110–117** to Admin group; assign **110,111,112,113,114** to HR group; **115** to supervisor-capable groups if identifiable, else HR only + manual assign; **116,117** to general staff group(s) used by Leave self-service.

---

## 7. API surface (v1)

| Method | Path | Perm |
|--------|------|------|
| GET/PUT | `/payroll/settings` | MANAGE_SETUP / VIEW |
| CRUD | `/payroll/wage-types` | MANAGE_SETUP |
| CRUD | `/payroll/tax-rules` (+ bands) | MANAGE_SETUP |
| GET/PUT | `/payroll/staff/{id}/pay` | MANAGE_STAFF_PAY |
| CRUD | `/payroll/staff/{id}/wage-items` | MANAGE_STAFF_PAY |
| GET/POST | `/payroll/periods` | RUN_PAYROLL |
| PUT | `/payroll/periods/{id}/fx` | RUN_PAYROLL |
| GET/POST | `/payroll/runs` | RUN_PAYROLL |
| POST | `/payroll/runs/{id}/simulate` | RUN_PAYROLL |
| POST | `/payroll/runs/{id}/post` | RUN_PAYROLL |
| GET | `/payroll/runs/{id}/lines` | RUN_PAYROLL / VIEW_HUB |
| GET | `/payroll/payslips` (mine or filtered) | VIEW_OWN / RUN |
| GET | `/payroll/payslips/{id}/pdf` | owner or RUN |
| GET/POST | `/payroll/loans` | REQUEST / MANAGE |
| POST | `/payroll/loans/{id}/decide` | APPROVE_LOANS |
| POST | `/payroll/loans/{id}/disburse` | MANAGE_LOANS |
| GET | `/payroll/dashboard` | VIEW_HUB |

---

## 8. SPA UX

- Nav: **Payroll** (primary or more) — `anyPermission: [110..117]`; icon `fa-money-check-dollar`.  
- **Hub** `/payroll` — KPIs (open period, last run, pending loan approvals, staff missing pay master).  
- **Runs** `/payroll/runs`, detail with simulate/post.  
- **Payslips** `/payroll/payslips` — staff sees own; admin filters.  
- **Loans** `/payroll/loans` — tabs: Mine / Approvals / Admin.  
- **Setup** `/payroll/setup` — tabs: Settings & currency, Wage types, Tax rules, Benefits (wage types filtered).  

Visual language: match toned-down Performance/Leave (neutral cards, primary green actions only).

---

## 9. Implementation phases (same release train)

Still one module; ship in ordered commits:

1. Module scaffold + permissions seeder + settings/currency  
2. Wage types + tax rules + staff pay master APIs/UI  
3. Periods + FX + run simulate/post engine  
4. Payslip PDF + staff self-service  
5. Loans/advances + schedule deduction into run  
6. Dashboard, audit log, polish  

---

## 10. Testing

- Feature tests: permission gates; simulate math (fixed + % + band tax); post immutability; loan installment deduction; multi-currency aggregation.  
- Manual: create staff pay → wage items → period FX → simulate → post → download payslip; loan request → approve → disburse → appear on next run.

---

## Open points (defaults if unspecified)

1. **Net floor:** 0 (block post if any line net &lt; 0 unless RUN_PAYROLL override flag).  
2. **PDF engine:** reuse existing portal PDF approach (Dompdf/similar already used elsewhere).  
3. **Interest:** simple interest amortized evenly across installments.  
