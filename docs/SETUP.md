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
| Shared | Public base URL, `STAFF_SITE_ID`, secrets, Redis, DB |
| **Auth** | Microsoft Entra SSO (`TENANT_ID` / `CLIENT_ID` / secret) written to **root**, **staff-portal**, and **APM** (each with its own `MICROSOFT_REDIRECT_URI`); SPA **password login** (`ALLOW_ALTERNATIVE_LOGIN`, portal only) |
| **Mail** | Shared `MAIL_TRANSPORT` (**exchange** default · **smtp** · **zoho** · **http** [notifications.africacdc.org](https://notifications.africacdc.org/api/documentation)); shared Graph/SMTP/HTTP creds; per app only `MAIL_FROM_NAME` + `MAIL_FROM_ADDRESS` |
| **SPA rebuild** | **Default Yes** — Vite build + publish for `/{WEB_ROOT}/` (fixes folder renames like `cbpdemo` → `demo_staff`) |
| Per module | `DB_DATABASE` (+ forced mapped URLs / storage) |
| Installers | Optional `setup.sh` or `setup-production.sh` |
| Systemd | **Production only** |

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

Wizard keys (URLs, JWT, Share API, Redis, storage, Microsoft SSO where applicable, and DB when not “keep”) are **force-updated** on all of the above after each module’s `configure-env` (which otherwise only fills missing keys).

| Auth key | Root | staff-portal | APM | helpdesk | finance |
|----------|------|--------------|-----|----------|---------|
| `TENANT_ID` / `CLIENT_*` / `MICROSOFT_*` SSO | yes | yes (+ portal redirect) | yes (+ APM redirect) | — | — |
| `ALLOW_ALTERNATIVE_LOGIN` | yes | yes | — | — | — |
| `MAIL_TRANSPORT` / shared SMTP·HTTP·`EXCHANGE_*` | yes | yes | yes | yes | — |
| `MAIL_FROM_NAME` / `MAIL_FROM_ADDRESS` | shared default | per-app | per-app | per-app | — |

Outbound transports:

| `MAIL_TRANSPORT` | Laravel `MAIL_MAILER` | Notes |
|------------------|----------------------|--------|
| `exchange` (default) | `exchange` | Microsoft Graph; needs `EXCHANGE_*` (copied from SSO Azure app) |
| `smtp` | `smtp` | Shared `MAIL_HOST` / user / password |
| `zoho` | `smtp` | Defaults `smtp.zoho.com`; same SMTP keys |
| `http` | `http` | [Africa CDC Email Server](https://notifications.africacdc.org/api/documentation) via `MAIL_HTTP_*` |

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

On Linux with `systemctl`, installers write **site-scoped** units and env files so the WorkingDirectory matches this checkout:

| App | Example paths (`WEB_ROOT=cbp`) |
|-----|--------------------------------|
| staff-portal | `STAFF_PORTAL_ROOT=…/modules/staff-portal/backend`, `/etc/staff-portal/cbp.env`, units `staff-portal-queue-cbp.service` |
| helpdesk | `HELPDESK_ROOT=…/modules/helpdesk/backend`, `/etc/helpdesk/cbp.env` |
| APM | `WorkingDirectory=…/modules/apm`, `laravel-queue-apm-cbp.service` |

Health URLs use `/{WEB_ROOT}/…` (not a hardcoded `/staff/`). Installers **stop, disable, and remove** prior units for that app before writing new ones.

- **staff-portal** / **helpdesk** — `scripts/install-systemd.sh` (sets `INSTALL_SYSTEMD=true`)
- **APM** — `scripts/setup/install-apm-systemd.sh`

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
