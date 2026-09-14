# Africa CDC Staff Portal

Modern rewrite of the CodeIgniter 3 staff portal, living alongside the legacy app at `../application/` until cutover. Built with **Laravel 12**, **Livewire 4**, **Laravel Sanctum**, **nwidart/laravel-modules**, and a **Vue 3 SPA** — same layout pattern as Helpdesk (`backend/` + `frontend/`).

## Layout

```
staff-portal/
├── backend/                 # Laravel API + Livewire (Helpdesk-style)
│   ├── Modules/             # nwidart modules
│   ├── public/              # Laravel public (assets via cbp-assets → ../../../assets)
│   ├── server.php           # Apache front controller (no /public/ in URLs)
│   └── .htaccess
├── frontend/                # Vue 3 SPA (Atomic Design)
│   ├── src/
│   │   ├── components/
│   │   │   ├── atoms/
│   │   │   ├── molecules/
│   │   │   ├── organisms/
│   │   │   └── templates/
│   │   ├── pages/           # Route-level views
│   │   ├── composables/
│   │   ├── stores/
│   │   └── lib/
│   └── dist-build/          # Production SPA build
├── docker/                  # Optional Redis (+ MySQL) — same role as helpdesk/docker
├── deploy/                  # systemd units + worker scripts
├── scripts/                 # configure-env, install-systemd
├── setup.sh                 # Local/dev installer
├── setup-production.sh      # Production deploy / re-deploy
├── setup.env.example        # Copy to setup.env
├── package.json             # Orchestrates backend + frontend
└── .htaccess                # SPA + passthrough to backend/
```

## Requirements

- PHP 8.2+
- Composer 2.x
- MySQL 8+ (existing `staff` schema)
- Node.js 18+

## Quick start

### One-command setup (Helpdesk-style)

```bash
cd staff-portal
cp setup.env.example setup.env   # first run of ./setup.sh also creates this
# Edit DB_* / JWT_SECRET (or leave blank to inherit from ../.env)
./setup.sh                       # local/dev: composer, migrate, SPA build, optional systemd
```

**Production deploy / re-deploy after `git pull`:**

```bash
cd staff-portal
./setup-production.sh
# Options: --skip-migrate --skip-build --skip-systemd --skip-optimize
```

See [docs/SYSTEMD.md](docs/SYSTEMD.md) for queue/scheduler units.

### Manual

```bash
cd staff-portal
npm run install:all
cp backend/.env.example backend/.env   # set DB_* and JWT_SECRET to match parent staff/.env
cd backend && php artisan key:generate
ln -sfn ../../../assets public/cbp-assets   # if missing

php artisan migrate --force
php artisan module:migrate
cd ..
npm run dev:all          # Laravel :8081 + Vite :5175
```

Open SPA: `http://127.0.0.1:5175/`  
API (no `/public` in path): `http://localhost/staff/staff-portal/backend/`

## Optional Docker (Helpdesk-style)

Sidecar Redis (+ optional MySQL) for local queues/cache — same idea as `helpdesk/docker/`:

```bash
cd staff-portal/docker
docker compose up -d
# optional isolated MySQL on port 33070:
docker compose --profile bundled-mysql up -d
```

See [docker/README.md](docker/README.md). Production still uses host Apache/PHP; the repo-root `docker-compose.yml` covers Staff CI + APM.

## Environment

| Variable | Purpose |
|----------|---------|
| `DB_*` | Same as CI3 (`staff` database) |
| `JWT_SECRET` | **Must match** parent `.env` and APM/Helpdesk for SSO |
| `STAFF_PORTAL_BASE_URL` | Public API URL, e.g. `https://host/staff/staff-portal/backend/` |
| `APP_URL` | Same host path as backend (no `/public`) |
| `BASE_URL` | Legacy CI3 base (for module links during transition) |
| `STAFF_PORTAL_SPA_ENABLED` | `true` — Microsoft login + post-auth redirect use Vue SPA |
| `STAFF_PORTAL_SPA_URL` | Public SPA URL (e.g. `/staff/staff-portal/` or `http://localhost:5175/`) |

Frontend (Vite):

| Variable | Purpose |
|----------|---------|
| `VITE_STAFF_PORTAL_API_BASE_URL` | `/staff/staff-portal/backend` |
| `VITE_STAFF_PORTAL_BASE_PATH` | `/staff/staff-portal/` (prod SPA base) |

## Vue SPA (Atomic Design)

```bash
npm run install:all
npm run dev:all
cd frontend && npm run build   # → frontend/dist-build/
```

| Layer | Role |
|-------|------|
| **atoms** | Smallest UI (`StatusText`, …) |
| **molecules** | Composed atoms (`ModuleCard`, …) |
| **organisms** | Sections (`PortalTopHeader`, `PortalPrimaryNav`, `ModuleGrid`) |
| **templates** | Page shells (`PortalAppShell`) |
| **pages** | Route views (`HomePage`, `LoginPage`, …) |

Shared Helpdesk UI remains via Vite aliases (`@cbp/ui`, `@cbp/layout`, `@cbp/common`).

| Endpoint | Purpose |
|----------|---------|
| `POST /api/v1/auth/login` | Email/password → Sanctum token |
| `GET /api/v1/me` | Current user profile |
| `GET /api/v1/cbp-modules` | CBP module launcher data |
| `GET /auth/spa-bridge` | Post-Microsoft OAuth token hand-off to SPA |

## CI3 module → Laravel module map

See previous module map under `backend/Modules/`. High-traffic CI3 modules port here until cutover.

## Deployment

1. Deploy `staff-portal/` next to `application/` and `apm/`.
2. `cp setup.env.example setup.env` and set secrets (or inherit from `../.env`).
3. Run `./setup-production.sh` (composer --no-dev, migrate, SPA build, optimize, systemd).
4. Apache: root `staff-portal/.htaccess` serves SPA; `/backend` via `backend/.htaccess` + `server.php`.
5. Confirm `JWT_SECRET` matches APM / Helpdesk / Staff CI.
6. Azure redirect URI: `https://…/staff/staff-portal/backend/auth/microsoft/callback` (legacy `/public/` URLs 301 to `/backend/`).
7. If Microsoft login 500s after deploy: `cd backend && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o && php artisan config:clear`

## Related documentation

- [File storage & uploads](../docs/STORAGE.md)
- [CBP documentation hub](../documentation/README.md)
- [Staff portal security notes](../STAFF_PORTAL_SECURITY_README.txt)
