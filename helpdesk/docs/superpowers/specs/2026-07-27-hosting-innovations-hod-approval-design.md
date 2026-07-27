# Hosting, Innovations & HoD approval gate

**Date:** 2026-07-27  
**Status:** Implemented (2026-07-27)  
**Approach:** Staff Share divisions for HoD + Agents process flags (Approach 1 / light hybrid)  
**Related:** Software requests module; create-ticket tabs; Service Desk Modules nav

## Problem

Staff need first-class **Hosting** and **Innovations** request flows in Service Desk. Hosting (and Software requests) must be **approved by the requester’s Head of Division** before any admin or processing agent may act. Innovations should be processable without HoD approval. Create and nav surfaces still say “Helpdesk Modules” and only expose Service Desk + Information System Request on the new-request page.

## Goals

1. **Hosting requests** — available to all authenticated staff by default; categories **Cloud** vs **On Premises**; module under nav; tab on `/tickets/new`.
2. **Innovations requests** — same submit/process shape as Hosting, **without** HoD gate; module under nav; tab on `/tickets/new`.
3. **HoD approval gate** — for Hosting and Software requests: HoD approves first; agents/admins with process rights may process **only after** HoD approval (API-enforced).
4. **HoD identity** — from existing Staff Share divisions API (same source APM `divisions:sync` uses): `division_head`, with **active head OIC** treated as HoD (APM `effective_division_head_staff_id` behaviour).
5. **Process permissions** — Agents settings flags for process hosting / process innovations; **helpdesk admins can process by default**.
6. **Nav** — rename **Helpdesk Modules → Service Desk Modules**; add Hosting + Innovations (visible to all authenticated staff for submit/list; process actions gated).
7. **Create page** — tabs for Service Desk, Information System Request, Hosting, Innovations (gateway or inline form consistent with current Software tab pattern).

## Non-goals (this iteration)

- Local helpdesk `divisions` table / `divisions:sync` command (use live Staff Share fetch + existing reference cache).
- Agents override of who the HoD is.
- Full project/portfolio tooling beyond request CRUD + approve/process lifecycle.
- Email/notification polish beyond mirroring Software request notify patterns where cheap.
- Changing Software request review-board field model beyond inserting the HoD gate before process/manage actions.

## Decisions

| Topic | Choice |
|-------|--------|
| HoD source | Staff Share `/share/divisions` (`division_head` + active `head_oic_*`) via existing helpdesk Staff portal client |
| Who may process after HoD | Agents with process flag + helpdesk admins |
| Process before HoD | **Forbidden** (UI disabled + API 403) for Hosting + Software |
| Innovations HoD | **Not required** |
| Hosting categories | `cloud` (Azure / CDC-approved online), `on_premises` (Africa CDC servers) |
| Submit access | All authenticated staff (Hosting + Innovations) |
| Software existing approve/manage | Remains **process/review** after HoD; HoD is a separate first step |
| Create-tab UX | Same pattern as Information System Request: tab + dedicated tools route form |
| Nav label | Service Desk Modules |

## Architecture

```text
Staff Share divisions API
        │
        ▼
StaffPortalReferenceClient + StaffShareNormalizer (retain head fields)
        │
        ▼
DivisionHeadResolver (effective HoD staff_id for division_id)
        │
        ├── HostingRequestController (approve_hod / process)
        ├── SoftwareRequestController (approve_hod gate before manage)
        └── Profile / me flags (is_division_head_for_request, can_process_*)
```

### HoD resolution

1. Resolve requester (or request-for) staff → `division_id` from directory / SSO profile.
2. Load division from Staff Share (or cached reference bundle).
3. Effective HoD staff id:
   - If `head_oic_id` set and today within `[head_oic_start_date, head_oic_end_date]` (null bounds open) → OIC.
   - Else → `division_head`.
4. Current user may HoD-approve iff their staff id equals effective HoD (and request is awaiting HoD).
5. Helpdesk admin is **not** a substitute HoD for the approval step (admins process only after HoD). Optional later escape hatch out of scope.

Extend `StaffShareNormalizer::division()` to retain at least:

- `division_head` (staff id)
- `head_oic_id`, `head_oic_start_date`, `head_oic_end_date`

## Status machines

### Hosting

| Status | Meaning |
|--------|---------|
| `draft` | Optional save |
| `pending_hod` | Submitted; waiting HoD |
| `hod_approved` | HoD approved; waiting process |
| `hod_rejected` | HoD rejected (terminal or returnable) |
| `in_progress` | Processor accepted / working |
| `completed` | Done |
| `cancelled` | Requester/admin cancelled before completion |

**Transitions**

- Submit → `pending_hod`
- HoD approve → `hod_approved`
- HoD reject → `hod_rejected`
- Process start (process permission) → `in_progress` **only from** `hod_approved`
- Complete → `completed` from `in_progress`
- Cancel from draft / pending_hod / hod_approved (policy: requester own + admin)

### Innovations

| Status | Meaning |
|--------|---------|
| `draft` | Optional save |
| `submitted` | Waiting process |
| `in_progress` | Processor working |
| `completed` | Done |
| `rejected` | Processor rejected |
| `cancelled` | Cancelled |

No `pending_hod` / `hod_*`. Process allowed from `submitted`.

### Software requests (delta)

Insert HoD gate **after submit, before any process/manage**:

| New / adjusted | Meaning |
|----------------|---------|
| On submit | status → `pending_hod` (instead of immediately processable `submitted`) |
| HoD approve | → `hod_approved` (then existing review-board / manage / team actions allowed) |
| HoD reject | → `hod_rejected` |

**Hard rule:** `approve` (review board), `syncTeam`, and other manage/process endpoints require status ∈ {`hod_approved`, existing post-approval statuses such as `approved`, `team_formed`, …}. Reject with 403 if still `pending_hod` / `draft` / `hod_rejected`.

Existing Agents flags:

- `can_submit_software_requests` — submit (unchanged intent; still broadly available where `publicToAuth`)
- `can_approve_software_requests` / review board / `can_manage_software_requests` — **process after HoD**, not a substitute for HoD

## Data model

### `helpdesk_hosting_requests`

| Column | Type | Notes |
|--------|------|--------|
| id | bigint PK | |
| request_number | string unique | e.g. `HR-YYYY-#####` |
| status | string(32) | status machine above |
| category | string(32) | `cloud` \| `on_premises` |
| title | string | |
| description | text nullable | rich text / HTML as other tools |
| cloud_provider | string nullable | required when category=cloud (e.g. Azure / other CDC-approved) |
| environment_notes | text nullable | capacity, URL, etc. |
| requester_user_id | FK users | |
| requester_staff_id | unsigned int nullable | |
| requester_name | string | |
| requester_division_id | unsigned int nullable | snapshot at submit |
| requester_division_name | string nullable | snapshot |
| on_behalf_of_staff_id | unsigned int nullable | optional |
| hod_staff_id | unsigned int nullable | resolved at submit |
| hod_name | string nullable | |
| hod_decided_at | timestamp nullable | |
| hod_decided_by_user_id | FK nullable | |
| hod_decision_notes | text nullable | |
| processed_by_user_id | FK nullable | |
| processed_at | timestamp nullable | |
| process_notes | text nullable | |
| created_by_user_id | FK nullable | |
| timestamps | | |

### `helpdesk_innovation_requests`

Same shape as hosting **without** HoD columns / category hosting fields. Fields: request_number, status, title, description, requester_*, on_behalf_of_*, processed_*, created_by, timestamps. Optional `innovation_type` string nullable for future classification.

### Profile / Agents flags

On `helpdesk_profiles` (or existing profile JSON columns pattern):

| Flag | Default | Purpose |
|------|---------|---------|
| `can_process_hosting_requests` | false; **true for helpdesk admins** | Process after HoD |
| `can_process_innovation_requests` | false; **true for helpdesk admins** | Process without HoD |

No Agents flag for “is HoD” — derived from divisions API.

Expose on `/api/v1/auth/me` (or profile payload):

- `can_process_hosting_requests`, `can_process_innovation_requests`
- `can_approve_as_hod` helper optional: computed per request is preferred over global flag

## API (sketch)

Prefix: `/api/v1/tools/hosting-requests`, `/api/v1/tools/innovation-requests`

| Method | Path | Authz |
|--------|------|--------|
| GET | `/` | Authenticated; list own + all if process/admin; HoD sees pending for their divisions |
| GET | `/{id}` | Owner, HoD for that request, process/admin |
| POST | `/` | Authenticated (submit/draft) |
| PATCH | `/{id}` | Owner while draft / returned |
| POST | `/{id}/hod-approve` | Effective HoD only; Hosting (+ Software equivalent) |
| POST | `/{id}/hod-reject` | Effective HoD only |
| POST | `/{id}/process` | Process flag or admin; **requires** `hod_approved` (Hosting) or `submitted` (Innovations) |
| POST | `/{id}/complete` | Process flag or admin; from `in_progress` |

Software: add `POST /api/v1/tools/software-requests/{id}/hod-approve` and `hod-reject`; gate existing manage endpoints.

## Frontend

### Nav (`CbpPrimaryNav`, `toolsNav.ts`, guides copy)

- Label: **Service Desk Modules**
- Items: existing + **Hosting** (`/tools/hosting-requests`, `publicToAuth`) + **Innovations** (`/tools/innovation-requests`, `publicToAuth`)
- Process actions in UI only when flags + status allow

### Create ticket (`TicketCreateView.vue`)

Tabs:

1. Service Desk (existing ticket form)
2. Information System Request (gateway → software-requests)
3. Hosting (gateway → `/tools/hosting-requests?tab=new`)
4. Innovations (gateway → `/tools/innovation-requests?tab=new`)

### Module views

Clone Software requests list/detail/new tabs pattern:

- Status chips including **Pending HoD** / **HoD approved**
- HoD actions visible only when current user is effective HoD for that row
- Process / Complete disabled until gate passes (show short reason)

### Agents settings

New columns/toggles: process Hosting, process Innovations (alongside existing SW / IT Assets flags).

## Testing

- Unit: `DivisionHeadResolver` — head vs active OIC vs expired OIC
- Feature: Hosting submit → process before HoD → 403; HoD approve → process OK; non-HoD cannot approve
- Feature: Innovations process without HoD OK
- Feature: Software manage/approve before HoD → 403; after HoD OK
- Feature: admin cannot skip HoD on Hosting/Software process
- Frontend smoke: tabs + nav rename (manual / existing Vue patterns)

## Rollout

1. Migrations + resolver + normalizer extension  
2. Hosting + Innovations APIs + views  
3. Software HoD gate + Agents flags UI  
4. Create tabs + nav rename  
5. Feature tests  

Production deploy remains separate (`setup-production.sh` on server).

## Open points (resolved defaults)

| Item | Default if unstated |
|------|---------------------|
| Active head OIC as HoD | **Yes** (APM-aligned) |
| Admin as HoD substitute | **No** |
| Software submit status | **`pending_hod`** until HoD acts |
