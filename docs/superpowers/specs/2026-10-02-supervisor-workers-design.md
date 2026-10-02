# Supervisor workers for all CBP modules

**Date:** 2026-10-02  
**Status:** Implemented  
**Approach:** Central Supervisor installer (Approach 1)

## Goals

1. Run **queue workers** and **schedulers** for all Laravel CBP modules via **Supervisor** by default on host installs.
2. Manage install/reload through root **`./setup.sh`** (optional prompt; default Yes on Linux production).
3. **Remove** systemd unit files and install scripts from the repo; retire any leftover host systemd units when Supervisor is chosen (and on Demo).
4. **Always** fix Laravel storage permissions for all modules during setup (no prompt).
5. Keep **Docker** on Compose `--profile workers`, which runs the same five apps under **Supervisor** (`docker/supervisord-workers.conf`) — do not install host Supervisor under Docker deploy mode.

## Non-goals

- Replacing Docker Compose queue containers with Supervisor inside the web image (optional later).
- Changing job/schedule PHP code or queues themselves (only how processes are supervised).
- Supporting macOS launchd as a first-class worker runner (skip Supervisor install; storage fix still runs).
- Multi-tenant Supervisor isolation beyond site-slug program name prefixes.

## Decisions (from brainstorming)

| Topic | Choice |
|-------|--------|
| Wizard option | Supervisor optional; default **Yes** on Linux production when tooling available; Demo/Docker skip |
| Modules | **All five:** staff-portal, helpdesk, APM, finance, risk-register |
| systemd in repo | **Delete** unit files and installers |
| Storage perms | **Always** run for all modules (extend existing setup helper) |

## Current state

- `setup.sh` installs **systemd** for staff-portal, helpdesk, APM only (`INSTALL_SYSTEMD`, `scripts/install-systemd.sh`, `scripts/setup/install-apm-systemd.sh`).
- Finance and risk-register ship copied `deploy/systemd/` trees but are **not** wired from root setup.
- APM has sample `supervisor-laravel-*.conf` with hardcoded paths.
- `setup_fix_laravel_storage` already runs shared + staff-portal/helpdesk/finance scripts; **risk-register** (and sometimes APM) are incomplete in that loop.
- Demo path retires systemd units and sets `INSTALL_SYSTEMD=false`.

## Architecture

```
./setup.sh
  ├─ setup_fix_laravel_storage()     # always: all 5 modules + host staffdata
  └─ [production + host deploy]
       prompt Install Supervisor? (default Yes)
         ├─ No  → skip workers (optionally still retire systemd if migrating)
         └─ Yes → scripts/setup/install-supervisor.sh
                    ├─ retire CBP systemd units (reuse systemd-cleanup.sh)
                    ├─ ensure supervisord present (apt/yum or fail with message)
                    ├─ write /etc/supervisor/conf.d/cbp-{slug}-*.conf
                    └─ supervisorctl reread && update
```

### Programs (per module)

For site slug `WEB_ROOT` (e.g. `staff`, `cbp`):

| Program name pattern | Command |
|----------------------|---------|
| `cbp-{slug}-{app}-queue` | `{php} {app_root}/artisan queue:work --sleep=3 --tries=3 --max-time=3600` |
| `cbp-{slug}-{app}-scheduler` | `{php} {app_root}/artisan schedule:work` (preferred) or 60s `schedule:run` loop if `schedule:work` unavailable |

**App roots:**

| App | Working directory |
|-----|-------------------|
| staff-portal | `modules/staff-portal/backend` |
| helpdesk | `modules/helpdesk/backend` |
| finance | `modules/finance/backend` |
| risk-register | `modules/risk-register/backend` |
| apm | `modules/apm` |

**Process defaults:** `user=www-data` (Linux), `autostart=true`, `autorestart=true`, `numprocs=1` for queue (APM may use `2` to match prior sample), stdout/stderr → `{app_root}/storage/logs/supervisor-{queue\|scheduler}.log`.

**Conf destination:** `/etc/supervisor/conf.d/cbp-{slug}-{app}-{queue\|scheduler}.conf` (or one include file per app). Generated from templates under `scripts/setup/supervisor/` — no hardcoded install paths.

### Env / setup.env flags

| Key | Meaning |
|-----|---------|
| `INSTALL_SUPERVISOR` | `true` / `false` / `auto` (replace `INSTALL_SYSTEMD`) |
| `SUPERVISOR_USER` | Process user (default `www-data`) |
| `PHP_BIN` | Absolute PHP binary |
| `WEB_ROOT` | Site slug for program names |

Module `setup-production.sh`: `--skip-supervisor` replaces `--skip-systemd`; drop calls to deleted systemd installers.

## Storage permissions

Extend `scripts/setup/fix-laravel-storage.sh` to always invoke:

1. Shared `scripts/fix-laravel-storage-permissions.sh`
2. Per-module: staff-portal, helpdesk, finance, **risk-register**, **apm** (`fix-storage-permissions.sh` where present)
3. Host `scripts/storage/fix-staff-storage-permissions.sh` when `STAFF_DATA_ROOT` is set

No wizard prompt. Failures warn but do not abort setup (current behaviour).

## Repo cleanup (delete)

- `modules/*/deploy/systemd/` (staff-portal, helpdesk, finance, risk-register)
- `modules/*/scripts/install-systemd.sh`
- `scripts/setup/install-apm-systemd.sh` (logic replaced by central installer; keep `systemd-cleanup.sh` for retirement)
- APM root `*.service` unit files and obsolete `supervisor-laravel-*.conf` samples (replaced by generated confs)
- Docs that prescribe systemd as the primary path (`docs/SETUP.md`, README links, APM `SYSTEMD_QUEUE_GUIDE.md` → rewrite or replace with Supervisor guide)

Keep `scripts/setup/systemd-cleanup.sh` until hosts no longer have units; call it from the Supervisor installer and Demo path.

## Setup.sh behaviour matrix

| Condition | Supervisor install | Storage fix | Retire systemd |
|-----------|-------------------|-------------|----------------|
| Demo | Skip | Always | Yes |
| Docker deploy | Skip (Compose workers) | Always | No (N/A) |
| Linux production, Yes | Install | Always | Yes |
| Linux production, No | Skip | Always | Optional migrate: still retire if prior CBP units detected (recommended Yes to avoid double workers) |
| macOS / no supervisorctl | Skip + message | Always | No |

**Recommended:** when user chooses Supervisor **Yes**, always retire CBP systemd units first. When user chooses **No**, leave existing systemd alone (operator may still be on old units during transition) **unless** Demo.

## Operator commands (document)

```bash
sudo supervisorctl status
sudo supervisorctl restart cbp-staff-*:
sudo tail -f modules/staff-portal/backend/storage/logs/supervisor-queue.log
```

## Success criteria

- [ ] Root setup can install Supervisor programs for all five apps with one Yes.
- [ ] No systemd installers or unit templates remain in the repo.
- [ ] Demo/Docker never start host Supervisor workers.
- [ ] Storage permission scripts run for all five modules on every setup.
- [ ] Docs describe Supervisor as the default host worker path.

## Open points (resolved defaults)

- **Double workers:** Choosing Supervisor Yes always stops CBP systemd units first.
- **Choosing No:** Do not auto-remove systemd (except Demo).
- **package install:** If `supervisor` missing, attempt `apt-get install -y supervisor` when root/sudo; otherwise fail with install instructions.
