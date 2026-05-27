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
./setup.sh
# Backend (one-off): install deps + configure env + run migrations
cd backend \
  && composer install \
  && cp .env.example .env \
  && php artisan key:generate \
  && php artisan migrate --seed
# Frontend: install + build the SPA Apache serves at /staff/helpdesk/
cd ../frontend \
  && npm install --cache ./.npm-cache --legacy-peer-deps \
  && npm run build
```

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

## Composer cache (sandboxed environments)

If global Composer cache is not writable, this repo configures a **project-local** cache under `backend/.composer-cache` (see `backend/composer.json` → `config.cache-dir`).

## OpenAPI / Swagger

OpenAPI 3 outline lives in `documentation/openapi.yaml`. For generated Swagger UI, add `darkaonline/l5-swagger` when your Composer cache is writable, or use an external spec viewer against `openapi.yaml`.

## Compliance & roadmap

ISO **27001 / 20000 / 9001** controls, AI providers, WhatsApp, Teams, SLA engine, RTL locales, and exports are phased per `helpdesk-module.text` and `cursor.txt` — schema stubs and category seeder are in place; controllers and UI will follow the same module layout.
