# Performance Approver Nav, Pending Badges & Approval History — Design

**Date:** 2026-10-07  
**Status:** Approved  
**Scope:** Staff Portal Performance module — primary nav dropdown, pending-count badges, approver approval history.  
**Out of scope:** Staff data-quality page; Leave badges; changing who can approve; workflow rule changes.

## Goals

1. Approvers can open Performance as a **dropdown** with: My forms, Pending reviews, Approval history, Analytics.
2. Approvers see **PPA / midterm / endterm** items pending their action (existing Pending tab).
3. Approvers see **approval history**: forms they **approved or returned** only.
4. A **badge** shows the pending count on:
   - the **Performance** primary-nav parent, and
   - the **Pending reviews** dropdown item and hub tab.

## Current state

- Hub tabs: `dashboard` (My forms), `my` (employee’s own form history), `pending`, `analytics`.
- `GET /api/v1/performance/hub` already returns `pending` + `pending_count` for the logged-in staff via `PerformanceApprovalService::pendingActionsFor()`.
- Hub tab already badges Pending with `pending_count`.
- Primary nav has no children or badge; Performance is a single link to `/performance`.
- No API lists trail actions where the actor is the current staff.

## Approach (A)

Extend the existing Performance hub and primary nav. Prefer `?tab=` deep links over new page components. Add a small pending-count fetch for the nav shell and an approval-history list for the new tab.

---

## 1. Primary navigation

### Behaviour

- **Performance** becomes a dropdown toggle (same interaction pattern as **More** in `PortalPrimaryNav.vue`).
- Menu items (all require Performance permission `74` / module `performance`):

  | Label | Target |
  |-------|--------|
  | My forms | `/performance?tab=dashboard` |
  | Pending reviews | `/performance?tab=pending` |
  | Approval history | `/performance?tab=approval-history` |
  | Analytics | `/performance?tab=analytics` |

- Parent **Performance** shows a numeric badge when `pending_count > 0`.
- **Pending reviews** menu row shows the same count badge.
- Active state: parent and matching child highlight when `route.path` starts with `/performance` and the query `tab` matches (default tab = `dashboard` when absent).
- Mobile: dropdown expands inside the existing collapsible nav links panel.

### Data for badges

- On authenticated shell load (and after Performance approve/return/submit success when already on Performance), fetch pending count.
- Prefer lightweight `GET /api/v1/performance/pending-count` → `{ data: { pending_count: number } }` so the nav does not pull the full hub.
- Fallback: if that endpoint is unavailable, nav may omit the badge until the hub has been loaded once in-session (not preferred).

---

## 2. Hub tabs

Pill subnav on `PerformancePage.vue`:

| Tab key | Label | Notes |
|---------|-------|--------|
| `dashboard` | My forms | Existing |
| `pending` | Pending reviews | Existing; keep badge = `pending_count` |
| `approval-history` | Approval history | **New** — actions I took as approver |
| `my` | My submissions | Existing employee history (renamed from “History” to avoid confusion) |
| `analytics` | Analytics | Existing |

Nav dropdown does **not** include “My submissions”; that remains hub-only for staff reviewing their own forms.

Deep links from the dropdown set `?tab=` and the page selects the matching pill.

---

## 3. Approval history (backend)

### Endpoint

`GET /api/v1/performance/approval-history`

Query: `page`, `per_page` (default 25, max 100), optional `period`, optional `phase` (`ppa` \| `midterm` \| `endterm`).

Auth: same as hub (permission 74 / Performance access for linked staff).

### Semantics

Union of trail rows from:

- `ppa_approval_trail`
- `ppa_approval_trail_midterm`
- `ppa_approval_trail_end_term`

where `actor_staff_id` (or equivalent) = logged-in staff id and action is **Approved** or **Returned** (case-insensitive). Exclude superseded noise only if the existing trail helpers already do so; do not invent new workflow rules.

### Response item shape

```json
{
  "entry_id": "…",
  "staff_id": 123,
  "staff_name": "Doe, Jane",
  "phase": "midterm",
  "phase_label": "Midterm",
  "performance_period": "2026",
  "action": "Approved",
  "comments": "…",
  "acted_at": "2026-10-01 14:22:00",
  "form_url": "/performance/form/midterm/{entryId}/{staffId}"
}
```

Paginated: `{ data: [...], meta: { current_page, per_page, total, last_page } }`.

Hub may optionally embed the first page when `tab=approval-history` to avoid a second round-trip; dedicated endpoint remains the source of truth for paging/filters.

---

## 4. Pending reviews

No change to eligibility: continue using `PerformanceApprovalService::pendingActionsFor()` (PPA, midterm, endterm, and endterm employee-consent when applicable).

Pending-count endpoint must use the same counting logic as hub `pending_count`.

---

## 5. Frontend implementation notes

- Extend `PortalNavItem` with optional `children?: PortalNavItem[]` and optional `badgeKey?: 'performancePending'` (or pass badge from a small Pinia/composable store).
- `PortalPrimaryNav.vue`: render Performance like More when `children` present; show badge chip on parent and child.
- `usePerformancePendingCount` (or auth/shell store field): load count after login / module enable; refresh after approve/return/consent on form page.
- `PerformancePage.vue`: add `approval-history` tab table (Staff, Phase, Period, Action, Date, Open); wire rename History → My submissions.
- `performanceApi.ts`: `fetchPendingCount()`, `fetchApprovalHistory(params)`.

---

## 6. Tests

- Feature: pending-count matches hub pending length for a fixture supervisor.
- Feature: approval-history returns only Approved/Returned rows for the actor; excludes other actors; paginates.
- Feature/unit as needed for nav active tab query parsing (frontend smoke optional).

---

## 7. Acceptance

- [ ] Performance in primary nav opens a dropdown with the four items.
- [ ] Pending count badge appears on Performance and on Pending reviews when count > 0; hidden when 0.
- [ ] Pending reviews lists PPA/midterm/endterm awaiting the logged-in approver; Review opens the form.
- [ ] Approval history lists only that user’s Approved/Returned actions with working form links.
- [ ] My submissions still shows the employee’s own forms.
- [ ] Analytics and My forms behaviour unchanged aside from nav entry points.
