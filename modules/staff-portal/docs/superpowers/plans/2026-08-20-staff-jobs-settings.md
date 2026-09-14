# Staff Jobs Settings Implementation Plan

> **For agentic workers:** Execute task-by-task on the **current branch** (`main`). Do **not** create a new branch or worktree. Do **not** commit unless the user asks.

**Goal:** Port CI3 Staff jobs into staff-portal SPA with shared `staff_jobs_schedule.json`, and default Profile completion reminder **off**.

**Architecture:** `StaffJobsScheduleService` resolves defaults ⊕ `application/cache/staff_jobs_schedule.json`. Laravel scheduler and Settings API both use it. SPA page at `/settings/staff-jobs`.

**Tech Stack:** Laravel Jobs/Settings modules, Vue 3 + Vuetify, Sanctum + permission 15.

**Spec:** `docs/superpowers/specs/2026-08-20-staff-jobs-settings-design.md`

## Global Constraints

- Stay on current branch; no new branch.
- Shared JSON path: `{STAFF_ROOT}/application/cache/staff_jobs_schedule.json` where `STAFF_ROOT = dirname(base_path(), 2)`.
- Default `staff_profile_completion_reminder` = `false`.
- Permission 15 for all endpoints.
- Run-now: whitelist `job_key` → Artisan only.

---

### Task 1: Schedule service + defaults off for profile reminder

**Files:**
- Create: `staff-portal/backend/Modules/Jobs/app/Services/StaffJobsScheduleService.php`
- Modify: `staff-portal/backend/Modules/Jobs/config/schedule.php`
- Modify: `application/helpers/staff_jobs_schedule_helper.php` (default false)
- Modify: `staff-portal/backend/Modules/Jobs/app/Providers/JobsServiceProvider.php`
- Test: `staff-portal/backend/tests/Unit/StaffJobsScheduleServiceTest.php`

- [ ] Defaults include `staff_profile_completion_reminder => false`
- [ ] `resolved()`, `write()`, `dailyJobsMeta()`, `instantJobs()`
- [ ] `JobsServiceProvider` calls `$service->resolved()` instead of raw config for enablement
- [ ] Unit tests: default false; merge JSON override; normalize unknown keys dropped

### Task 2: Settings API

**Files:**
- Create: `staff-portal/backend/Modules/Settings/app/Http/Controllers/Api/V1/StaffJobsSettingsController.php`
- Modify: `staff-portal/backend/Modules/Settings/routes/api.php`
- Modify: `staff-portal/backend/Modules/Settings/app/Http/Controllers/Api/V1/SettingsApiController.php` (hub card)
- Test: `staff-portal/backend/tests/Feature/StaffJobsSettingsApiTest.php`

- [ ] `GET/PUT settings/staff-jobs`, `POST settings/staff-jobs/run`
- [ ] Permission 15; run whitelist maps to Artisan commands

### Task 3: SPA page

**Files:**
- Create: `staff-portal/frontend/src/pages/settings/StaffJobsSettingsPage.vue`
- Modify: `staff-portal/frontend/src/lib/settingsApi.ts`
- Modify: `staff-portal/frontend/src/router/index.ts`

- [ ] Schedule form + Run now buttons; route `/settings/staff-jobs`

### Task 4: Smoke

- [ ] `php artisan schedule:list` does not show profile reminder when default/JSON off
- [ ] Manual GET hub includes Staff jobs card

---

## Job key → Artisan map (Run now)

| job_key | command |
|---------|---------|
| notify_staff_profile_extension | `jobs:staff-profile-completion-reminder` |
| staff_birthday | `jobs:staff-birthday` then `jobs:send-instant-mails` |
| mark_due_contracts | `jobs:mark-due-contracts` |
| audit_extended_contracts | `jobs:audit-extended-contracts` |
| send_instant_mails | `jobs:send-instant-mails` |
| send_mails | `jobs:send-mails` |
| manage_accounts | `jobs:manage-accounts` |
| performance_approval_reminder | `jobs:performance-approval-reminder` |
| performance_notifications_bundle | `jobs:performance-notifications` |
| prune_user_logs_get_access | `jobs:prune-user-logs-get-access` |
| sync_pra_workplan | `workplan:sync-pra` |
| cron_register | `jobs:manage-accounts` (legacy bundle) |
