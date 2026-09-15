# CBP root setup (`./setup.sh`)

Interactive wizard at the repository root that configures **shared and per-module** environment files, rewrites **public path prefixes** in `.htaccess` when the deploy folder is not `/staff`, optionally runs each module’s installer, and can install **systemd** queue/scheduler units.

## Quick start

```bash
cd /path/to/staff   # or cbp / demo_cbp / cbpdemo
./setup.sh
```

Requires a TTY. Passwords are entered without echo.

## What it asks

| Step | Options |
|------|---------|
| **Site role** | **Production · Demo** (demo never enables systemd workers) |
| Install type | New · Existing |
| Deploy | Host Apache · Docker Compose |
| Database | Bundled MySQL (`DB_HOST=mysql`) · External · Keep current |
| Shared | Public base URL (`…/staff`, `…/cbp`, `…/cbpdemo`), `STAFF_SITE_ID`, `JWT_SECRET`, Share API, Redis, DB (unless keep) |
| Per module | `DB_DATABASE` (+ forced mapped `APP_URL` / Share / Redis / storage on **every** module `.env`) |
| Installers | Optional `setup.sh` or `setup-production.sh` |
| Systemd | **Production only** — optional queue + scheduler for staff-portal, helpdesk, APM |

Folder names containing `demo` default the site role to **Demo**.

## Files written / updated

| Path | Role |
|------|------|
| `.env` | Root inheritance hub (`SITE_KIND`, `WEB_ROOT`, `STAFF_SITE_ID`, `BASE_URL`, secrets, DB) |
| `.htaccess` (+ staff-portal / APM) | Public redirects use `/{WEB_ROOT}/` |
| `modules/staff-portal/setup.env` + `backend/.env` | Portal |
| `modules/apm/.env` | APM |
| `modules/finance/setup.env` + `.env` | Finance |
| `modules/helpdesk/setup.env` + `backend/.env` | Helpdesk |

Wizard keys (URLs, JWT, Share API, Redis, storage, and DB when not “keep”) are **force-updated** on all of the above after each module’s `configure-env` (which otherwise only fills missing keys).

## Site role, systemd, and CI3 uploads

| Role | Systemd | Storage |
|------|---------|---------|
| **Production** | Prompt to install workers (default Yes on Linux prod-style installs) | `STAFF_SITE_ID` → `/var/staffdata/{id}/ci` for CI3 uploads |
| **Demo** | **Never installs**; retires existing CBP units if present | Same site-id rules so demo does not share production upload trees |

`STAFF_SITE_ID` is derived from the public base URL (host + path), e.g. `https://cpb.africacdc.org/cbpdemo` → `cpb-africacdc-org-cbpdemo`. Confirm or override in the wizard so migrations never land under another site’s folder.

## URL mapping / web folder

From public base `https://host/cbp` (or `…/staff`, `…/demo_cbp`, `…/cbpdemo`):

| Derived | Example |
|---------|---------|
| `WEB_ROOT` | `cbp` |
| Redirects / Vite base | `/cbp/`, `/cbp/backend`, … |

| App | `APP_URL` |
|-----|-----------|
| Staff portal API | `{base}/backend` |
| SPA | `{base}/` |
| APM | `{base}/apm` |
| Finance | `{base}/finance` |
| Helpdesk API | `{base}/helpdesk/backend` |

Share internal base: `http://127.0.0.1/{WEB_ROOT}/backend` (host) or `http://web/{WEB_ROOT}/backend` (Docker).

`.htaccess` REQUEST_URI match groups keep common aliases (`staff`, `demo_staff`, `cbp`, `demo_cbp`, `cbpdemo`) so legacy bookmarks still match; absolute redirects use the current `WEB_ROOT`.

## Systemd (production only)

On Linux with `systemctl`, installers **stop, disable, and remove** prior units for that app before writing new ones (avoids duplicate queue/scheduler workers). APM also retires legacy unit names (`laravel-queue-worker`, `laravel-queue-cleanup`, `laravel12-queue-apm`).

- **staff-portal** / **helpdesk** — existing `scripts/install-systemd.sh` (sets `INSTALL_SYSTEMD=true`)
- **APM** — `scripts/setup/install-apm-systemd.sh` installs `laravel-queue-apm.service` + `laravel-scheduler.service` with `WorkingDirectory=…/modules/apm`

Under **Docker Compose**, prefer:

```bash
docker compose --env-file docker/.env --profile workers up -d
```

Do not run host systemd workers and Compose `--profile workers` against the same queue at once.

## Related

- [docker/README.md](../docker/README.md)
- [STORAGE.md](./STORAGE.md) — `STAFF_SITE_ID` / `/var/staffdata`
- [migrate-to-modules-layout.sh](../scripts/migrate-to-modules-layout.sh)
- Module READMEs under `modules/*/README.md`
