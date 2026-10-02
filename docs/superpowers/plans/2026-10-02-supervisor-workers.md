# Supervisor workers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install Supervisor queue + scheduler programs for all five Laravel CBP modules from `./setup.sh` (optional, default Yes on Linux production), delete systemd installers/units from the repo, and always fix storage permissions for every module.

**Architecture:** Central `scripts/setup/install-supervisor.sh` generates `/etc/supervisor/conf.d/cbp-{slug}-*.conf` from templates, retires leftover systemd units, then `supervisorctl reread/update`. `setup.sh` swaps the systemd wizard block for Supervisor. Storage helper lists all five module fix scripts.

**Tech Stack:** Bash (`setup.sh`, install scripts), Supervisor (`supervisord`/`supervisorctl`), Laravel `artisan queue:work` + `schedule:work`.

**Spec:** `docs/superpowers/specs/2026-10-02-supervisor-workers-design.md`

## Global Constraints

- Supervisor optional; default **Yes** on Linux production when tooling available; Demo/Docker skip.
- Modules: staff-portal, helpdesk, APM, finance, risk-register.
- Delete systemd unit trees and installers from the repo; keep `systemd-cleanup.sh` for host retirement.
- Storage permissions: always run for all modules (no prompt).
- Docker deploy: do not install host Supervisor (Compose `--profile workers`).
- Choosing Supervisor Yes: retire CBP systemd units first.
- Choosing Supervisor No: leave host systemd alone (except Demo, which always retires).
- Commit subjects under ~72 chars; no Conventional Commit prefixes (`feat:`, etc.).

## File map

| Path | Responsibility |
|------|----------------|
| `scripts/setup/supervisor/program.conf.tmpl` | Template for one program block |
| `scripts/setup/install-supervisor.sh` | Ensure package, render confs, reload Supervisor, retire systemd |
| `scripts/setup/fix-laravel-storage.sh` | Add risk-register + apm to always-run list |
| `setup.sh` | Replace systemd prompt/install with Supervisor |
| `modules/*/setup-production.sh` | `--skip-supervisor`; call central installer or skip |
| `docs/SETUP.md`, `README.md` | Document Supervisor default |
| `modules/apm/documentation/SUPERVISOR_QUEUE_GUIDE.md` | Replace SYSTEMD guide |
| Delete | `deploy/systemd/`, `install-systemd.sh`, APM `*.service`, APM sample supervisor confs, `install-apm-systemd.sh` |

---

### Task 1: Storage — cover all five modules

**Files:**
- Modify: `scripts/setup/fix-laravel-storage.sh`

**Interfaces:**
- Produces: `setup_fix_laravel_storage` always runs staff-portal, helpdesk, finance, risk-register, apm fix scripts when present

- [ ] **Step 1: Extend the per-app loop**

Replace the `for app_script in …` list with:

```bash
  for app_script in \
    "$ROOT/modules/staff-portal/fix-storage-permissions.sh" \
    "$ROOT/modules/helpdesk/fix-storage-permissions.sh" \
    "$ROOT/modules/finance/fix-storage-permissions.sh" \
    "$ROOT/modules/risk-register/fix-storage-permissions.sh" \
    "$ROOT/modules/apm/fix-storage-permissions.sh"
  do
    [[ -f "$app_script" ]] || continue
    chmod +x "$app_script" 2>/dev/null || true
    if bash "$app_script"; then
      echo "    $(basename "$(dirname "$app_script")") storage OK"
    else
      setup_warn "$(basename "$(dirname "$app_script")") storage fix failed"
    fi
  done
```

- [ ] **Step 2: Syntax check**

```bash
bash -n scripts/setup/fix-laravel-storage.sh && echo OK
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add scripts/setup/fix-laravel-storage.sh
git commit -m "Always fix storage permissions for all CBP modules."
```

---

### Task 2: Supervisor conf template + installer

**Files:**
- Create: `scripts/setup/supervisor/program.conf.tmpl`
- Create: `scripts/setup/install-supervisor.sh`
- Keep using: `scripts/setup/systemd-cleanup.sh` (`systemd_retire_units`)

**Interfaces:**
- Consumes: env `WEB_ROOT`, `PHP_BIN`, `SUPERVISOR_USER`, `STAFF_ROOT` (or derive from script location), `INSTALL_SUPERVISOR=true|false|auto`, `CBP_SUPERVISOR_DRY_RUN=1` (write under `/tmp` instead of `/etc`)
- Produces: conf files `cbp-{slug}-{app}-queue.conf` and `cbp-{slug}-{app}-scheduler.conf`; exit 0 on success

- [ ] **Step 1: Write template**

`scripts/setup/supervisor/program.conf.tmpl`:

```
[program:__NAME__]
process_name=%(program_name)s_%(process_num)02d
command=__COMMAND__
directory=__DIRECTORY__
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=__USER__
numprocs=__NUMPROCS__
redirect_stderr=true
stdout_logfile=__LOGFILE__
stopwaitsecs=__STOPWAIT__
```

- [ ] **Step 2: Write installer skeleton with dry-run**

`scripts/setup/install-supervisor.sh` must:

1. `set -euo pipefail`; source `systemd-cleanup.sh` from same dir.
2. Resolve `STAFF_ROOT` = repo root (`dirname` twice from `scripts/setup`).
3. `WEB_ROOT="${WEB_ROOT:-$(basename "$STAFF_ROOT")}"`; slug = alphanumeric-safe `WEB_ROOT`.
4. `PHP_BIN="${PHP_BIN:-$(command -v php)}"`; `SUPERVISOR_USER="${SUPERVISOR_USER:-www-data}"`.
5. Honour `INSTALL_SUPERVISOR`: `false` → exit 0; `auto` → skip if not Linux or no path to install; `true` → continue.
6. Define apps associative-style array of `name|relative_artisan_dir|queue_numprocs`:
   - `staff-portal|modules/staff-portal/backend|1`
   - `helpdesk|modules/helpdesk/backend|1`
   - `finance|modules/finance/backend|1`
   - `risk-register|modules/risk-register/backend|1`
   - `apm|modules/apm|2`
7. Skip any app whose `$STAFF_ROOT/$rel/artisan` is missing.
8. If `CBP_SUPERVISOR_DRY_RUN=1`, `CONF_DIR=/tmp/cbp-supervisor-$$`; else `CONF_DIR=/etc/supervisor/conf.d`.
9. If not dry-run: retire CBP systemd unit name list (same names as current `setup.sh` Demo retire block + `*-${WEB_ROOT}.*` globs).
10. Ensure `supervisorctl` exists; if missing and not dry-run, `apt-get install -y supervisor` via sudo/root or print instructions and exit 1.
11. For each app, render queue + scheduler confs via `sed` replacements on the template:
    - Queue command: `"$PHP_BIN" artisan queue:work --sleep=3 --tries=3 --max-time=3600`
    - Scheduler command: `"$PHP_BIN" artisan schedule:work` (Laravel 11+; all five apps support it)
    - Log: `$app_abs/storage/logs/supervisor-queue.log` / `supervisor-scheduler.log`
    - `stopwaitsecs`: 3600 queue / 60 scheduler
    - Program names: `cbp-${slug}-${app}-queue` / `cbp-${slug}-${app}-scheduler`
12. If not dry-run: `supervisorctl reread && supervisorctl update`.
13. Echo written paths and `supervisorctl status` hint.

Core render helper (include in script):

```bash
render_program() {
  local name="$1" command="$2" directory="$3" logfile="$4" numprocs="$5" stopwait="$6"
  local out="$CONF_DIR/${name}.conf"
  mkdir -p "$CONF_DIR" "$(dirname "$logfile")"
  sed -e "s|__NAME__|${name}|g" \
      -e "s|__COMMAND__|${command}|g" \
      -e "s|__DIRECTORY__|${directory}|g" \
      -e "s|__USER__|${SUPERVISOR_USER}|g" \
      -e "s|__NUMPROCS__|${numprocs}|g" \
      -e "s|__LOGFILE__|${logfile}|g" \
      -e "s|__STOPWAIT__|${stopwait}|g" \
      "$TMPL" > "$out"
  echo "    wrote $out"
}
```

- [ ] **Step 3: Dry-run locally**

```bash
chmod +x scripts/setup/install-supervisor.sh
CBP_SUPERVISOR_DRY_RUN=1 INSTALL_SUPERVISOR=true WEB_ROOT=staff \
  PHP_BIN="$(command -v php)" SUPERVISOR_USER="$(whoami)" \
  ./scripts/setup/install-supervisor.sh
ls /tmp/cbp-supervisor-*/cbp-staff-*-queue.conf | head
```

Expected: five queue + five scheduler confs (or fewer if a module path missing); no `/etc` writes.

- [ ] **Step 4: Commit**

```bash
git add scripts/setup/supervisor/program.conf.tmpl scripts/setup/install-supervisor.sh
git commit -m "Add central Supervisor installer for CBP queue workers."
```

---

### Task 3: Wire `setup.sh` to Supervisor

**Files:**
- Modify: `setup.sh` (header comment; systemd block ~802–893)

**Interfaces:**
- Consumes: `scripts/setup/install-supervisor.sh`
- Produces: prompt `Install Supervisor background workers…`; sets `INSTALL_SUPERVISOR` in module setup.env files

- [ ] **Step 1: Update header**

Change top comment from `optional installers + systemd` to `optional installers + Supervisor`.

- [ ] **Step 2: Replace Demo / Docker / production worker block**

Demo branch:
- Keep retiring systemd units via `systemd_retire_units` (same unit list).
- Set `INSTALL_SUPERVISOR=false` on staff-portal/helpdesk/finance/risk-register/apm setup.env where those files exist (replace `INSTALL_SYSTEMD` writes).
- Echo skipping Supervisor (demo).

Docker branch:
- Echo Compose workers note; skip Supervisor.

Production/host branch:

```bash
  SUP_DEFAULT=2
  if [[ "$(uname -s)" == "Linux" ]]; then
    if [[ "$SITE_KIND" == "production" ]]; then
      if command -v supervisorctl >/dev/null 2>&1 || command -v apt-get >/dev/null 2>&1; then
        if [[ "$INST_PROFILE" == "2" || "$INSTALL_TYPE" == "2" ]]; then
          SUP_DEFAULT=1
        else
          SUP_DEFAULT=1
        fi
      fi
    fi
  fi
  prompt_choice RUN_SUPERVISOR "Install Supervisor background workers (queue/scheduler)?" "1) Yes  2) No" "$SUP_DEFAULT"

  if [[ "$RUN_SUPERVISOR" == "1" ]]; then
    PHP_BIN_RESOLVED="$(command -v php || echo /usr/bin/php)"
    for setupf in "$SP_SETUP" "$HD_SETUP" \
      "$ROOT/modules/finance/setup.env" \
      "$ROOT/modules/risk-register/setup.env"
    do
      [[ -f "$setupf" ]] || continue
      env_set "$setupf" INSTALL_SUPERVISOR "true"
      env_set "$setupf" PHP_BIN "$PHP_BIN_RESOLVED"
      env_set "$setupf" WEB_ROOT "$WEB_ROOT"
    done
    WEB_ROOT="$WEB_ROOT" PHP_BIN="$PHP_BIN_RESOLVED" \
      SUPERVISOR_USER="${SUPERVISOR_USER:-www-data}" \
      INSTALL_SUPERVISOR=true \
      "$ROOT/scripts/setup/install-supervisor.sh" \
      || echo "warn: Supervisor install failed" >&2
  else
    echo "==> Skipping Supervisor"
  fi
```

Simplify `SUP_DEFAULT`: on Linux production default `1`, else `2` (remove nested INST_PROFILE noise if redundant).

- [ ] **Step 3: Syntax check**

```bash
bash -n setup.sh && echo OK
```

- [ ] **Step 4: Commit**

```bash
git add setup.sh
git commit -m "Manage CBP queue workers via Supervisor in setup."
```

---

### Task 4: Delete systemd trees and update module production setup

**Files:**
- Delete: all under `modules/{staff-portal,helpdesk,finance,risk-register}/deploy/systemd/`
- Delete: `modules/{staff-portal,helpdesk,finance,risk-register}/scripts/install-systemd.sh`
- Delete: `scripts/setup/install-apm-systemd.sh`
- Delete: `modules/apm/laravel-*.service`, `modules/apm/laravel12-queue-apm.service`
- Delete: `modules/apm/supervisor-laravel-worker.conf`, `modules/apm/supervisor-laravel-scheduler.conf`, `modules/apm/supervisor-laravel-scheduler-loop.conf`
- Modify: `modules/staff-portal/setup-production.sh`, `modules/helpdesk/setup-production.sh`, `modules/risk-register/setup-production.sh` (and finance if it has the same flags)
- Modify: any `chmod +x … install-systemd.sh` lines

**Interfaces:**
- Produces: `--skip-supervisor` / `INSTALL_SUPERVISOR`; call `"$STAFF_ROOT/scripts/setup/install-supervisor.sh"` instead of module systemd installer

- [ ] **Step 1: git rm systemd + APM unit/sample files**

```bash
git rm -r modules/staff-portal/deploy/systemd \
  modules/helpdesk/deploy/systemd \
  modules/finance/deploy/systemd \
  modules/risk-register/deploy/systemd
git rm modules/staff-portal/scripts/install-systemd.sh \
  modules/helpdesk/scripts/install-systemd.sh \
  modules/finance/scripts/install-systemd.sh \
  modules/risk-register/scripts/install-systemd.sh \
  scripts/setup/install-apm-systemd.sh
git rm modules/apm/laravel-queue-apm.service \
  modules/apm/laravel-scheduler.service \
  modules/apm/laravel-queue-worker.service \
  modules/apm/laravel12-queue-apm.service \
  modules/apm/laravel-queue-cleanup.service \
  modules/apm/supervisor-laravel-worker.conf \
  modules/apm/supervisor-laravel-scheduler.conf \
  modules/apm/supervisor-laravel-scheduler-loop.conf
```

- [ ] **Step 2: Patch setup-production.sh scripts**

For each of staff-portal / helpdesk / risk-register (and finance if applicable):

- Rename `--skip-systemd` → `--skip-supervisor` (`SKIP_SUPERVISOR=1`).
- Replace `INSTALL_SYSTEMD` with `INSTALL_SUPERVISOR`.
- Replace install block with:

```bash
    log "Installing / restarting Supervisor (queue + scheduler)"
    STAFF_ROOT="$(cd "$ROOT/../.." && pwd)"
    WEB_ROOT="${WEB_ROOT:-$(basename "$STAFF_ROOT")}"
    INSTALL_SUPERVISOR=true WEB_ROOT="$WEB_ROOT" PHP_BIN="${PHP_BIN:-$(command -v php)}" \
      bash "$STAFF_ROOT/scripts/setup/install-supervisor.sh" \
      || warn "Supervisor install failed — run: sudo $STAFF_ROOT/scripts/setup/install-supervisor.sh"
```

- Update final echo hints from `systemctl status …` to `supervisorctl status`.

- [ ] **Step 3: Grep for leftovers**

```bash
rg -n "install-systemd|INSTALL_SYSTEMD|deploy/systemd|systemctl status staff-portal" \
  setup.sh modules scripts docs README.md || echo "clean"
```

Expected: only `systemd-cleanup.sh` / Demo retire mentions / historical docs being updated in Task 5.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Remove systemd workers; use Supervisor from production setup."
```

---

### Task 5: Docs

**Files:**
- Modify: `docs/SETUP.md`
- Modify: `README.md` (setup / systemd mentions)
- Create: `modules/apm/documentation/SUPERVISOR_QUEUE_GUIDE.md`
- Delete or rewrite: `modules/apm/documentation/SYSTEMD_QUEUE_GUIDE.md` (rewrite as stub pointing to Supervisor guide, or `git rm` + fix README link)
- Modify: `docs/superpowers/specs/2026-10-02-supervisor-workers-design.md` status → `Implemented` when done (optional last step)

- [ ] **Step 1: Update SETUP.md**

- Replace “systemd” site-role / workers sections with Supervisor.
- Document prompt, `INSTALL_SUPERVISOR`, operator `supervisorctl` commands from the spec.
- Note Docker still uses `--profile workers`.

- [ ] **Step 2: Update README links**

Point “Root setup” and queue guide to Supervisor.

- [ ] **Step 3: APM guide**

Write short `SUPERVISOR_QUEUE_GUIDE.md` (status, restart, logs). Remove or redirect `SYSTEMD_QUEUE_GUIDE.md`.

- [ ] **Step 4: Commit + push**

```bash
git add docs/SETUP.md README.md modules/apm/documentation/
git commit -m "Document Supervisor as the default CBP worker runner."
git push
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Central Supervisor installer | 2 |
| setup.sh optional default Yes | 3 |
| All five modules | 2, 3 |
| Delete systemd from repo | 4 |
| Retire host systemd on Yes / Demo | 2, 3 |
| Storage always all modules | 1 |
| Docker skip host Supervisor | 3 |
| Docs | 5 |

## Placeholder / consistency scan

- Program naming: `cbp-{slug}-{app}-queue|scheduler` everywhere.
- Flag: `INSTALL_SUPERVISOR` only (no remaining `INSTALL_SYSTEMD` writes after Task 4).
- Dry-run env: `CBP_SUPERVISOR_DRY_RUN=1` for safe local verification without root.
