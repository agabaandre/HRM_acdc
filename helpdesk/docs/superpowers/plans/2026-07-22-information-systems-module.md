# Information Systems Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Helpdesk Modules inventory for Africa CDC information systems (Excel import, nested modules, shared lifecycle statuses, staff-directory focals, permission-gated CRUD, reports with trends, and optional system link on ticket resolve for IT & MIS).

**Architecture:** Mirror IT Assets/Licenses: Laravel models + `/api/v1/tools/information-systems` CRUD, status-change events for trends, artisan Excel import, Vue tools page + Reports tab, Agents permission flag. Ticket resolve reuses the asset-link pattern with a BU flag and `linked_information_system_id`.

**Tech Stack:** Laravel 11, PhpSpreadsheet, PHPUnit feature tests, Vue 3 + Vuetify (existing helpdesk frontend), staff directory lookup via existing Staff API clients.

**Spec:** `helpdesk/docs/superpowers/specs/2026-07-22-information-systems-module-design.md`

## Global Constraints

- Shared statuses (DB values): `to_be_developed`, `in_development`, `under_testing`, `in_use`, `decommissioned`.
- Excel map: Active→`in_use`, Developed→`under_testing`, Not yet Developed / blank→`to_be_developed`.
- Empty version → `1.0`.
- Permission: `can_manage_information_systems`; `HelpdeskProfile::canManageInformationSystems()` returns true for helpdesk admins (same pattern as licenses) **or** the flag; backfill flag true for existing admins / portal role 10.
- Nav + tools APIs: permission only (no public browse).
- Ticket link: optional; BU `allows_information_system_link_on_resolve`; seed **true** for slug `it-mis`.
- Import default file: `helpdesk/Africa CDC Information Systems.xlsx`.
- Programming languages: normalized catalogue + pivot (not free-text `tech_stack`).
- Division: Staff/APM `division_id`; **null = All** (Excel All/blank/unmatched → null).
- Profile + three manuals: URL columns only; APM special-memo style preview modal.
- Sync `helpdesk/client/src/...` mirrors when changing frontend under `helpdesk/frontend/src/...`.
- Do not commit unless the user asks (workspace git rule).

## File map

| Path | Responsibility |
|------|----------------|
| `backend/database/migrations/2026_07_22_200000_information_systems_module.php` | Tables, permission col, BU flag, ticket FK, admin backfill |
| `backend/app/Support/InformationSystemLanguageNormalizer.php` | Tokenize + canonicalize Excel tech strings |
| `backend/app/Models/HelpdeskInformationSystemLanguage.php` | Language catalogue |
| `frontend/src/components/common/DocumentLinkPreviewModal.vue` | APM-style link/attachment preview |
| `backend/app/Models/HelpdeskInformationSystem.php` | System model |
| `backend/app/Models/HelpdeskInformationSystemModule.php` | Nested module |
| `backend/app/Models/HelpdeskInformationSystemStatusEvent.php` | History |
| `backend/app/Services/InformationSystemStatusRecorder.php` | Write events on create/status change |
| `backend/app/Services/InformationSystemStaffMatcher.php` | Exact + fuzzy name → staff_id |
| `backend/app/Console/Commands/ImportInformationSystemsCommand.php` | Excel import |
| `backend/app/Http/Controllers/Api/V1/Tools/InformationSystemController.php` | Tools CRUD + summary/export/trends |
| `backend/app/Http/Controllers/Api/V1/Tools/AuthorizesHelpdeskTools.php` | `ensureInformationSystemsManager` |
| `backend/app/Http/Controllers/Api/V1/TicketResolutionController.php` | Optional `linked_information_system_id` |
| `backend/app/Http/Controllers/Api/V1/TicketController.php` | `linkableInformationSystems` |
| `frontend/src/views/tools/InformationSystemsView.vue` | Manage UI |
| `frontend/src/views/ReportsView.vue` | IS reports tab |
| `frontend/src/components/tickets/TicketResolveModal.vue` | Optional system picker |
| `backend/tests/Feature/InformationSystemsTest.php` | CRUD, permission, import, resolve link, reports |

---

### Task 1: Status helper, migration, models, permission plumbing

**Files:**
- Create: `helpdesk/backend/app/Support/InformationSystemStatus.php`
- Create: `helpdesk/backend/app/Support/InformationSystemLanguageNormalizer.php`
- Create: `helpdesk/backend/database/migrations/2026_07_22_200000_information_systems_module.php`
- Create: `helpdesk/backend/app/Models/HelpdeskInformationSystem.php`
- Create: `helpdesk/backend/app/Models/HelpdeskInformationSystemModule.php`
- Create: `helpdesk/backend/app/Models/HelpdeskInformationSystemStatusEvent.php`
- Create: `helpdesk/backend/app/Models/HelpdeskInformationSystemLanguage.php`
- Modify: `helpdesk/backend/app/Models/HelpdeskProfile.php`
- Modify: `helpdesk/backend/app/Models/HelpdeskBusinessUnit.php`
- Modify: `helpdesk/backend/app/Models/HelpdeskTicket.php`
- Modify: `helpdesk/backend/app/Http/Resources/Api/V1/MeResource.php`
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/Tools/AuthorizesHelpdeskTools.php`
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/Admin/AdminHelpdeskAgentController.php` (validate/expose flag)
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/Admin/AdminStaffPermissionController.php` (if tools flags listed there)
- Test: `helpdesk/backend/tests/Feature/InformationSystemsTest.php` (permission helpers / migration smoke via later tasks)

**Interfaces:**
- Produces: `InformationSystemStatus::ALL`, `::fromExcel(?string): string`, `::label(string): string`
- Produces: `HelpdeskProfile::canManageInformationSystems(): bool`
- Produces: models with relations `modules()`, `statusEvents()` (polymorphic-style via entity_type)

- [ ] **Step 1: Add status support class**

```php
<?php

namespace App\Support;

final class InformationSystemStatus
{
    public const TO_BE_DEVELOPED = 'to_be_developed';
    public const IN_DEVELOPMENT = 'in_development';
    public const UNDER_TESTING = 'under_testing';
    public const IN_USE = 'in_use';
    public const DECOMMISSIONED = 'decommissioned';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::TO_BE_DEVELOPED,
            self::IN_DEVELOPMENT,
            self::UNDER_TESTING,
            self::IN_USE,
            self::DECOMMISSIONED,
        ];
    }

    public static function fromExcel(?string $raw): string
    {
        $key = strtolower(trim((string) $raw));
        return match ($key) {
            'active' => self::IN_USE,
            'developed' => self::UNDER_TESTING,
            'not yet developed', '' => self::TO_BE_DEVELOPED,
            default => in_array($key, self::all(), true) ? $key : self::TO_BE_DEVELOPED,
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::TO_BE_DEVELOPED => 'To be Developed',
            self::IN_DEVELOPMENT => 'In development',
            self::UNDER_TESTING => 'Under Testing',
            self::IN_USE => 'In Use',
            self::DECOMMISSIONED => 'Decommissioned',
            default => $status,
        };
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
```

- [ ] **Step 2: Write migration** creating:
  - `helpdesk_information_systems` (columns per **updated** spec: `division_id` nullable, `*_url` doc fields, **no** `tech_stack` / free-text division)
  - `helpdesk_information_system_languages` + pivot `helpdesk_information_system_language`
  - `helpdesk_information_system_modules` (unique `information_system_id`+`name`)
  - `helpdesk_information_system_status_events`
  - `helpdesk_profiles.can_manage_information_systems` default false
  - Backfill: admins / portal role 10 → permission true
  - `helpdesk_business_units.allows_information_system_link_on_resolve`; set true where `slug='it-mis'`
  - `helpdesk_tickets.linked_information_system_id` nullable FK nullOnDelete

- [ ] **Step 2b: Language normalizer** with alias map (`javascript`→JavaScript, `mysql`→MySQL, `nodejs`/`node js`→Node.js, `codeigniter3`→CodeIgniter 3, etc.) and `normalizeList(string $raw): list<string>` returning display names.

- [ ] **Step 3: Run migration**

```bash
cd helpdesk/backend && php artisan migrate --force --no-interaction
```

Expected: migration runs without error; columns exist.

- [ ] **Step 4: Add Eloquent models** with fillable/casts/relations; add `canManageInformationSystems()` mirroring `canManageLicenses()`; include flag in `hasAnyToolsAccess()`; wire ticket `linkedInformationSystem()`; BU fillable/cast for new flag; MeResource + admin agent permission payloads.

- [ ] **Step 5: Add `ensureInformationSystemsManager` to AuthorizesHelpdeskTools**

```php
protected function ensureInformationSystemsManager(Request $request): void
{
    $p = $request->user()?->helpdeskProfile;
    abort_unless($p && $p->canManageInformationSystems(), 403, 'You need Information Systems management permission.');
}
```

---

### Task 2: Status recorder + staff name matcher + import command

**Files:**
- Create: `helpdesk/backend/app/Services/InformationSystemStatusRecorder.php`
- Create: `helpdesk/backend/app/Services/InformationSystemStaffMatcher.php`
- Create: `helpdesk/backend/app/Console/Commands/ImportInformationSystemsCommand.php`
- Modify: `helpdesk/backend/app/Services/StaffDirectoryLookupService.php` (add `searchByName(string $name, int $limit = 10): array` if missing — query staff API / DB the same way ticket staff search does)
- Test: extend `InformationSystemsTest.php`

**Interfaces:**
- Produces: `InformationSystemStatusRecorder::record(string $entityType, int $entityId, ?string $from, string $to, ?int $userId): void`
- Produces: `InformationSystemStaffMatcher::resolve(?string $displayName): array{staff_id:?int,name_raw:?string}`
- Produces: artisan `helpdesk:import-information-systems {path?} {--fresh}`

- [ ] **Step 1: Failing test — import maps status and defaults version**

```php
public function test_import_maps_excel_status_and_defaults_version(): void
{
    $this->artisan('helpdesk:import-information-systems', [
        'path' => base_path('../Africa CDC Information Systems.xlsx'),
    ])->assertSuccessful();

    $row = \App\Models\HelpdeskInformationSystem::query()->where('name', 'RedCap')->first();
    $this->assertNotNull($row);
    $this->assertSame('in_use', $row->status);
    $this->assertSame('1.0', $row->version);
    $this->assertDatabaseHas('helpdesk_information_system_status_events', [
        'entity_type' => 'system',
        'entity_id' => $row->id,
        'to_status' => 'in_use',
    ]);
}
```

- [ ] **Step 2: Run test — expect FAIL (command missing)**

```bash
cd helpdesk/backend && php artisan test --filter=test_import_maps_excel_status_and_defaults_version
```

- [ ] **Step 3: Implement recorder, matcher, import command**
  - Read sheet with PhpSpreadsheet; skip empty System Name.
  - Upsert by `name` (update fields; do not delete modules unless `--fresh` which truncates systems/modules/events first).
  - On create only: record status event `from=null`.
  - Focal / MIS: `InformationSystemStaffMatcher` — normalize (strip `Dr.`, collapse spaces), exact match on directory name, else fuzzy (Levenshtein / `similar_text` ≥ 85% among first N search hits); store `*_staff_id` and always keep `*_name_raw`.

- [ ] **Step 4: Re-run test — expect PASS**

- [ ] **Step 5: Run import locally once** after migrate:

```bash
cd helpdesk/backend && php artisan helpdesk:import-information-systems
```

Expected: ~28 systems.

---

### Task 3: Tools API (CRUD, modules, summary, export, trends)

**Files:**
- Create: `helpdesk/backend/app/Http/Controllers/Api/V1/Tools/InformationSystemController.php`
- Create: `helpdesk/backend/app/Exports/InformationSystemsExport.php` (Maatwebsite Excel, mirror TicketsExport style)
- Modify: `helpdesk/backend/routes/api.php`
- Test: `InformationSystemsTest.php`

**Interfaces:**
- Routes under auth middleware group:
  - `GET/POST /api/v1/tools/information-systems`
  - `GET /api/v1/tools/information-systems/summary`
  - `GET /api/v1/tools/information-systems/export`
  - `GET /api/v1/tools/information-systems/reports/trends?date_from=&date_to=&bucket=day`
  - `GET/PUT/DELETE /api/v1/tools/information-systems/{informationSystem}`
  - `POST /api/v1/tools/information-systems/{informationSystem}/modules`
  - `PUT/DELETE /api/v1/tools/information-systems/{informationSystem}/modules/{module}`

- [ ] **Step 1: Failing tests**

```php
public function test_information_systems_index_requires_permission(): void
{
    $user = /* create user with profile can_manage_information_systems=false, not admin */;
    $this->actingAs($user)->getJson('/api/v1/tools/information-systems')->assertForbidden();
}

public function test_manager_can_create_system_and_module_and_status_event_on_change(): void
{
    $user = /* manager */;
    $create = $this->actingAs($user)->postJson('/api/v1/tools/information-systems', [
        'name' => 'Test Sys',
        'status' => 'in_development',
        'version' => '1.0',
    ])->assertCreated();
    $id = $create->json('data.id');
    $this->actingAs($user)->postJson("/api/v1/tools/information-systems/{$id}/modules", [
        'name' => 'Auth',
        'description' => 'Login',
        'status' => 'to_be_developed',
    ])->assertCreated();
    $this->actingAs($user)->putJson("/api/v1/tools/information-systems/{$id}", [
        'status' => 'in_use',
    ])->assertOk();
    $this->assertDatabaseHas('helpdesk_information_system_status_events', [
        'entity_type' => 'system',
        'entity_id' => $id,
        'from_status' => 'in_development',
        'to_status' => 'in_use',
    ]);
    $list = $this->actingAs($user)->getJson('/api/v1/tools/information-systems');
    $list->assertOk();
    $this->assertSame(1, $list->json('data.0.modules_count') ?? $list->json('data.0.modules_count'));
}
```

(Adjust pagination JSON shape to match LicenseController — likely Laravel paginator `data` array.)

- [ ] **Step 2: Implement controller** — validate status via `Rule::in(InformationSystemStatus::all())`; default version `1.0`; withCount modules; on store/update call recorder when status changes; summary returns:

```php
[
  'systems_total' => int,
  'systems_by_status' => [status => count],
  'modules_total' => int,
  'modules_by_status' => [...],
  'missing_focal' => int,
  'missing_mis_focal' => int,
  'by_division' => [division => count],
]
```

Trends: group events by `DATE(changed_at)` (or week) within date range; return `[{date, to_status, count}]`.

- [ ] **Step 3: Wire routes** next to licenses block in `api.php`.

- [ ] **Step 4: Run tests — expect PASS**

```bash
cd helpdesk/backend && php artisan test --filter=InformationSystemsTest
```

---

### Task 4: Ticket resolve linkage

**Files:**
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/TicketResolutionController.php`
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/TicketController.php` (`linkableInformationSystems`)
- Modify: `helpdesk/backend/app/Http/Resources/Api/V1/TicketResource.php`
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/Admin/AdminHelpdeskBusinessUnitController.php`
- Modify: `helpdesk/backend/app/Http/Controllers/Api/V1/BusinessUnitController.php` (expose flag)
- Modify: `helpdesk/backend/routes/api.php`
- Modify: `helpdesk/frontend/src/components/tickets/TicketResolveModal.vue` (+ client copy)
- Modify: `helpdesk/frontend/src/views/TicketDetailView.vue` (+ client)
- Modify: `helpdesk/frontend/src/components/settings/CategoriesManagementPanel.vue` (+ client) — BU checkbox
- Test: `InformationSystemsTest` or `TicketResolutionInformationSystemLinkTest`

**Interfaces:**
- Produces: `GET /api/v1/tickets/{ticket}/linkable-information-systems?q=`
- Consumes: BU flag; systems where `status != decommissioned` (or include all except force-hide decommissioned from picker)

- [ ] **Step 1: Failing test**

```php
public function test_resolve_can_link_information_system_when_bu_allows(): void
{
    // IT & MIS ticket + system in_use + agent with resolve permission
    $this->postJson("/api/v1/tickets/{$ticket->id}/submit-resolution", [
        'resolution_summary' => '<p>Fixed</p>',
        'linked_information_system_id' => $system->id,
    ])->assertOk();
    $this->assertSame($system->id, $ticket->fresh()->linked_information_system_id);
}

public function test_resolve_rejects_system_link_when_bu_disallows(): void
{
    // BU with flag false
    $this->postJson(...)->assertStatus(422);
}
```

- [ ] **Step 2: Implement backend** mirroring asset link validation (no requester ownership check). History log meta includes `linked_information_system_id`.

- [ ] **Step 3: Frontend resolve modal** — if `ticket.business_unit.allows_information_system_link_on_resolve`, show searchable select; POST field when selected. Detail view shows linked system name under asset line.

- [ ] **Step 4: BU settings checkbox** `Allow Information System on resolve` bound to `allows_information_system_link_on_resolve`.

- [ ] **Step 5: Run tests — PASS**

---

### Task 5: Frontend tools page + nav + agents permission

**Files:**
- Create: `helpdesk/frontend/src/views/tools/InformationSystemsView.vue`
- Create: `helpdesk/frontend/src/components/common/DocumentLinkPreviewModal.vue`
- Modify: `helpdesk/frontend/src/lib/toolsNav.ts`
- Modify: `helpdesk/frontend/src/lib/toolsPermissions.ts` (if needed)
- Modify: `helpdesk/frontend/src/router/index.ts`
- Modify: `helpdesk/frontend/src/stores/auth.ts` (profile type)
- Modify: `helpdesk/frontend/src/components/settings/AgentsManagementPanel.vue`
- Mirror all under `helpdesk/client/src/...`

**Interfaces:**
- Nav item: `{ path: '/tools/information-systems', label: 'Information Systems', icon: 'bx bx-server', permission: 'can_manage_information_systems' }`
- Agents TOOLS_KEYS entry `{ key: 'can_manage_information_systems', label: 'Information systems' }`

- [ ] **Step 1: Wire nav + router meta `requiresToolsPermission: 'can_manage_information_systems'`**

- [ ] **Step 2: Build `InformationSystemsView.vue`** patterned on `LicensesView.vue` / `ItAssetsView.vue`:
  - Load summary + paginated list; load divisions from `/api/v1/reference-data`; languages from `/tools/information-systems/languages`
  - Columns: Name, Status, Version, Division (All if null), Languages chips, Focal, MIS Focal, Modules count, Actions
  - Modal: division select with All option (`null`), multi-select languages, four URL fields
  - Wire `DocumentLinkPreviewModal` (image / PDF iframe / Google Docs viewer / open fallback — same rules as APM special-memo)
  - Nested modules editor (name, description, status)
  - Staff picker for focals via existing directory search

- [ ] **Step 3: AgentsManagementPanel** — add permission checkbox to tools list.

- [ ] **Step 4: `npm run build` in `helpdesk/frontend`** — expect success; sync client copies.

---

### Task 6: Reports UI + docs

**Files:**
- Modify: `helpdesk/frontend/src/views/ReportsView.vue` (+ client)
- Modify: `helpdesk/documentation/ADMIN_GUIDE.md`
- Modify: `helpdesk/documentation/USER_GUIDE.md` (resolve optional system link)
- Optional: `VerifyProductionReadinessCommand` columns check for new tables/flags

**Interfaces:**
- Reports tab `infosystems` visible when `auth.me.profile.can_manage_information_systems` or admin (same as canManage helper — frontend uses me flag; admins always true from API).

- [ ] **Step 1: Add Reports tab** calling `/summary`, `/reports/trends`, export button → `/export`
  - Tiles + by-division table + simple trend table/chart (v-data-table of daily counts is enough if chart lib absent)
  - Date from/to using existing `UDateInput` pattern on Reports

- [ ] **Step 2: Document** import command, permission, IT & MIS resolve link, status enum in ADMIN_GUIDE; agent resolve step in USER_GUIDE.

- [ ] **Step 3: Full backend test suite slice**

```bash
cd helpdesk/backend && php artisan test --filter=InformationSystems
```

Expected: all PASS.

- [ ] **Step 4: Frontend build**

```bash
cd helpdesk/frontend && npm run build
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Shared status enum + Excel map | 1, 2 |
| Systems + modules tables | 1, 3 |
| Language catalogue + pivot + normalizer | 1, 2, 3, 5 |
| Division_id + All default | 1, 2, 3, 5 |
| Doc URL fields + preview modal | 1, 5 |
| Status events / trends | 1, 3, 6 |
| Version default 1.0 | 2, 3 |
| Focal fuzzy match | 2 |
| Excel import artisan | 2 |
| Permission + role 10/admin | 1, 5 |
| Tools nav + CRUD UI | 5 |
| Reports summary/export/trends | 3, 6 |
| Optional resolve link + IT & MIS flag | 1, 4 |
| Docs | 6 |

## Placeholder / consistency scan

- Status keys consistently snake_case in API/DB; labels only in UI/`InformationSystemStatus::label`.
- Permission method name: `canManageInformationSystems` / column `can_manage_information_systems`.
- Route model binding: `{informationSystem}` → `HelpdeskInformationSystem`.
