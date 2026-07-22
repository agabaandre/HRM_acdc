# Africa CDC Service Desk & ITSM

Enterprise helpdesk platform (**Laravel 11** JSON API + **Vue 3.5** SPA), structured like the [`finance/`](../finance) module: separate `backend/` and `frontend/` trees plus `documentation/` and optional `docker/`.

| Area | Path | Notes |
|------|------|--------|
| API | `backend/` | Laravel 11, Sanctum, Predis, `/api/v1/*` |
| SPA | `frontend/` | Vue 3.5.34, Vite 8, Pinia, Vue Router, Axios |
| Spec | `../helpdesk-module.text` | Full URS |
| Brief | `../cursor.txt` | Architecture checklist |

**Staff portal:** `http://localhost/staff/` · **APM settings (colours):** `http://localhost/staff/apm/system-settings`

A **Helpdesk** tab on `home/index` will be registered manually in the CodeIgniter app (same pattern as other modules).

## Quick start

Both the **Laravel API** and the built **Vue SPA** are served by **Apache** — same pattern as APM (`apm/server.php` + `apm/.htaccess`). No `php artisan serve` and no `:5174` Vite URL are required for end users.

| What | URL | Served from |
|------|-----|-------------|
| SPA (full-page) | `http://<host>/staff/helpdesk/` | `helpdesk/frontend/dist-build/` via `helpdesk/.htaccess` |
| Static assets | `http://<host>/staff/helpdesk/assets/*` | `helpdesk/frontend/dist-build/assets/*` |
| Laravel API | `http://<host>/staff/helpdesk/backend/api/v1/*` | `helpdesk/backend/public/index.php` via `helpdesk/backend/.htaccess` |

```bash
cd helpdesk
cp setup.env.example setup.env   # first time: set DB_* and JWT_SECRET
./setup.sh
```

`setup.sh` writes `backend/.env` from `setup.env` (MySQL, URLs, JWT), runs migrations, builds the SPA, and on **Linux** installs **systemd** units (queue + scheduler) when `INSTALL_SYSTEMD=auto`.

**Production (beside running Staff):**

```bash
cd helpdesk
cp setup.env.example setup.env   # set DB_DATABASE; leave URLs as localhost or blank
./setup-production.sh
```

`setup-production.sh` is the one-command production installer: production Laravel flags, `composer install --no-dev`, migrations, category seed only (no demo admin), Vite build, permissions, systemd, and smoke tests. On production it **auto-fills public URLs** from `../.env` (`PRODUCTION_URL`, `CI_BASE_URL`, or `BASE_URL`) or the server hostname (`https://your-server/staff/helpdesk/...`). MySQL credentials copy from Staff `DB_HOST` / `DB_USER` / `DB_PASS` when `DB_PASSWORD` is left blank in `setup.env`. Re-run after `git pull` to redeploy.

Smoke-test:

```bash
curl -i http://localhost/staff/helpdesk/                    # SPA index.html
curl -i http://localhost/staff/helpdesk/backend/api/v1/health  # Laravel API
```

The Staff portal **CBP Modules** menu and home tiles launch Helpdesk via **secure POST SSO** (see [Cross-module SSO](#cross-module-sso-staff--apm--finance--helpdesk) below). Legacy `?token=` in the URL still works in non-production when `SSO_ALLOW_URL_TOKEN` is enabled.

### Dev with hot-reload (optional)

When iterating on the Vue code, run Vite for HMR alongside Apache:

```bash
cd helpdesk/frontend && npm run dev   # serves http://localhost:5174 with HMR
```

Vite's `/api` proxy targets `http://localhost/staff/helpdesk/backend` by default (see `frontend/.env.development`). Override per-machine via `VITE_HELPDESK_API_PROXY_TARGET` in `frontend/.env.local`, e.g.:

```env
VITE_HELPDESK_API_PROXY_TARGET=http://localhost:8080/staff/helpdesk/backend
```

For end-user traffic, however, the Apache-served `/staff/helpdesk/` is the canonical URL — `Home.php` no longer points at `127.0.0.1:5174`.

### How the Apache routing works

| Component | File | Purpose |
|-----------|------|---------|
| SPA rewrite | `helpdesk/.htaccess` | Serves `frontend/dist-build/<file>` for assets, `frontend/dist-build/index.html` for SPA routes, and leaves `/staff/helpdesk/backend/*` to the API rewrite. |
| API rewrite | `helpdesk/backend/.htaccess` | Routes every URL under `/staff/helpdesk/backend/` through `server.php`; preserves the `Authorization` header so Sanctum Bearer tokens reach PHP — must be the first rule in the rewrite block. |
| API front controller | `helpdesk/backend/server.php` | Forwards to `backend/public/index.php` — copied from `apm/server.php`. |
| `/public/`-less entry | `helpdesk/backend/index.php` | Fallback used when mod_rewrite is unavailable (e.g. the PHP built-in server). |
| SPA base path | `helpdesk/frontend/vite.config.ts` `base` | `/staff/helpdesk/` in production builds so Vue Router + asset URLs work under the subpath. |
| API base URL | `helpdesk/frontend/.env.production` `VITE_HELPDESK_API_BASE_URL` | `/staff/helpdesk/backend` so axios calls resolve to the Apache-served API on the same host. |
| Laravel APP_URL | `helpdesk/backend/.env` `APP_URL` | `http://localhost/staff/helpdesk/backend` locally; `https://<host>/staff/helpdesk/backend` in prod. |
| Portal hand-off | `application/modules/home/controllers/Home.php` | `POST home/launch_module` → auto-POST JWT to `<module>/sso/accept` (see [INTEGRATION.md](./documentation/INTEGRATION.md)). |

## Cross-module SSO (Staff ↔ APM ↔ Finance ↔ Helpdesk)

CBP modules share a **POST-based SSO hand-off** so JWTs never appear in browser URLs (Referer/history safe):

```
Staff home  ──POST home/launch_module──►  sso_launch_redirect view
           ──auto-POST staff_sso_jwt──►   /staff/{module}/sso/accept
Module      ──verify JWT, start session──► redirect to module home
```

| Module | Accept endpoint | Session notes |
|--------|-----------------|---------------|
| Helpdesk | `/staff/helpdesk/backend/sso/accept` | `sso_accept_dispatch.php` → `SsoAcceptController` |
| APM | `/staff/apm/sso/accept` | Dispatch runs Laravel session middleware so the cookie persists |
| Finance | `/staff/finance/sso/accept` | Same session middleware pattern as APM |

Shared implementation: `application/helpers/sso_launch_helper.php` (`staff_sso_build_jwt`, `staff_sso_compact_claims`, `staff_sso_accept_url_for_module`).

### SSO session refresh (while working in a module)

After the initial POST launch, sibling modules stay logged in by refreshing the Staff portal JWT in the background (same-site cookie required):

```
Module tab  ──GET /auth/refresh_sso_session──►  Staff CI (session cookie)
           ◄── fresh JWT ──
           ──cbp:sso-refreshed──►  module handler updates local session
```

| Module | Refresh handler |
|--------|-----------------|
| APM | `POST /staff/apm/sso/refresh` with `{ sso_token }` |
| Helpdesk | `POST /api/v1/auth/staff-sso` → new Sanctum token (`helpdesk/client/src/lib/sessionRefresh.ts`) |
| Finance | Can register the same handler pattern via `cbp-session-refresh.js` |

Client: `assets/js/cbp-session-refresh.js` (polls every ~15 minutes, or ~20 minutes before JWT expiry).

Server: `GET /auth/refresh_sso_session`, `GET /share/validate_session`, `POST /share/refresh_token`.

### URL configuration (local dev)

Use **`http://localhost/staff`** everywhere — not your Mac’s mDNS hostname (`Users-MacBook-Pro.local`). Mismatched hosts break CBP **Home** links and SSO redirects.

| Setting | File | Local value |
|---------|------|-------------|
| `HELPDESK_STAFF_PORTAL_URL` | `backend/.env` | `http://localhost/staff` |
| `APP_URL` / `BASE_URL` | `backend/.env` | `http://localhost/staff/helpdesk/backend` / `http://localhost/staff/` |
| `VITE_STAFF_PORTAL_HOME_URL` | `frontend/.env.production.local` | `/staff/home/index` (relative — browser host is used) |
| `VITE_STAFF_BASE_URL` | `frontend/.env.production.local` | `/staff` |

After changing env values, rebuild the SPA (`cd frontend && npm run build`) and clear Laravel caches (`php artisan cache:clear`; delete `bootstrap/cache/config.php` if present).

### File storage (production)

Ticket attachments and rich-text images use Laravel’s `public` disk. Staff portrait files are read from the shared CI uploads tree (`STAFF_PORTAL_UPLOADS_ROOT`).

See [File storage & uploads](../docs/STORAGE.md) for host-side paths, migration scripts, and `.env` variables (`STAFF_HELPDESK_FILES_ROOT`, `HELPDESK_STAFF_UPLOADS_ROOT`).

The CBP Modules API (`GET /api/v1/cbp-modules`) rewrites staff-portal URLs to the current request host when accessed on `localhost`, so cached Staff Share responses cannot send you to the wrong hostname.

## Production: systemd (boot + auto-restart)

`./setup.sh` installs systemd on Linux automatically (`INSTALL_SYSTEMD=auto` in `setup.env`). Manual install: [`documentation/SYSTEMD.md`](./documentation/SYSTEMD.md) or `sudo ./deploy/systemd/install.sh`.

## Composer cache (sandboxed environments)

If global Composer cache is not writable, this repo configures a **project-local** cache under `backend/.composer-cache` (see `backend/composer.json` → `config.cache-dir`).

## OpenAPI / Swagger

OpenAPI 3 outline lives in `documentation/openapi.yaml`. For generated Swagger UI, add `darkaonline/l5-swagger` when your Composer cache is writable, or use an external spec viewer against `openapi.yaml`.

## Compliance & features

| Area | Status |
|------|--------|
| Ticket lifecycle, comments, reopen-via-comment, agent email | Live |
| Support groups & category routing | Live |
| Agent desk KPI filters, reports, Excel export | Live |
| Knowledge base + APM FAQ ingest | Live |
| Branded email (Africa CDC Service Desk template) | Live |
| Signed attachment downloads | Live |
| AI provider (OpenAI / Gemini / custom) + optional agent assignment | Live |
| WhatsApp & Teams webhook registration | Credentials + verify; ticket creation phased |
| Public TV dashboard (`/screen`) | Live |
| Security tests (IDOR, XSS sanitization, signed URLs) | `tests/Feature/SecurityApiTest.php` |
| ISO JSON logging (`LOG_STACK=iso_json`) | Optional |

Phased per `helpdesk-module.text` and `cursor.txt`: full WhatsApp/Teams ticket creation, RTL locales, advanced SLA automation.

## Documentation

| Guide | Path |
|-------|------|
| **User guide** (requesters & agents) | [`documentation/USER_GUIDE.md`](./documentation/USER_GUIDE.md) |
| **Administrator guide** (AI, WhatsApp, Teams, env) | [`documentation/ADMIN_GUIDE.md`](./documentation/ADMIN_GUIDE.md) |
| **Developer guide** | [`documentation/DEVELOPER_GUIDE.md`](./documentation/DEVELOPER_GUIDE.md) |
| **Doc index + screenshots** | [`documentation/README.md`](./documentation/README.md) |

Regenerate UI screenshots: `npm run docs:screenshots` (see [`documentation/screenshots/README.md`](./documentation/screenshots/README.md)).
