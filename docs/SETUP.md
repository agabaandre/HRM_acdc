# CBP root setup (`./setup.sh`)

Interactive wizard at the repository root that configures **shared and per-module** environment files, rewrites **public path prefixes** in `.htaccess` when the deploy folder is not `/staff`, optionally runs each module’s installer, and can install **systemd** queue/scheduler units.

## Quick start

```bash
cd /path/to/staff   # or cbp / demo_cbp
./setup.sh
```

Requires a TTY. Passwords are entered without echo.

## What it asks

| Step | Options |
|------|---------|
| Install type | New · Existing |
| Deploy | Host Apache · Docker Compose |
| Database | Bundled MySQL (`DB_HOST=mysql`) · External · Keep current |
| Shared | Public base URL (`…/staff`, `…/cbp`, `…/demo_cbp`), `JWT_SECRET`, Share API, Redis, DB (unless keep) |
| Per module | `DB_DATABASE` (+ forced mapped `APP_URL` / Share / Redis on **every** module `.env`) |
| Installers | Optional `setup.sh` or `setup-production.sh` |
| Systemd | Optional queue + scheduler for staff-portal, helpdesk, APM |

## Files written / updated

| Path | Role |
|------|------|
| `.env` | Root inheritance hub (`WEB_ROOT`, `BASE_URL`, secrets, DB) |
| `.htaccess` (+ staff-portal / APM) | Public redirects use `/{WEB_ROOT}/` |
| `modules/staff-portal/setup.env` + `backend/.env` | Portal |
| `modules/apm/.env` | APM |
| `modules/finance/setup.env` + `.env` | Finance |
| `modules/helpdesk/setup.env` + `backend/.env` | Helpdesk |

Wizard keys (URLs, JWT, Share API, Redis, and DB when not “keep”) are **force-updated** on all of the above after each module’s `configure-env` (which otherwise only fills missing keys).

## URL mapping / web folder

From public base `https://host/cbp` (or `…/staff`, `…/demo_cbp`):

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

`.htaccess` REQUEST_URI match groups keep common aliases (`staff`, `demo_staff`, `cbp`, `demo_cbp`) so legacy bookmarks still match; absolute redirects use the current `WEB_ROOT`.

## Systemd

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
- [migrate-to-modules-layout.sh](../scripts/migrate-to-modules-layout.sh)
- Module READMEs under `modules/*/README.md`
