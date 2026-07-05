# Integration with Staff Portal & APM

> Related reading from the APM side: [`apm/documentation/HELPDESK_INTEGRATION.md`](../../apm/documentation/HELPDESK_INTEGRATION.md) (Sanctum bridge, `HELPDESK_*` URLs, mail failover / Exchange OAuth alignment).
> For an end-to-end overview of this module see the [Helpdesk documentation hub](./README.md) — [User Guide](./USER_GUIDE.md) · [Developer Guide](./DEVELOPER_GUIDE.md) · [Architecture](./ARCHITECTURE.md).

## URLs (Apache layout)

| System | URL |
|--------|-----|
| CodeIgniter staff | `http://localhost/staff/` |
| Helpdesk SPA | `http://localhost/staff/helpdesk/` |
| Helpdesk API | `http://localhost/staff/helpdesk/backend/api/v1/*` |
| APM (Laravel) | `http://localhost/staff/apm/` |
| Finance (Laravel) | `http://localhost/staff/finance/` |

Use **`localhost`** (or your production hostname) consistently in every `.env`. Do **not** mix in macOS mDNS hostnames such as `Users-MacBook-Pro.local` — they break CBP **Home** links and SSO redirects when the browser is on `localhost`.

Environment variables (see `backend/.env.example` and `setup.env.example`):

- `HELPDESK_STAFF_PORTAL_URL` — Staff portal base (`http://localhost/staff` locally)
- `HELPDESK_APM_BASE_URL` — APM mount (`http://localhost/staff/apm`)
- `HELPDESK_FRONTEND_URL` — Helpdesk SPA (`http://localhost/staff/helpdesk`)
- `HELPDESK_BRIDGE_SECRET` — shared **only** with trusted Staff/APM/Finance backends for `POST /api/v1/auth/exchange` (HMAC); never expose to browsers in production.
- `SANCTUM_STATEFUL_DOMAINS` — include the SPA origin for cookie auth if using Sanctum SPA guard.
- **Mail:** `MAIL_MAILER=failover`, `MAIL_FAILOVER_MAILERS` (e.g. `smtp,log`), plus `MAIL_*` for SMTP. Optional `EXCHANGE_*` keys mirror APM for Microsoft 365 / Graph when you add a Graph transport (see `apm/documentation/HELPDESK_INTEGRATION.md`).
- **Security / SSO:** `JWT_SECRET` — **must match** Staff root `.env` for all module SSO accept endpoints and `POST /api/v1/auth/staff-sso`. `HELPDESK_SSO_PERMISSION_CODES` (comma list, default **85,92,93** — APM, Finance, Helpdesk-only) gates which Staff permission IDs may open Helpdesk. JWT payload email is usually **`work_email`**; the API resolves that automatically. `JWT_TTL`, `SESSION_SECRET` optional parity with other services.

Frontend build (Vite):

- `VITE_STAFF_PORTAL_HOME_URL=/staff/home/index` — **relative** path preferred; the SPA resolves the current browser host on localhost.
- `VITE_STAFF_BASE_URL=/staff`
- `VITE_HELPDESK_API_BASE_URL=/staff/helpdesk/backend`

## Cross-module SSO (Staff → Helpdesk / APM / Finance)

### Primary flow: secure POST launch

JWTs are **not** placed in browser URLs for module hand-off. Instead:

1. User clicks a CBP module tile on Staff **`home/index`** or a module link in the **CBP Modules** dropdown (Helpdesk, APM, Finance headers).
2. Browser **POST**s to Staff `home/launch_module` with `module_key` (e.g. `helpdesk_itsm`, `approvals_management`, `finance_management`).
3. Staff builds a compact HS256 JWT (`staff_sso_build_jwt(staff_sso_compact_claims($session))`) and renders `sso_launch_redirect`, which **auto-POST**s `staff_sso_jwt` to the target module’s accept URL.
4. The module verifies the JWT, starts a session (Laravel modules), and redirects to the module home.

Shared helper: `application/helpers/sso_launch_helper.php`.

| Module | Accept URL | Entry file |
|--------|------------|------------|
| Helpdesk | `/staff/helpdesk/backend/sso/accept` | `helpdesk/backend/sso_accept_dispatch.php` |
| APM | `/staff/apm/sso/accept` | `apm/sso_accept_dispatch.php` |
| Finance | `/staff/finance/sso/accept` | `finance/sso_accept_dispatch.php` |

Accept URLs are allowlisted to the **same host** as the Staff portal request (`staff_sso_is_allowed_accept_url`).

### Helpdesk-specific SSO steps

After the POST accept succeeds:

1. `SsoAcceptController` verifies the JWT and stores a short-lived bridge payload.
2. Browser loads the SSO bridge view, which calls **`POST /api/v1/auth/staff-sso`**.
3. `StaffSsoController` checks **`HELPDESK_SSO_PERMISSION_CODES`**, upserts `User` + `HelpdeskProfile`, returns a **Sanctum** Bearer token.
4. The SPA stores the token and strips any legacy query params from the address bar.

### Legacy URL token (dev only)

`?token=<jwt>` on `/staff/helpdesk/` still works when `SSO_ALLOW_URL_TOKEN` is enabled (default in non-production). Production should rely on POST SSO only.

### Optional server bridge

`POST /api/v1/auth/exchange` with HMAC `sig` and `HELPDESK_BRIDGE_SECRET` for CI/APM backends — **never** ship that secret to browsers.

## CBP Modules navigation (Helpdesk header)

The Helpdesk SPA loads **`GET /api/v1/cbp-modules`** for the top **CBP Modules** dropdown (Home, APM, Finance, …).

- **Source:** Staff Share API when configured; otherwise `CbpModulesNavService::fallbackPayload()` using `HELPDESK_STAFF_PORTAL_URL`.
- **Caching:** `helpdesk_cbp_modules_v4_{staff_id}_{perm_hash}` (TTL from `HELPDESK_REFERENCE_DATA_CACHE_TTL`, default 300s).
- **Localhost rewrite:** When the API request host is `localhost` / `127.0.0.1`, `CbpModulesNavService` rewrites `home.href` and module URLs to the current origin so stale cached hostnames cannot redirect to `*.local`.

Client-side fallback: `frontend/src/lib/sso.ts` (`staffPortalHomeUrl`, `staffPortalBaseUrl`) uses the browser host on localhost even when env vars are relative paths.

## Staff / Directorate / Division APIs

Consume existing internal APIs to sync `helpdesk_profiles` (`staff_id`, `directorate_id`, `division_id`, …) per URS §5.

### Agent requester picker (implemented)

The Helpdesk API proxies the **same CodeIgniter Share endpoints** used by APM sync (`staff:sync`, `divisions:sync`, `directorates:sync`):

- URL shape: `{base_url}{endpoint}/{token}` with **HTTP Basic Auth** (username/password). `config/helpdesk.php` resolves **`HELPDESK_STAFF_API_*` first**, then falls back to the same env keys as APM: **`BASE_URL`** (or **`STAFF_API_BASE_URL`**), **`STAFF_API_TOKEN`**, **`STAFF_API_USERNAME`**, **`STAFF_API_PASSWORD`** — copy those lines from `apm/.env` into Helpdesk `backend/.env` so directory sync matches `staff:sync` / `divisions:sync` / `directorates:sync`.
- **Credentials:** `STAFF_API_USERNAME` is the Staff portal **user login email** (the same account APM uses for Share API Basic Auth — validated in `application/modules/share/controllers/Share.php` → `api_login()`). It is **not** an arbitrary label. `STAFF_API_PASSWORD` must be that user’s **current** password. If APM sync fails with the same values, fix Staff/APM first; placeholder emails like `api.user@example.org` will return **401 Invalid credentials**.
- JSON shapes are normalised to match APM `GET /api/apm/v1/reference-data` lists (`id`, `name`, `directorate_id` on divisions; `id`, `name` on directorates; staff `id` = `staff_id`, `name`, `work_email`, `division_id`, enriched `directorate_id`).
- **Caching:** `HELPDESK_REFERENCE_CACHE_TTL` (default **300** seconds) applies to bundled divisions/directorates and to the staff list fetch (same ballpark as short-lived APM reference usage; APM DB sync remains on its own schedule).

Endpoints:

- `GET /api/v1/reference-data` (Sanctum; agents+) — divisions + directorates  
- `GET /api/v1/reference-data/staff` (Sanctum; agents+) — query: `directorate_id`, `division_id`, `q` (client-side filter after normalise; debounced in SPA)

## Home dashboard card (Staff)

The **IT Service Desk** tile is registered in **`cbp_modules`** as `helpdesk_itsm` (see `application/sql/create_cbp_modules_table.sql` and `Cbp_modules_mdl::default_rows()`). Resolver **`external_microservice`**: local dev → `base_url_development` (`http://localhost/staff/helpdesk`), production → `base_url_production` on the same host.

Launch uses **`home/launch_module`** (POST SSO) when `uses_staff_portal_token = 1`.

Grant permission **93** in Staff RBAC for users who should see the Helpdesk card without Finance (**92**).

## Admin: directory sync job (Staff reference cache)

Admins can run **`POST /api/v1/admin/reference-sync`** (Bearer token) to clear and repopulate the same cached Staff Share responses used by `GET /api/v1/reference-data` and `GET /api/v1/reference-data/staff` (see § Staff / Directorate / Division APIs). This does **not** modify Staff data.

Clear CBP modules cache after URL changes: `php artisan cache:clear` (or wait for TTL).

## Webhooks (WhatsApp Cloud API & Microsoft Teams)

Public endpoints (no Sanctum cookie; verify tokens / signatures per vendor docs):

| Channel | Method | Path | Notes |
|---------|--------|------|--------|
| WhatsApp | `GET` | `/api/v1/webhooks/whatsapp` | Meta [webhook verification](https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components) (`hub.mode`, `hub.verify_token`, `hub.challenge`). |
| WhatsApp | `POST` | `/api/v1/webhooks/whatsapp` | Inbound payloads; ticket creation from messages is wired in a follow-up iteration. |
| Teams | `POST` | `/api/v1/webhooks/teams/activities` | Placeholder for [Azure Bot Service](https://learn.microsoft.com/en-us/azure/bot-service/bot-service-overview-introduction) messaging endpoint. |

Configure secrets and IDs under **Settings → WhatsApp & Teams** in the SPA (`PUT /api/v1/admin/settings`).

## Audit trail & ISO-oriented logging

- **Database:** append-only rows in `helpdesk_audit_logs` (actor, action, IP, user agent, optional `correlation_id`, JSON `new_values` including `@timestamp` in UTC).
- **Files:** optional Monolog channel **`iso_json`** writes JSON Lines to `storage/logs/helpdesk-iso.jsonl` when `LOG_STACK` includes `iso_json` (see `config/logging.php`). Aligns with structured logging practices useful for **ISO/IEC 27001** and **ISO/IEC 27014** governance evidence.

Each API response includes an **`X-Correlation-ID`** header (client may also send one) for request tracing across logs and audits.

## Troubleshooting SSO & URLs

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| CBP **Home** opens `Users-MacBook-Pro.local` | Stale `HELPDESK_STAFF_PORTAL_URL`, cached config, or old Vite build | Set URLs to `localhost`, `php artisan cache:clear`, rebuild SPA |
| `helpdesk_error=sso&reason=unauthorized` | JWT secret mismatch, expired JWT, or missing permission | Align `JWT_SECRET` with Staff root `.env`; check permission 92/93 |
| Redirect to `/staff/home/index` after SSO | Module session cookie not set | Ensure accept dispatch runs Laravel `StartSession` middleware (APM/Finance pattern) |
| `npm run build` EACCES on `dist/` | Root-owned assets from a prior deploy | `sudo chown -R $(whoami) helpdesk/frontend/dist-build` or build fresh (output is `dist-build/`) |
