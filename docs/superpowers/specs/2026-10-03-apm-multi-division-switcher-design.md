# APM multi-division switcher

**Date:** 2026-10-03  
**Status:** Approved (pending implementation)  
**Module focus:** APM (`modules/apm`), Staff Portal share/staff sync  
**Out of scope for behaviour change:** Finance / Risk / Helpdesk product UX (sync must remain non-breaking)

## Problem

Staff may act for more than one division:

- Contract **primary** division plus Staff Portal **Other associated divisions**
- Role appointments on other divisions (focal person, head / head OIC, director / director OIC, admin assistant)

Today APM largely uses a single `session('user.division_id')` (primary). Focal persons and multi-division staff cannot easily create matrices/memos or view lists for their other divisions. Finance officer approval routing must stay unchanged.

## Goals

1. Let eligible staff switch **active division** on APM home (`/apm/home`).
2. Active division drives session-scoped create/view for matrices and memos (and division-filtered lists).
3. Persist associated divisions in APM as JSON (`staff.associated_divisions`).
4. Keep Staff Portal → share API → module staff sync working; do not break finance/risk/helpdesk sync.
5. Finance-officer **approval** behaviour unchanged; finance officers **may** use the switcher if they have multiple divisions as staff (primary / associations / non-finance roles).

## Non-goals

- Changing finance approval workflow or finance_officer / finance OIC assignment rules.
- Persisting active division across logout (reset to primary each session).
- Building a switcher in Finance / Risk / Helpdesk SPAs in this work.

## Decisions (approved)

| Topic | Choice |
|--------|--------|
| Switchable set | **Union:** primary + `associated_divisions` + role-based divisions (focal, head, head OIC active, director, director OIC active, admin assistant). Deduped. |
| Finance officer slots | Do **not** add divisions solely because the user is finance officer / finance OIC. |
| FO with multi-division staff | **May** switch if the union above has ≥ 2 divisions. |
| Active scope | Entire APM session: matrix/memo create, view, division-filtered lists. |
| Persistence | Session only; reset to primary on login / new session. |
| Approach | Session overlay (`active_division_id`) preferred over mutating primary permanently or per-URL params. |

## Current building blocks

- Staff Portal: `staff_contracts.other_associated_divisions` (JSON); UI “Other associated divisions”.
- Share API (`ShareReferenceDataService`): exposes `associated_divisions` array on staff rows.
- APM: column `staff.associated_divisions` (JSON migration already present); `SyncStaffCommand` already normalizes and stores it.
- APM `Division` model already has helpers for director / head OIC / admin assistant queries.

## Design

### 1. Data model (APM)

- `staff.division_id` — primary (contract); unchanged meaning.
- `staff.associated_divisions` — JSON array of secondary division IDs from portal; unchanged storage.
- No new DB table required for the switcher.
- Session keys:
  - `active_division_id` (int)
  - `active_division_name` (string, optional cache)

On SSO accept / login: clear `active_division_*` so context starts at primary.

### 2. Resolving switchable divisions

New helper (e.g. `App\Support\StaffDivisionContext` or helpers in `CustomHelper.php`):

```
switchableDivisionIds(staffId): int[]
  = unique non-zero of:
      - staff.division_id (primary)
      - staff.associated_divisions[]
      - divisions.focal_person = staffId
      - divisions.division_head = staffId
      - active head_oic_id = staffId
      - divisions.director_id = staffId
      - active director_oic_id = staffId
      - divisions.admin_assistant = staffId
  # explicitly NOT finance_officer / finance_officer_oic_id
```

Return metadata for UI: `{ id, name, is_primary, sources[] }` where `sources` may be `primary|associated|focal|head|head_oic|director|director_oic|admin_assistant` (for labels/debug, optional in UI).

### 3. Active division resolution

- `resolved_session_division_id(): ?int`
  - If `active_division_id` set and ∈ switchable list → use it
  - Else → primary `user_session` / staff `division_id`
- Update `user_session('division_id')` and `user_session('division_name')` to prefer active context when reading those keys (same pattern as staff_id ↔ auth_staff_id aliases), **or** document that all create/list paths must call `resolved_session_division_id()` — prefer centralizing in `user_session` for `division_id` / `division_name` so existing controllers pick it up.

Validation: never allow active id outside switchable list; invalid → clear and fall back to primary.

### 4. Home UI switcher

- Show on `/apm/home` (and optionally header) only when `count(switchable) >= 2`.
- Control: select/dropdown of division names; mark primary.
- POST route e.g. `POST /division-context` (CSRF) with `division_id`.
- On success: set session `active_division_*`, flash confirmation, redirect back.
- Visible indicator of current active division near the switcher.

### 5. Behaviour after switch

While session active division is D:

- Matrix create uses division D (focal path already sets focal_person_id to current user when non-admin).
- Matrix / memo lists that filter by `user_session('division_id')` use D.
- Memo create (`staff_id` still creator; `division_id` = D).
- Finance approval assignment still reads division’s `finance_officer` fields — unchanged.

### 6. API / sync (non-breaking)

| Layer | Change |
|--------|--------|
| Staff Portal contract API | No behaviour change; keep validating `other_associated_divisions[]`. |
| Share `/share/...` staff | Keep decoding JSON → `associated_divisions` array. Add regression test if missing. |
| APM `SyncStaffCommand` | Keep normalize; ensure empty/null → `[]`. |
| Finance / Risk / Helpdesk staff sync | Tolerate unknown/extra `associated_divisions` key; do not require the column. Smoke/regression: sync still succeeds with field present. |
| APM mobile/API session payload | Include `associated_divisions` and optionally `active_division_id` when session overlay exists (`ApmAuthController` already partially exposes associated). |

### 7. Testing

1. **Unit:** `switchableDivisionIds` — primary only; + associated; + focal on another division; finance_officer-only does **not** add; FO who is also focal **does** include that division; OIC date window respected.
2. **Unit:** `user_session('division_id')` / resolved helper respects `active_division_id` and rejects invalid.
3. **Feature (APM):** POST division-context switches; matrix create uses active division; after new login active resets to primary.
4. **Feature (Portal/Share):** staff with `other_associated_divisions` appears as `associated_divisions` in share payload.
5. **Regression:** APM sync with associated JSON; finance/risk/helpdesk sync path still OK when payload includes `associated_divisions` (ignore or store if they already have a field — must not 500).

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Controllers reading raw `session('user')['division_id']` bypass overlay | Prefer `user_session('division_id')` alias; grep and fix hot paths that read raw session. |
| Stale active id after role/association removed | Validate against switchable list on each resolve. |
| Sync breakage in other modules | Additive JSON only; tests + ignore unknown keys. |

## Success criteria

- Multi-division staff (associated and/or role-based) see a home switcher and can create/view matrices and memos for each switchable division in one session.
- Primary remains contract division; associated stored as JSON in APM.
- Finance approval behaviour unchanged; FO can still switch when they have ≥ 2 switchable divisions from staff assignment/roles (non-finance).
- Staff sync across portal → APM / finance / risk / helpdesk does not break.
