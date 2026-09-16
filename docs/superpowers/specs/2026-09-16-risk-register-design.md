# Africa CDC Risk Register — Design

**Date:** 2026-09-16  
**Status:** Approved for planning (pending user review of this file)  
**Source workbook:** `modules/risk-register/Copy of Africa CDC Risk Register Tracker 2026 Categorised.xlsx`  
**Sheets:** Risk Register · Dashboard · Reference & Methodology  

## Goal

Deliver a **sibling CBP app** at `/staff/risk-register` (Laravel 12 + Vue 3), architecturally aligned with Staff Portal / Helpdesk, for enterprise risk capture, approval, quarterly review, and dashboards. Staff Portal remains the **only** place to edit org people (risk focal, HOD, director). Risk Register owns risk domain data, Sheet-3 reference settings, and approval workflow configuration.

## Decisions (locked)

| Topic | Choice |
|-------|--------|
| Placement | Sibling app `modules/risk-register` (Approach A) |
| Auth | CBP SSO launch like Helpdesk/APM (`staff_app_token`) |
| Org / people | Hybrid: Staff Portal for risk focal, HOD, director; Risk Register for SM Focal + extra steps |
| Workflow | Configurable per division (optional directorate override); default **1** extra level after SM |
| Import | One-time Excel import; responsible owners default to HOD; match `Directorate/Division` via short codes + names |
| Imported risks | Enter as active register entries (no forced re-approval); audited edits thereafter |
| Charts | Highcharts with **credits disabled** |
| Residual math | Per Sheet 3 (effectiveness reduction on L; half on I; floor 1) |

## Architecture

```
Staff Portal (/staff/)                     Risk Register (/staff/risk-register/)
┌─────────────────────────────┐            ┌──────────────────────────────────┐
│ divisions.risk_focal_person │──read API─▶│ Org mirror (read-only)           │
│ division_head, director_id  │            │ Risks, reviews, workflow, audit  │
│ CBP module + permissions    │──SSO──────▶│ SSO accept + JWT validate        │
│ Share / staff search        │            │ Own DB: risk_register            │
└─────────────────────────────┘            └──────────────────────────────────┘
```

- Strip the current Staff Portal clone in `modules/risk-register` down to Risk-focused modules (Auth/SSO, Risks, Workflow, Dashboard, Settings, Audit).
- Own MySQL database `risk_register` (or env-configured name); no writes to Staff Portal tables.
- Apache root maps `risk-register` like `helpdesk` / `finance`.

## Staff Portal changes

1. **Column** `divisions.risk_focal_person` (nullable staff_id FK).
2. **Migration/seed:** copy `focal_person` → `risk_focal_person` for all divisions.
3. **Divisions settings UI:** editable Risk Focal Person (alongside existing focal).
4. **Permissions** (seeded; names may be numeric codes in `permissions` table per portal convention):
   - CBP module permission for Risk Register (default assign pattern like other CBP apps).
   - `view_division_risks` — default for authenticated staff (own division).
   - `view_all_risks` — configurable user categories / groups.
   - `manage_risks` — Admin + Internal Oversight (OIO) groups.
5. **`cbp_modules` row:** `module_key=risk_register`, SSO launch to `/staff/risk-register/sso` (or app SSO accept path), `uses_staff_portal_token=1`.
6. **Share/API extensions** (read-only) for Risk app: divisions with short name, directorate, risk_focal, head, director; staff search for assignee pickers.

## Risk Register data model

### Lookups (Sheet 3 — managed only in Risk Register settings)

- `rr_likelihoods` — Unlikely…Certain (scores 1–5)
- `rr_impacts` — Negligible…Critical (scores 1–5)
- `rr_risk_types` — Strategic, Operational, Financial, Compliance, Reputational, Project, Contextual, Public Health Emergency Response, …
- `rr_enterprise_themes` — the **nine** Enterprise Risk thematic areas from the register
- `rr_statuses` — Not Started, In Progress, Open - Extended, Closed - Mitigated, Overdue
- `rr_mitigation_effectiveness` — Not Assessed…Highly Effective + likelihood reduction factors (0–4)
- `rr_rating_bands` — Low 1–4, Medium 5–9, High 10–15, Critical 16–25

### Core

- `rr_risks` — Sheet 1 fields: business unit mapping (`division_id`, `directorate_id`, `unmapped_business_unit`), enterprise theme, name, consequence, root causes, risk type, inherent L/I/score/rating, mitigation, management response, timeline, status update, action update, date of update, OIO verification notes, residual fields, risk movement, import metadata, workflow state.
- `rr_risk_owners` — many-to-many staff_id (import default = division HOD).
- `rr_risk_reviews` — quarterly (year, quarter): likelihood, impact, mitigation strategy, timeline (optional; default previous), derived scores, author, timestamps.
- `rr_approval_workflows` — per `division_id` (optional `directorate_id` override).
- `rr_approval_workflow_steps` — ordered steps with `role` ∈ {`risk_focal`, `hod`, `director`, `sm_focal`, `extra`}; `staff_id` required for `sm_focal`/`extra`; `director` skippable when no director; default template includes **one** `extra` after `sm_focal`.
- `rr_risk_approvals` — runtime step instances per risk (pending / approved / feedback_requested).
- `rr_feedback_requests` (+ responses) — requester must select recipients **only from levels below** their current step; actionable by lower levels.
- `rr_audit_logs` — who, when, action, entity, before/after JSON for all manipulations.

## Workflow

1. Division Risk Focal creates/submits risk → workflow starts at configured first step (typically focal already satisfied on submit).
2. Each pending approver: **Approve** or **Request feedback** (picker restricted to lower workflow levels / division contributors as defined by step order).
3. Skip `director` when Staff Portal shows no director for that division.
4. After final step (including default extra after SM), risk is **signed off**.
5. Post–sign-off (and generally for OIO): users with `manage_risks` may edit risk content; all edits audited.
6. Non-focal division staff: **view** own division risks only (unless `view_all_risks`).

## Excel import

1. Seed lookups from Sheet 3.
2. Parse Business Unit:
   - `PHC/CHSHP` → directorate token `PHC`, division token `CHSHP`.
   - Bare codes/names (`Lab`, `EPR`, `Digital Health`) → division-only match.
3. Match division: exact `division_short_name` (ci) then fuzzy `division_name` (e.g. CHSHP → Community Health Systems & Health Promotion).
4. Match directorate: alias map + `directorates.name` (e.g. PHC → Primary Health Care / Centre of Primary Health Care).
5. Unmatched → retain `unmapped_business_unit`; listed on Import report for admin fix.
6. Owners → HOD (`division_head`) when matched.
7. Map likelihood/impact labels → scores; compute inherent; residual = inherent until effectiveness assessed (Sheet 3 rules).
8. Command: `php artisan risk:import-excel` (path defaulting to the workbook in module root).

## UI (Vue SPA)

| Page | Notes |
|------|--------|
| Dashboard | Sheet 2 KPIs + Highcharts (credits off): rating distribution, by type, by BU, L×I heat map, top 10 residual, status & effectiveness distributions; filters |
| Division register | Full field profile form; dropdowns; multi owners; mitigation timeline |
| Risk drill-down | Full detail, owners, audit, approvals/feedback; quarterly trend; annual aggregate trend |
| Quarterly review | L, I, mitigation strategy, timeline (default previous) |
| My approvals | Approve / Request feedback |
| Approval workflow management | Per division/directorate model |
| Risk settings | Sheet 3 only |
| Org mirror | Read-only focals/HODs/directors from Staff Portal |
| Import report | Match stats |

## Residual risk methodology (Sheet 3)

- Inherent score = L × I (1–25); band via `rr_rating_bands`.
- Residual L = max(1, inherent L − effectiveness reduction).
- Residual I = max(1, inherent I − ⌊reduction / 2⌋).
- Residual score/rating from residual L×I; movement compares residual vs inherent.
- Until effectiveness assessed (Not Assessed), residual displays equal to inherent.

## Non-goals (this phase)

- Editing Staff Portal org structure from Risk Register.
- Replacing AD/email account management.
- Multi-language UI (follow portal later if needed).
- Continuous Excel sync (one-time import only; no scheduled re-import).

## Implementation phases (for planning)

1. Scaffold Risk app (strip clone), DB, SSO, Apache, CBP module + Staff Portal `risk_focal_person` + perms.
2. Lookups + import + org mirror API.
3. Risk CRUD, owners, audit, division register + drill-down.
4. Workflow engine + feedback + My approvals + workflow admin UI.
5. Quarterly reviews + trend charts.
6. Dashboard (Highcharts).
7. Hardening, seed groups (Admin/OIO → `manage_risks`), docs/setup.

## Open points (resolved default)

- Imported risks: **no forced re-approval** (active register). New risks created in-app follow full workflow.
