# Helpdesk Licenses / Date Picker / Alerts Implementation Plan

> **For agentic workers:** Implement task-by-task. Checkboxes track progress.

**Goal:** Shared `UDateInput` + staff picker, licenses search/responsible person, expiry email alerts with settings, and natural-height general settings cards.

**Architecture:** Thin `U*` wrappers over Vuetify labs; license schema + daily Artisan command; settings keys for enable/interval; GeneralSettingsPanel card + CSS `align-items: start`.

**Tech Stack:** Laravel, Vue 3, Vuetify 3 labs (`VDateInput`), existing Helpdesk mail + scheduler.

## Global Constraints

- `v-model` dates stay `YYYY-MM-DD` strings
- Admin recipients = all helpdesk admins
- Warning window = per-license `warning_days_before` only
- Settings: enable + interval 1|3|7 days
- Production SPA: `helpdesk/frontend` → `dist-build`
- Do not commit unless user asks

---

### Task 1: UDateInput + register VDateInput

**Files:**
- Modify: `helpdesk/frontend/src/plugins/vuetify.ts`
- Create: `helpdesk/frontend/src/components/ui/UDateInput.vue`
- Modify: `helpdesk/frontend/src/components/ui/register.ts`
- Modify: ItAssetsView, LicensesView, ReportsView, LoggingAuditPanel

### Task 2: UStaffDirectoryPicker

**Files:**
- Create: `helpdesk/frontend/src/components/ui/UStaffDirectoryPicker.vue`
- Register in `register.ts`

### Task 3: License schema + API

**Files:**
- Migration: `responsible_staff_id`, `expiry_alert_last_sent_at`
- Model + LicenseController validation/index search
- Enrich response with responsible person display

### Task 4: LicensesView UI

Search, responsible person, warning_days_before, UDateInput

### Task 5: Alert job + mail + settings

Settings keys, mailable, command, schedule, GeneralSettingsPanel card + align-items

### Task 6: Tests + frontend build
