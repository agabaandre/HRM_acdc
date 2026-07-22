# Information Systems module (Helpdesk Modules)

**Date:** 2026-07-22  
**Status:** Approved; implementation plan written  
**Approach:** Mirror IT Assets / Licenses tools pattern (Approach 1)  
**Plan:** `docs/superpowers/plans/2026-07-22-information-systems-module.md`

## Problem

Africa CDC maintains an inventory of information systems in a spreadsheet (`Africa CDC Information Systems.xlsx`). Helpdesk has no first-class place to manage systems, their lifecycle status, nested functional modules, or focal people from the staff directory. There is also no reporting or status history for that inventory.

## Goals

1. Add **Information Systems** under Helpdesk Modules (nav label already “Helpdesk Modules”).
2. Store each system with overall **status**, infra/meta fields from Excel, and **version** (default `1.0` when missing).
3. Allow nested **system modules** with name, description, and status; show **module count** on the system list.
4. Use one shared status enum for systems and modules:
   - To be Developed
   - In development
   - Under Testing
   - In Use
   - Decommissioned
5. Map Focal Person and MIS Focal Person to the **staff directory** (exact then fuzzy name match; keep raw name if unmatched).
6. Import/seed the current Excel file into the database.
7. Permission `can_manage_information_systems` in Agents settings; Staff portal **role 10** / helpdesk admins get it by default. Nav + APIs gated by this permission only (no public view).
8. Reports: summary, by status, by division, missing focals, Excel export, and **status-change trends** over time.
9. Optional **link Information System on ticket resolve**, same UX pattern as IT asset link — enabled per Business Unit; **IT & MIS on by default**.

## Non-goals (this iteration)

- Linking systems to IT assets / licenses inventory records.
- Linking a nested system-*module* on resolve (system-level link only for v1).
- Uploading profile/manual files into Helpdesk storage (links only; content lives elsewhere).
- Multi-version release history beyond a single `version` string + status events.
- Graph/Azure infra sync.
- Public read-only catalogue for all staff.

## Decisions (from brainstorming)

| Topic | Choice |
|-------|--------|
| Status model | **B** — one shared enum; Excel remapped on import |
| Focal matching | **A** — exact then fuzzy; unmatched remain as raw text |
| Reports scope | **C** — summary + export + by-division + status history trends |
| Access | **A** — permission-gated only; role 10 / helpdesk admin default manage |
| Implementation shape | **Approach 1** — tools CRUD + Reports tab section |
| Ticket resolve link | Optional system link on resolve (same pattern as IT assets); IT & MIS flag on by default |
| Programming / DB stack | **Normalized catalogue** of languages/tech tags (many-to-many); Excel free text split + normalized on import |
| Division | **Staff/APM directory `division_id`** (via helpdesk reference-data); empty / “All” / unmatched → **All** (`division_id` null) |
| Docs (profile + manuals) | Stored as **external URL links**; preview with APM special-memo style modal (img / PDF iframe / Google Docs viewer) |

### Excel → status mapping

| Excel `Status` | Stored status |
|----------------|---------------|
| Active | `in_use` |
| Developed | `under_testing` |
| Not yet Developed | `to_be_developed` |
| (blank / unknown) | `to_be_developed` |
| (manual later) | `in_development`, `decommissioned` |

Labels in UI: To be Developed, In development, Under Testing, In Use, Decommissioned.

## Data model

### `helpdesk_information_systems`

| Column | Type | Notes |
|--------|------|--------|
| id | bigint PK | |
| name | string | System Name (unique recommended) |
| description | text nullable | |
| status | string(32) | shared enum |
| host | string nullable | |
| host_name | string nullable | |
| ip | string nullable | |
| domain | string nullable | URL/domain |
| os | string nullable | |
| version | string | default `1.0` |
| last_update_on | date nullable | Last Update |
| division_id | unsigned int nullable | Staff/APM division id from `/api/v1/reference-data` divisions; **null = All** |
| focal_staff_id | unsigned int nullable | staff directory id |
| focal_name_raw | string nullable | Excel / unresolved name |
| mis_focal_staff_id | unsigned int nullable | |
| mis_focal_name_raw | string nullable | |
| system_profile_url | string nullable | External link (not uploaded file) |
| user_manual_users_url | string nullable | External link |
| user_manual_managers_url | string nullable | External link |
| user_manual_technical_url | string nullable | External link |
| faqs | text nullable | Free text / notes (not in the four URL preview fields) |
| sops | text nullable | |
| total_users | unsigned int nullable | |
| estimated_annual_hosting_cost | decimal(12,2) nullable | |
| created_by_user_id | FK nullable | helpdesk users |
| timestamps | | |

**Removed vs earlier draft:** free-text `division`, free-text `tech_stack`, and free-text profile/manual columns (replaced by URLs + language catalogue).

### Programming languages (normalized)

| Table | Purpose |
|-------|---------|
| `helpdesk_information_system_languages` | Catalogue: `id`, `name` (display), `slug` (unique), `is_active` |
| `helpdesk_information_system_language` | Pivot: `information_system_id`, `language_id` |

Seed/import builds catalogue from normalized Excel tokens (e.g. `Javascript`→`JavaScript`, `Mysql`/`MysQL RDMBS`→`MySQL`, `Codeigniter3`→`CodeIgniter 3`, `Node Js`/`NodeJS`→`Node.js`). UI multi-select from catalogue; admins can add new language names when needed.

### Division linkage

- Form/API: `division_id` optional integer from Staff reference divisions (same source Settings → General uses via `/api/v1/reference-data`).
- **If none selected / Excel empty / Excel “All” / name unmatched → store `division_id = null` and display as “All”.**
- Import: match Excel Division string to directory division name (case-insensitive); “All” and blank → null.
- List/filter/reports: group by division name resolved from reference cache; null bucket labelled **All**.

### `helpdesk_information_system_modules`

| Column | Type | Notes |
|--------|------|--------|
| id | bigint PK | |
| information_system_id | FK cascade | |
| name | string | |
| description | text nullable | |
| status | string(32) | same enum as systems |
| sort_order | unsigned int default 0 | |
| timestamps | | |

Indexes: `(information_system_id, sort_order)`, unique `(information_system_id, name)`.

### `helpdesk_information_system_status_events`

| Column | Type | Notes |
|--------|------|--------|
| id | bigint PK | |
| entity_type | string | `system` \| `module` |
| entity_id | unsigned bigint | |
| from_status | string nullable | null on create |
| to_status | string | |
| changed_by_user_id | FK nullable | |
| changed_at | timestamp | |
| note | string nullable | optional |

Used for trend reports (counts of transitions over time, current distribution history).

### Permission column

On `helpdesk_profiles`:

- `can_manage_information_systems` boolean default `false`

Default grant when:

- Helpdesk profile `isHelpdeskAdmin()` / Staff portal role in `HELPDESK_SSO_STAFF_ROLE_IDS_ADMIN` (default `10`), on SSO sync or via existing permission bootstrap patterns used for tools flags.
- Agents settings UI can toggle for any agent profile.

### Ticket resolve linkage (mirror IT assets)

Same pattern as `allows_asset_link_on_resolve` / `linked_it_asset_id`:

| Piece | Detail |
|-------|--------|
| BU flag | `helpdesk_business_units.allows_information_system_link_on_resolve` boolean default `false` |
| Seed | Set **true** for IT & MIS (slug `it-mis` / existing IT & MIS row), same as asset flag |
| Ticket FK | `helpdesk_tickets.linked_information_system_id` nullable FK → `helpdesk_information_systems` |
| Resolve API | Optional `linked_information_system_id` on `POST /tickets/{id}/submit-resolution` when BU flag is on; otherwise `prohibited` |
| Search API | `GET /tickets/{ticket}/linkable-information-systems?q=` — searchable active/non-decommissioned systems by name (no requester ownership filter; systems are institutional, not staff-assigned) |
| UI | `TicketResolveModal` / ticket detail resolve flow: optional select next to asset link when BU allows it; show linked system on ticket detail |
| Admin BU modal | Checkbox **Allow Information System on resolve** next to **Allow Asset on resolve** |
| TicketResource | Expose `linked_information_system_id`, nested `linked_information_system` `{ id, name, status, version }`, and BU `allows_information_system_link_on_resolve` |

Agents resolving an IT & MIS ticket may optionally pick which information system the issue related to; field is not required.

### Document links + preview (APM special-memo pattern)

Four fields are **URLs only** (managed outside Helpdesk; no file upload in this module):

- System Profile → `system_profile_url`
- User Manual - Users → `user_manual_users_url`
- User Manual - Managers → `user_manual_managers_url`
- User Manual - Technical → `user_manual_technical_url`

**Preview UX** (match APM `special-memo/show` attachment preview):

- Modal with body `#previewModalBody` style behaviour:
  - Image (`jpg`/`jpeg`/`png`/`gif`/`webp`): `<img>`
  - PDF: iframe of the URL (`#toolbar=1…`)
  - Office-ish (`doc`/`docx`/`xls`/`xlsx`/`ppt`/`pptx`) or unknown remote docs: Google Docs viewer `https://docs.google.com/viewer?url=…&embedded=true`
  - Else: message + “Open in new tab” link
- Infer type from URL path extension; if no extension, open in new tab / Google viewer fallback.
- Buttons on system detail: Preview / Open for each non-empty URL.
- Reuse a small shared Vue component (e.g. `DocumentLinkPreviewModal.vue`) so resolve/detail and Information Systems pages stay consistent.

## APIs

Under `/api/v1/tools/information-systems` (auth + `can_manage_information_systems`):

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/languages` | List language catalogue |
| POST | `/languages` | Add language name (manager) |
| GET | `/` | Paginated list + `modules_count`, `languages`, filters (q, status, division_id including null=All) |
| GET | `/summary` | Counts by system status, module status, missing focals, by division (All bucket for null) |
| GET | `/export` | Excel export of systems (+ module counts, language names, division name) |
| GET | `/{id}` | Detail with modules |
| POST | `/` | Create system (record status event) |
| PUT | `/{id}` | Update; if status changes, write event |
| DELETE | `/{id}` | Delete system (cascade modules; optional event) |
| POST | `/{id}/modules` | Add module |
| PUT | `/{id}/modules/{module}` | Update module |
| DELETE | `/{id}/modules/{module}` | Delete module |
| GET | `/reports/trends` | Status events aggregated by day/week for charts |

Staff search for focals: reuse existing staff directory search endpoints used by licenses / tickets.

### Import

- Artisan: `php artisan helpdesk:import-information-systems {path?}`  
  Default path: `helpdesk/Africa CDC Information Systems.xlsx` (repo-relative).
- Idempotent-ish: match by system `name`; update fields; do not wipe modules already added unless `--fresh`.
- On first create: status event `null → mapped_status`.
- Focal resolve via `StaffDirectoryLookupService` / name matcher.
- Division: map Excel text → `division_id` via reference divisions; All/blank/unmatched → null.
- Languages: split Excel “Programming Language/DB” on commas/`and`/slashes; normalize tokens; upsert catalogue + sync pivot.
- Profile/manual Excel cells: if they look like URLs, store in `*_url` fields; otherwise leave null (no free-text dump into URL columns).

## Frontend

### Nav

`toolsNav.ts`: add item

- path `/tools/information-systems`
- label `Information Systems`
- permission `can_manage_information_systems`

### Agents settings

`AgentsManagementPanel`: checkbox/toggle **Information systems** next to IT assets / Licenses.

### Page `InformationSystemsView.vue`

- Summary chips (by status) + table: Name, Status, Version, Division (All or directory name), Languages (chips), Focal, MIS Focal, **Modules** (count), Actions.
- Create/Edit modal: status, division select (**All** = null), multi-select languages, focal pickers, four URL fields with Preview / Open.
- Nested modules editor on edit (name, description, status).
- Document preview: shared `DocumentLinkPreviewModal.vue` using APM special-memo rules (image / PDF iframe / Google Docs viewer / fallback open).
- Import remains CLI for v1 (`helpdesk:import-information-systems`).

### Reports (`ReportsView.vue`)

New tab or section **Information systems** (visible when user has permission):

1. Tiles: total systems, by status, total modules, systems missing focal / MIS focal.
2. By division breakdown.
3. Trend chart: status events over selected date range (reuse date filters pattern).
4. Export Excel button.

## Authorization

- Route meta `requiresToolsPermission: 'can_manage_information_systems'`.
- Backend trait method `ensureInformationSystemsManager` (same pattern as licenses).
- `MeResource` exposes `can_manage_information_systems`.
- `HelpdeskProfile::canManageInformationSystems()` and `hasAnyToolsAccess()` include the new flag.
- Role 10 / helpdesk admin: set flag true on SSO when applying admin role (and backfill migration for existing admins).

## Testing

- Feature: CRUD system + modules; permission 403 without flag; admin with flag 200.
- Import: maps statuses, defaults version `1.0`, normalizes languages, maps division names (All/blank → null), resolves staff names when possible.
- Status event written on create and on status change.
- Reports summary endpoints return expected aggregates (All division bucket).
- Resolve: IT & MIS ticket can submit optional `linked_information_system_id`; other BUs without flag reject the field; ticket resource returns linked system.

## Rollout

1. Migrate tables + permission column + backfill admins.
2. Import Excel once on deploy / local: `php artisan helpdesk:import-information-systems`.
3. Build frontend; sync `client/` copies if still mirrored.
4. Document in ADMIN_GUIDE (permission + import command).

## Open points (resolved unless user revises)

- Excel has no nested modules → import creates **systems only**; modules added in UI afterward.
- Division is Staff/APM `division_id`; null displays as **All** (Excel All/blank/unmatched → null).
- Programming languages are a normalized catalogue + pivot; Excel free text is tokenized/normalized on import.
- Profile + three manuals are external **URL** fields with APM-style preview modal (no uploads here).
- Ticket link is to the **system** (not a nested module) on resolve; optional; IT & MIS enabled by default.
