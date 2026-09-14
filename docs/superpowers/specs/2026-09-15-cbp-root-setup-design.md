# CBP root setup orchestrator (`setup.sh`)

**Date:** 2026-09-15  
**Status:** Approved for planning  
**Approach:** B — interactive wizard + upsert envs + optionally call existing module installers

## Goals

1. Provide a **root** `./setup.sh` that guides **new** and **existing** installations.
2. Choose **deploy target** (host Apache vs Docker Compose) and **database mode** (bundled MySQL / external / keep current).
3. Collect **shared** and **per-module** env values interactively (Enter keeps current / default).
4. **Create or update** env files without wiping unrelated keys.
5. Map URL / DB / Redis / Share API values correctly for each module under `modules/`.
6. Optionally run each module’s existing `setup.sh` / `setup-production.sh` (and bootstrap APM env, which has no configure-env today).

## Non-goals

- Non-interactive / CI flags in v1 (`--yes` can be a follow-up).
- Replacing module `setup.sh` implementations (orchestrate, don’t duplicate composer/migrate/SPA logic).
- Deploying to remote servers or pushing GHCR images.
- Migrating folder layout (use `scripts/migrate-to-modules-layout.sh` separately if needed).
- Editing secrets into git-tracked examples.

## Decisions

| Topic | Choice |
|-------|--------|
| Structure | Approach B — orchestrator |
| After env | Optional call to existing module installers |
| DB modes | Bundled (`mysql`) · External · Keep current (existing) |
| Deploy | Host Apache · Docker Compose |
| Upsert | Patch keys wizard touched; preserve others |
| APM | New `scripts/setup/configure-apm-env.sh` (APM lacked configure-env) |
| Reuse | Existing `modules/{staff-portal,finance,helpdesk}/scripts/configure-env.sh` |

## User flow

```text
./setup.sh
  ├─ Install type: New | Existing
  ├─ Deploy: Host Apache | Docker Compose
  ├─ Database: Bundled | External | Keep current*
  ├─ Shared prompts (base URL, JWT, Share API, Redis, DB if needed)
  ├─ Per module: staff-portal → apm → finance → helpdesk
  │     show current; Enter = keep; set mapped defaults from shared
  ├─ Write/upsert env files
  ├─ Run module configure-env helpers
  └─ Ask: run module installers? → setup.sh / setup-production.sh
```

\* “Keep current” only offered (or defaulted) when install type is **Existing** and DB keys already exist.

### Shared prompts

| Prompt | Writes |
|--------|--------|
| Public staff base (no trailing path beyond `/staff`) e.g. `http://localhost:8088/staff` | Derives all `APP_URL` / SPA / Share internal bases |
| `JWT_SECRET` | Root + all modules (generate with `openssl rand -hex 32` if blank on new) |
| `STAFF_API_USERNAME` / `PASSWORD` / `TOKEN` | Root + APM + consumers |
| Redis host/port/password | Modules that use Redis; Docker defaults `redis`:`6379` |
| DB host/port/user/password (+ optional root `DB_NAME`) | When not keep-current; bundled ⇒ `DB_HOST=mysql` |

### Database mode mapping

| Mode | `DB_HOST` (apps) | Notes |
|------|------------------|--------|
| Bundled | `mysql` | Print: `docker compose --env-file docker/.env --profile bundled-db up -d` |
| External | user-entered (e.g. `host.docker.internal` or LAN IP) | Same credentials prompted once; per-module `DB_DATABASE` still prompted |
| Keep current | unchanged | Skip DB prompts unless user chooses to override a module |

### Deploy mode URL defaults

| Deploy | Example public base | Share internal (Docker network) |
|--------|---------------------|----------------------------------|
| Host Apache | `http://localhost/staff` | `http://127.0.0.1/staff/backend` |
| Docker Compose | `http://localhost:8088/staff` | `http://web/staff/backend` |

Browser-facing `APP_URL` always uses the public base + module path.

## Per-module env mapping

### Root `.env`

Keys upserted when touched: `BASE_URL`, `CI_BASE_URL`, `APM_BASE_URL`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `JWT_SECRET`, `STAFF_API_*`, optional MS SSO if prompted later (v1: optional skip).

If missing: create from a new `setup.env.example`-style template `env/root.env.example` **or** minimal generated file (prefer `scripts/setup/templates/root.env.example`).

### Staff portal

- Files: `modules/staff-portal/setup.env` then run `modules/staff-portal/scripts/configure-env.sh`
- Defaults: `APP_URL` / `STAFF_PORTAL_BASE_URL` = `{base}/backend`; SPA = `{base}/`; `DB_DATABASE=staff`; `JWT_SECRET` shared; Redis as chosen

### APM

- File: `modules/apm/.env` via `scripts/setup/configure-apm-env.sh` (copy from `.env.example` if missing, then upsert)
- Defaults: `APP_URL={base}/apm`; `BASE_URL={base}/`; `STAFF_API_INTERNAL_BASE_URL` per deploy; `DB_DATABASE` default `apm_local` or prompt; map root `DB_USER`→`DB_USERNAME`, `DB_PASS`→`DB_PASSWORD`

### Finance

- Files: `modules/finance/setup.env` + `configure-env.sh`
- Defaults: `APP_URL={base}/finance`; Share + JWT inherited; `DB_DATABASE` prompt (default `finance` or keep)

### Helpdesk

- Files: `modules/helpdesk/setup.env` + `configure-env.sh`
- Defaults: `APP_URL={base}/helpdesk/backend`; `HELPDESK_FRONTEND_URL={base}/helpdesk`; `HELPDESK_STAFF_API_INTERNAL_BASE_URL` / `STAFF_API_*` mapped; `DB_DATABASE` prompt

## Upsert semantics

`scripts/setup/env-upsert.sh`:

- `env_get FILE KEY` → current value or empty  
- `env_set FILE KEY VALUE` → replace line `KEY=...` or append; preserve comments/other keys  
- Never delete unknown keys  
- Quote values that contain spaces/special chars as needed  

Prompt helper: `prompt KEY "Label" DEFAULT` → prints `[DEFAULT]`; empty input keeps default.

## After env: installers

If user confirms:

| Module | Local (host) | Production-ish |
|--------|--------------|----------------|
| staff-portal | `./setup.sh` | `./setup-production.sh` |
| finance | `./setup.sh` | `./setup-production.sh` |
| helpdesk | `./setup.sh` | `./setup-production.sh` |
| apm | `composer install`, `key:generate` if no `APP_KEY`, `jwt:secret` if no JWT in file / artisan | same + note queue workers |

Choice of local vs production script: prompt “Installer profile: development | production” (independent of Docker vs host).

Docker: after env, print compose commands; do not require Docker daemon for env-only path.

## Files to add

| Path | Role |
|------|------|
| `setup.sh` | Root entry (TTY check, menu, orchestration) |
| `scripts/setup/prompt.sh` | Read helpers |
| `scripts/setup/env-upsert.sh` | Get/set `.env` keys |
| `scripts/setup/map-urls.sh` | Derive module URLs from public base + deploy mode |
| `scripts/setup/configure-apm-env.sh` | APM `.env` upsert |
| `scripts/setup/templates/root.env.example` | Minimal root template if `.env` missing |
| `docs/SETUP.md` | Operator guide |
| `README.md` | Link under Quick Start |

## Success criteria

1. New install on empty env trees produces valid root + four module envs with matching `JWT_SECRET` and coherent `/staff/…` URLs.  
2. Existing install with “keep current” DB does not change `DB_HOST` unless overridden.  
3. Docker mode sets Share internal URL to `http://web/staff/backend` and Redis host `redis`.  
4. Bundled DB mode sets `DB_HOST=mysql` across written module DB hosts.  
5. Re-running wizard updates only prompted keys; custom keys remain.  
6. Optional installer step invokes existing module scripts without rewriting them.

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| `configure-env.sh` overwrites wizard values | Write `setup.env` first with finals; document order; APM uses dedicated upsert |
| Host vs Docker DB_HOST confusion | Explicit DB mode + print summary table before write |
| Secrets on screen | No echo for passwords (`read -s`) |
| Partial failure mid-module | Continue other modules; summarize failures at end |

## Out of scope follow-ups

- `--non-interactive` / env-file driven runs  
- Azure AD / Exchange interactive setup  
- Auto `docker compose up`  
- Unifying all modules onto one `.env`
