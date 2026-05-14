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

```bash
cd helpdesk
./setup.sh
# Terminal A
cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && composer run dev
# Terminal B
cd frontend && npm install --cache ./.npm-cache --legacy-peer-deps && npm run dev
```

Or use root orchestration:

```bash
cd helpdesk && npm run install:all && npm run dev:all
```

- API: `http://127.0.0.1:8000/api/v1/health`
- SPA (proxies `/api` → backend): `http://127.0.0.1:5174/`

## Composer cache (sandboxed environments)

If global Composer cache is not writable, this repo configures a **project-local** cache under `backend/.composer-cache` (see `backend/composer.json` → `config.cache-dir`).

## OpenAPI / Swagger

OpenAPI 3 outline lives in `documentation/openapi.yaml`. For generated Swagger UI, add `darkaonline/l5-swagger` when your Composer cache is writable, or use an external spec viewer against `openapi.yaml`.

## Compliance & roadmap

ISO **27001 / 20000 / 9001** controls, AI providers, WhatsApp, Teams, SLA engine, RTL locales, and exports are phased per `helpdesk-module.text` and `cursor.txt` — schema stubs and category seeder are in place; controllers and UI will follow the same module layout.
