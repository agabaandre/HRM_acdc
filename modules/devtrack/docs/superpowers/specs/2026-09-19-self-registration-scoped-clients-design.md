# Self-registration & scoped clients

**Date:** 2026-09-19  
**Status:** Implemented (2026-09-19)  
**Surface:** Admin panel (`/login`, `/register`, `/integrations`, `/users`, `/logs`)

## Problem

Today only system admins can create panel users and integration clients. Partner organisations need to:

1. Request an account (public registration)
2. Wait for admin approval before signing in
3. After approval, register their own clients and manage provider/IP settings
4. See only their own clients and email logs for those clients
5. Have new clients start inactive until an admin activates them

## Decisions (locked)

| Topic | Choice |
|---|---|
| Account gate | **B** — cannot log in until admin approves |
| New clients | **C** — created `is_active = false`; admin activates (no separate approve workflow) |
| Registration entry | **A** — public `/register` + link on improved login page |
| Extra fields | Phone number + organisation |
| Client capabilities after approval | **B** — create/edit own clients including email provider + allowed IPs; logs scoped to own clients only |

## Approach

Extend the existing `User` model and `external_integration_user` pivot (Approach 1). Do **not** introduce a separate Organisation table in this iteration.

## Data model

### `users` (additions)

| Column | Type | Notes |
|---|---|---|
| `approval_status` | string enum | `pending`, `approved`, `rejected`. Default for **self-registered** users: `pending`. Existing/admin-created users: `approved`. |
| `phone` | string nullable | Required on self-registration |
| `organisation` | string nullable | Required on self-registration |
| `approved_at` | timestamp nullable | Set when admin approves |
| `approved_by` | FK users nullable | Admin who approved |
| `rejected_at` | timestamp nullable | Optional |
| `rejection_reason` | string nullable | Optional |

Existing fields continue to apply:

- `is_admin` — self-registered users always `false`
- `is_active` — self-registered start `false`; set `true` on approve
- `totp_required` — set `true` on approve (same as admin-created users) so first login forces authenticator setup

### Clients (`external_integrations`)

No new approval columns. Ownership via existing pivot `external_integration_user`:

- On client create by a non-admin, attach the creating user to the pivot
- Admin-created clients: optional assignment as today
- `is_active = false` when created by a non-admin account
- Admin (or existing UI) sets `is_active = true` to enable JWT/token use

### Login rules

Reject login (no Sanctum token) when:

- `approval_status === pending` → message: account awaiting approval  
- `approval_status === rejected` → message: account was not approved  
- `is_active === false` (and not pending — e.g. deactivated after approval) → existing inactive message  

Admins always `approval_status = approved`.

## API

### Public

- `POST /api/v1/admin/auth/register`  
  Body: `name`, `email`, `password`, `password_confirmation`, `phone`, `organisation`, captcha fields  
  Creates pending user; returns 201 with no token  
  Rate-limited + captcha (same pattern as login)

### Auth

- Login: enforce approval checks before issuing token / 2FA challenge

### Admin (existing users API + extensions)

- List users: support `approval_status` filter; show phone, organisation, status  
- `POST /api/v1/admin/users/{user}/approve`  
- `POST /api/v1/admin/users/{user}/reject` (optional body: `reason`)  
- Admin create-user path remains; created users are `approved` + active as today

### Clients

- Move `/admin/external-integrations` **read/create/update** out of admin-only middleware for authenticated approved users, **or** add parallel routes under a shared group with authorization in the controller/policy  
- Prefer: keep routes under authenticated group; authorize with policy:
  - Admin: full access
  - Approved non-admin: CRUD only on integrations linked via pivot; cannot delete other users’ clients; list filtered to own  
- Non-admin create: force `is_active = false`; auto-attach pivot to current user  
- Non-admin may set `email_provider_id` and `allowed_ips`  
- Secret reveal: same one-time `client_secret` on create/regenerate as today  
- Token endpoint already requires `is_active` — inactive clients cannot authenticate

### Email logs

- Already scoped via `allowedExternalIntegrationIds()` for non-admins — verify all log endpoints use it; fix any gaps (e.g. dashboard recent logs if exposed to non-admins)

## Frontend

### Login page

- Visual refresh consistent with existing admin branding (not a new design system)
- Add clear **Create account** / **Register** CTA linking to `/register`
- Keep captcha, forgot password, 2FA flow

### Register page (`/register`)

- Fields: name, email, phone, organisation, password, confirm password, captcha  
- Success state: “Registration received. An administrator must approve your account before you can sign in.”  
- Link back to login

### Sidebar / router

| Item | Admin | Approved non-admin | Pending (N/A — cannot login) |
|---|---|---|---|
| Dashboard | yes | yes (scoped if needed) | — |
| Clients / Integrations | all | own only | — |
| Email logs | all / filtered | own clients only | — |
| Users | yes | no | — |
| Providers, branding, audit, blocked IPs, send-mail | yes | no | — |

Non-admins: remove `adminOnly` from Clients nav; keep route accessible with scoped API.

### Users admin UI

- Tabs or filter: Pending / Approved / Rejected / All  
- Pending row actions: Approve, Reject  
- Show phone + organisation columns

### Integrations UI

- Non-admin: list only own; “New client” creates inactive client  
- Show Active/Inactive badge; copy explaining admin activation  
- Allow editing provider + allowed IPs  
- Admin: unchanged global list + ability to activate

## Security

- Public register: captcha, throttle, blocked-email check if available  
- No privilege escalation: self-register never sets `is_admin`  
- Policies on every integration mutate/show  
- Audit log: `user_registered`, `user_approved`, `user_rejected`, client create by account

## Notifications (minimal)

- On register: optional email to `ADMIN_EMAIL` that a pending account exists (nice-to-have in same PR if low cost)  
- On approve/reject: optional email to the user (nice-to-have)

Out of scope for v1 if it blocks delivery: fancy notification centre.

## Out of scope

- Multi-user organisations / roles within an organisation  
- Separate client “approval” status beyond `is_active`  
- Public API docs access for non-admins (unless already gated by login)  
- Changing integration JWT auth model

## Success criteria

1. Visitor can register from login → register with phone + organisation  
2. Pending user cannot obtain a panel token  
3. Admin can approve/reject from Users  
4. Approved user sees only their clients and can create inactive clients with provider + IPs  
5. Approved user sees only their clients’ email logs  
6. Inactive client cannot obtain integration JWT until admin activates  
7. Feature tests cover register, login gate, approve, scoped client CRUD, scoped logs

## Spec self-review

- [x] No unresolved placeholders  
- [x] Consistent with choices B / C / A / capabilities B  
- [x] Scope limited to User + pivot extension  
- [x] Tests called out in success criteria  
