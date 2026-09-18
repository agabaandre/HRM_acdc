# Staff Portal SPA + API Migration Design

**Date:** 2026-08-12  
**Status:** Approved  
**Scope:** Full application — Vue SPA UI; Laravel modules API-only

## Goal

Move all Staff Portal user interfaces from Livewire/Blade into the Vue SPA. Backend modules keep models, migrations, and services; expose Sanctum JSON APIs (Helpdesk-style). Delete Livewire/Blade UI after each module reaches parity.

## Architecture

- **Frontend:** `staff-portal/frontend` (Vue 3 + Vuetify + existing CBP chrome).
- **Backend:** nwidart modules under `staff-portal/backend/Modules/*`.
- **API:** `auth:sanctum`, prefix `/api/v1/...`, FormRequests + Resources under `Http/{Requests,Resources,Controllers}/Api/V1`.
- **Auth:** Existing SPA login, Microsoft web callback, spa-bridge. Domain code must resolve staff/role from `PortalUser` (Sanctum), not only `session('user')`.
- **Cutover rule:** Vue parity verified → remove that module’s Livewire + blades + Livewire web routes; optional redirect from old URLs to SPA.

## Out of scope (until needed)

Empty scaffolds with no real UI: Contracts, Jobs, Workflows module shells.

## Shared platform (Wave 0)

1. `GET /api/v1/lookups/{type}` — Sanctum wrapper around existing list maps (keep CI3 `/lists/{type}` for legacy).
2. Vue molecules: filter bar, simple data table, status badge (reusable across modules).
3. SPA router entries for migrated pages; PlaceholderPage only for not-yet-migrated modules.
4. Document API conventions for later waves.

## Module waves

| Wave | Deliverable | Delete Livewire when done |
|------|-------------|---------------------------|
| **0** | Lookups API + shared Vue primitives | — |
| **1** | Leave (balances, requests, approvals, apply) + Leave settings (policy + types) | Leave Livewire; Settings `LeaveSettings` |
| **2** | Permissions/RBAC + Settings hub + Performance settings + lookup managers | **Done** — Livewire removed |
| **3** | Staff (directory, show, contracts, birthdays, data quality) | Staff Livewire |
| **4** | Dashboard | Dashboard Livewire |
| **5** | Tasks + Workplan | Tasks/Workplan Livewire |
| **6** | Attendance + AdManager + Auth admin (users, audit) + Reports | Those Livewire UIs |
| **7** | Performance (PPA / midterm / endterm) | Performance Livewire |

## Wave 1 — Leave + Leave settings (detail)

### API (`Modules/Leave`)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/leave/balances` | Current staff balances |
| GET | `/api/v1/leave/requests` | Filters: status, dates, `scope=mine\|all` (all requires perm 77) |
| GET | `/api/v1/leave/approvals` | Pending queue for approver/HR |
| POST | `/api/v1/leave/requests` | Multipart apply; uses `LeaveRequestService` |
| POST | `/api/v1/leave/requests/{id}/decide` | Body: `role`, `action` (`approve`\|`reject`), optional message |
| POST | `/api/v1/leave/working-days` | `{ start_date, end_date }` → days |
| GET | `/api/v1/leave/types` | Active types for apply form |
| GET/PUT | `/api/v1/leave/settings/policy` | HR only (`role_id === 20`) |
| GET/POST | `/api/v1/leave/settings/types` | HR only |
| PUT | `/api/v1/leave/settings/types/{leaveId}` | HR only |

### Vue routes

- `/leave` — tabs: balances, requests, approvals
- `/leave/apply` — application form
- `/settings/leave` — tabs: policy, types (HR)

### Access

- Adapt `LeaveAccess` for Sanctum `PortalUser` (`auth_staff_id`, `role`).
- HR: `role_id === 20` (same as Livewire).
- All-staff requests view: `portal_can(77)` / permission in me payload.

### Cleanup

- Remove `LeaveDashboard`, `LeaveApplication`, leave livewire blades.
- Remove `Settings\Livewire\LeaveSettings` + blade + `settings/leave` web route.
- Replace stub `LeaveController` apiResource with real V1 routes.
- Redirect `backend/leave` and `backend/settings/leave` to SPA paths when hit via web.

## Error handling

- Validation → 422 JSON.
- Business rules (`InvalidArgumentException`) → 422 with `message`.
- Unauthorized / missing staff link → 401/403.
- Frontend: `apiErrorMessage` + inline alerts (Helpdesk pattern).

## Testing

- Feature tests for Leave API (balances, submit, decide, settings HR gate).
- Manual: SPA leave tabs, apply with/without medical cert, approvals, settings save.
- Rebuild `frontend/dist-build` after Vue changes.

## Success criteria (Wave 0+1)

- No Livewire Leave or Leave-settings UI remains.
- All former Leave Livewire actions work via SPA + API.
- Unmigrated modules still usable via Livewire (PlaceholderPage links).
