# User Self-Service Profile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a portal-native My Profile self-service module with full CI3 `auth/profile` parity, conditional password change, and correct user-menu links.

**Architecture:** Dedicated Auth-module APIs under `/api/v1/me/profile*` scoped to the Sanctum user’s `auth_staff_id`, plus Vue routes `/profile` and `/profile/password`. Media stored in existing CI3 upload paths; signature accepts draw/type data URL or file upload as PNG.

**Tech Stack:** Laravel (staff-portal/backend Modules/Auth + Staff), Sanctum, Vue 3 + Vuetify 3 SPA, existing staff-media routes.

**Spec:** `docs/superpowers/specs/2026-08-13-user-self-service-profile-design.md`

## Global Constraints

- Full CI3 self-service parity for editable sections only; employment/identity remain read-only.
- Writes never accept a client-supplied `staff_id`; always use current user’s linked staff.
- Keep DB column name `langauge`.
- Change password only when `allow_email_login && config('auth.allow_alternative_login')`.
- Photo ≤1MB image; passport ≤4MB image/PDF; signature → PNG under `uploads/staff/signature/`.
- Compact outlined fields; reuse portal chrome.
- Do not commit unless the user explicitly asks.

## File map

| File | Responsibility |
|------|----------------|
| `Modules/Auth/app/Services/SelfServiceProfileService.php` | Load/update self profile, NOK validation, media save |
| `Modules/Auth/app/Http/Controllers/Api/V1/SelfServiceProfileController.php` | HTTP for profile + media + password |
| `Modules/Auth/routes/api.php` | Register routes |
| `Modules/Auth/app/Http/Controllers/Api/PortalSpaAuthController.php` | Add `allow_email_login` / `password_login_available` to `userPayload` |
| `app/Http/Controllers/StaffUploadController.php` + Staff `web.php` | Serve signature + passport biodata if missing |
| `frontend/src/lib/profileApi.ts` | API client |
| `frontend/src/pages/profile/ProfilePage.vue` | Main self-service UI |
| `frontend/src/pages/profile/ProfilePasswordPage.vue` | Conditional password change |
| `frontend/src/components/molecules/ProfileSignaturePad.vue` | Draw / type / file → data URL or File |
| `frontend/src/router/index.ts` | Routes |
| `frontend/src/stores/auth.ts` | Expose password-login flag |
| `frontend/src/components/organisms/PortalTopHeader.vue` | Fix Profile + Change password links |

---

### Task 1: Enrich `/me` with password-login flags

**Files:**
- Modify: `backend/Modules/Auth/app/Http/Controllers/Api/PortalSpaAuthController.php`
- Modify: `frontend/src/stores/auth.ts` (types + getter)

**Produces:** `profile.allow_email_login`, `profile.password_login_available` on login/me/bootstrap.

- [x] **Step 1:** In `userPayload()`, set:
  - `allow_email_login` = `(bool) $user->allow_email_login`
  - `password_login_available` = `$user->allow_email_login && (bool) config('auth.allow_alternative_login', true)`
- [ ] **Step 2:** Extend frontend `PortalProfile` type and `auth.passwordLoginAvailable` computed.
- [ ] **Step 3:** Smoke: `GET /api/v1/me` returns both flags.

---

### Task 2: Self-service profile service + GET/PUT API

**Files:**
- Create: `backend/Modules/Auth/app/Services/SelfServiceProfileService.php`
- Create: `backend/Modules/Auth/app/Http/Controllers/Api/V1/SelfServiceProfileController.php`
- Modify: `backend/Modules/Auth/routes/api.php`
- Reuse: `Modules/Staff/app/Services/StaffProfileService.php` patterns / kin_relationship_types table

**Produces:**
- `GET /api/v1/me/profile`
- `PUT /api/v1/me/profile`

- [ ] **Step 1:** Implement service `show(PortalUser $user): array` returning staff, contract summary, supervisors, media URLs, kin lookups, flags.
- [ ] **Step 2:** Implement `update(PortalUser $user, array $data): array` with CI3 validation (private_email, tel_1, address, dependants, langauge, next_of_kin max 2).
- [ ] **Step 3:** Controller methods `show` / `update`; abort 404 if no linked staff.
- [ ] **Step 4:** Register sanctum routes.
- [ ] **Step 5:** Manual curl/Postman: GET then PUT contact fields; confirm NOK required rules.

---

### Task 3: Media upload endpoints + serving

**Files:**
- Modify: `SelfServiceProfileService.php` / controller
- Modify: `backend/app/Http/Controllers/StaffUploadController.php`
- Modify: `backend/Modules/Staff/routes/web.php`

**Produces:**
- `POST /api/v1/me/profile/photo`
- `POST /api/v1/me/profile/passport`
- `POST /api/v1/me/profile/signature` (multipart or `data_url`)
- Media GET for signature + passport biodata

- [ ] **Step 1:** Add `signature` and `passport_biodata` actions on `StaffUploadController` mirroring photo (path + MIME checks).
- [ ] **Step 2:** Register `/staff-media/signature/{filename}` and `/staff-media/passport-biodata/{filename}`.
- [ ] **Step 3:** Implement upload handlers; write filenames to staff columns (`photo`, `passport_biodata_page`, `signature`).
- [ ] **Step 4:** Convert signature data URL / uploaded image to PNG (same idea as CI3 `_profile_save_signature_data_url`).
- [ ] **Step 5:** Verify URLs returned by GET profile load in browser.

---

### Task 4: Conditional password change API

**Files:**
- Modify: `SelfServiceProfileController.php` (+ service helper if needed)
- Modify: `Modules/Auth/routes/api.php`

**Produces:** `PUT /api/v1/me/password`

- [ ] **Step 1:** Validate current / new / confirmation; verify current with `password_verify`.
- [ ] **Step 2:** If `!password_login_available` → 403 with clear message.
- [ ] **Step 3:** Hash with `password_hash(..., PASSWORD_ARGON2ID)` or match existing portal hashing used on create/reset.
- [ ] **Step 4:** Test allowed vs denied accounts.

---

### Task 5: Frontend API client + routes + header links

**Files:**
- Create: `frontend/src/lib/profileApi.ts`
- Modify: `frontend/src/router/index.ts`
- Modify: `frontend/src/components/organisms/PortalTopHeader.vue`

- [ ] **Step 1:** Add `fetchMyProfile`, `updateMyProfile`, upload helpers, `changeMyPassword`.
- [ ] **Step 2:** Lazy routes `/profile` and `/profile/password`.
- [ ] **Step 3:** Replace external `profileUrl` with `RouterLink` to `/profile`; add Change password item when `passwordLoginAvailable`.

---

### Task 6: Profile page UI (read + edit)

**Files:**
- Create: `frontend/src/pages/profile/ProfilePage.vue`

- [ ] **Step 1:** Two-column layout: left summary, right edit form.
- [ ] **Step 2:** Wire load/save; show validation errors from API.
- [ ] **Step 3:** Next-of-kin two rows with kin relationship select from lookups.
- [ ] **Step 4:** Photo / passport file inputs calling upload endpoints; refresh media URLs.
- [ ] **Step 5:** Build frontend; hard-refresh and walk the form.

---

### Task 7: Signature pad + password page

**Files:**
- Create: `frontend/src/components/molecules/ProfileSignaturePad.vue`
- Create: `frontend/src/pages/profile/ProfilePasswordPage.vue`
- Modify: `ProfilePage.vue` to embed pad

- [ ] **Step 1:** Signature pad: draw canvas, type-to-render, clear, file picker; emit File or data URL.
- [ ] **Step 2:** Password page with guard redirect if `!passwordLoginAvailable`.
- [ ] **Step 3:** End-to-end QA against spec acceptance checklist.
- [ ] **Step 4:** `npm run build` in `staff-portal/frontend`.

---

## Manual QA checklist

- [ ] User menu Profile opens SPA `/profile`
- [ ] Save contact / address / NOK; reload persists
- [ ] Employment fields not editable
- [ ] Photo, passport, signature (draw + file) preview
- [ ] Password menu + page only when flag true; API 403 otherwise
- [ ] Field height/labels match portal compact outlined style
