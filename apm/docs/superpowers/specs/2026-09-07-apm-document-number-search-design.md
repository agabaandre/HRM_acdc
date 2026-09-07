# APM general document-number search (reusable)

**Date:** 2026-09-07  
**Status:** Approved for implementation planning  
**Approach:** Shared Vue widget + new session-auth search API (Approach 1)  
**Surfaces:** `/apm/home`, `/apm/approver-dashboard` (reusable elsewhere)

## Problem

APM home is a module launcher; users have no fast way to jump to a document by number across types. Document numbers can repeat across years, so year must be part of the lookup (same idea as signature-verify’s “Year of creation”). Existing JWT APIs are close but incomplete for this UI (type+status required, or status-split memo-list; JWT-only; visibility not aligned with web list pages).

## Goals

1. Reusable **document number search** component for home and approver-dashboard (and future pages).
2. Search **all primary document types** by partial document number.
3. **Year required**, default **current calendar year** (`date('Y')` / `new Date().getFullYear()`), with a select of years available in the system.
4. **Realtime** search: debounced, **no Search button**; fire when query length ≥ **3**.
5. Result click opens the document’s normal **show** page.
6. Results limited to documents the user can already open (same visibility idea as module lists).

## Non-goals (this iteration)

- Title / requester / free-text search (document number only).
- “All years” option (year always set to keep repeats unambiguous).
- Reusing JWT `memo-list` or `documents/{type}/{status}` from Blade pages.
- Changing signature-verify lookup UX.
- Other Memo in v1 search set (unless show-route mapping is already consistent; default exclude).
- Mobile JWT endpoint in v1 (service may be reused later).

## Decisions (from brainstorming)

| Topic | Choice |
|-------|--------|
| Result action | A — navigate to show/detail page |
| Match fields | Document number + year (numbers can repeat) |
| Visibility | B — only documents the user can already open |
| Trigger | 3+ characters, debounced, no button |
| Architecture | Approach 1 — shared Vue widget + one search API |
| Existing API | Insufficient alone → new session-auth endpoints; reuse query patterns from memo-list / reports |

## Architecture

### Frontend (reusable)

| Piece | Path |
|-------|------|
| Blade partial | `resources/views/partials/apm-document-search.blade.php` |
| Vue widget | `public/js/apm-document-search.js` |

Config passed via JSON (or data attributes): `searchUrl`, `yearsUrl`, `defaultYear`, optional `placeholder`.

Mount on:

- Home (`home.blade.php` / `home-dashboard-app.js` header area above module cards)
- Approver dashboard (`approver-dashboard` near existing filters)

### Backend

| Piece | Role |
|-------|------|
| `App\Services\DocumentNumberSearchService` | Cross-type query, visibility, show URLs, year discovery |
| `DocumentSearchController` (or equivalent) | Session-auth JSON endpoints |
| Web routes | `GET /api/document-search`, `GET /api/document-search/years` |

Reuse show-URL mapping pattern from `ReportsController::memoListShowUrl` (extract into shared helper/service method if practical).

### Document types (v1)

Same set as memo-list:

- QM (Quarterly Matrix activities)
- SM (Single Memo)
- SPM (Special Memo)
- NT (Non-Travel Memo)
- CR (Change Request)
- SR (Service Request)
- ARF

Year filter:

- Matrix-scoped (QM/SM): `matrices.year`
- Others: `YEAR(created_at)` / `whereYear('created_at', …)`

## UI & interaction

- Compact bar: **year select** (left) + **search field** (right) with search icon.
- Placeholder: e.g. `Document number…`
- Debounce ~300ms; request only when `q.length >= 3`.
- Results panel (dropdown-style card) while focused/querying.
- Each row: document number · type chip · status · short title · chevron.
- Click / Enter on row → full navigation to `show_url`.
- States:
  - &lt; 3 chars: no request; hint “Type at least 3 characters”
  - Loading: linear progress
  - Empty: “No documents found for this year”
  - Error: short error message; clear stale results
- No “All years”; year always selected (default current year).

## API contract

### `GET /api/document-search`

**Auth:** web session (same middleware as other APM page JSON APIs).

**Query:**

| Param | Rules |
|-------|--------|
| `q` | required, string, min 3 |
| `year` | required, integer 2000–2100 |

**Response:** capped at ~20 rows.

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "document_type": "SPM",
      "type_label": "Special Memo",
      "document_number": "AU/CDC/.../SPM/012",
      "title": "...",
      "overall_status": "pending",
      "year": 2026,
      "show_url": "https://…/special-memo/123"
    }
  ]
}
```

Match: `document_number LIKE %q%` (escape LIKE wildcards), scoped by year.

### `GET /api/document-search/years`

Distinct years from `matrices.year` union `YEAR(created_at)` across memo tables used in search, sorted descending. Always include the current calendar year if missing from the union.

## Visibility

Align with web module list access:

- If permission **87** (`canViewAll*` pattern): all divisions.
- Else: user’s primary `division_id` + `associated_divisions` from Staff.

No status filter: draft, pending, approved, rejected, returned, etc. are all searchable if visible.

## Performance

- Per-type queries with number LIKE + year + division scope; merge and sort; limit 20.
- Client debounce to limit request volume.
- Avoid N+1 when building show URLs (map by type codes only).

## Testing

- Unit: `DocumentNumberSearchService` — year filter, min length handling at controller, permission 87 vs division scope, show_url per type.
- Feature: both endpoints — unauthenticated 401/redirect, validation errors, happy path with seeded docs.

## Out of scope follow-ups (optional later)

- JWT twin endpoint for mobile.
- Include Other Memo.
- Exact-prefix-only match for stricter performance.
- Keyboard arrow navigation polish beyond basic Enter.
