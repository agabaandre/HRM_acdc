# Integration with Staff Portal & APM

## URLs (development)

| System | URL |
|--------|-----|
| CodeIgniter staff | `http://localhost/staff/` |
| APM (Laravel) | `http://localhost/staff/apm/` |
| APM system settings (branding) | `http://localhost/staff/apm/system-settings` |
| Helpdesk API | `http://127.0.0.1:8000` |
| Helpdesk SPA | `http://127.0.0.1:5174` |

Environment variables (see `backend/.env.example`):

- `HELPDESK_STAFF_PORTAL_URL`
- `HELPDESK_APM_BASE_URL`
- `HELPDESK_FRONTEND_URL`
- `HELPDESK_BRIDGE_SECRET` — shared **only** with trusted Staff/APM/Finance backends for `POST /api/v1/auth/exchange` (HMAC); never expose to browsers in production.
- `SANCTUM_STATEFUL_DOMAINS` — include the SPA origin for cookie auth if using Sanctum SPA guard.
- **Mail:** `MAIL_MAILER=failover`, `MAIL_FAILOVER_MAILERS` (e.g. `smtp,log`), plus `MAIL_*` for SMTP. Optional `EXCHANGE_*` keys mirror APM for Microsoft 365 / Graph when you add a Graph transport (see `apm/documentation/HELPDESK_INTEGRATION.md`).
- **Security / SSO:** `JWT_SECRET` — **must match** Staff root `.env` for `POST /api/v1/auth/staff-sso` (browser hand-off from `home/index`). `HELPDESK_SSO_PERMISSION_CODES` (comma list, default **85,92,93** — APM, Finance, Helpdesk-only) gates which Staff permission IDs may open the app. JWT payload email is usually **`work_email`**; the API resolves that automatically. `JWT_TTL`, `SESSION_SECRET` optional parity with other services.

## Authentication (implemented + production hardening)

1. User authenticates on **CI** or **APM** as today.
2. Staff portal opens Helpdesk SPA (new tab or embedded route after manual nav registration on `home/index`).
3. **Primary:** the SPA receives **`?token=`** (HS256 JWT from Staff, same as Finance). On load it calls **`POST /api/v1/auth/staff-sso`**; the API verifies **`JWT_SECRET`** (must match Staff root `.env`) and checks **`HELPDESK_SSO_PERMISSION_CODES`** (default **85, 92, 93**), then returns a **Sanctum** Bearer token.
4. **Optional:** server-only **`POST /api/v1/auth/exchange`** with HMAC `sig` and `HELPDESK_BRIDGE_SECRET` for CI/APM backends — never ship that secret to browsers.

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

The **IT Service Desk** tile is registered like Finance:

- **`cbp_modules`** row `helpdesk_itsm` (see `application/sql/create_cbp_modules_table.sql` and `Cbp_modules_mdl::default_rows()`). Uses resolver **`finance_host`**: local dev → `base_url_development` (`http://127.0.0.1:5174`), production → `base_url_production` path segment `helpdesk` on the same host unless overridden.
- **Legacy fallback** in `application/modules/home/controllers/Home.php` when the table is empty: users with permission **92** or **93** see the Helpdesk link (JWT appended).

Grant permission **93** in Staff RBAC for users who should see the Helpdesk card without Finance (**92**).

## Admin: directory sync job (Staff reference cache)

Admins can run **`POST /api/v1/admin/reference-sync`** (Bearer token) to clear and repopulate the same cached Staff Share responses used by `GET /api/v1/reference-data` and `GET /api/v1/reference-data/staff` (see § Staff / Directorate / Division APIs). This does **not** modify Staff data.

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
