# CBP root setup (`./setup.sh`)

Interactive wizard at the repository root that configures **shared and per-module** environment files, rewrites **public path prefixes** in `.htaccess` when the deploy folder is not `/staff`, optionally runs each module’s installer, and can install **Supervisor** queue/scheduler programs.

## Quick start

```bash
cd /path/to/staff   # or cbp / demo_cbp / cbpdemo
./setup.sh
```

Requires a TTY for the interactive wizard. Passwords are entered without echo.

The **first prompt** is:

1. **Run with current defaults** — no further questions (same as pressing Enter on every later step; keeps existing `.env` secrets)
2. **Configure step by step** — full wizard

**Skip the first prompt entirely (CI / scripts):**

```bash
./setup.sh --defaults
# aliases: ./setup.sh -y   ./setup.sh --yes   ./setup.sh --non-interactive
```

Defaults mode uses Docker Compose, external MySQL, production installers, SPA rebuild, Supervisor on Linux production when available, and keeps existing Microsoft Entra values. Set `MAIL_FROM_ADDRESS` (or `MAIL_USERNAME`) in root `.env` first if it is empty.

## What it asks

| Step | Options |
|------|---------|
| **Start** | **Run with current defaults (default)** · Configure step by step |
| **Site role** | **Production · Demo** (demo never enables Supervisor workers) |
| Install type | New · Existing |
| Deploy | Host Apache · **Docker Compose (default)** |
| **Redis** (Docker) | **Docker Redis (default)** · External Redis |
| **Redis** (Host) | Host/port/password (default `127.0.0.1`) |
| Database | Bundled MySQL (`DB_HOST=mysql`) · **External MySQL (default)** — always prompts user/password; under Docker, external host defaults to `host.docker.internal` |
| Shared | Public base URL, `STAFF_SITE_ID`, secrets, Redis, DB |
| **Auth** | Microsoft Entra (`EXCHANGE_TENANT_ID` / `EXCHANGE_CLIENT_ID` / `EXCHANGE_CLIENT_SECRET`) written to **root `.env` only**; modules inherit via `shared/load-staff-root-env.php` and keep per-app `MICROSOFT_REDIRECT_URI` / `EXCHANGE_REDIRECT_URI`; SPA **password login** (`ALLOW_ALTERNATIVE_LOGIN`, portal only) |
| **Mail** | Shared `MAIL_TRANSPORT` (**http** preferred · **exchange** · **smtp** · **zoho**); Graph/SMTP/HTTP creds in root `.env`; Staff Portal UI providers + Share hub (`POST /share/mail/send`, `GET /share/mail/active-config`); modules use `STAFF_MAIL_DISPATCH=auto|portal|local` |
| **URL / web root** | Public Alias is always the **checkout folder name** (`basename` of the install dir). `APP_URL` = `{origin}/{folder}/backend` so post-login never redirects to bare `/auth/spa-bridge`. |
| Per module | `DB_DATABASE` (+ forced `DB_CONNECTION=mysql`, mapped URLs / storage) for staff-portal, APM, finance, helpdesk, risk-register |
| Installers | Default **Yes** · profile default **production** — always migrate; seed only when the target schema has **no tables** |
| **Storage** | Always: Laravel `storage/` + `bootstrap/cache` for **all five** modules (staff-portal, helpdesk, finance, risk-register, APM); host `STAFF_DATA_ROOT` when set |
| **Supervisor** | **Production host only** (optional; default Yes on Linux) — queue + scheduler for all five apps |

Folder names containing `demo` default the site role to **Demo**.

## Files written / updated

| Path | Role |
|------|------|
| `.env` | Root inheritance hub (`SITE_KIND`, `WEB_ROOT`, `STAFF_SITE_ID`, `BASE_URL`, secrets, DB) |
| `.htaccess` (+ staff-portal / APM) | Public redirects use `/{WEB_ROOT}/` |
| `modules/staff-portal/setup.env` + `backend/.env` | Portal |
| `modules/apm/.env` | APM |
| `modules/finance/setup.env` + `.env` (+ `backend/.env` when present) | Finance |
| `modules/helpdesk/setup.env` + `backend/.env` | Helpdesk |
| `modules/risk-register/setup.env` + `backend/.env` | Risk Register |

Wizard keys (URLs, JWT, Share API, Redis, storage, Microsoft SSO where applicable, and MySQL including `DB_CONNECTION=mysql`) are **force-updated** on all of the above after each module’s `configure-env` (which otherwise only fills missing keys).

| Auth key | Root | staff-portal | APM | helpdesk | finance |
|----------|------|--------------|-----|----------|---------|
| `TENANT_ID` / `CLIENT_*` / `MICROSOFT_*` SSO | yes | yes (+ portal redirect) | yes (+ APM redirect) | — | — |
| `ALLOW_ALTERNATIVE_LOGIN` | yes | yes | — | — | — |
| `MAIL_TRANSPORT` / shared SMTP·HTTP·`EXCHANGE_*` | yes | yes | yes | yes | — |
| `MAIL_FROM_NAME` / `MAIL_FROM_ADDRESS` | shared default | per-app | per-app | per-app | — |

Outbound transports:

| `MAIL_TRANSPORT` | Laravel `MAIL_MAILER` | Notes |
|------------------|----------------------|--------|
| `http` (preferred) | `http` | [Africa CDC Email Server](https://notifications.africacdc.org/api/documentation) via `MAIL_HTTP_*`; seeded into Settings → Email servers when missing |
| `exchange` | `exchange` | Microsoft Graph; needs `EXCHANGE_*` (copied from SSO Azure app) |
| `smtp` | `smtp` | Shared `MAIL_HOST` / user / password |
| `zoho` | `smtp` | Defaults `smtp.zoho.com`; same SMTP keys |

Modules dispatch via Share by default (`STAFF_MAIL_DISPATCH=auto`): portal `POST …/share/mail/send`, with encrypted `active-config` local fallback. Set `STAFF_MAIL_CONFIG_KEY` (or rely on `APP_KEY`) for AES-GCM.

## Site role, Supervisor, and CI3 uploads

| Role | Supervisor | Storage |
|------|------------|---------|
| **Production** | Prompt to install workers (default Yes on Linux) | `STAFF_SITE_ID` → `/var/staffdata/{id}/ci` for CI3 uploads |
| **Demo** | **Never installs**; retires leftover CBP **systemd** units if present | Same site-id rules so demo does not share production upload trees |

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

## Supervisor (production host)

On Linux, `./setup.sh` can install **Supervisor** programs for every Laravel module (queue + scheduler). Choosing Yes also **retires** legacy CBP systemd units so workers are not doubled.

| Item | Detail |
|------|--------|
| Installer | `scripts/setup/install-supervisor.sh` |
| Flag | `INSTALL_SUPERVISOR=true\|false\|auto` |
| Conf dir | `/etc/supervisor/conf.d/cbp-{WEB_ROOT}-*.conf` |
| Apps | staff-portal, helpdesk, finance, risk-register, apm |
| Program names | `cbp-{slug}-{app}-queue`, `cbp-{slug}-{app}-scheduler` |
| Logs | `{app}/storage/logs/supervisor-queue.log` / `supervisor-scheduler.log` |

```bash
# Enable the daemon once (fixes: unix:///var/run/supervisor.sock no such file)
sudo apt-get install -y supervisor
sudo systemctl enable --now supervisor

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
sudo supervisorctl restart cbp-staff-:
sudo tail -f modules/staff-portal/backend/storage/logs/supervisor-queue.log
```

Dry-run (no root / no `/etc` writes):

```bash
CBP_SUPERVISOR_DRY_RUN=1 INSTALL_SUPERVISOR=true WEB_ROOT=staff \
  ./scripts/setup/install-supervisor.sh
```

Under **Docker Compose**, prefer:

```bash
docker compose --env-file docker/.env --profile workers up -d
```

That starts one `workers` container running **Supervisor** (`docker/supervisord-workers.conf`) with queue + scheduler for all five Laravel apps — same process set as the host installer. Do not also run host Supervisor against the same queues.

## Related

- Portal mail hub design: `docs/superpowers/specs/2026-10-02-portal-mail-hub-design.md`
- Supervisor workers design: `docs/superpowers/specs/2026-10-02-supervisor-workers-design.md`
- Share endpoints (staff-portal): `POST /share/mail/send`, `GET /share/mail/active-config` (Bearer `STAFF_API_TOKEN`)
- [docker/README.md](../docker/README.md)
- [STORAGE.md](./STORAGE.md) — `STAFF_SITE_ID` / `/var/staffdata`
- [migrate-to-modules-layout.sh](../scripts/migrate-to-modules-layout.sh)
- Module READMEs under `modules/*/README.md`
