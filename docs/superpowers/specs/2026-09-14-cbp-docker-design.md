# CBP Docker stack (modules/ layout)

**Date:** 2026-09-14  
**Status:** Approved for planning  
**Scope:** Local development + production-ready option for Africa CDC CBP (staff-portal, APM, finance, helpdesk)

## Goals

1. Run the full CBP stack under Docker with the same public URL shape as production: `/staff/`, `/staff/backend`, `/staff/apm`, `/staff/finance`, `/staff/helpdesk`.
2. Prefer **external MySQL** by default (host or managed); offer **bundled MySQL** for greenfield setups.
3. Always run **Redis in Docker**.
4. Prefer **simplicity + adequate performance**: one Apache/PHP web container; optional queue workers.
5. Support a **production profile/docs** (same images, no bind-mount of source, workers on, external DB).

## Non-goals

- Merging all apps into one Laravel codebase or one shared `vendor/`.
- Splitting each CBP app into its own HTTP container behind a reverse proxy (rejected for complexity).
- Replacing host Homebrew Apache for developers who prefer it (Docker is additive).
- Upgrading Helpdesk Laravel 11 → 12 as part of this work.

## Decisions

| Topic | Choice |
|-------|--------|
| Topology | One `web` container + optional workers |
| Redis | Always on (`redis` service) |
| MySQL | External by default; `bundled-db` profile for internal MySQL |
| URL routing | Repo root DocumentRoot + existing root `.htaccess` → `modules/…` |
| Local code | Bind-mount repo into `web` |
| Production option | Same image; no source bind-mount (or RO mount); `workers` on; external DB |
| PHP | 8.2 Apache image (parity with current APM/Helpdesk local stack) |

## Target compose services

| Service | Profile | Role |
|---------|---------|------|
| `web` | default | Apache + PHP; DocumentRoot = staff repo root |
| `redis` | default | Cache / queues / sessions as configured per app |
| `queue-apm` | `workers` | `php artisan queue:work` in `modules/apm` |
| `queue-helpdesk` | `workers` | `php artisan queue:work` in `modules/helpdesk/backend` |
| `mysql` | `bundled-db` | MySQL 8 for new setups only |

### Example commands

```bash
# Local default: web + redis, DB on host
cp docker/compose.env.example docker/.env   # compose vars; do not overwrite repo-root .env
docker compose --env-file docker/.env up -d --build

# With queue workers
docker compose --profile workers up -d --build

# Greenfield: bundled MySQL (+ set DB_HOST=mysql in env)
docker compose --profile bundled-db up -d --build

# Production-ish: workers, no bind-mount (via compose.prod.yml override)
docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile workers up -d --build
```

**Local URL:** `http://localhost:${APP_PORT:-8080}/staff/`

## Image (`docker/Dockerfile`)

Update the existing image (currently CI+APM era) for the modules layout:

- Base: `php:8.2-apache-bookworm` (or 8.3 if we later standardize; start with 8.2).
- Extensions: `pdo_mysql`, `mysqli`, `redis`, `gd`, `intl`, `zip`, `bcmath`, `pcntl`, `exif`, `opcache`.
- Keep APM PDF annex tools: Ghostscript, Poppler (`pdftoppm`), LibreOffice Writer + fonts.
- Composer 2 in image.
- Entrypoint: wait for Redis; ensure storage/bootstrap cache dirs writable; then `apache2-foreground`.
- Workers reuse the **same image** with a different command.

## Apache config

Replace CI-era aliases with modules-aware config:

- `DocumentRoot /var/www/html` (staff repo root).
- `AllowOverride All` so root `.htaccess` maps:
  - `/staff/` SPA, `/staff/backend` → `modules/staff-portal/backend`
  - `/staff/apm|finance|helpdesk` → `modules/…`
- Do **not** rely on outdated `Alias /apm → apm/public` (path no longer exists at repo root).
- Logs to stdout/stderr for `docker compose logs`.

If the container DocumentRoot is the staff repo itself (not parent `/var/www` with `/staff` subdir), Apache vhost path prefix may be `/` inside the container while apps expect `/staff` in `APP_URL`. **Decision:** keep apps’ `APP_URL` / `BASE_URL` as `http://localhost:8080/staff/...` and either:

- **A (preferred):** mount/serve so request URI includes `/staff` (e.g. DocumentRoot parent + Alias `/staff` → `/var/www/html`), **or**
- **B:** DocumentRoot = repo and set `APP_URL` without `/staff` only inside Docker (diverges from prod).

**Preferred:** Option A — `DocumentRoot /var/www` with staff repo at `/var/www/staff`, `Alias` or directory `/staff`, so URLs stay `/staff/...` identical to production and current local Homebrew.

## Networking & data

### Default (external MySQL)

| Variable | Typical value |
|----------|----------------|
| `DB_HOST` / app `DB_HOST` | `host.docker.internal` |
| `REDIS_HOST` | `redis` (compose service name) |
| `REDIS_PORT` | `6379` |

`extra_hosts: host.docker.internal:host-gateway` for Linux.

### Profile `bundled-db`

- Service `mysql:8.0` with init scripts creating schemas used by CBP (staff, apm, finance, helpdesk as needed).
- Set `DB_HOST=mysql` in compose env and document copying into each module `.env`.
- Persist `mysql_data` volume.

### Redis (always)

- Image `redis:7-alpine`.
- Persist optional `redis_data` volume (recommended for local queues).
- All module `.env` examples: `REDIS_HOST=redis`, `CACHE_STORE=redis` / `QUEUE_CONNECTION=redis` where already used.

## Environment files

- `docker/compose.env.example` → root `.env` for Compose (`APP_PORT`, `DB_*`, `COMPOSE_PROJECT_NAME`, MySQL profile vars).
- Module envs remain authoritative for Laravel:
  - `modules/staff-portal/backend/.env`
  - `modules/apm/.env`
  - `modules/finance/.env`
  - `modules/helpdesk/backend/.env`
- Entrypoint or README checklist: Share API bases must be reachable **from inside the container**:
  - Prefer `http://web/staff/backend` (compose service DNS) for APM/Helpdesk/Finance → staff-portal Share calls.
  - Host-side browsers still use `http://localhost:8080/staff/...`.
- Compose may use `docker/compose.env.example` copied to `docker/.env` (or `docker compose --env-file`) so it does **not** overwrite the existing repo-root `.env` used by host tooling.

## Local vs production

| Concern | Local (default compose) | Production option |
|---------|-------------------------|-------------------|
| Code | Bind-mount `.:/var/www/staff` | **Preferred:** bake app tree into the image via `COPY` in a prod stage / build arg. **Alt:** host checkout + read-only mount for VM deploys |
| Compose | `docker-compose.yml` | + `docker-compose.prod.yml` (restart policies, no bind-mount, resource limits) |
| DB | External host/managed | External only (no `bundled-db`) |
| Redis | Compose `redis` | Compose `redis` by default; override `REDIS_HOST` only if using managed Redis |
| Workers | Optional `--profile workers` | Recommended `--profile workers` (APM + Helpdesk only; no Finance worker unless later required) |
| SPA assets | Bind-mount includes pre-built SPA under staff-portal public; rebuild on host as today | Build/publish SPA in CI **before** image bake |

## Files to change / add

| Path | Action |
|------|--------|
| `docker-compose.yml` | Rewrite for modules + redis + profiles |
| `docker-compose.prod.yml` | New production overrides |
| `docker/Dockerfile` | Update PHP/exts/redis; drop CI assumptions |
| `docker/apache/000-staff.conf` | `/staff` DocumentRoot layout |
| `docker/entrypoint.sh` | Redis wait; storage perms for all modules |
| `docker/compose.env.example` | Redis + modules DB notes |
| `docker/README.md` | Replace CI docs with CBP modules docs |
| `.dockerignore` | Update paths under `modules/` |
| Root `dockerfile` | Deprecate or point to `docker/Dockerfile` (avoid two sources of truth) |

## Success criteria

1. `docker compose up -d --build` serves `http://localhost:8080/staff/` (SPA) and `/staff/backend/up`.
2. `/staff/apm/`, `/staff/finance/`, `/staff/helpdesk/` respond (auth redirects OK).
3. Redis reachable from PHP (`REDIS_HOST=redis`).
4. External MySQL works via `host.docker.internal` without starting `mysql` service.
5. `--profile bundled-db` starts MySQL and apps can connect when `DB_HOST=mysql`.
6. `--profile workers` runs APM and Helpdesk queue workers without crashing.
7. `docker-compose.prod.yml` documented and starts without source bind-mount.

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| `/staff` path mismatch inside container | Prefer DocumentRoot parent + `/staff` directory (Option A) |
| Module `.env` still points at localhost Redis | Document + example env overrides for Docker |
| Large bind-mount I/O on macOS | Document; optional named volumes for `vendor` later if needed |
| LibreOffice image size | Keep in web image only (workers don’t need it unless jobs call it) |
| Stale root `dockerfile` | Mark deprecated; single Dockerfile under `docker/` |

## Out of scope follow-ups

- Azure Pipelines build/push of the image
- Helm/K8s manifests
- Helpdesk Laravel 11 → 12
- Sharing a single Composer `vendor` across apps
