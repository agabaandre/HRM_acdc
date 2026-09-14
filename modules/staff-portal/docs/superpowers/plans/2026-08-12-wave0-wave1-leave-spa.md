# Wave 0+1 Leave SPA Migration — Implementation Plan

> **For agentic workers:** Execute task-by-task. Steps use checkbox syntax.

**Goal:** Platform lookups + Vue Leave (full Livewire parity) + Leave settings; delete Leave/LeaveSettings Livewire.

**Architecture:** Sanctum `/api/v1/leave/*` wrapping existing Leave services; Vue pages under `frontend/src/pages/leave` and `pages/settings`.

**Tech Stack:** Laravel 11 modules, Sanctum, Vue 3, Vuetify, existing `@cbp/*` chrome.

## Global Constraints

- Do not break Microsoft OAuth / spa-bridge.
- Keep CI3 `/lists/{type}` working.
- HR = `role_id === 20`; all-staff leave list needs permission `77`.
- Prefer reusing `LeaveRequestService`, `LeaveBalanceService`, `LeavePolicyService`.

---

### Task 1: Sanctum-aware LeaveAccess + me `is_hr`

**Files:**
- Modify: `backend/Modules/Leave/app/Support/LeaveAccess.php`
- Modify: `backend/Modules/Auth/app/Http/Controllers/Api/PortalSpaAuthController.php` (add `is_hr` on profile)
- Modify: `frontend/src/stores/auth.ts` types if needed

- [ ] Update `LeaveAccess::staffId()` / `isHr()` to prefer `auth()->user()` PortalUser, fall back to session.
- [ ] Add `profile.is_hr` boolean to SPA `me` / login payload.
- [ ] Smoke: `php artisan tinker` or feature test later.

---

### Task 2: Leave API V1 controllers + routes

**Files:**
- Create: `backend/Modules/Leave/app/Http/Controllers/Api/V1/LeaveBalanceController.php`
- Create: `backend/Modules/Leave/app/Http/Controllers/Api/V1/LeaveRequestController.php`
- Create: `backend/Modules/Leave/app/Http/Controllers/Api/V1/LeaveApprovalController.php`
- Create: `backend/Modules/Leave/app/Http/Controllers/Api/V1/LeaveMetaController.php`
- Create: `backend/Modules/Leave/app/Http/Controllers/Api/V1/LeaveSettingsController.php`
- Create: FormRequests under `.../Http/Requests/Api/V1/`
- Create: Resources under `.../Http/Resources/Api/V1/`
- Modify: `backend/Modules/Leave/routes/api.php`
- Modify: `backend/Modules/Leave/routes/web.php` (redirects; remove Livewire later)

- [ ] Implement endpoints per design spec.
- [ ] Replace stub `apiResource('leaves')`.
- [ ] Feature tests under `Modules/Leave/tests` or `backend/tests/Feature/Leave`.

---

### Task 3: Lookup API V1

**Files:**
- Create: `backend/Modules/Lookup/app/Http/Controllers/Api/V1/LookupController.php`
- Modify: `backend/Modules/Lookup/routes/api.php`

- [ ] `GET /api/v1/lookups/{type}` Sanctum, reuse MAP from ListsApiController.

---

### Task 4: Vue Leave lib + pages

**Files:**
- Create: `frontend/src/lib/leaveApi.ts`
- Create: `frontend/src/pages/leave/LeavePage.vue`
- Create: `frontend/src/pages/leave/LeaveApplyPage.vue`
- Create: `frontend/src/pages/settings/LeaveSettingsPage.vue`
- Create: molecules as needed under `frontend/src/components/molecules/`
- Modify: `frontend/src/router/index.ts`
- Modify: `frontend/src/lib/portalNav.ts` if needed

- [ ] Wire `/leave`, `/leave/apply`, `/settings/leave`.
- [ ] Parity with Livewire tabs/actions.
- [ ] `npm run build`.

---

### Task 5: Remove Livewire Leave + LeaveSettings; redirects

**Files:**
- Delete Livewire classes/views for Leave + LeaveSettings
- Modify Settings `routes/web.php`, Leave `routes/web.php`
- Redirect old paths to SPA URLs

- [ ] Confirm SPA works without Livewire.
- [ ] Rebuild frontend dist-build.

---

### Task 6: Verify

- [ ] API routes listed: `php artisan route:list --path=api/v1/leave`
- [ ] Manual leave flow on localhost SPA
- [ ] Config clear if needed
