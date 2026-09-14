# CBP root setup.sh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an interactive root `./setup.sh` that chooses new/existing install, deploy + DB mode, prompts shared and per-module env values, upserts env files, and optionally runs existing module installers.

**Architecture:** Small bash libraries under `scripts/setup/`; root `setup.sh` orchestrates prompts → URL/DB mapping → force-upsert touched keys into root + module envs → reuse `configure-env.sh` (and new APM helper) to fill blanks → optional `setup.sh` / `setup-production.sh`.

**Tech Stack:** Bash (`set -euo pipefail`), existing `dotenv_*` patterns, OpenSSL for JWT generation.

**Spec:** `docs/superpowers/specs/2026-09-15-cbp-root-setup-design.md`

## Global Constraints

- Approach B: orchestrator; do not rewrite module installers.
- DB modes: bundled (`mysql`) · external · keep current (existing).
- Deploy: Host Apache · Docker Compose.
- Upsert only keys the wizard touched; never wipe unknown keys.
- Force-write user-confirmed values into target `.env` / `setup.env` (existing `configure-env.sh` only fills *missing* keys — orchestrator must `env_set` overrides).
- Passwords via `read -s`.
- TTY required for v1 interactive flow.
- Paths under `modules/` layout.

## File map

| File | Responsibility |
|------|----------------|
| `scripts/setup/env-upsert.sh` | `env_get` / `env_set` / `env_ensure_file` |
| `scripts/setup/prompt.sh` | `prompt_value` / `prompt_secret` / `prompt_choice` |
| `scripts/setup/map-urls.sh` | Derive module URLs from public base + deploy mode |
| `scripts/setup/configure-apm-env.sh` | Ensure APM `.env` + upsert mapped keys |
| `scripts/setup/templates/root.env.example` | Minimal root template |
| `setup.sh` | Wizard + orchestration |
| `docs/SETUP.md` | Operator docs |
| `README.md` | Link to setup |

---

### Task 1: Env upsert + prompt + URL map libraries

**Files:**
- Create: `scripts/setup/env-upsert.sh`
- Create: `scripts/setup/prompt.sh`
- Create: `scripts/setup/map-urls.sh`
- Create: `scripts/setup/templates/root.env.example`

**Interfaces:**
- Produces: `env_get`, `env_set`, `env_ensure_file`, `prompt_value`, `prompt_secret`, `prompt_choice`, `setup_map_urls` (sets globals)

- [ ] **Step 1: Create `scripts/setup/env-upsert.sh`**

```bash
#!/usr/bin/env bash
# shellcheck shell=bash
# Get/set KEY=value in dotenv files without wiping other keys.

env_get() {
  local file="$1" key="$2"
  [[ -f "$file" ]] || return 0
  local line val
  line="$(grep -E "^${key}=" "$file" 2>/dev/null | tail -n 1 || true)"
  [[ -n "$line" ]] || return 0
  val="${line#*=}"
  val="${val%$'\r'}"
  if [[ "$val" =~ ^\".*\"$ ]]; then val="${val:1:${#val}-2}"; fi
  if [[ "$val" =~ ^\'.*\'$ ]]; then val="${val:1:${#val}-2}"; fi
  printf '%s' "$val"
}

env_set() {
  local file="$1" key="$2" value="$3"
  local dir tmp
  dir="$(dirname "$file")"
  mkdir -p "$dir"
  touch "$file"
  tmp="${file}.tmp.$$"
  grep -v -E "^${key}=" "$file" >"$tmp" 2>/dev/null || : >"$tmp"
  if [[ "$value" =~ [[:space:]#\$\"\\] ]]; then
    printf '%s="%s"\n' "$key" "${value//\\/\\\\}" | sed 's/"/\\"/g' >/dev/null
    # Prefer simple quoting:
    printf '%s="%s"\n' "$key" "${value//\"/\\\"}" >>"$tmp"
  else
    printf '%s=%s\n' "$key" "$value" >>"$tmp"
  fi
  mv "$tmp" "$file"
}

env_ensure_file() {
  local file="$1" template="${2:-}"
  if [[ -f "$file" ]]; then
    return 0
  fi
  mkdir -p "$(dirname "$file")"
  if [[ -n "$template" && -f "$template" ]]; then
    cp "$template" "$file"
  else
    : >"$file"
  fi
}
```

Fix the botched sed line in `env_set` when implementing — use only:

```bash
  if [[ "$value" =~ [[:space:]#\$] || "$value" == *\"* ]]; then
    printf '%s="%s"\n' "$key" "${value//\"/\\\"}" >>"$tmp"
  else
    printf '%s=%s\n' "$key" "$value" >>"$tmp"
  fi
```

- [ ] **Step 2: Create `scripts/setup/prompt.sh`**

```bash
#!/usr/bin/env bash
# shellcheck shell=bash

prompt_value() {
  # usage: prompt_value VAR "Label" "default"
  local __var="$1" __label="$2" __default="${3:-}" __in
  if [[ -n "$__default" ]]; then
    read -r -p "$__label [$__default]: " __in || true
  else
    read -r -p "$__label: " __in || true
  fi
  if [[ -z "$__in" ]]; then
    printf -v "$__var" '%s' "$__default"
  else
    printf -v "$__var" '%s' "$__in"
  fi
}

prompt_secret() {
  local __var="$1" __label="$2" __default="${3:-}" __in
  if [[ -n "$__default" ]]; then
    read -r -s -p "$__label [***** keep]: " __in || true
  else
    read -r -s -p "$__label: " __in || true
  fi
  echo
  if [[ -z "$__in" ]]; then
    printf -v "$__var" '%s' "$__default"
  else
    printf -v "$__var" '%s' "$__in"
  fi
}

prompt_choice() {
  # usage: prompt_choice VAR "Label" "1)a 2)b" default_num
  local __var="$1" __label="$2" __options="$3" __default="$4" __in
  echo "$__label"
  echo "  $__options"
  read -r -p "Choice [$__default]: " __in || true
  [[ -z "$__in" ]] && __in="$__default"
  printf -v "$__var" '%s' "$__in"
}
```

- [ ] **Step 3: Create `scripts/setup/map-urls.sh`**

```bash
#!/usr/bin/env bash
# shellcheck shell=bash
# Sets URL globals from PUBLIC_BASE (…/staff) and DEPLOY_MODE (host|docker).

setup_normalize_base() {
  local b="${1%/}"
  b="${b%/staff}/staff"
  case "$b" in
    */staff) ;;
    *) b="${b}/staff" ;;
  esac
  printf '%s' "${b%/}"
}

setup_map_urls() {
  local base deploy
  base="$(setup_normalize_base "${PUBLIC_BASE:?}")"
  deploy="${DEPLOY_MODE:?}" # host|docker
  PUBLIC_BASE="$base"
  BASE_URL="${base}/"
  CI_BASE_URL="${base}/"
  APM_BASE_URL="${base}/apm"
  STAFF_PORTAL_APP_URL="${base}/backend"
  STAFF_PORTAL_SPA_URL="${base}/"
  APM_APP_URL="${base}/apm"
  FINANCE_APP_URL="${base}/finance"
  HELPDESK_APP_URL="${base}/helpdesk/backend"
  HELPDESK_FRONTEND_URL="${base}/helpdesk"
  if [[ "$deploy" == "docker" ]]; then
    SHARE_INTERNAL_BASE="http://web/staff/backend"
    REDIS_HOST_DEFAULT="redis"
  else
    SHARE_INTERNAL_BASE="http://127.0.0.1/staff/backend"
    REDIS_HOST_DEFAULT="127.0.0.1"
  fi
  STAFF_API_INTERNAL_BASE_URL="$SHARE_INTERNAL_BASE"
  HELPDESK_STAFF_API_INTERNAL_BASE_URL="$SHARE_INTERNAL_BASE"
}
```

- [ ] **Step 4: Create `scripts/setup/templates/root.env.example`**

```bash
APP_ENV=local
BASE_URL=http://localhost/staff/
CI_BASE_URL=http://localhost/staff/
APM_BASE_URL=http://localhost/staff/apm
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=staff
JWT_SECRET=
STAFF_API_USERNAME=
STAFF_API_PASSWORD=
STAFF_API_TOKEN=
```

- [ ] **Step 5: Syntax-check and commit**

```bash
bash -n scripts/setup/env-upsert.sh scripts/setup/prompt.sh scripts/setup/map-urls.sh
chmod +x scripts/setup/*.sh
# quick map test:
bash -c 'source scripts/setup/map-urls.sh; PUBLIC_BASE=http://localhost:8088/staff; DEPLOY_MODE=docker; setup_map_urls; echo "$STAFF_API_INTERNAL_BASE_URL $REDIS_HOST_DEFAULT"'
# Expected: http://web/staff/backend redis
git add scripts/setup/
git commit -m "$(cat <<'EOF'
Add setup helper libraries for env upsert, prompts, and URL maps.

EOF
)"
```

---

### Task 2: APM configure-env helper

**Files:**
- Create: `scripts/setup/configure-apm-env.sh`

**Interfaces:**
- Consumes: env vars already exported / files; uses `env_set`
- Produces: `modules/apm/.env` updated

- [ ] **Step 1: Write `scripts/setup/configure-apm-env.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck source=env-upsert.sh
source "$ROOT/scripts/setup/env-upsert.sh"

APM_DIR="$ROOT/modules/apm"
ENV_FILE="$APM_DIR/.env"
EXAMPLE="$APM_DIR/.env.example"

env_ensure_file "$ENV_FILE" "$EXAMPLE"

# Expect caller to export keys to apply (empty = skip).
apply() {
  local key="$1" val="${2:-}"
  [[ -n "$val" ]] || return 0
  env_set "$ENV_FILE" "$key" "$val"
}

apply APP_URL "${APM_APP_URL:-}"
apply BASE_URL "${BASE_URL:-}"
apply CI_BASE_URL "${CI_BASE_URL:-}"
apply JWT_SECRET "${JWT_SECRET:-}"
apply DB_HOST "${DB_HOST:-}"
apply DB_PORT "${DB_PORT:-}"
apply DB_DATABASE "${APM_DB_DATABASE:-}"
apply DB_USERNAME "${DB_USERNAME:-${DB_USER:-}}"
apply DB_PASSWORD "${DB_PASSWORD:-${DB_PASS:-}}"
apply REDIS_HOST "${REDIS_HOST:-}"
apply REDIS_PORT "${REDIS_PORT:-}"
apply REDIS_PASSWORD "${REDIS_PASSWORD:-}"
apply STAFF_API_USERNAME "${STAFF_API_USERNAME:-}"
apply STAFF_API_PASSWORD "${STAFF_API_PASSWORD:-}"
apply STAFF_API_TOKEN "${STAFF_API_TOKEN:-}"
apply STAFF_API_INTERNAL_BASE_URL "${STAFF_API_INTERNAL_BASE_URL:-}"

echo "==> APM .env upserted at $ENV_FILE"
```

- [ ] **Step 2: Commit**

```bash
chmod +x scripts/setup/configure-apm-env.sh
bash -n scripts/setup/configure-apm-env.sh
git add scripts/setup/configure-apm-env.sh
git commit -m "$(cat <<'EOF'
Add APM env configure helper for root setup orchestrator.

EOF
)"
```

---

### Task 3: Root `setup.sh` orchestrator

**Files:**
- Create: `setup.sh`

**Interfaces:**
- Consumes: libraries from Tasks 1–2; module `configure-env.sh` / installers
- Produces: updated env files; optional installer runs

- [ ] **Step 1: Implement `setup.sh`** with this structure (full file in repo; critical sections below):

```bash
#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ ! -t 0 ]]; then
  echo "error: ./setup.sh requires an interactive TTY" >&2
  exit 1
fi

source "$ROOT/scripts/setup/env-upsert.sh"
source "$ROOT/scripts/setup/prompt.sh"
source "$ROOT/scripts/setup/map-urls.sh"

echo "=== Africa CDC CBP setup ==="

prompt_choice INSTALL_TYPE "Install type" "1) New installation  2) Existing installation" "1"
prompt_choice DEPLOY_CHOICE "Deploy target" "1) Host Apache  2) Docker Compose" "1"
[[ "$DEPLOY_CHOICE" == "2" ]] && DEPLOY_MODE=docker || DEPLOY_MODE=host

DB_DEFAULT=1
[[ "$INSTALL_TYPE" == "2" ]] && DB_DEFAULT=3
prompt_choice DB_CHOICE "Database" "1) Docker bundled MySQL  2) External MySQL  3) Keep current" "$DB_DEFAULT"
case "$DB_CHOICE" in
  1) DB_MODE=bundled ;;
  2) DB_MODE=external ;;
  *) DB_MODE=keep ;;
esac

# Defaults from existing root .env if present
ROOT_ENV="$ROOT/.env"
env_ensure_file "$ROOT_ENV" "$ROOT/scripts/setup/templates/root.env.example"

DEFAULT_BASE="$(env_get "$ROOT_ENV" BASE_URL)"
DEFAULT_BASE="${DEFAULT_BASE%/}"
if [[ "$DEPLOY_MODE" == "docker" && -z "$DEFAULT_BASE" ]]; then
  DEFAULT_BASE="http://localhost:8088/staff"
elif [[ -z "$DEFAULT_BASE" ]]; then
  DEFAULT_BASE="http://localhost/staff"
fi

prompt_value PUBLIC_BASE "Public staff base URL (…/staff)" "$DEFAULT_BASE"
setup_map_urls

JWT_DEFAULT="$(env_get "$ROOT_ENV" JWT_SECRET)"
if [[ -z "$JWT_DEFAULT" && "$INSTALL_TYPE" == "1" ]]; then
  JWT_DEFAULT="$(openssl rand -hex 32)"
fi
prompt_secret JWT_SECRET "JWT_SECRET (shared SSO)" "$JWT_DEFAULT"

prompt_value STAFF_API_USERNAME "STAFF_API_USERNAME" "$(env_get "$ROOT_ENV" STAFF_API_USERNAME)"
prompt_secret STAFF_API_PASSWORD "STAFF_API_PASSWORD" "$(env_get "$ROOT_ENV" STAFF_API_PASSWORD)"
prompt_value STAFF_API_TOKEN "STAFF_API_TOKEN" "$(env_get "$ROOT_ENV" STAFF_API_TOKEN)"

if [[ "$DEPLOY_MODE" == "docker" ]]; then
  REDIS_DEF="$REDIS_HOST_DEFAULT"
else
  REDIS_DEF="$(env_get "$ROOT_ENV" REDIS_HOST)"
  REDIS_DEF="${REDIS_DEF:-$REDIS_HOST_DEFAULT}"
fi
prompt_value REDIS_HOST "REDIS_HOST" "$REDIS_DEF"
prompt_value REDIS_PORT "REDIS_PORT" "$(env_get "$ROOT_ENV" REDIS_PORT)"
REDIS_PORT="${REDIS_PORT:-6379}"
prompt_secret REDIS_PASSWORD "REDIS_PASSWORD" "$(env_get "$ROOT_ENV" REDIS_PASSWORD)"

DB_HOST="$(env_get "$ROOT_ENV" DB_HOST)"
DB_PORT="$(env_get "$ROOT_ENV" DB_PORT)"
DB_USER="$(env_get "$ROOT_ENV" DB_USER)"
DB_PASS="$(env_get "$ROOT_ENV" DB_PASS)"
DB_NAME="$(env_get "$ROOT_ENV" DB_NAME)"
if [[ "$DB_MODE" == "bundled" ]]; then
  DB_HOST=mysql
  prompt_value DB_PORT "DB_PORT" "${DB_PORT:-3306}"
  prompt_value DB_USER "DB_USER" "${DB_USER:-staff}"
  prompt_secret DB_PASS "DB_PASS" "${DB_PASS:-staff}"
  prompt_value DB_NAME "Root DB_NAME" "${DB_NAME:-staff}"
elif [[ "$DB_MODE" == "external" ]]; then
  prompt_value DB_HOST "DB_HOST" "${DB_HOST:-host.docker.internal}"
  prompt_value DB_PORT "DB_PORT" "${DB_PORT:-3306}"
  prompt_value DB_USER "DB_USER" "${DB_USER:-root}"
  prompt_secret DB_PASS "DB_PASS" "$DB_PASS"
  prompt_value DB_NAME "Root DB_NAME" "${DB_NAME:-staff}"
else
  echo "==> Keeping current DB_* (host=${DB_HOST:-unset})"
fi

DB_USERNAME="$DB_USER"
DB_PASSWORD="$DB_PASS"

# --- write root ---
for kv in \
  "BASE_URL=${BASE_URL}" \
  "CI_BASE_URL=${CI_BASE_URL}" \
  "APM_BASE_URL=${APM_BASE_URL}" \
  "JWT_SECRET=${JWT_SECRET}" \
  "STAFF_API_USERNAME=${STAFF_API_USERNAME}" \
  "STAFF_API_PASSWORD=${STAFF_API_PASSWORD}" \
  "STAFF_API_TOKEN=${STAFF_API_TOKEN}"
 do
  env_set "$ROOT_ENV" "${kv%%=*}" "${kv#*=}"
done
if [[ "$DB_MODE" != "keep" ]]; then
  env_set "$ROOT_ENV" DB_HOST "$DB_HOST"
  env_set "$ROOT_ENV" DB_PORT "$DB_PORT"
  env_set "$ROOT_ENV" DB_USER "$DB_USER"
  env_set "$ROOT_ENV" DB_PASS "$DB_PASS"
  env_set "$ROOT_ENV" DB_NAME "$DB_NAME"
fi

# --- staff-portal setup.env ---
SP_SETUP="$ROOT/modules/staff-portal/setup.env"
SP_EX="$ROOT/modules/staff-portal/setup.env.example"
env_ensure_file "$SP_SETUP" "$SP_EX"
prompt_value SP_DB "staff-portal DB_DATABASE" "$(env_get "$SP_SETUP" DB_DATABASE)"
SP_DB="${SP_DB:-staff}"
echo "==> staff-portal"
# force keys into setup.env
env_set "$SP_SETUP" APP_URL "$STAFF_PORTAL_APP_URL"
env_set "$SP_SETUP" STAFF_PORTAL_BASE_URL "$STAFF_PORTAL_APP_URL"
env_set "$SP_SETUP" STAFF_PORTAL_SPA_URL "$STAFF_PORTAL_SPA_URL"
env_set "$SP_SETUP" BASE_URL "$BASE_URL"
env_set "$SP_SETUP" JWT_SECRET "$JWT_SECRET"
env_set "$SP_SETUP" DB_DATABASE "$SP_DB"
if [[ "$DB_MODE" != "keep" ]]; then
  env_set "$SP_SETUP" DB_HOST "$DB_HOST"
  env_set "$SP_SETUP" DB_PORT "$DB_PORT"
  env_set "$SP_SETUP" DB_USERNAME "$DB_USER"
  env_set "$SP_SETUP" DB_PASSWORD "$DB_PASS"
fi
env_set "$SP_SETUP" REDIS_HOST "$REDIS_HOST"
env_set "$SP_SETUP" REDIS_PORT "$REDIS_PORT"
[[ -n "$REDIS_PASSWORD" ]] && env_set "$SP_SETUP" REDIS_PASSWORD "$REDIS_PASSWORD"
# Also force Laravel .env keys user set (configure-env only fills missing)
SP_ENV="$ROOT/modules/staff-portal/backend/.env"
env_ensure_file "$SP_ENV" "$ROOT/modules/staff-portal/backend/.env.example"
env_set "$SP_ENV" APP_URL "$STAFF_PORTAL_APP_URL"
env_set "$SP_ENV" JWT_SECRET "$JWT_SECRET"
# … same DB/redis if not keep …
"$ROOT/modules/staff-portal/scripts/configure-env.sh" || echo "warn: staff-portal configure-env failed"

# --- APM ---
prompt_value APM_DB_DATABASE "APM DB_DATABASE" "$(env_get "$ROOT/modules/apm/.env" DB_DATABASE)"
APM_DB_DATABASE="${APM_DB_DATABASE:-apm_local}"
export APM_APP_URL BASE_URL CI_BASE_URL JWT_SECRET DB_HOST DB_PORT DB_USER DB_PASS DB_USERNAME DB_PASSWORD
export APM_DB_DATABASE REDIS_HOST REDIS_PORT REDIS_PASSWORD
export STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN STAFF_API_INTERNAL_BASE_URL
"$ROOT/scripts/setup/configure-apm-env.sh"

# --- finance (setup.env + force .env + configure-env) ---
# mirror staff-portal pattern with FINANCE_APP_URL, DB_DATABASE default finance

# --- helpdesk ---
# HELPDESK_APP_URL, HELPDESK_FRONTEND_URL, HELPDESK_STAFF_API_INTERNAL_BASE_URL, DB default helpdesk

echo
echo "=== Summary ==="
echo "Deploy=$DEPLOY_MODE DB=$DB_MODE Base=$PUBLIC_BASE"
echo "Share internal=$STAFF_API_INTERNAL_BASE_URL Redis=$REDIS_HOST:$REDIS_PORT"
[[ "$DB_MODE" == "bundled" ]] && echo "Next: docker compose --env-file docker/.env --profile bundled-db up -d"

prompt_choice RUN_INSTALL "Run module installers now?" "1) Yes  2) No" "2"
prompt_choice INST_PROFILE "Installer profile" "1) development (setup.sh)  2) production (setup-production.sh)" "1"

if [[ "$RUN_INSTALL" == "1" ]]; then
  if [[ "$INST_PROFILE" == "2" ]]; then
    (cd modules/staff-portal && ./setup-production.sh) || true
    (cd modules/finance && ./setup-production.sh) || true
    (cd modules/helpdesk && ./setup-production.sh) || true
  else
    (cd modules/staff-portal && ./setup.sh) || true
    (cd modules/finance && ./setup.sh) || true
    (cd modules/helpdesk && ./setup.sh) || true
  fi
  (cd modules/apm && composer install --no-interaction && \
    php artisan key:generate --force 2>/dev/null || true && \
    php artisan jwt:secret --force 2>/dev/null || true) || true
fi

echo "Done."
```

Implement the finance/helpdesk blocks fully in the real file (do not leave stubs). Force-set the same shared keys into each module’s Laravel `.env` before calling `configure-env.sh`.

- [ ] **Step 2: chmod + bash -n + commit**

```bash
chmod +x setup.sh
bash -n setup.sh
git add setup.sh
git commit -m "$(cat <<'EOF'
Add interactive root setup.sh for CBP env orchestration.

EOF
)"
```

---

### Task 4: Docs

**Files:**
- Create: `docs/SETUP.md`
- Modify: `README.md` Quick Start

- [ ] **Step 1: Write `docs/SETUP.md`** covering flow, DB/deploy modes, files touched, password prompts, Docker bundled reminder, link to module READMEs.

- [ ] **Step 2: README** — under Docker / developer Quick Start add:

```bash
./setup.sh   # interactive env for all modules (+ optional installers)
```

Link to `docs/SETUP.md`.

- [ ] **Step 3: Commit**

```bash
git add docs/SETUP.md README.md
git commit -m "$(cat <<'EOF'
Document root CBP setup.sh wizard.

EOF
)"
```

---

### Task 5: Verification

- [ ] **Step 1:** `bash -n setup.sh scripts/setup/*.sh`
- [ ] **Step 2:** Unit-ish map test (docker + host) via `source map-urls.sh`
- [ ] **Step 3:** Dry logic — copy a temp dir or use `env_set`/`env_get` round-trip in `/tmp/cbp-env-test`
- [ ] **Step 4:** Do **not** run full interactive `./setup.sh` in CI agents without TTY; manual smoke on a machine is enough

```bash
tmp=$(mktemp)
source scripts/setup/env-upsert.sh
env_set "$tmp" FOO bar
test "$(env_get "$tmp" FOO)" = bar
env_set "$tmp" FOO baz
test "$(env_get "$tmp" FOO)" = baz
grep -c '^FOO=' "$tmp" | grep -q 1
rm -f "$tmp"
echo OK
```

---

## Spec coverage

| Spec | Task |
|------|------|
| Libraries upsert/prompt/map | Task 1 |
| APM configure | Task 2 |
| Wizard + module writes + installers | Task 3 |
| Docs | Task 4 |
| Success criteria checks | Task 5 |

## Plan complete
