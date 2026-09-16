# Risk Register Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a sibling Risk Register app at `/staff/risk-register` with Staff Portal org hooks, Excel import, approval workflow, quarterly reviews, and Highcharts dashboard per the approved design.

**Architecture:** Strip the Staff Portal clone in `modules/risk-register` into a Risk-only Laravel+Vue app with its own DB; read org/people from Staff Portal via Share/SSO; Staff Portal gains `risk_focal_person`, CBP module, and permissions.

**Tech Stack:** Laravel 12, Vue 3, Vuetify (portal patterns), Highcharts (credits: false), MySQL, PHPUnit, Apache rewrite (same as helpdesk).

**Spec:** `docs/superpowers/specs/2026-09-16-risk-register-design.md`

## Global Constraints

- Sibling app only — no risk CRUD inside Staff Portal SPA.
- Staff Portal is sole writer for org roles (risk focal, HOD, director).
- Risk Register DB owns risks, lookups, workflow, reviews, audit (`rr_*` tables).
- SSO: `staff_app_token` → POST `/staff/risk-register/sso/accept` (Helpdesk pattern).
- Permission IDs (allocate if free): CBP module `118`, `view_division_risks` `119`, `view_all_risks` `120`, `manage_risks` `121`.
- Residual: L′=max(1,L−r), I′=max(1,I−⌊r/2⌋); Not Assessed ⇒ residual = inherent.
- Import: one-time; owners default HOD; no forced re-approval for imported rows.
- Highcharts: `credits: { enabled: false }` on every chart.
- TDD for domain services; frequent commits per task.
- Do not commit secrets (`.env`, passwords) or binary noise.

## File map

| Path | Responsibility |
|------|----------------|
| `modules/staff-portal/backend/database/migrations/*_add_risk_focal_person_to_divisions.php` | Column + backfill |
| `modules/staff-portal/.../CbpModulesAdminService.php` | `risk_register` CORE_MODULES entry |
| `modules/staff-portal/.../DivisionsSettingsPage.vue` + OrgUnits API | Risk focal UI |
| `modules/staff-portal/.../ShareReferenceDataService.php` | Divisions payload includes risk focal |
| `.htaccess` (repo root) | Map `risk-register` like helpdesk |
| `modules/risk-register/backend/` | Laravel API (stripped modules) |
| `modules/risk-register/frontend/` | Vue SPA |
| `modules/risk-register/backend/app/Services/ResidualRiskCalculator.php` | Score/rating math |
| `modules/risk-register/backend/app/Services/BusinessUnitMatcher.php` | Excel BU → division/directorate |
| `modules/risk-register/backend/app/Services/ExcelRiskImportService.php` | Import orchestration |
| `modules/risk-register/backend/app/Services/RiskWorkflowService.php` | Approve / feedback / skip director |
| `modules/risk-register/backend/app/Services/StaffPortalOrgClient.php` | Read-only Share client |
| `modules/risk-register/backend/app/Services/RiskAuditLogger.php` | Audit trail |
| `docs/SETUP.md` | Operator notes for Risk Register |

---

### Task 1: Staff Portal — risk focal, permissions, CBP module

**Files:**
- Create: `modules/staff-portal/backend/database/migrations/2026_09_16_120000_add_risk_focal_person_to_divisions.php`
- Modify: `modules/staff-portal/backend/Modules/Settings/app/Services/CbpModulesAdminService.php`
- Modify: `modules/staff-portal/backend/Modules/Settings/app/Http/Controllers/Api/V1/OrgUnitsSettingsController.php`
- Modify: `modules/staff-portal/frontend/src/pages/settings/DivisionsSettingsPage.vue`
- Modify: `modules/staff-portal/frontend/src/lib/settingsApi.ts`
- Test: `modules/staff-portal/backend/tests/Feature/RiskFocalPersonMigrationTest.php`

**Interfaces:**
- Produces: `divisions.risk_focal_person` (int|null); CBP `module_key=risk_register`, `base_url=risk-register`, `permission_code=118`, `uses_staff_portal_token=1`, `target_resolver=staff_app_token`
- Produces permissions rows 119–121 (or next free) with names `view_division_risks`, `view_all_risks`, `manage_risks`; assign 119 to default staff group pattern; 121 to System Administrator (10) + OIO group if present

- [x] **Step 1: Write failing test for risk_focal backfill**

```php
public function test_risk_focal_defaults_from_focal_person(): void
{
    // sqlite memory + divisions table with focal_person, then run migration logic
    DB::table('divisions')->insert([
        'division_id' => 1,
        'division_name' => 'Test',
        'focal_person' => 62,
        'division_head' => 169,
    ]);
    // after migration helper:
    $this->assertSame(62, (int) DB::table('divisions')->where('division_id', 1)->value('risk_focal_person'));
}
```

- [x] **Step 2: Run test — expect FAIL (column missing)**

Run: `cd modules/staff-portal/backend && php artisan test --filter=RiskFocalPersonMigrationTest`

- [x] **Step 3: Add migration**

```php
Schema::table('divisions', function (Blueprint $table) {
    if (! Schema::hasColumn('divisions', 'risk_focal_person')) {
        $table->unsignedInteger('risk_focal_person')->nullable()->after('focal_person');
    }
});
DB::table('divisions')->whereNull('risk_focal_person')->update([
    'risk_focal_person' => DB::raw('focal_person'),
]);
```

- [x] **Step 4: Seed CBP module + permissions**

In `CbpModulesAdminService::CORE_MODULES` append:

```php
[
    'module_key' => 'risk_register',
    'system_name' => 'Risk Register',
    'description' => 'Enterprise risk register, quarterly reviews, and OIO oversight.',
    'base_url' => 'risk-register',
    'icon_class' => 'fa-shield-halved',
    'permission_code' => '118',
    'uses_staff_portal_token' => 1,
    'is_production' => 1,
    'is_enabled' => 1,
    'show_in_apm_menu' => 1,
    'target_resolver' => 'staff_app_token',
    'sort_order' => 40,
],
```

Add seeder/artisan one-shot to insert permissions 118–121 and `user_group_permissions` for group 10 on 118+121; default group(s) on 119.

- [x] **Step 5: Wire Divisions settings API + Vue**

Extend validation/payload with `risk_focal_person` (required integer like `focal_person`). Add staff picker on `DivisionsSettingsPage.vue` labeled “Risk Focal Person”.

- [x] **Step 6: Re-run tests; commit**

```bash
cd modules/staff-portal/backend && php artisan test --filter=RiskFocalPerson
git add modules/staff-portal docs/superpowers/specs/2026-09-16-risk-register-design.md
git commit -m "Add division risk focal person, CBP Risk Register module, and risk permissions."
```

---

### Task 2: Scaffold Risk Register app + Apache + SSO accept

**Files:**
- Modify: `.htaccess` (root) — add `risk-register` to sibling app rewrite list
- Modify/create: `modules/risk-register/backend/.env.example`, `setup.env.example`, `README.md`
- Create: `modules/risk-register/backend/routes/web.php` SSO routes
- Create: `modules/risk-register/backend/app/Http/Controllers/SsoAcceptController.php` (mirror helpdesk)
- Remove/disable unused nwidart modules (Leave, Payroll, Performance, …) from `modules_statuses.json` / providers — keep Auth-equivalent session, API skeleton
- Create DB `risk_register` via setup docs

**Interfaces:**
- Produces: `POST /sso/accept` sets session from Staff Portal JWT; redirects to SPA `/`
- Consumes: Staff Portal launch_module token (same as helpdesk)

- [ ] **Step 1: Write failing feature test**

```php
public function test_sso_accept_requires_token(): void
{
    $this->post('/sso/accept', [])->assertStatus(422);
}
```

- [ ] **Step 2: Run — FAIL or 404 until route exists**

- [ ] **Step 3: Implement minimal SSO accept + rewrite**

Root `.htaccess` (with existing apm/finance/helpdesk rules):

```apache
RewriteRule ^(apm|finance|helpdesk|risk-register)$ /staff/$1/ [R=301,L]
RewriteRule ^(apm|finance|helpdesk|risk-register)/(.*)$ modules/$1/$2 [L]
```

SSO controller: validate token via Staff Share `validate_session` / launch payload; store `staff_id`, `permissions`, `division_id` in session; redirect SPA.

- [ ] **Step 4: Smoke curl**

```bash
curl -sI http://localhost/staff/risk-register/ | head -5
# expect 200/302 HTML, not 404
```

- [ ] **Step 5: Commit**

```bash
git commit -m "Scaffold Risk Register sibling app routing and SSO accept."
```

---

### Task 3: Lookups schema + ResidualRiskCalculator

**Files:**
- Create migrations for all `rr_*` lookup tables + seeders from Sheet 3
- Create: `modules/risk-register/backend/app/Services/ResidualRiskCalculator.php`
- Test: `tests/Unit/ResidualRiskCalculatorTest.php`

**Interfaces:**
- Produces:

```php
final class ResidualRiskCalculator
{
    /** @return array{inherent_score:int,inherent_rating:string,residual_likelihood:int,residual_impact:int,residual_score:int,residual_rating:string,movement:string} */
    public function compute(int $likelihood, int $impact, int $effectivenessReduction, bool $effectivenessAssessed): array;
}
```

- [ ] **Step 1: Failing unit tests**

```php
public function test_inherent_critical_and_residual_with_substantial(): void
{
    $c = new ResidualRiskCalculator(fn (int $s) => match (true) {
        $s <= 4 => 'Low', $s <= 9 => 'Medium', $s <= 15 => 'High', default => 'Critical',
    });
    $r = $c->compute(5, 5, 3, true); // Substantial reduction 3
    $this->assertSame(25, $r['inherent_score']);
    $this->assertSame(2, $r['residual_likelihood']); // 5-3
    $this->assertSame(4, $r['residual_impact']);     // 5-floor(3/2)
    $this->assertSame(8, $r['residual_score']);
}

public function test_not_assessed_copies_inherent(): void
{
    $c = new ResidualRiskCalculator(fn (int $s) => 'Critical');
    $r = $c->compute(5, 5, 0, false);
    $this->assertSame(25, $r['residual_score']);
}
```

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement calculator + migrations + `RiskLookupSeeder`**

Nine enterprise themes must match Excel Sheet 1 column values (full strings).

- [ ] **Step 4: Pass tests; commit**

```bash
git commit -m "Add risk lookup tables and residual risk calculator."
```

---

### Task 4: Org client + BusinessUnitMatcher + Excel import

**Files:**
- Create: `StaffPortalOrgClient.php`, `BusinessUnitMatcher.php`, `ExcelRiskImportService.php`
- Create: `app/Console/Commands/ImportRiskExcelCommand.php` (`risk:import-excel`)
- Test: `tests/Unit/BusinessUnitMatcherTest.php`, `tests/Feature/ExcelRiskImportTest.php`
- Keep workbook at `modules/risk-register/Copy of Africa CDC Risk Register Tracker 2026 Categorised.xlsx`

**Interfaces:**
- `BusinessUnitMatcher::match(string $businessUnit): array{division_id:?int,directorate_id:?int,unmapped:?string}`
- `ExcelRiskImportService::import(string $path): array{imported:int,unmatched:int,owners_defaulted:int}`
- Import sets `workflow_state=active_imported` (signed-off historical); owners = HOD when division matched

- [x] **Step 1: Matcher tests**

```php
public function test_parses_phc_chshp(): void
{
    $org = [
        'divisions' => [
            ['division_id' => 35, 'division_short_name' => 'CHSHP', 'division_name' => 'Community Health Systems & Health Promotion', 'directorate_id' => 1, 'division_head' => 169],
        ],
        'directorates' => [
            ['id' => 1, 'name' => 'Centre for Primary Health Care', 'aliases' => ['PHC']],
        ],
    ];
    $m = new BusinessUnitMatcher($org);
    $r = $m->match('PHC/CHSHP');
    $this->assertSame(35, $r['division_id']);
    $this->assertSame(1, $r['directorate_id']);
}
```

- [x] **Step 2: Implement matcher (short_name exact ci → name contains; directorate alias map)**

- [x] **Step 3: Feature test import inserts ≥1 risk with owner HOD on fixture xlsx subset or full file**

- [x] **Step 4: Implement import using PhpSpreadsheet or XML zip reader (repo may lack PhpSpreadsheet — prefer lightweight sharedStrings parser already used in exploration, or `composer require phpoffice/phpspreadsheet` in risk-register)**

- [x] **Step 5: Run `php artisan risk:import-excel` locally; commit**

```bash
git commit -m "Import Excel risks with business-unit matching and HOD owners."
```

---

### Task 5: Risk CRUD, owners, audit, register + drill-down UI

**Files:**
- Migrations: `rr_risks`, `rr_risk_owners`, `rr_audit_logs`
- API: `Modules/Risks` or `app/Http/Controllers/Api/V1/RiskController.php`
- `RiskAuditLogger::log(string $action, string $entity, int $id, ?array $before, ?array $after): void`
- Frontend: `RiskRegisterPage.vue`, `RiskShowPage.vue`, router entries
- Policies: division scope unless `view_all_risks` / `manage_risks`

**Interfaces:**
- `GET /api/v1/risks?division_id=`
- `POST /api/v1/risks` (focal / manage)
- `GET /api/v1/risks/{id}` — full profile + owners + audit
- `PUT /api/v1/risks/{id}` — manage or focal (pre-signoff) or manage always

- [x] **Step 1: Feature test create risk writes audit + owners**

- [x] **Step 2: Implement API + policy**

- [x] **Step 3: Vue list/form with all Sheet 1 fields + multi owner select (staff search via org client)**

- [x] **Step 4: Drill-down shows full info + audit timeline**

- [x] **Step 5: Commit**

```bash
git commit -m "Add risk CRUD, multi-owners, audit trail, and profile UI."
```

---

### Task 6: Approval workflow + feedback

**Files:**
- Tables: `rr_approval_workflows`, `rr_approval_workflow_steps`, `rr_risk_approvals`, `rr_feedback_requests`, `rr_feedback_responses`
- `RiskWorkflowService.php`
- UI: `WorkflowSettingsPage.vue`, `MyApprovalsPage.vue`
- Default workflow template factory: risk_focal → hod → director (optional) → sm_focal → **one** extra

**Interfaces:**
- `RiskWorkflowService::start(int $riskId): void`
- `approve(int $approvalId, int $actorStaffId): void`
- `requestFeedback(int $approvalId, int $actorStaffId, array $recipientStaffIds, string $message): void` — throws if any recipient not strictly below actor step order
- `eligibleFeedbackRecipients(int $approvalId): list<array{staff_id:int,name:string,level:int}>`

- [ ] **Step 1: Unit/feature tests — director skipped when no director; feedback rejects peer/higher; approve advances; final step sets signed_off**

- [ ] **Step 2: Implement service + API**

- [ ] **Step 3: Workflow admin UI per division; My Approvals actions**

- [ ] **Step 4: Commit**

```bash
git commit -m "Add configurable risk approval workflow and feedback requests."
```

---

### Task 7: Quarterly reviews + trend charts

**Files:**
- `rr_risk_reviews` migration
- `RiskReviewService.php` — default timeline from previous review or risk.timeline
- API + `RiskShowPage.vue` charts (Highcharts, credits false): quarterly L/I/score; annual aggregate

**Interfaces:**
- `POST /api/v1/risks/{id}/reviews` body: `{year, quarter, likelihood_id, impact_id, mitigation_strategy, timeline?}`
- `GET /api/v1/risks/{id}/trends` → `{quarters: [...], annual: [...]}`

- [ ] **Step 1: Test timeline defaults to previous**

- [ ] **Step 2: Implement service + endpoints**

- [ ] **Step 3: UI quarterly form + Highcharts trends on drill-down**

- [ ] **Step 4: Commit**

```bash
git commit -m "Add quarterly risk reviews and trend charts."
```

---

### Task 8: Dashboard (Sheet 2) + settings + org mirror + setup docs

**Files:**
- `DashboardApiController.php` / `RiskDashboardService.php`
- `DashboardPage.vue` — KPIs + charts matching Sheet 2
- Settings CRUD for lookups only
- Org mirror page (read-only from `StaffPortalOrgClient`)
- Import report page (last import stats from cache/table `rr_import_runs`)
- Update `docs/SETUP.md`, `modules/risk-register/README.md`, root `setup.sh` optional risk-register env upsert

- [ ] **Step 1: Feature test dashboard KPI totals match seeded risks**

- [ ] **Step 2: Implement aggregations (inherent/residual bands, by type, by BU, heat map matrix, top 10 residual, status counts)**

- [ ] **Step 3: Vue dashboard with Highcharts `credits: { enabled: false }`**

- [ ] **Step 4: Settings + org mirror + docs**

- [ ] **Step 5: Commit + push if requested**

```bash
git commit -m "Add risk dashboard, settings, org mirror, and setup docs."
```

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| Sibling app + Apache | 2 |
| SSO launch | 2 |
| risk_focal_person + UI | 1 |
| CBP module + perms | 1 |
| Lookups Sheet 3 | 3 |
| Residual math | 3 |
| Excel import + HOD owners + BU match | 4 |
| Risk CRUD, multi owners, audit | 5 |
| Workflow + feedback below | 6 |
| Quarterly + trends | 7 |
| Dashboard Highcharts | 8 |
| Org read-only mirror | 8 |
| manage_risks / OIO edit | 5–6 (policies) |

## Placeholder scan

No TBD/TODO steps; permission IDs fixed to 118–121 (adjust if collision — check `max(id)` before insert).
