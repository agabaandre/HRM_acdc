# Africa CDC Helpdesk & ITSM

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
| SPA (full-page) | `http://<host>/staff/helpdesk/` | `helpdesk/frontend/dist/` via `helpdesk/.htaccess` |
| Static assets | `http://<host>/staff/helpdesk/assets/*` | `helpdesk/frontend/dist/assets/*` |
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

The Staff portal helpdesk tile (`home/index`) now links straight to `<host>/staff/helpdesk?token=…`.

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
| SPA rewrite | `helpdesk/.htaccess` | Serves `frontend/dist/<file>` for assets, `frontend/dist/index.html` for SPA routes, and leaves `/staff/helpdesk/backend/*` to the API rewrite. |
| API rewrite | `helpdesk/backend/.htaccess` | Routes every URL under `/staff/helpdesk/backend/` through `server.php`; preserves the `Authorization` header so Sanctum Bearer tokens reach PHP — must be the first rule in the rewrite block. |
| API front controller | `helpdesk/backend/server.php` | Forwards to `backend/public/index.php` — copied from `apm/server.php`. |
| `/public/`-less entry | `helpdesk/backend/index.php` | Fallback used when mod_rewrite is unavailable (e.g. the PHP built-in server). |
| SPA base path | `helpdesk/frontend/vite.config.ts` `base` | `/staff/helpdesk/` in production builds so Vue Router + asset URLs work under the subpath. |
| API base URL | `helpdesk/frontend/.env.production` `VITE_HELPDESK_API_BASE_URL` | `/staff/helpdesk/backend` so axios calls resolve to the Apache-served API on the same host. |
| Laravel APP_URL | `helpdesk/backend/.env` `APP_URL` | `http://localhost/staff/helpdesk/backend` locally; `https://<host>/staff/helpdesk/backend` in prod. |
| Portal hand-off | `application/modules/home/controllers/Home.php` | Builds `<host>/staff/helpdesk?token=…` (override the subpath via `HELPDESK_SPA_PATH` env). |

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
| Branded email (Africa CDC Helpdesk template) | Live |
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
