# Hosting, Innovations & HoD Approval Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox syntax.

**Goal:** Add Hosting + Innovations tools with Staff Share HoD approval; gate Software processing until HoD approves; rename nav to Service Desk Modules; add create-page tabs.

**Architecture:** Extend Staff Share division normalizer + `DivisionHeadResolver` (head / active OIC). New request tables and Tools controllers mirroring Software requests. Process endpoints require `hod_approved` (Hosting/Software) or `submitted` (Innovations). Agents flags for process; admins process by default but cannot skip HoD.

**Tech Stack:** Laravel (helpdesk/backend), Vue 3 + Vuetify (frontend + client mirror), Sanctum, PHPUnit.

**Spec:** `helpdesk/docs/superpowers/specs/2026-07-27-hosting-innovations-hod-approval-design.md`

## Global Constraints

- HoD from Staff Share only; active head OIC counts as HoD
- Admins are not substitute HoDs
- Process before HoD → API 403
- Hosting categories: `cloud` | `on_premises`
- Submit Hosting/Innovations: all authenticated staff
- Mirror changes under `helpdesk/client/src/` where the team keeps parity

---

### Task 1: Division head resolution + profile flags

**Files:**
- Migrate `helpdesk_profiles` + hosting/innovation tables + software HoD columns
- `StaffShareNormalizer.php` — retain head/OIC fields
- `App\Services\DivisionHeadResolver.php` — new
- `HelpdeskProfile`, `MeResource`, `AdminHelpdeskAgentController`, `AdminStaffPermissionController`

- [ ] Migration + resolver + normalizer + profile helpers
- [ ] Unit test resolver; feature smoke for flags on `/me`

### Task 2: Hosting + Innovations APIs

**Files:**
- Models `HelpdeskHostingRequest`, `HelpdeskInnovationRequest`
- Controllers + routes
- Feature tests

- [ ] CRUD + hod-approve/reject + process/complete
- [ ] Tests: process before HoD 403; HoD then process OK; Innovations no HoD

### Task 3: Software HoD gate

**Files:**
- `HelpdeskSoftwareRequest` + `SoftwareRequestController`
- Update `SoftwareRequestModuleTest`

- [ ] Submit → `pending_hod`; gate approve/team; add hod endpoints
- [ ] Fix/extend tests

### Task 4: Frontend modules + nav + create tabs

**Files:**
- `toolsNav.ts`, `toolsPermissions.ts`, `CbpPrimaryNav.vue`, router
- `HostingRequestsView.vue`, `InnovationRequestsView.vue`
- `TicketCreateView.vue`, `AgentsManagementPanel.vue`
- Mirror under `client/src/`

- [ ] Nav rename + items; Agents toggles; create tabs; module UIs with HoD/process gates

### Task 5: Verify

- [ ] Run PHPUnit for new/updated tests
- [ ] Mark spec status Approved/Implemented in header when done
