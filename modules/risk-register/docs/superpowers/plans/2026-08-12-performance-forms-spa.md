# Performance Forms Full SPA Migration Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Replace Livewire iframe bridge with native Vue PPA / midterm / endterm forms backed by APIs over existing services. No dependency on Livewire session for editing.

**Architecture:** Thin `PerformanceFormApiController` over `PpaFormService` + `PerformanceApprovalService`; one phase-aware Vue form page with section components; delete `PerformanceFormBridgePage`.

**Tech Stack:** Laravel Sanctum APIs, Vue 3, existing Performance services, `ppa_entries` + trail tables.

## Global Constraints

- No iframe bridge; SPA must work with Sanctum token only
- Reuse server validation/workflow from Livewire/`PpaFormService` — do not reimplement business rules in Vue only
- Leave module untouched
- After SPA works, redirect Livewire form web routes to SPA (or 410) so nothing links back

---

### Task 1: Form read/create APIs

**Files:**
- Modify: `Modules/Performance/app/Http/Controllers/Api/V1/PerformanceFormApiController.php`
- Modify: `Modules/Performance/routes/api.php`
- Reference: `Livewire/PerformanceForm.php` for payload shape / readonly flags

**Routes:**
- `GET /api/v1/performance/entries/{entryId}?phase=ppa|midterm|endterm`
- `POST /api/v1/performance/entries` — body: `period`, optional staff context for create

**Produces JSON:** entry fields for phase, contract display, objectives array, competencies, training skills catalog, AU values, workflow step, `readonly`, `can_approve`, `can_return`, `can_consent`, `can_save`, trail[].

- [ ] **Step 1: Implement show + create using existing services (mirror Livewire mount).**

- [ ] **Step 2: Feature test or manual tinker for an existing entry id.**

---

### Task 2: Write APIs — draft / submit / approve / return / consent

**Files:**
- Same controller + routes
- Services: `PpaFormService`, `PerformanceApprovalService`

**Routes:**
- `PUT /api/v1/performance/entries/{entryId}` — draft (`phase` + form payload)
- `POST /api/v1/performance/entries/{entryId}/submit`
- `POST /api/v1/performance/entries/{entryId}/approve`
- `POST /api/v1/performance/entries/{entryId}/return` — `{ comments }`
- `POST /api/v1/performance/entries/{entryId}/consent`

- [ ] **Step 1: Map request bodies to service method signatures (copy from Livewire `saveDraft` / `saveSubmit` / …).**

- [ ] **Step 2: Return validation errors as 422 JSON.**

- [ ] **Step 3: Happy-path test: draft → submit for PPA on a test staff/period.**

---

### Task 3: Vue form page + API client

**Files:**
- Modify: `frontend/src/lib/performanceApi.ts`
- Create: `frontend/src/pages/performance/PerformanceFormPage.vue`
- Create: `frontend/src/components/performance/` section components (`PpaSections.vue`, `MidtermSections.vue`, `EndtermSections.vue`, `PerformanceWorkflowCard.vue`)
- Modify: `frontend/src/router/index.ts` — replace bridge components with `PerformanceFormPage`
- Delete: `frontend/src/pages/performance/PerformanceFormBridgePage.vue`
- Modify: `frontend/src/pages/performance/PerformancePage.vue` — ensure create/open only hit SPA routes

- [ ] **Step 1: Client methods for show/create/save/submit/approve/return/consent.**

- [ ] **Step 2: Port section UI from Livewire blades under `resources/views/forms/{ppa,midterm,endterm}/`.**

- [ ] **Step 3: Workflow card + trail; respect readonly/can_* flags.**

- [ ] **Step 4: Client validation** — objective weights sum 100 where Livewire required it.

---

### Task 4: Retire Livewire entry points from SPA path

**Files:**
- Modify: `Modules/Performance/routes/web.php` — redirect create/view_ppa/midterm/endterm to SPA URLs
- Modify: Hub API `form_url` generators if any still emit Livewire URLs

- [ ] **Step 1: Web redirects to `/staff/staff-portal/performance/...` SPA paths.**

- [ ] **Step 2: Grep for `PerformanceFormBridge` / iframe / `view_ppa` in frontend — must be zero.**

- [ ] **Step 3: `npm run build`.**

---

### Task 5: End-to-end smoke

- [ ] Create PPA → Save draft → Submit → Approve/Return as supervisor
- [ ] Open midterm / endterm after PPA approved
- [ ] Endterm consent when settings require it
- [ ] PDF print still works from hub
