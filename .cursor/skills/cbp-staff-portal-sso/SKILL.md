---
name: cbp-staff-portal-sso
description: >-
  Integrate an external or sibling CBP module with Africa CDC Staff Portal SSO
  (staff_sso_jwt handoff). Use when wiring Finance, DevTrack, IT, Risk Register,
  Helpdesk, Registry, APM, or a new module so home-tile launch skips the module
  login page; when SSO lands on the module's own login; when JWT signature
  checks fail; when /staff/{module}/staff/{module}/... paths appear; or when
  registering cbp_modules rows with target_resolver staff_app_token.
---

# CBP Staff Portal SSO for modules

Wire a module so Staff Portal home / CBP Modules launch posts a short-lived HS256 JWT and the module opens already authenticated (no second login).

## When this applies

- New or existing apps under `/staff/{segment}/` (Finance, DevTrack, IT, Risk, Helpdesk, Registry, APM, …)
- External systems that should accept the same Staff Portal SSO token
- Debugging: tile greyed out, SSO → module login page, empty page, doubled mount path

## End-to-end flow

1. User is signed into Staff Portal.
2. Frontend `launchCbpModule(module_key)` POSTs `/api/v1/cbp-modules/launch`.
3. Portal `StaffSsoLaunch::prepareLaunch` builds claims and `SsoJwt::encode` with **shared secret**.
4. Browser POSTs `staff_sso_jwt` (+ optional `next`) to the module **accept URL**.
5. Module `SsoAcceptController` verifies JWT, creates its own API/session token, returns `sso-bridge` HTML.
6. Bridge stores the token in `sessionStorage` and `location.replace`s into the SPA.

Reference implementations: `modules/devtrack`, `modules/it`, `modules/helpdesk`, `modules/finance`, `modules/risk-register`.

## Checklist (new or broken module)

Copy and track:

```
SSO integrate:
- [ ] 1. cbp_modules row (enabled, staff_app_token, correct base_url)
- [ ] 2. Shared JWT secret on module matches Staff Portal
- [ ] 3. POST /sso/accept (CSRF-exempt) + SsoJwt decode
- [ ] 4. sso-bridge writes the SPA's real token key
- [ ] 5. Redirects are app-relative (no doubled mount path)
- [ ] 6. Accept URL registered in StaffSsoLaunch if special
- [ ] 7. Smoke: portal JWT decodes; accept returns bridge HTML 200
```

### 1. Register in `cbp_modules`

Staff Portal table `cbp_modules` (usually `staff_tracker`):

| Field | Typical value |
|-------|----------------|
| `module_key` | e.g. `devtrack`, `it`, `finance_management`, `risk_register` |
| `base_url` | path segment: `devtrack`, `it`, `finance`, `risk-register` |
| `target_resolver` | `staff_app_token` |
| `uses_staff_portal_token` | `1` |
| `is_enabled` | `1` |
| `permission_code` | unused free `permissions.id` (never reuse an existing id) |

Add to `CbpModulesAdminService::CORE_MODULES` + a small permission seeder when the module is first-party. Home catalog tiles with `moduleKey` stay grey until a live enabled row exists.

### 2. Shared JWT secret (most common break)

Staff Portal signs with:

```php
env('JWT_SECRET', env('APP_KEY', ''))
```

Module decode **must** use the same chain (DevTrack pattern):

```php
CBP_STAFF_JWT_SECRET → JWT_SECRET → APP_KEY
```

If the module only reads empty `CBP_STAFF_JWT_SECRET`, every launch falls through to the module login page.

On prod: set `CBP_STAFF_JWT_SECRET` (or `JWT_SECRET`) on the module to the Staff Portal / `/staff/.env` `JWT_SECRET`, then `php artisan config:clear`.

### 3. Accept endpoint

- **Route:** `POST /sso/accept` on the module (exclude CSRF / session middleware for this POST).
- **Body:** `staff_sso_jwt` (required), `next` (optional in-module path).
- **Verify:** HS256 JWT; require `staff_id`; honour `exp`.
- **Claims used:** `staff_id`, `name` / `fname`+`lname`, `email` / `work_email`, `role_id` / `role`, `permissions`, `division_id`, …
- **On success:** issue module API token (Sanctum or opaque cache token); return `sso-bridge` view.
- **On failure:** redirect to Staff Portal or a module access-error — do not silently show login without reason when the intent was SSO.

### 4. `sso-bridge` must match the SPA token key

| Module family | Typical `sessionStorage` key |
|---------------|------------------------------|
| DevTrack (email-server lineage) | `email_server.token` |
| Helpdesk | `helpdesk_api_token` |
| IT / Finance / Risk / Registry | `risk_register_api_token` (or module-specific) |

Wrong key ⇒ bridge “succeeds” but SPA still looks logged out.

Redirect URL for `window.location.replace` must be a **host-absolute** path or full URL built from `APP_URL` (e.g. `https://cbp.africacdc.org/staff/devtrack/`). Do not rely on relative paths under `<base href>`.

### 5. Never double the mount path

Laravel `redirect('/staff/devtrack/login')` when the app is already mounted at `/staff/devtrack` becomes `/staff/devtrack/staff/devtrack/login`.

- Laravel redirects: use **app-relative** paths only (`redirect('/login')`).
- JS / bridge: use full `config('app.url')` + optional `next`.

Optional safety route (DevTrack): map `/staff/{segment}/{any?}` → strip duplicate → redirect into the SPA.

### 6. Accept URL mapping

`StaffSsoLaunch::acceptUrlForModule` special-cases several segments to `…/backend/sso/accept`. Default for unknown `base_url` is:

`{STAFF_LEGACY_BASE_URL}/{base_url}/sso/accept`

| `base_url` | Accept URL |
|------------|------------|
| `apm` | `/staff/apm/sso/accept` |
| `finance` | `/staff/finance/backend/sso/accept` |
| `it` | `/staff/it/backend/sso/accept` |
| `helpdesk` | `/staff/helpdesk/backend/sso/accept` |
| `risk-register` | `/staff/risk-register/backend/sso/accept` |
| `registry` | `/staff/registry/backend/sso/accept` |
| `devtrack` (default) | `/staff/devtrack/sso/accept` |

New modules: either follow the default root `/sso/accept`, or add an explicit branch + keep Apache/`fix-public-script-name.php` in sync.

Ensure `STAFF_LEGACY_BASE_URL` on the portal matches the public host (not a leftover `http://localhost/staff/`).

### 7. Smoke tests

```bash
# Portal encodes; module decodes (same secret)
php artisan tinker   # staff-portal: SsoJwt::encode([...])
php artisan tinker   # module: SsoJwt::decode($jwt) → staff_id present

curl -sS -D- -o /tmp/sso.body -X POST "$ACCEPT_URL" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "staff_sso_jwt=$JWT"
# Expect HTTP 200 HTML with sessionStorage.setItem and location.replace to APP_URL
# Not 302 to .../login and not .../staff/{seg}/staff/{seg}/...
```

## External systems (outside this monorepo)

Same contract:

1. Portal (or a trusted broker) issues HS256 JWT with the shared secret and compact claims.
2. Browser or backend POSTs `staff_sso_jwt` to your accept URL (HTTPS, same-site or documented CORS).
3. You verify signature + `exp` + `staff_id`, mint your own session, never store the portal JWT as the long-lived API credential.
4. Register the accept URL in `cbp_modules` / `StaffSsoLaunch` (or call your accept URL from a custom launcher).

Minimal claim set: `staff_id`, `exp`, plus `email` or `work_email`.

## Do not

- Point `DB_DATABASE` / Staff lookup at the wrong DB while debugging SSO (orthogonal; fix secrets first).
- Reuse an existing `permissions.id` for a new `permission_code`.
- Use `redirect('/'+spaPath()+'/…')` under a subpath-mounted Laravel app.
- Ship SSO that only works when the module has its own username/password form filled in.

## More detail

- Templates and claim list: [reference.md](reference.md)
