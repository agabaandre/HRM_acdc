# Staff Portal: User Self-Service Profile

**Date:** 2026-08-13  
**Status:** Approved  
**Reference:** CI3 [`/staff/auth/profile`](https://cbp.africacdc.org/staff/auth/profile) + `auth/change_password`

## Goals

1. Portal-native **My Profile** self-service with full CI3 field/capability parity.
2. Fix the user-menu Profile link (today points at broken `/staff/staff/profile`).
3. Signature capture via **draw/type + file upload**; photo and passport biodata uploads.
4. **Change password** only when password login is enabled for that user (`user.allow_email_login`) and global password login is allowed.

## Non-goals

- Editing employment / identity fields managed by HR (name, SAP, DOB, nationality, gender, job, division, duty station, grade, contract, funder, supervisors, dates, status).
- Replacing Microsoft SSO / staff-email sign-in.
- Migrating incomplete-profile reminder emails off CI3 (can keep pointing at portal URL later).
- Admin editing of another user’s profile via this module.

## Decisions (locked)

| Topic | Decision |
|-------|----------|
| Scope | **Full CI3 parity** for self-service editable sections |
| Architecture | Dedicated SPA module (Approach A), not Staff-show edit mode |
| Password | Show Change password **only if** `allow_email_login` and `config('auth.allow_alternative_login')` |
| Signature | Both canvas draw/type **and** file upload → stored as PNG |
| DB column | Keep `langauge` spelling (existing typo) |
| Storage | Reuse CI3 paths under staff uploads: `staff/`, `staff/passport_biodata/`, `staff/signature/` |
| Auth | Sanctum SPA token; all writes scoped to current `auth_staff_id` only |

---

## 1. Product & UX

### Entry points

- User menu **Profile** → `/staff/staff-portal/profile` (Vue route `/profile`).
- User menu **Change password** → `/staff/staff-portal/profile/password` — visible only when `password_login_available` is true.
- Optional later: CI3 header / reminder emails can deep-link to the portal Profile URL.

### Page layout (`ProfilePage`)

Two-column layout matching CI3:

**Left — read-only summary**

- Photo, display name, job / acting, group / contract type / grade badges
- Personal: DOB, nationality, gender, SAPNO
- Address & dependants (current values)
- Next of kin summary
- Contact: work email, private email, phones, WhatsApp
- Passport biodata status (preview / download link or “not uploaded”)
- Signature preview
- Employment: division, directorate (if available), duty station, physical location, job, grade, contract type / institution / funder, dates, status
- Supervisors (primary / secondary)

**Right — Edit my details**

1. Contact & language — `private_email`*, `whatsapp`, `tel_1`*, `tel_2`, `langauge` (en / fr / sw / ar)
2. Media — photo upload (≤1MB image); passport biodata (image/PDF ≤4MB); signature draw/type pad + file upload
3. Address & dependants — `residential_address_duty_station`*, `number_of_dependants`* (integer ≥ 0)
4. Next of kin — up to 2 rows; first required (name, relationship, phone, email); second optional

\* = required on save.

### Password page (`ProfilePasswordPage`)

- Fields: current password, new password, confirm
- Route guard: redirect to `/profile` if `password_login_available` is false
- API returns 403 if called when not allowed

### Look & feel

- Portal chrome (`PortalPageChrome`), compact outlined floating labels (MFL-aligned field height)
- Africa CDC green theme; no new design system

---

## 2. APIs & data

All routes under Sanctum auth. Ownership: resolve staff via `PortalUser.auth_staff_id`; never accept another `staff_id` from the client for writes.

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/v1/me/profile` | Self-service payload (see below) |
| `PUT` | `/api/v1/me/profile` | Update editable scalar + NOK JSON fields |
| `POST` | `/api/v1/me/profile/photo` | Multipart `photo` |
| `POST` | `/api/v1/me/profile/passport` | Multipart `passport` |
| `POST` | `/api/v1/me/profile/signature` | Multipart `signature` **or** JSON `{ "data_url": "data:image/png;base64,..." }` |
| `PUT` | `/api/v1/me/password` | `{ current_password, password, password_confirmation }` |

### Enrich thin session user

`GET /api/v1/me` (and login/bootstrap payloads) gain:

```json
{
  "profile": {
    "allow_email_login": true,
    "password_login_available": true
  }
}
```

`password_login_available` = `allow_email_login && config('auth.allow_alternative_login')`.

### `GET /api/v1/me/profile` shape (illustrative)

```json
{
  "data": {
    "staff": { "...editable + read-only staff columns..." },
    "contract": { "...latest / current contract summary..." },
    "supervisors": { "first": {}, "second": {} },
    "media": {
      "photo_url": "...",
      "signature_url": "...",
      "passport_url": "...",
      "passport_is_pdf": false
    },
    "lookups": {
      "kin_relationship_types": [{ "id": 1, "name": "Spouse" }]
    },
    "flags": {
      "allow_email_login": true,
      "password_login_available": true
    }
  }
}
```

### Validation (match CI3)

- `private_email`: required, email  
- `tel_1`: required, string  
- `residential_address_duty_station`: required, string  
- `number_of_dependants`: required, integer ≥ 0  
- `langauge`: nullable, in `en,fr,sw,ar`  
- `next_of_kin`: array max 2; index 0 requires name, relationship_id (exists in kin types), phone, email; index 1 optional but if any field set, require all four  
- Photo: image, max 1MB  
- Passport: image or PDF, max 4MB  
- Signature file: image → convert/store PNG; data URL → PNG under `uploads/staff/signature/`  
- Password: current must verify; new must meet existing password policy / confirmation

### Services / reuse

- Read contract/supervisor summary via existing `StaffProfileService` / directory contract subquery patterns
- Media serving: existing staff-media secure URLs where present; extend if signature/passport missing
- NOK stored in `staff.next_of_kin_json` (same shape as CI3)
- Do not expose or update `user.password` hash except via dedicated password endpoint

---

## 3. Frontend structure

| Piece | Path / note |
|-------|-------------|
| Routes | `/profile`, `/profile/password` |
| Pages | `pages/profile/ProfilePage.vue`, `ProfilePasswordPage.vue` |
| API client | `lib/profileApi.ts` |
| Signature | Reusable component (canvas draw + type-to-image + file input) |
| Header | `PortalTopHeader.vue` — Profile → router link; Change password conditional |
| Auth store | Expose `passwordLoginAvailable` from `me` / login payload |

---

## 4. Security

- All mutations authorized only for the authenticated user’s linked staff row
- Reject password change when flag/config false (403)
- Validate upload MIME + size server-side; store under existing upload roots with safe filenames
- Never return password hashes; never allow updating another staff_id
- Audit: prefer writing to existing portal/CI3 audit log if a hook already exists for profile updates; otherwise log via Laravel log + optional Auth audit table if already used for similar actions

---

## 5. Delivery order

1. Backend: `me` flags + `GET/PUT /me/profile` + NOK validation  
2. Backend: media upload endpoints + secure URL wiring  
3. Backend: conditional password change  
4. Frontend: Profile page (read + edit form)  
5. Frontend: signature pad + uploads  
6. Frontend: password page + header links  
7. Manual QA against CI3 parity checklist  

---

## 6. Acceptance checklist

- [ ] Profile opens from user menu without leaving the SPA  
- [ ] Editable sections save and reload correctly  
- [ ] Employment/identity fields remain read-only  
- [ ] Photo / passport / signature (draw and file) upload and preview  
- [ ] Next of kin validation matches CI3  
- [ ] Change password hidden and API-blocked when password login not enabled  
- [ ] Change password works when `allow_email_login` is true  
- [ ] Compact field chrome consistent with portal filters / MFL-style height  

## Out of scope follow-ups

- Point CI3 incomplete-profile reminder emails at portal `/profile`  
- DocuSeal vendor widget parity (portal uses native canvas/type instead)  
- Admin “impersonate / edit as user” from HR tools  
