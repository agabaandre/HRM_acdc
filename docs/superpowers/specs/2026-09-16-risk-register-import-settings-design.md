# Risk Register — production import, settings trim, DB naming

**Date:** 2026-09-16  
**Status:** Approved  
**Parent:** `docs/superpowers/specs/2026-09-16-risk-register-design.md`

## Decisions

| Topic | Choice |
|-------|--------|
| Import frequency | One-time Excel; thereafter manual entry |
| Unmatched BUs | Preview → import matched; stage unmatched; admin maps to division; then commit |
| BU aliases | None (no permanent alias store) |
| Disable import | Setting hides Import nav + blocks API |
| Settings | Risk-only; lookups editable by Admin + Internal Oversight (`manage_risks`) |
| Methodology | Read-only Reference & Methodology preview for all risk users |
| Clone cleanup | Remove unused Staff Portal SPA/modules from Risk Register |
| DB names | Prod/demo pair per app (see below) |

## Import flow (Approach B)

1. `manage_risks` uploads xlsx → `POST /api/v1/import/preview` stores file + returns matched/unmatched/skipped stats and unmatched BU list with counts.
2. Confirm → `POST /api/v1/import/commit-matched` imports matched rows (`active_imported`, HOD owners); unmatched rows → `rr_import_staged_rows`.
3. Admin maps each unmatched label → `division_id` (+ optional `directorate_id`) → `POST /api/v1/import/apply-mappings`.
4. Setting `rr_settings.import_enabled` (default true). When false: hide Import UI; API returns 403.

## Settings & methodology

- Nav: Risks, Approvals, Workflows, Dashboard, Import (if enabled), Reference, Settings.
- Settings: import toggle; lookup CRUD for Sheet 3 tables (non-critical).
- Reference page: likelihoods, impacts, types, themes, statuses, effectiveness, rating bands, residual formula text.

## Clone cleanup

- Frontend: drop Staff/Leave/Payroll/Performance/Tasks/Workplan/AD/portal-admin routes from primary experience; keep minimal shell + Risk pages.
- Backend: disable unused nwidart modules in `modules_statuses.json` where safe; Risk API remains in `app/`.

## Database naming (setup defaults)

| App | Production | Demo |
|-----|------------|------|
| Staff Portal | `staff_tracker` | `demo_staff_tracker` |
| APM | `approvals_management` | `demo_approvals_management` |
| Helpdesk | `helpdesk` | `demo_helpdesk` |
| Finance | `finance` | `demo_finance` |
| Risk Register | `risk_register` | `demo_risk_register` |

Setup scripts / `.env.example` use these unless the operator overrides `DB_DATABASE`.

## Non-goals

- Continuous Excel sync  
- Permanent BU alias library  
- Editing Staff Portal org from Risk Register  
