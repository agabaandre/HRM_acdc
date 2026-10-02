# Setup wizard MySQL / Redis / migrate-seed Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `./setup.sh` always collect MySQL credentials, default Redis to Compose when on Docker, default MySQL to external, force `DB_CONNECTION=mysql` on every module, always migrate on install, and seed only empty databases — with installers defaulting to Yes + production.

**Architecture:** Add `scripts/setup/db-probe.sh` for empty-DB detection. Rework Redis + DB prompts and `apply_db_to_file` in `setup.sh`. Pass migrate always / `--skip-seed` when not empty into module `setup-production.sh` calls; add risk-register to the installer loop.

**Tech Stack:** Bash (`setup.sh`, setup helpers), MySQL/MariaDB client for probes, existing module `setup-production.sh` `--skip-migrate` / `--skip-seed` flags.

**Spec:** `docs/superpowers/specs/2026-10-02-setup-wizard-db-redis-design.md`

## Global Constraints

- MySQL: global Docker bundled vs **external (default)**; always prompt user/password/host/port.
- Docker deploy: Redis prompt Docker Redis (**default**) vs external.
- Host Apache: Redis default `127.0.0.1` (unchanged pattern).
- Write shared MySQL into all modules with `DB_CONNECTION=mysql`.
- Migrations: **always** when installers run (do not pass `--skip-migrate`).
- Seeders: only when `setup_db_is_empty` is true; otherwise `--skip-seed`.
- Installers default **Yes**; profile default **production**.
- Demo Supervisor skip unchanged.
- Commit subjects under ~72 chars; no Conventional Commit prefixes (`feat:`, `fix:`).

## File map

| Path | Responsibility |
|------|----------------|
| `scripts/setup/db-probe.sh` | `setup_db_table_count` / `setup_db_is_empty` |
| `setup.sh` | Redis choice, MySQL defaults, `apply_db_to_file`, risk-register env block, installer defaults + migrate/seed wiring |
| `README.md` / `docker/README.md` | Short wizard notes |
| Module `setup-production.sh` | Callers only (flags); no seeder PHP changes |

---

### Task 1: Empty-database probe helper

**Files:**
- Create: `scripts/setup/db-probe.sh`
- Test: run helper manually with a known empty/non-empty schema (or dry-run path when client missing)

**Interfaces:**
- Produces:
  - `setup_db_table_count host port user pass database` → prints integer count or empty on failure; exit 0 on success, non-zero on failure
  - `setup_db_is_empty host port user pass database` → exit 0 if empty/unreachable-as-new, exit 1 if tables exist
- Consumes: `mysql` or `mariadb` CLI when present

- [ ] **Step 1: Create `scripts/setup/db-probe.sh`**

```bash
#!/usr/bin/env bash
# shellcheck shell=bash
# Empty MySQL schema detection for ./setup.sh installers.

setup_db_mysql_bin() {
  if command -v mysql >/dev/null 2>&1; then
    command -v mysql
  elif command -v mariadb >/dev/null 2>&1; then
    command -v mariadb
  else
    return 1
  fi
}

# Prints table count for schema. Exit 1 if client missing or query fails.
setup_db_table_count() {
  local host="$1" port="$2" user="$3" pass="$4" database="$5"
  local bin sql out
  bin="$(setup_db_mysql_bin)" || return 1
  [[ -n "$database" ]] || return 1
  sql="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();"
  # shellcheck disable=SC2086
  out="$(
    MYSQL_PWD="$pass" "$bin" -h "$host" -P "$port" -u "$user" -N -B "$database" \
      -e "$sql" 2>/dev/null
  )" || return 1
  out="$(printf '%s' "$out" | tr -d '[:space:]')"
  [[ "$out" =~ ^[0-9]+$ ]] || return 1
  printf '%s\n' "$out"
}

# Exit 0 = treat as new (empty or unreachable). Exit 1 = has tables.
setup_db_is_empty() {
  local host="$1" port="$2" user="$3" pass="$4" database="$5"
  local count
  if ! count="$(setup_db_table_count "$host" "$port" "$user" "$pass" "$database")"; then
    echo "warn: could not probe MySQL ${database}@${host}:${port} — treating as new (will seed)" >&2
    return 0
  fi
  if [[ "$count" -eq 0 ]]; then
    echo "    ${database}: 0 tables (new)" >&2
    return 0
  fi
  echo "    ${database}: ${count} tables (existing — skip seed)" >&2
  return 1
}
```

- [ ] **Step 2: Syntax check**

```bash
bash -n scripts/setup/db-probe.sh && echo OK
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add scripts/setup/db-probe.sh
git commit -m "Add MySQL empty-schema probe for setup installers."
```

---

### Task 2: Redis Docker-vs-external + MySQL external default + always credentials

**Files:**
- Modify: `setup.sh` (early Redis block ~303–337; DB_CHOICE ~58–66; remove silent keep for normal path)
- Source: `scripts/setup/db-probe.sh` near other setup sources at top of `setup.sh`

**Interfaces:**
- Consumes: `DEPLOY_MODE`, `REDIS_HOST_DEFAULT` from `map-urls.sh`
- Produces: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `DB_MODE` (`bundled`|`external`), `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` always set when writing envs

- [ ] **Step 1: Source db-probe after other setup libs**

Near the existing `source …/prompt.sh` block add:

```bash
# shellcheck source=scripts/setup/db-probe.sh
source "$ROOT/scripts/setup/db-probe.sh"
```

- [ ] **Step 2: Replace Database choice defaults**

Change the DB prompt block to:

```bash
# Default: External MySQL (bundled Compose MySQL is opt-in).
DB_DEFAULT=2
prompt_choice DB_CHOICE "Database" "1) Docker bundled MySQL  2) External MySQL" "$DB_DEFAULT"
case "$DB_CHOICE" in
  1) DB_MODE=bundled ;;
  *) DB_MODE=external ;;
esac
```

Remove the old `Keep current` option and `DB_MODE=keep` path that skipped credential prompts.

- [ ] **Step 3: Redis choice when Docker**

Replace the free-text-only Redis block with:

```bash
if [[ "$DEPLOY_MODE" == "docker" ]]; then
  prompt_choice REDIS_CHOICE "Redis" "1) Docker Redis  2) External Redis" "1"
  if [[ "$REDIS_CHOICE" == "2" ]]; then
    _rh="$(env_get "$ROOT_ENV" REDIS_HOST)"
    prompt_value REDIS_HOST "REDIS_HOST" "${_rh:-127.0.0.1}"
  else
    REDIS_HOST=redis
  fi
else
  REDIS_DEF="$(env_get "$ROOT_ENV" REDIS_HOST)"
  REDIS_DEF="${REDIS_DEF:-$REDIS_HOST_DEFAULT}"
  prompt_value REDIS_HOST "REDIS_HOST" "$REDIS_DEF"
fi
_rport="$(env_get "$ROOT_ENV" REDIS_PORT)"
prompt_value REDIS_PORT "REDIS_PORT" "${_rport:-6379}"
prompt_secret REDIS_PASSWORD "REDIS_PASSWORD" "$(env_get "$ROOT_ENV" REDIS_PASSWORD)"
```

Keep the subsequent MySQL prompt block for `bundled` / `external` (always prompt `DB_USER` / `DB_PASS`). Delete the `else` branch that only printed `Keeping current DB_*`.

- [ ] **Step 4: Root `.env` write always applies DB_***

Change:

```bash
if [[ "$DB_MODE" != "keep" ]]; then
```

to always write `DB_HOST`/`DB_PORT`/`DB_USER`/`DB_PASS`/`DB_NAME` (no keep gate).

- [ ] **Step 5: Syntax check**

```bash
bash -n setup.sh && echo OK
```

Expected: `OK`

- [ ] **Step 6: Commit**

```bash
git add setup.sh
git commit -m "Prompt Docker Redis and always collect MySQL credentials."
```

---

### Task 3: Force `DB_CONNECTION=mysql` on every module apply

**Files:**
- Modify: `setup.sh` — `apply_db_to_file`
- Modify: finance `write_finance_env` loop to also touch `modules/finance/backend/.env` if that file exists (Laravel backend may read it)
- Add: risk-register env block (setup.env + backend/.env) mirroring helpdesk pattern for DB/redis/URLs minimally

**Interfaces:**
- Consumes: `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`
- Produces: every targeted env file has `DB_CONNECTION=mysql` and matching credentials

- [ ] **Step 1: Update `apply_db_to_file`**

```bash
apply_db_to_file() {
  local file="$1" user_key="${2:-DB_USERNAME}" pass_key="${3:-DB_PASSWORD}"
  env_set "$file" DB_CONNECTION "mysql" || return 1
  env_set "$file" DB_HOST "${DB_HOST:-}" || return 1
  env_set "$file" DB_PORT "${DB_PORT:-}" || return 1
  env_set "$file" "$user_key" "${DB_USER:-}" || return 1
  env_set "$file" "$pass_key" "${DB_PASS:-}" || return 1
  return 0
}
```

Remove any `[[ "$DB_MODE" == "keep" ]] && return 0` short-circuit.

- [ ] **Step 2: Finance — include backend `.env` when present**

In `write_finance_env`, extend the file list:

```bash
  for f in "$FN_SETUP" "$FN_ENV" "$ROOT/modules/finance/backend/.env"; do
    [[ -f "$f" || "$f" == "$FN_SETUP" || "$f" == "$FN_ENV" ]] || continue
    # ensure backend/.env exists only if directory exists
    if [[ "$f" == "$ROOT/modules/finance/backend/.env" && ! -f "$f" ]]; then
      [[ -d "$ROOT/modules/finance/backend" ]] || continue
      env_ensure_file "$f" "$ROOT/modules/finance/backend/.env.example" || continue
    fi
    …
  done
```

(Keep the existing key writes; ensure `DB_CONNECTION` comes from `apply_db_to_file`.)

- [ ] **Step 3: Add risk-register section after helpdesk**

Before `setup_fix_laravel_storage`, add:

```bash
# ----- risk-register -----
echo
echo "==> risk-register"
RR_SETUP="$ROOT/modules/risk-register/setup.env"
RR_ENV="$ROOT/modules/risk-register/backend/.env"
env_ensure_file "$RR_SETUP" "$ROOT/modules/risk-register/setup.env.example" \
  || setup_warn "risk-register setup.env missing/unwritable"
env_ensure_file "$RR_ENV" "$ROOT/modules/risk-register/backend/.env.example" \
  || setup_warn "risk-register backend/.env missing/unwritable"
prompt_value RR_DB "risk-register DB_DATABASE" "$(env_get "$RR_SETUP" DB_DATABASE)"
RR_DB="${RR_DB:-risk_register}"
write_risk_register_env() {
  local f
  for f in "$RR_SETUP" "$RR_ENV"; do
    env_set "$f" APP_URL "${BASE_URL%/}/risk-register/backend" || return 1
    env_set "$f" BASE_URL "$BASE_URL" || return 1
    env_set "$f" DB_DATABASE "$RR_DB" || return 1
    apply_storage_to_file "$f" || return 1
    apply_db_to_file "$f" DB_USERNAME DB_PASSWORD || return 1
    apply_redis_to_file "$f" || return 1
  done
}
if write_risk_register_env; then
  if [[ -x "$ROOT/modules/risk-register/scripts/configure-env.sh" ]]; then
    "$ROOT/modules/risk-register/scripts/configure-env.sh" \
      && echo "    configure-env OK" \
      || setup_warn "risk-register configure-env failed"
  fi
  write_risk_register_env || setup_warn "risk-register force env rewrite failed"
else
  setup_warn "risk-register env write failed"
fi
```

Adjust `APP_URL` if the module already documents a canonical URL helper — prefer existing `FINANCE_APP_URL`-style globals if present in `map-urls.sh`; if missing, use `${BASE_URL%/}/risk-register` / backend path consistent with module README.

Update Summary line to include `risk=$RR_DB`.

- [ ] **Step 4: Syntax check + commit**

```bash
bash -n setup.sh && echo OK
git add setup.sh
git commit -m "Force MySQL on all modules including risk-register."
```

---

### Task 4: Installer defaults + always migrate + seed if empty

**Files:**
- Modify: `setup.sh` final installer block (~750–800)

**Interfaces:**
- Consumes: `setup_db_is_empty`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, per-module DB names (`SP_DB`, `APM_DB_DATABASE`, `FN_DB`, `HD_DB`, `RR_DB`)
- Produces: production installer invocations with `--skip-seed` only when not empty

- [ ] **Step 1: Change defaults**

```bash
INST_PROFILE_DEFAULT=2
prompt_choice RUN_INSTALL "Run module installers now?" "1) Yes  2) No" "1"
prompt_choice INST_PROFILE "Installer profile" "1) development (setup.sh)  2) production (setup-production.sh)" "$INST_PROFILE_DEFAULT"
```

- [ ] **Step 2: Helper to run one production installer with seed policy**

Add before the `if [[ "$RUN_INSTALL" == "1" ]]` body (or inside it):

```bash
setup_run_module_production() {
  local name="$1" dir="$2" database="$3"
  shift 3
  local seed_flags=()
  local probe_host="$DB_HOST"
  # Host-side probe: Compose service name mysql is not resolvable on the host.
  if [[ "$probe_host" == "mysql" ]]; then
    probe_host="127.0.0.1"
  fi
  if setup_db_is_empty "$probe_host" "${DB_PORT:-3306}" "$DB_USER" "$DB_PASS" "$database"; then
    echo "==> $name: new DB — migrate + seed"
  else
    echo "==> $name: existing DB — migrate only (skip seed)"
    seed_flags=(--skip-seed)
  fi
  (cd "$dir" && ./setup-production.sh "$@" "${seed_flags[@]}") \
    || echo "warn: $name setup-production failed" >&2
}
```

- [ ] **Step 3: Wire production branch**

Replace the hard-coded production installer calls with:

```bash
  if [[ "$INST_PROFILE" == "2" ]]; then
    setup_run_module_production staff-portal "$ROOT/modules/staff-portal" "$SP_DB" --skip-build
    setup_run_module_production finance "$ROOT/modules/finance" "$FN_DB"
    setup_run_module_production helpdesk "$ROOT/modules/helpdesk" "$HD_DB"
    setup_run_module_production risk-register "$ROOT/modules/risk-register" "$RR_DB"
  else
    …
  fi
```

Keep APM block but add migrate always + seed-if-empty:

```bash
  if [[ -f "$ROOT/modules/apm/artisan" ]]; then
    (
      cd "$ROOT/modules/apm"
      composer install --no-interaction --prefer-dist || true
      php artisan key:generate --force 2>/dev/null || true
      php artisan jwt:secret --force 2>/dev/null || true
      php artisan migrate --force --no-interaction || true
      probe_host="$DB_HOST"
      [[ "$probe_host" == "mysql" ]] && probe_host="127.0.0.1"
      if setup_db_is_empty "$probe_host" "${DB_PORT:-3306}" "$DB_USER" "$DB_PASS" "$APM_DB_DATABASE"; then
        php artisan db:seed --force --no-interaction 2>/dev/null || true
      fi
    ) || echo "warn: APM bootstrap failed" >&2
  fi
```

Do **not** pass `--skip-migrate` anywhere in this path.

- [ ] **Step 4: Syntax check + commit**

```bash
bash -n setup.sh && echo OK
git add setup.sh
git commit -m "Default production installers; seed only empty databases."
```

---

### Task 5: Docs

**Files:**
- Modify: `README.md` (setup wizard section — search for Database / Redis / setup.sh)
- Modify: `docker/README.md` (Redis + external MySQL defaults)
- Modify: `docs/superpowers/specs/2026-10-02-setup-wizard-db-redis-design.md` — set Status to Implemented after verify

- [ ] **Step 1: Document wizard behaviour**

In `README.md` near root setup instructions, add a short bullet list:

- Docker deploy → Redis defaults to Compose `redis` (prompt allows external).
- MySQL defaults to **external**; always prompts username/password.
- Module installers default to **production**; migrations always run; seeders only when the target schema has no tables.

Mirror one paragraph in `docker/README.md`.

- [ ] **Step 2: Commit**

```bash
git add README.md docker/README.md docs/superpowers/specs/2026-10-02-setup-wizard-db-redis-design.md
git commit -m "Document setup MySQL Redis migrate and seed defaults."
```

---

### Task 6: Smoke verification (manual)

- [ ] **Step 1: Dry prompts**

```bash
bash -n setup.sh scripts/setup/db-probe.sh
```

Expected: no output / success.

- [ ] **Step 2: Probe behaviour without live MySQL**

```bash
source scripts/setup/db-probe.sh
setup_db_is_empty 127.0.0.1 3306 root wrongpass nonexistent_db; echo exit:$?
```

Expected: warn + `exit:0` (treat as new).

- [ ] **Step 3: Confirm finance cannot stay sqlite after apply**

After a dry run or by inspecting `apply_db_to_file` usage: any file passed through it must set `DB_CONNECTION=mysql`. Optionally:

```bash
# After a real ./setup.sh on a safe machine, or unit-style:
grep -n 'DB_CONNECTION' setup.sh | head
```

Expected: `apply_db_to_file` sets `mysql`.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Always prompt MySQL user/password | Task 2 |
| Docker → Redis Docker default + choice | Task 2 |
| MySQL external default | Task 2 |
| `DB_CONNECTION=mysql` all modules | Task 3 |
| Always migrate | Task 4 |
| Seed only empty DB | Task 1 + 4 |
| Installers Yes + production default | Task 4 |
| risk-register included | Task 3 + 4 |
| Docs | Task 5 |
| Demo Supervisor unchanged | No change (out of scope) |

## Placeholder / consistency review

- Function names: `setup_db_table_count`, `setup_db_is_empty`, `setup_run_module_production` — consistent across tasks.
- No `--skip-migrate` in production path.
- Probe remaps host `mysql` → `127.0.0.1` for host-side checks.
