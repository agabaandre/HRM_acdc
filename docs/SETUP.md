# CBP root setup (`./setup.sh`)

Interactive wizard at the repository root that configures **shared and per-module** environment files, optionally runs each module’s installer, and can install **systemd** queue/scheduler units.

## Quick start

```bash
cd /path/to/staff
./setup.sh
```

Requires a TTY. Passwords are entered without echo.

## What it asks

| Step | Options |
|------|---------|
| Install type | New · Existing |
| Deploy | Host Apache · Docker Compose |
| Database | Bundled MySQL (`DB_HOST=mysql`) · External · Keep current |
| Shared | Public `/staff` base URL, `JWT_SECRET`, Share API, Redis, DB (unless keep) |
| Per module | `DB_DATABASE` (+ forced mapped `APP_URL` / Share / Redis) |
| Installers | Optional `setup.sh` or `setup-production.sh` |
| Systemd | Optional queue + scheduler for staff-portal, helpdesk, APM |

## Files written / updated

| Path | Role |
|------|------|
| `.env` | Root inheritance hub |
| `modules/staff-portal/setup.env` + `backend/.env` | Portal |
| `modules/apm/.env` | APM |
| `modules/finance/setup.env` + `.env` | Finance |
| `modules/helpdesk/setup.env` + `backend/.env` | Helpdesk |

Existing keys the wizard does **not** touch are left alone. Keys you confirm are **force-updated**.

## URL mapping

From public base `https://host/staff` (or `http://localhost:8088/staff` for Docker):

| App | `APP_URL` |
|-----|-----------|
| Staff portal API | `{base}/backend` |
| SPA | `{base}/` |
| APM | `{base}/apm` |
| Finance | `{base}/finance` |
| Helpdesk API | `{base}/helpdesk/backend` |

Share internal base: `http://127.0.0.1/staff/backend` (host) or `http://web/staff/backend` (Docker).

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
