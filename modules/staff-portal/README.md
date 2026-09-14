# Africa CDC Staff Portal

Laravel 12 + Vue 3 staff portal for Africa CDC CBP. Lives at `modules/staff-portal/` in the monorepo. Public URLs (unchanged):

| URL | Serves |
|-----|--------|
| `/staff/` | Vue SPA (published `public-spa/`) |
| `/staff/backend/` | Laravel API (`backend/`) |
| `/staff/assets/…` | SPA hashed assets via `spa-static.php` |
| `/staff/share/…` | Share reference API (rewritten to Laravel) |

Root Apache `.htaccess` maps these into this directory — there is no root `backend` symlink and no CodeIgniter `application/` tree.

## Layout

```
modules/staff-portal/
├── backend/                 # Laravel 12 API (+ Livewire / nwidart modules)
│   ├── Modules/             # Auth, Share, Staff, Leave, Payroll, …
│   ├── public/              # Laravel public (optional cbp-assets → repo assets)
│   ├── server.php           # Apache front controller (no /public/ in URLs)
│   └── .htaccess
├── frontend/                # Vue 3 SPA (Atomic Design)
│   ├── src/
│   │   ├── components/{atoms,molecules,organisms,templates}/
│   │   ├── pages/
│   │   ├── composables/
│   │   ├── stores/
│   │   └── lib/
│   └── dist-build/          # Vite production output
├── public-spa/              # Published SPA (./scripts/publish-spa.sh)
├── spa-static.php           # Serves /staff/assets/* from public-spa
├── docker/                  # Optional Redis (+ MySQL) sidecar only
├── deploy/                  # systemd units + worker scripts
├── scripts/                 # publish-spa, configure-env, …
├── docs/                    # SYSTEMD, OAuth notes, design specs
├── setup.sh                 # Local/dev installer
├── setup-production.sh      # Production deploy / re-deploy
├── setup.env.example        # Copy to setup.env
└── package.json             # Orchestrates backend + frontend
```

## Requirements

- PHP 8.2+
- Composer 2.x
- MySQL 8+ (`staff` schema)
- Node.js 20+ (18+ may work)

## Quick start

From the **repository root** (`staff/`):

```bash
cd modules/staff-portal
cp setup.env.example setup.env   # first run of ./setup.sh also creates this
# Edit DB_* / JWT_SECRET (or leave blank to inherit from repo-root .env)
./setup.sh                       # composer, migrate, SPA build, optional systemd
```

**Production deploy / re-deploy after `git pull`:**

```bash
cd modules/staff-portal
./setup-production.sh
# Options: --skip-migrate --skip-build --skip-systemd --skip-optimize
```

See [docs/SYSTEMD.md](docs/SYSTEMD.md) for queue/scheduler units.

### Manual

```bash
cd modules/staff-portal
npm run install:all
cp backend/.env.example backend/.env   # set DB_* and JWT_SECRET
cd backend && php artisan key:generate
# optional: ln -sfn ../../../../assets public/cbp-assets

php artisan migrate --force
php artisan module:migrate
cd ..
npm run build:web
./scripts/publish-spa.sh
# Dev: npm run dev:all   # Laravel :8081 + Vite :5175
```

| Mode | URL |
|------|-----|
| Production (Apache) | SPA `http://localhost/staff/` · API `http://localhost/staff/backend/` |
| Vite dev | `http://127.0.0.1:5175/` |
| Artisan serve | `http://127.0.0.1:8081/` |

Health: `GET /staff/backend/up`

## Docker

**Preferred (full CBP stack):** use the **repo-root** Compose project (web + Redis, optional workers / bundled MySQL). See [../../docker/README.md](../../docker/README.md) and [../../docs/CI.md](../../docs/CI.md).

**Optional sidecar only** (Redis / MySQL for this app while using host Apache):

```bash
cd modules/staff-portal/docker
docker compose up -d
docker compose --profile bundled-mysql up -d   # optional
```

See [docker/README.md](docker/README.md).

## Environment

| Variable | Purpose |
|----------|---------|
| `DB_*` | MySQL `staff` database |
| `JWT_SECRET` | **Must match** APM / Helpdesk / Finance for SSO |
| `APP_URL` | Public API base, e.g. `https://host/staff/backend` |
| `STAFF_PORTAL_BASE_URL` | Same as API public URL (trailing slash OK) |
| `STAFF_PORTAL_SPA_ENABLED` | `true` — Microsoft login + post-auth redirect use Vue SPA |
| `STAFF_PORTAL_SPA_URL` | Public SPA URL (e.g. `/staff/` or `http://localhost:5175/`) |

Frontend (Vite):

| Variable | Purpose |
|----------|---------|
| `VITE_STAFF_PORTAL_API_BASE_URL` | `/staff/backend` |
| `VITE_STAFF_PORTAL_BASE_PATH` | `/staff/` (prod SPA base) |

## Vue SPA (Atomic Design)

```bash
cd modules/staff-portal
npm run install:all
npm run build:web              # → frontend/dist-build/
./scripts/publish-spa.sh       # → public-spa/ + index.html + assets/
```

| Layer | Role |
|-------|------|
| **atoms** | Smallest UI (`StatusText`, …) |
| **molecules** | Composed atoms (`ModuleCard`, …) |
| **organisms** | Sections (`PortalTopHeader`, `PortalPrimaryNav`, `ModuleGrid`) |
| **templates** | Page shells (`PortalAppShell`) |
| **pages** | Route views (`HomePage`, `LoginPage`, …) |

Shared Helpdesk UI remains via Vite aliases (`@cbp/ui`, `@cbp/layout`, `@cbp/common`).

### Key API endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST /api/v1/auth/login` | Email/password → Sanctum token |
| `GET /api/v1/me` | Current user profile |
| `GET /api/v1/cbp-modules` | CBP module launcher data |
| `GET /auth/spa-bridge` | Post-Microsoft OAuth token hand-off to SPA |
| Share API | See [Modules/Share/README.md](backend/Modules/Share/README.md) |

## Laravel modules (`backend/Modules/`)

Ported domains include: Auth, Share, Staff, Leave, Performance, Payroll, Contracts, Permissions, Settings, Jobs, Audit, Attendance, Dashboard, Reports, Tasks, Workflows, Workplan, Lookup, AdManager, Core.

## Deployment

1. App path on disk: `…/staff/modules/staff-portal/` (repo root remains the Apache `/staff` DocumentRoot / Alias target).
2. `cp setup.env.example setup.env` and set secrets (or inherit from repo-root `.env`).
3. Run `./setup-production.sh` (composer --no-dev, migrate, SPA build + publish, optimize, systemd).
4. Confirm root `.htaccess` rewrites `/staff/` and `/staff/backend` into this module.
5. Confirm `JWT_SECRET` matches APM / Helpdesk / Finance.
6. Azure redirect URI: `https://…/staff/backend/auth/microsoft/callback`.
7. If Microsoft login 500s after deploy: `cd backend && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o && php artisan config:clear`

## Related documentation

- [File storage & uploads](../../docs/STORAGE.md)
- [CBP CI](../../docs/CI.md)
- [CBP Docker](../../docker/README.md)
- [CBP documentation hub](../../documentation/README.md)
- [Share API](backend/Modules/Share/README.md)
- [OAuth / OIDC clients](docs/oauth-oidc-clients.md)
- [Systemd](docs/SYSTEMD.md)
