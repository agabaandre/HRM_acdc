# Staff Portal Wave: Contracts, Staff UX, Audit, Passport OIDC, Full Performance Forms

**Date:** 2026-08-12  
**Status:** Approved  
**Wave:** Single delivery wave, internal merge order Approach 1

## Goals

1. Staff create + full contract management with one-current-contract enforcement.
2. Contract type **category** (Main / Other) and staff directory UX (filters, photo, counter, dynamic columns).
3. Audit logs CI3 parity (`auth/logs`).
4. Staff Portal as OAuth2/OIDC **provider** via Laravel Passport, while keeping legacy SSO JWT for existing CBP apps.
5. **Fully migrate Performance PPA / midterm / endterm forms to Vue + API** — no Livewire iframe bridge. CI3/Livewire forms will be taken offline later; the SPA must not depend on them.

## Non-goals

- Migrating APM / Helpdesk / Finance off SSO JWT in this wave (register them as Passport clients later).
- Leave module changes.
- Keeping or polishing the iframe bridge (it will be removed).

## Decisions (locked)

| Topic | Decision |
|-------|----------|
| Auth strategy | **C** — Passport for new apps + keep legacy SSO JWT |
| Contract uniqueness | **A** — at most one current contract (`status_id` ∈ 1 Active, 2 Due, 7 Under Renewal) |
| Previous contract on renew | If previous ≠ Expired (3) → set to **Renewed (6)**; if Expired → leave Expired |
| Contract type category | `main_staff` \| `other_staff`; all existing types → `main_staff` |
| Staff list default | Main staff only; filter Main / Other / All |
| Staff table | `#` counter, photo, column picker (localStorage), export honors visible columns |
| Delivery | Everything in one wave; build order Staff+Contracts → Audit → Passport → Performance forms |
| Performance forms | Full SPA migration; delete iframe bridge |

---

## 1. Auth — Passport OIDC + legacy SSO

### Current

- Microsoft Entra is the human IdP (custom OAuth client).
- SPA uses Sanctum personal access tokens.
- Cross-app SSO uses HS256 JWT (`JWT_SECRET`) + `/sso/callback` — not standards OAuth.

### Target

```
Human → Microsoft Entra → Staff Portal session
                              ├─ SPA: Sanctum (unchanged)
                              ├─ Legacy CBP apps: SSO JWT (unchanged)
                              └─ New apps: Passport OAuth2/OIDC (authorization code + PKCE)
```

### Work

- Install and configure `laravel/passport` on `staff-portal/backend`.
- Expose authorize, token, userinfo; OpenID discovery/JWKS as supported by Passport.
- OAuth clients admin UI (perm 17): name, redirect URIs, public vs confidential, secret reveal once.
- Document migration path for APM/Helpdesk from SSO JWT → Passport.
- Do **not** remove Sanctum or SSO JWT in this wave.

### Success criteria

- A registered confidential/public client can complete auth code + PKCE against the portal and call a protected resource (e.g. `/api/v1/me` or Passport userinfo) with the access token.
- Existing SPA Microsoft login and SSO JWT module launch still work.

---

## 2. Audit logs — CI3 parity

Mirror CI3 `application/modules/auth/views/users/user_logs.php` / `Auth::logs` (not the KPI dashboard).

### API (`GET /api/v1/auth/audit-logs`)

Filters: `search`, `name`, `email`, `http_method`, `event_type`, `date_from`, `date_to`, `page`, `per_page` (default 50).

Response includes summary meta: matching total, page count, extended-audit flag if applicable.

### UI

- Summary cards + short integrity/retention note.
- Filters + Reset.
- Table: `#`, ID, User (name + email), When, Method, Event, URI, Target, Actions.
- Details modal: IP, User-Agent, action, old/new JSON, reverted banner.
- Revert action → `POST /api/v1/auth/audit-logs/{id}/revert` using existing whitelist (`user` table today).

### Success criteria

Parity with CI3 `auth/logs` filters, columns, details, and revert for whitelisted targets.

---

## 3. Staff create + contract management

### Create staff

- Vue route `/staff/new` + `POST /api/v1/staff`.
- Fields/validation aligned with CI3 `staff/new`: biodata + first contract; work email unique; age ≥ 18; end_date > start_date; first contract Active.
- On success → staff show / contracts section.

### Contracts

Wire and extend `StaffContractService`:

| Endpoint | Purpose |
|----------|---------|
| `GET /api/v1/staff/{id}/contracts` | History (may already be on show) |
| `POST /api/v1/staff/{id}/contracts` | Add / renew |
| `PUT /api/v1/staff/{id}/contracts/{contractId}` | Edit |

### Enforcement (server-side, authoritative)

Before insert/update that results in a **current** status (1, 2, or 7):

1. Reject if another current contract already exists for that `staff_id` (excluding self on update) **unless** this is a renew flow that will demote the previous row in the same transaction.
2. On add/renew of a new current contract:
   - Identify prior “latest” contract (by `staff_contract_id`).
   - If prior `status_id` ≠ 3 (Expired) → set prior to **6 (Renewed)**.
   - If prior is Expired → leave as Expired.
   - Also demote any other stray rows with status ∈ {1, 2, 7} (legacy duplicates) to Renewed (or keep Expired if already 3).
3. Edit that would create a second concurrent current contract → 422 with clear message.

UI: staff show page — contract history table, Add/Renew form, Edit form (status + dates + lookups). Permissions: existing manage-staff / manage-contracts flags (71 / related).

### Success criteria

- Cannot leave two Active/Due/Under Renewal contracts for one staff after any API call.
- Renew of non-expired prior → Renewed; expired prior stays Expired.

---

## 4. Contract type category + staff directory UX

### Schema

- Add `contract_types.category` string/enum: `main_staff` | `other_staff`, default `main_staff`.
- Migration backfill: `UPDATE contract_types SET category = 'main_staff'`.
- Settings lookup `contract_types`: columns Type + Category (select).

### Directory (`/staff` + export)

- Filter `category`: `main_staff` (default) | `other_staff` | `all` (join current contract → contract_types).
- Keep existing status presets (active, due, expired, former, renewal, all).
- Table columns:
  - Always: `#` (row counter across pages: `(page-1)*perPage + index + 1`).
  - Photo thumbnail from `staff.photo` (fallback avatar).
  - Dynamic columns via column picker; prefs in `localStorage` (key e.g. `staff-directory-columns`).
  - Default visible: photo, name, work email, job, division, duty station, contract type, status, end date (adjust to match current useful set).
- CSV export uses selected columns + same filters.

### Success criteria

- Fresh visit shows Main staff only.
- Toggling Other / All changes the set; column picker shows/hides columns without reload of prefs incorrectly.

---

## 5. Performance forms — full SPA (no iframe)

### Remove

- `PerformanceFormBridgePage.vue` and iframe usage.
- Hub links that target Livewire web URLs for editing (replace with SPA form routes).
- After SPA parity: redirect or retire Livewire `PerformanceForm` web routes (no dependency from SPA).

### Keep / reuse backend services

- `PpaFormService` (save draft/submit for ppa/midterm/endterm)
- `PerformanceApprovalService` (approve / return / consent / trail)
- `PerformanceWorkflowService`, `CompetencyService`, `PpaContractService`, windows/settings helpers

### New APIs (thin controllers)

| Method | Route | Role |
|--------|-------|------|
| GET | `/api/v1/performance/entries/{id}` | Entry + phase payload, contract, catalogs, trail, canAct/readonly |
| POST | `/api/v1/performance/entries` | Create / bootstrap PPA |
| PUT | `/api/v1/performance/entries/{id}` | Save draft (`action=draft`) |
| POST | `/api/v1/performance/entries/{id}/submit` | Submit |
| POST | `/api/v1/performance/entries/{id}/approve` | Approve |
| POST | `/api/v1/performance/entries/{id}/return` | Return (comments required) |
| POST | `/api/v1/performance/entries/{id}/consent` | Employee consent (endterm) |

### Vue

- One phase-aware form page replacing bridge: create + `/performance/form/:phase/:entryId/:staffId`.
- Sections: PPA A–C; Midterm/Endterm A–F; workflow card + trail.
- Client validation parity (e.g. objective weights = 100) + server validation in services.
- PDF print remains existing API.

### Success criteria

Create → draft → submit → approve/return works for PPA; midterm/endterm after approved PPA; endterm consent when configured — **entirely in SPA with Sanctum**, no Livewire session required.

---

## Build order (within the wave)

1. Contract type category migration + settings field  
2. Staff directory filters / photo / counter / column picker / export  
3. Staff create + contract CRUD + uniqueness rules  
4. Audit logs API + Vue parity  
5. Passport install, clients admin, docs for legacy SSO coexistence  
6. Performance form APIs + Vue sections; delete iframe bridge; retire Livewire form entry points from SPA  

## Testing notes

- Feature tests for contract uniqueness / renew status transitions.
- Feature tests for audit filters + revert whitelist.
- Feature tests for Passport token issuance (smoke).
- Feature tests for performance draft/submit/approve happy path.
- Manual smoke on `/staff`, `/staff/new`, staff show contracts, `/auth/audit-logs`, `/performance`, Microsoft login + SSO JWT launch.

## Open follow-ups (after this wave)

- Point APM/Helpdesk at Passport clients and deprecate SSO JWT.
- Take CI3 Staff Tracker offline once SPA parity is signed off.
- Optional DB partial unique index for current contracts if MySQL version/generated columns allow (app rules remain source of truth).
