# APM Activity Reports + Reporting & Finance Registry

**Date:** 2026-10-12  
**Status:** Approved for implementation planning  
**Approach:** Mirror ARF (Approach 1) — shared draft report, HasApprovalWorkflow, registry permission, portal AI providers  
**Surfaces:** `/reports` registry card, activity report form/show/print, pending approvals, `/api/apm/v1`, staff Permissions + AI settings

## Problem

After memo approval and (for finance) registry processing, Africa CDC needs a structured activity report aligned with the official Activity Reporting Template. Today there is no shared report model, no registry process status on `/reports`, no reminder cadence for overdue reports, and no HOD approval path for reports. Staff also need collaborative editing on one report, related-activity context tags, AI-assisted synthesis, and table preview.

## Goals

1. **One shared Activity Report per source memo** (Activity / Single memo / Special memo / Non-travel with `pra_activity_id`).
2. **Collaborative edit** by responsible person + memo staff on the same draft.
3. **Related activities** tagger (Vuetify search + checkboxes) for context only; optional inclusion in AI synthesis.
4. **Reporting & finance registry** on `/reports` with Started / Processed actions for holders of a new staff permission.
5. **Reminders** (current quarter only): **all types** = end date + 7 days (not gated on registry processed or ARF/SR); daily until report is submitted; not-executed stops reminders.
6. **HOD workflow** via `HasApprovalWorkflow` + `PendingApprovalsService` (approve / return for edit).
7. **AI assist** using staff-portal AI providers (OpenAI, Gemini, custom OpenAI-compatible / Copilot-style).
8. **Preview** from the registry/report table without leaving the list.
9. **API** under `/api/apm/v1` for report, registry, preview, and AI chat.
10. Form fields and mission-type extras match the PDF Activity Reporting Template.

## Non-goals (this iteration)

- Multiple independent reports per activity.
- Prefilling content from tagged related activities into form fields (tags are context only; AI may use them when checkbox is on).
- Marking related activities as “covered” so they skip their own report.
- Building a second AI key store inside APM (reuse portal `portal_ai_providers`).
- Real-time collaborative cursors / OT; last-save wins with `updated_by`.
- Changing ARF / Service Request workflows.

## Decisions (from brainstorming)

| Topic | Choice |
|-------|--------|
| Scope | Activities & single memos (incl. extramural/external fund types), special memos, non-travel (`pra_activity_id` **or** extramural/external). Not gated on ARF/SR. |
| Reminders | **All types:** end date + 7 days (NT uses `date_to` if set, else `memo_date`). Daily; not-executed / after first submit stops; current quarter |
| Who can edit | Responsible primary + memo staff (shared draft) |
| Multiple reports | No — one report; multi-contributor |
| Related activities | Context links only (search + checkboxes); AI may include when checked |
| Registry process | New permission at `/staff/permissions`; assignees can Started / Processed |
| Architecture | Approach 1 — mirror ARF workflow patterns + portal AI + PendingApprovalsService (reports themselves are not tied to ARF/SR documents) |
| AI providers | Staff-portal Settings → AI providers |

## Architecture

### Core model: `ActivityReport`

| Piece | Role |
|-------|------|
| Table `activity_reports` | One row per source memo |
| Unique | `(source_id, source_type)` |
| Trait | `HasApprovalWorkflow`, document number, auditable as needed |
| Pivot `activity_report_related` | Tagged related memos/activities (polymorphic or typed id + type) |
| Mission extras | JSON column (or type-specific columns) keyed by mission type |

**Source types:** `App\Models\Activity`, `App\Models\SpecialMemo`, `App\Models\NonTravelMemo` (and single memos as Activity with `is_single_memo` / existing convention).

**Key columns (illustrative):**

- `source_id`, `source_type`
- `mission_type` (enum/string): official_engagement, training, events, procurement, in_office, recruitment_hr, others
- Autofill snapshots: tier2, tier4, activity_name_code, broad_activity_amert, dates, duration, objectives (where applicable)
- Editable: location_venue, lead_organiser_staff_id, other_organiser_staff_ids (JSON), summary blocks (participants, MS list, strategic needs, achievements, challenges, MoV refs), budget_code, total_cost_usd, logistics actuals
- Type-specific extras JSON per PDF sections (official engagement / events / training)
- `not_executed` (bool)
- `forward_workflow_id`, approval fields as per ARF pattern
- `created_by`, `updated_by`, timestamps

**Create-on-first-open:** first eligible participant opening “Create/Open report” creates the draft if missing.

### Registry process status

Dedicated table `reporting_registry_statuses` keyed by `(source_id, source_type)` (keeps memo tables unchanged):

- `started_at`, `started_by`
- `processed_at`, `processed_by`

Writable only if user has permission `apm_reporting_finance_registry`.

### Permission

| Name (system) | Definition (UI) |
|---------------|-----------------|
| `apm_reporting_finance_registry` | APM Reporting & Finance Registry |

- Managed at staff-portal Permissions (`https://cbp.africacdc.org/staff/permissions`).
- Seed via migration/seeder or documented create via Permissions API so admins can assign.
- Gates Started / Processed only; does not grant report edit or HOD approve.

### UI

#### `/reports` — Reporting & finance registry card

- Lists current-quarter in-scope memos.
- Columns: ref, title, type, division, dates, process status, report status, actions.
- Actions: Start / Process (permission), Open report, Preview, Not executed (report-eligible participants only).
- Preview: read-only modal/drawer of report sections + mission extras.

#### Report form

1. Related activities — Vuetify searchable multi-select with checkboxes; chips for selection.
2. Template fields (PDF order); mission-type sections show/hide.
3. Attachments / MoV.
4. AI assist panel: provider (default from portal), chat, **Include related activities** checkbox, apply-to-draft with confirm.
5. Submit for approval when complete (not when `not_executed`).

#### Pending approvals

- Register `ActivityReport` in `PendingApprovalsService` (same pattern as `RequestARF`).
- HOD can view, approve, or return with comment; return reopens collaborative edit.

### Reminders

Scheduled daily command (APM `ScheduleServiceProvider`):

| Memo type | Reminder start |
|-----------|----------------|
| Activity / single / special / non-travel | **End date** + 7 days (even if registry not processed; independent of ARF/SR) |

End date resolution: `date_to` / `end_date` when present; for non-travel fall back to `memo_date`.

In-scope for registry + reminders:

- Activities & single memos in current quarter (all fund types, including extramural `fund_type_id = 2` and external `fund_type_id = 3`)
- Special memos in current quarter
- Non-travel with `pra_activity_id` **or** extramural/external fund type

Rules:

- Current calendar quarter only.
- Skip if `not_executed`.
- Skip if report `has_been_submitted` (draft does **not** stop reminders).
- Notify responsible person + memo staff daily until condition clears.

### AI

- Call staff-portal `PortalAiProviderService` / default active provider (OpenAI, Gemini, custom endpoint).
- Chat endpoint builds context from current report draft + source memo autofill; if checkbox on, append tagged related activities’ titles/summaries/objectives (read-only context, not form merge).
- User must confirm before applying synthesized text into fields.
- No APM-local API key storage.

### API (`/api/apm/v1`)

| Area | Endpoints (illustrative) |
|------|---------------------------|
| Registry | list, start, process |
| Report | show/create-or-get, update, submit, approve, return, preview |
| Related | search candidates, sync tags |
| AI | chat/synthesize with `include_related` flag |
| Lookups | mission types, staff organisers |

Auth and visibility aligned with existing APM JWT/session patterns used by other memo APIs.

### Workflow

- Seed or reuse a forward workflow suitable for HOD approval of Activity Reports (mirror ARF workflow definition pattern).
- Approval trail stored like other memo types.
- Returned reports are editable by all participants again.
- **Reminders:** stop once the report has been submitted at least once (including after HOD return). Draft-only reports do not stop reminders.

## Field mapping (PDF template)

| # | Attribute | Source |
|---|-----------|--------|
| 1–3, 5–6 | Tier 2/4, name/code, broad activity, dates/duration | Autofill from memo / PRA / AWP |
| 4 | Activity and mission type | User select; drives extra sections |
| 7 | Location & venue | User |
| 8 | Organising staff | Staff pickers (system) |
| 9 | Objectives | Autofill except In-office / Others |
| 10a–e | Summary blocks + MoV | User (+ attachments) |
| 11 | Logistics | Planned from CBP; actuals user |
| Extras | Official engagement / Events / Training | Conditional sections |

Procurement / In-office / Recruitment & HR / Others: core template only unless extras added later.

## Testing notes (for plan)

- Unique report constraint; multi-user update last-write.
- Permission gates process actions.
- Reminder eligibility matrix (NT vs end date, not-executed, draft vs submitted, quarter filter).
- Pending approvals inclusion for ActivityReport.
- AI include_related context assembly without writing related data into form.
- Preview payload completeness.

## Open implementation details (non-blocking)

- Exact workflow_id / definition seed values (follow existing ARF seed style).
- Print/PDF blade parity with on-screen preview.
