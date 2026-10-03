#!/usr/bin/env bash
# Interactive CBP root setup: env for all modules + optional installers + Supervisor.
# Non-interactive: ./setup.sh --defaults   (or -y / --yes / --non-interactive)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

SETUP_ASSUME_DEFAULTS=0
SETUP_SKIP_GIT=0
# Preserve argv so we can re-exec after git pull updates this script.
SETUP_ARGV=("$@")

setup_print_usage() {
  cat <<'EOF'
Usage: ./setup.sh [options]

  (no flags)              Interactive wizard (requires a TTY)
  --defaults, --yes, -y   Accept wizard defaults and continue (no prompts)
  --non-interactive       Same as --defaults
  --skip-git              Do not stash/pull at start
  -h, --help              Show this help

Starts with git stash + git pull (unless --skip-git) so the tree matches the
remote before the wizard runs. Defaults mode uses the same answers as pressing
Enter in the wizard and keeps existing .env values for secrets when present.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --defaults|--yes|--non-interactive|-y)
      SETUP_ASSUME_DEFAULTS=1
      shift
      ;;
    --skip-git)
      SETUP_SKIP_GIT=1
      shift
      ;;
    -h|--help)
      setup_print_usage
      exit 0
      ;;
    *)
      echo "error: unknown option: $1" >&2
      setup_print_usage >&2
      exit 1
      ;;
  esac
done
export SETUP_ASSUME_DEFAULTS

if [[ "$SETUP_ASSUME_DEFAULTS" != "1" && ! -t 0 ]]; then
  echo "error: ./setup.sh requires an interactive TTY (or pass --defaults). See docs/SETUP.md" >&2
  exit 1
fi

# ----- Sync from git (stash local tracked edits, pull, restore stash) -----
# SETUP_GIT_BEFORE / SETUP_GIT_AFTER track the pull range for "install what changed".
SETUP_GIT_BEFORE="${SETUP_GIT_BEFORE:-}"
SETUP_GIT_AFTER="${SETUP_GIT_AFTER:-}"
SETUP_STATE_FILE="$ROOT/.setup-last-head"

setup_git_sync() {
  if [[ "$SETUP_SKIP_GIT" == "1" ]]; then
    echo "==> Skipping git stash/pull (--skip-git)"
    if command -v git >/dev/null 2>&1 && git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
      SETUP_GIT_AFTER="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"
      SETUP_GIT_BEFORE="${SETUP_GIT_BEFORE:-$SETUP_GIT_AFTER}"
      export SETUP_GIT_BEFORE SETUP_GIT_AFTER
    fi
    return 0
  fi
  if [[ "${SETUP_SKIP_GIT_SYNC:-0}" == "1" ]]; then
    # Inner re-exec after pull already synced.
    if command -v git >/dev/null 2>&1 && git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
      SETUP_GIT_AFTER="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"
      export SETUP_GIT_AFTER
    fi
    return 0
  fi
  if ! command -v git >/dev/null 2>&1; then
    echo "==> git not found — skipping stash/pull"
    return 0
  fi
  if ! git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "==> Not a git checkout — skipping stash/pull"
    return 0
  fi

  echo "==> Git: stash local changes (if any), then pull"
  local before after stashed=0
  before="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"
  local setup_before
  setup_before="$(git -C "$ROOT" hash-object "$ROOT/setup.sh" 2>/dev/null || echo none)"

  # Stash tracked modifications only — leave untracked .env / vendor alone.
  if ! git -C "$ROOT" diff --quiet 2>/dev/null \
    || ! git -C "$ROOT" diff --cached --quiet 2>/dev/null; then
    if git -C "$ROOT" stash push -m "setup.sh auto-stash $(date -u +%Y%m%dT%H%M%SZ)" --quiet; then
      stashed=1
      echo "    stashed local tracked changes"
    fi
  else
    echo "    working tree clean (nothing to stash)"
  fi

  local branch remote
  branch="$(git -C "$ROOT" rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)"
  remote="$(git -C "$ROOT" rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null || true)"
  if [[ -n "$remote" ]]; then
    echo "    pulling $remote …"
    if ! git -C "$ROOT" pull --ff-only; then
      echo "    warn: git pull --ff-only failed — trying plain git pull" >&2
      git -C "$ROOT" pull || echo "    warn: git pull failed — continuing with current tree" >&2
    fi
  else
    echo "    no upstream for $branch — fetching origin/$branch if present"
    git -C "$ROOT" fetch origin "$branch" 2>/dev/null || true
    if git -C "$ROOT" rev-parse "origin/$branch" >/dev/null 2>&1; then
      git -C "$ROOT" merge --ff-only "origin/$branch" \
        || echo "    warn: could not ff-merge origin/$branch — continuing" >&2
    else
      echo "    warn: no origin/$branch — skipping pull" >&2
    fi
  fi

  after="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"
  if [[ "$before" != "$after" ]]; then
    echo "    updated: ${before:0:9} → ${after:0:9}"
  else
    echo "    already up to date ($after)"
  fi

  if [[ "$stashed" -eq 1 ]]; then
    echo "    restoring stash"
    if ! git -C "$ROOT" stash pop --quiet; then
      echo "    warn: stash pop had conflicts — resolve with: git status && git stash list" >&2
    fi
  fi

  SETUP_GIT_BEFORE="$before"
  SETUP_GIT_AFTER="$after"
  export SETUP_GIT_BEFORE SETUP_GIT_AFTER

  local setup_after
  setup_after="$(git -C "$ROOT" hash-object "$ROOT/setup.sh" 2>/dev/null || echo none)"
  if [[ "$setup_before" != "$setup_after" && "$setup_after" != "none" ]]; then
    echo "==> setup.sh was updated by git pull — re-running with the new script"
    export SETUP_SKIP_GIT_SYNC=1 SETUP_GIT_BEFORE SETUP_GIT_AFTER
    exec bash "$ROOT/setup.sh" "${SETUP_ARGV[@]}"
  fi
}

setup_git_sync

# shellcheck source=scripts/setup/env-upsert.sh
source "$ROOT/scripts/setup/env-upsert.sh"
# shellcheck source=scripts/setup/prompt.sh
source "$ROOT/scripts/setup/prompt.sh"
# shellcheck source=scripts/setup/secrets.sh
source "$ROOT/scripts/setup/secrets.sh"
# shellcheck source=scripts/setup/map-urls.sh
source "$ROOT/scripts/setup/map-urls.sh"
# shellcheck source=scripts/setup/update-htaccess.sh
source "$ROOT/scripts/setup/update-htaccess.sh"
# shellcheck source=scripts/setup/systemd-cleanup.sh
source "$ROOT/scripts/setup/systemd-cleanup.sh"
# shellcheck source=scripts/setup/fix-laravel-storage.sh
source "$ROOT/scripts/setup/fix-laravel-storage.sh"
# shellcheck source=scripts/setup/db-probe.sh
source "$ROOT/scripts/setup/db-probe.sh"
# shellcheck source=scripts/setup/docker-compose.sh
source "$ROOT/scripts/setup/docker-compose.sh"

echo "=== Africa CDC CBP setup ==="
echo "Repo: $ROOT"
echo

# First question (interactive only): run with wizard/env defaults, or step through.
if [[ "$SETUP_ASSUME_DEFAULTS" != "1" ]]; then
  prompt_choice SETUP_MODE_CHOICE \
    "How do you want to run setup?" \
    "1) Run with current defaults (no further prompts)  2) Configure step by step" \
    "1"
  if [[ "$SETUP_MODE_CHOICE" == "1" ]]; then
    SETUP_ASSUME_DEFAULTS=1
    export SETUP_ASSUME_DEFAULTS
  fi
fi

if [[ "$SETUP_ASSUME_DEFAULTS" == "1" ]]; then
  echo "Mode: defaults — accepting wizard defaults / existing .env values (no further prompts)"
  echo
fi

DEFAULT_WEB_ROOT="$(basename "$ROOT")"
if [[ ! "$DEFAULT_WEB_ROOT" =~ ^[A-Za-z0-9_-]+$ ]]; then
  DEFAULT_WEB_ROOT=staff
fi
export DEFAULT_WEB_ROOT

SITE_KIND_DEFAULT=1
case "$DEFAULT_WEB_ROOT" in
  *demo*|demo_*|Demo*) SITE_KIND_DEFAULT=2 ;;
esac
prompt_choice SITE_KIND_CHOICE "Site role" "1) Production  2) Demo" "$SITE_KIND_DEFAULT"
if [[ "$SITE_KIND_CHOICE" == "2" ]]; then
  SITE_KIND=demo
  APP_ENV=local
  APP_DEBUG=true
else
  SITE_KIND=production
  APP_ENV=production
  APP_DEBUG=false
fi
export SITE_KIND APP_ENV APP_DEBUG
echo "    Site role: $SITE_KIND  (APP_ENV=$APP_ENV APP_DEBUG=$APP_DEBUG)"
if [[ "$SITE_KIND" == "demo" ]]; then
  echo "    Demo: Supervisor queue/scheduler workers will NOT be enabled."
else
  echo "    Production: APP_DEBUG=false forced on every module .env"
  # Module configure-env / setup-production honour these.
  export STAFF_PORTAL_PRODUCTION_SETUP=1
  export HELPDESK_PRODUCTION_SETUP=1
  export FINANCE_PRODUCTION_SETUP=1
  export RISK_REGISTER_PRODUCTION_SETUP=1
fi

INSTALL_TYPE_DEFAULT=1
# Re-runs after a successful setup default to "existing" so we only full-install
# modules that changed (migrations still always run).
if [[ -f "$SETUP_STATE_FILE" ]]; then
  INSTALL_TYPE_DEFAULT=2
fi
prompt_choice INSTALL_TYPE "Install type" "1) New installation  2) Existing installation" "$INSTALL_TYPE_DEFAULT"
# New install may prompt for sudo when fixing perms; existing installs never block on a password.
if [[ "${INSTALL_TYPE}" == "1" ]]; then
  export ALLOW_INTERACTIVE_SUDO=1
else
  export ALLOW_INTERACTIVE_SUDO=0
fi
# Default: Docker Compose (Host Apache remains available as choice 1).
prompt_choice DEPLOY_CHOICE "Deploy target" "1) Host Apache  2) Docker Compose" "2"
[[ "$DEPLOY_CHOICE" == "2" ]] && DEPLOY_MODE=docker || DEPLOY_MODE=host
export DEPLOY_MODE
export STAFF_ROOT="$ROOT"

SETUP_ERRORS=0
setup_warn() {
  echo "warn: $*" >&2
  SETUP_ERRORS=$((SETUP_ERRORS + 1))
  return 0
}

if [[ "$DEPLOY_MODE" == "docker" ]]; then
  echo "==> Checking Docker API access…"
  if ! staff_docker_resolve_access; then
    setup_warn "Docker deploy selected but this user cannot use docker.sock — fix group membership (see above) or choose Host Apache"
    # Continue so env can still be written; Compose steps will skip/fail clearly.
  fi
fi

# Default: External MySQL (bundled Compose MySQL is opt-in). Always collect credentials.
DB_DEFAULT=2
prompt_choice DB_CHOICE "Database" "1) Docker bundled MySQL  2) External MySQL" "$DB_DEFAULT"
case "$DB_CHOICE" in
  1) DB_MODE=bundled ;;
  *) DB_MODE=external ;;
esac

ROOT_ENV="$ROOT/.env"
env_ensure_file "$ROOT_ENV" "$ROOT/scripts/setup/templates/root.env.example"
PREV_WEB_ROOT="$(env_get "$ROOT_ENV" WEB_ROOT)"

DEFAULT_BASE="$(env_get "$ROOT_ENV" BASE_URL)"
DEFAULT_BASE="${DEFAULT_BASE%/}"
if [[ -z "$DEFAULT_BASE" ]]; then
  if [[ "$DEPLOY_MODE" == "docker" ]]; then
    DEFAULT_BASE="http://localhost:8088/${DEFAULT_WEB_ROOT}"
  else
    DEFAULT_BASE="http://localhost/${DEFAULT_WEB_ROOT}"
  fi
fi

prompt_value PUBLIC_BASE "Public base URL (host; folder name ${DEFAULT_WEB_ROOT} is appended if missing)" "$DEFAULT_BASE"
# Always adopt the checkout folder name as the public Alias / URL prefix.
_pb_host="$(printf '%s' "${PUBLIC_BASE}" | sed -E 's#^(https?://[^/]+).*#\1#')"
if [[ -n "$_pb_host" && "$_pb_host" == http* ]]; then
  PUBLIC_BASE="${_pb_host}/${DEFAULT_WEB_ROOT}"
fi
setup_map_urls
# Re-assert folder-derived web root (do not trust a mismatched path segment).
if [[ "$WEB_ROOT" != "$DEFAULT_WEB_ROOT" ]]; then
  echo "    Note: URL path /${WEB_ROOT} overridden by folder name /${DEFAULT_WEB_ROOT}"
  PUBLIC_BASE="${_pb_host}/${DEFAULT_WEB_ROOT}"
  setup_map_urls
fi
echo "    Web folder / Alias: /${WEB_ROOT} (from $(basename "$ROOT"))"
echo "    Portal API APP_URL: ${STAFF_PORTAL_APP_URL}"
echo "    SPA URL:            ${STAFF_PORTAL_SPA_URL}"
if [[ -n "${PREV_WEB_ROOT:-}" && "$PREV_WEB_ROOT" != "$WEB_ROOT" ]]; then
  echo "    Web root changed: ${PREV_WEB_ROOT} → ${WEB_ROOT} (frontend must be rebuilt)"
fi

# CI3 / shared uploads site id — must match this deploy (avoid migrating into another site's tree)
STAFF_SITE_ID_DEFAULT="$(env_get "$ROOT_ENV" STAFF_SITE_ID)"
if [[ -z "$STAFF_SITE_ID_DEFAULT" ]]; then
  STAFF_SITE_ID_DEFAULT="$(setup_derive_site_id "$BASE_URL")"
fi
prompt_value STAFF_SITE_ID "STAFF_SITE_ID (CI3 uploads /var/staffdata/{id})" "$STAFF_SITE_ID_DEFAULT"
STAFF_SITE_ID="${STAFF_SITE_ID:-$STAFF_SITE_ID_DEFAULT}"
setup_apply_storage_paths
echo "    Uploads root: ${STAFF_PORTAL_UPLOADS_ROOT}"
echo "    Data root:    ${STAFF_DATA_ROOT}"

JWT_DEFAULT="$(env_get "$ROOT_ENV" JWT_SECRET)"
if setup_secret_is_placeholder "$JWT_DEFAULT"; then
  JWT_DEFAULT="$(setup_rand_hex 32)"
fi
prompt_secret JWT_SECRET "JWT_SECRET (shared SSO)" "$JWT_DEFAULT"
if setup_secret_is_placeholder "${JWT_SECRET:-}"; then
  JWT_SECRET="$(setup_rand_hex 32)"
  echo "    generated JWT_SECRET (was empty)"
fi

SESSION_SECRET_DEFAULT="$(env_get "$ROOT_ENV" SESSION_SECRET)"
if setup_secret_is_placeholder "$SESSION_SECRET_DEFAULT"; then
  SESSION_SECRET_DEFAULT="$(setup_rand_hex 32)"
fi
prompt_secret SESSION_SECRET "SESSION_SECRET (shared session signing)" "$SESSION_SECRET_DEFAULT"
if setup_secret_is_placeholder "${SESSION_SECRET:-}"; then
  SESSION_SECRET="$(setup_rand_hex 32)"
  echo "    generated SESSION_SECRET (was empty)"
fi

prompt_value STAFF_API_USERNAME "STAFF_API_USERNAME" "$(env_get "$ROOT_ENV" STAFF_API_USERNAME)"
_api_pw_def="$(env_get "$ROOT_ENV" STAFF_API_PASSWORD)"
if setup_secret_is_placeholder "$_api_pw_def" && [[ "$INSTALL_TYPE" == "1" ]]; then
  _api_pw_def="$(setup_rand_hex 24)"
fi
prompt_secret STAFF_API_PASSWORD "STAFF_API_PASSWORD" "$_api_pw_def"
if setup_secret_is_placeholder "${STAFF_API_PASSWORD:-}" && [[ "$INSTALL_TYPE" == "1" ]]; then
  STAFF_API_PASSWORD="$(setup_rand_hex 24)"
  echo "    generated STAFF_API_PASSWORD (was empty)"
fi

# Must match staff-portal Share `config('share.api_token')` (not a random secret).
SHARE_API_TOKEN_DEFAULT='YWZyY2FjZGNzdGFmZnRyYWNrZXI'
_api_tok_def="$(env_get "$ROOT_ENV" STAFF_API_TOKEN)"
if setup_secret_is_placeholder "$_api_tok_def"; then
  _api_tok_def="$SHARE_API_TOKEN_DEFAULT"
fi
prompt_value STAFF_API_TOKEN "STAFF_API_TOKEN (Staff Share path token)" "$_api_tok_def"
if setup_secret_is_placeholder "${STAFF_API_TOKEN:-}"; then
  STAFF_API_TOKEN="$SHARE_API_TOKEN_DEFAULT"
  echo "    using default STAFF_API_TOKEN (Staff Share API)"
fi

# --- Microsoft Entra SSO + Graph mail (single Azure app → EXCHANGE_* in root .env) ---
echo
echo "==> Auth (Microsoft SSO + password login)"
# Defaults mode: keep existing Azure values (do not force empty prompts).
_ms_default=1
if [[ "$SETUP_ASSUME_DEFAULTS" == "1" ]]; then
  _ms_default=2
fi
prompt_choice CONFIGURE_MS "Configure Microsoft Entra (Azure AD) for login + Graph mail?" "1) Yes  2) Keep existing / skip" "$_ms_default"
# Prefer EXCHANGE_*; fall back to legacy TENANT_ID / CLIENT_* / MICROSOFT_* in root .env.
_ex_tenant="$(env_get "$ROOT_ENV" EXCHANGE_TENANT_ID)"
[[ -z "$_ex_tenant" ]] && _ex_tenant="$(env_get "$ROOT_ENV" MICROSOFT_TENANT_ID)"
[[ -z "$_ex_tenant" ]] && _ex_tenant="$(env_get "$ROOT_ENV" TENANT_ID)"
_ex_client="$(env_get "$ROOT_ENV" EXCHANGE_CLIENT_ID)"
[[ -z "$_ex_client" ]] && _ex_client="$(env_get "$ROOT_ENV" MICROSOFT_CLIENT_ID)"
[[ -z "$_ex_client" ]] && _ex_client="$(env_get "$ROOT_ENV" CLIENT_ID)"
_ex_secret="$(env_get "$ROOT_ENV" EXCHANGE_CLIENT_SECRET)"
[[ -z "$_ex_secret" ]] && _ex_secret="$(env_get "$ROOT_ENV" MICROSOFT_CLIENT_SECRET)"
[[ -z "$_ex_secret" ]] && _ex_secret="$(env_get "$ROOT_ENV" CLIENT_SEC_VALUE)"
if [[ "$CONFIGURE_MS" == "1" ]]; then
  prompt_value EXCHANGE_TENANT_ID "EXCHANGE_TENANT_ID (Directory ID)" "$_ex_tenant"
  prompt_value EXCHANGE_CLIENT_ID "EXCHANGE_CLIENT_ID (Application ID)" "$_ex_client"
  prompt_secret EXCHANGE_CLIENT_SECRET "EXCHANGE_CLIENT_SECRET" "$_ex_secret"
  prompt_value CLIENT_SEC_ID "CLIENT_SEC_ID (optional secret id)" "$(env_get "$ROOT_ENV" CLIENT_SEC_ID)"
else
  EXCHANGE_TENANT_ID="$_ex_tenant"
  EXCHANGE_CLIENT_ID="$_ex_client"
  EXCHANGE_CLIENT_SECRET="$_ex_secret"
  CLIENT_SEC_ID="$(env_get "$ROOT_ENV" CLIENT_SEC_ID)"
fi
# Legacy aliases for older readers / load-staff-root-env.php mirroring
TENANT_ID="${EXCHANGE_TENANT_ID:-}"
CLIENT_ID="${EXCHANGE_CLIENT_ID:-}"
CLIENT_SEC_VALUE="${EXCHANGE_CLIENT_SECRET:-}"
# Per-app Microsoft redirect URIs (must be registered in Azure)
MICROSOFT_REDIRECT_URI_PORTAL="${PUBLIC_BASE}/backend/auth/microsoft/callback"
MICROSOFT_REDIRECT_URI_APM="${PUBLIC_BASE}/apm/oauth/callback"
MICROSOFT_REDIRECT_URI="$MICROSOFT_REDIRECT_URI_PORTAL"
echo "    Portal MS redirect: $MICROSOFT_REDIRECT_URI_PORTAL"
echo "    APM MS redirect:    $MICROSOFT_REDIRECT_URI_APM"

PW_LOGIN_DEFAULT=2
[[ "$SITE_KIND" == "demo" ]] && PW_LOGIN_DEFAULT=1
_existing_pw="$(env_get "$ROOT_ENV" ALLOW_ALTERNATIVE_LOGIN)"
case "$(printf '%s' "$_existing_pw" | tr '[:upper:]' '[:lower:]')" in
  true|1|yes) PW_LOGIN_DEFAULT=1 ;;
  false|0|no) PW_LOGIN_DEFAULT=2 ;;
esac
prompt_choice ALLOW_PW_CHOICE "Password (email) login on SPA?" "1) Enabled  2) Disabled (Microsoft only)" "$PW_LOGIN_DEFAULT"
if [[ "$ALLOW_PW_CHOICE" == "1" ]]; then
  ALLOW_ALTERNATIVE_LOGIN=true
else
  ALLOW_ALTERNATIVE_LOGIN=false
fi
echo "    ALLOW_ALTERNATIVE_LOGIN=$ALLOW_ALTERNATIVE_LOGIN"

# --- Outbound mail (shared transport + creds; per-app only FROM name/address) ---
echo
echo "==> Outbound mail"
_existing_transport="$(env_get "$ROOT_ENV" MAIL_TRANSPORT)"
[[ -z "$_existing_transport" ]] && _existing_transport="$(env_get "$ROOT_ENV" MAIL_MAILER)"
MAIL_TRANSPORT_DEFAULT=1
case "$(printf '%s' "$_existing_transport" | tr '[:upper:]' '[:lower:]')" in
  exchange|exchange_oauth|graph) MAIL_TRANSPORT_DEFAULT=2 ;;
  smtp) MAIL_TRANSPORT_DEFAULT=3 ;;
  zoho) MAIL_TRANSPORT_DEFAULT=4 ;;
  http|notifications|api_http|"") MAIL_TRANSPORT_DEFAULT=1 ;;
esac
prompt_choice MAIL_TRANSPORT_CHOICE "Outbound mail transport?" \
  "1) HTTP notifications.africacdc.org (default)  2) Exchange / Microsoft Graph  3) SMTP  4) Zoho SMTP" \
  "$MAIL_TRANSPORT_DEFAULT"
case "$MAIL_TRANSPORT_CHOICE" in
  2) MAIL_TRANSPORT=exchange ;;
  3) MAIL_TRANSPORT=smtp ;;
  4) MAIL_TRANSPORT=zoho ;;
  *) MAIL_TRANSPORT=http ;;
esac
echo "    MAIL_TRANSPORT=$MAIL_TRANSPORT"

_mail_from_def="$(env_get "$ROOT_ENV" MAIL_FROM_ADDRESS)"
[[ -z "$_mail_from_def" ]] && _mail_from_def="$(env_get "$ROOT_ENV" MAIL_USERNAME)"
# Do not ship a public default address — operator must enter notifications email
# (defaults mode keeps existing .env or MAIL_USERNAME).
prompt_required_email MAIL_FROM_ADDRESS_SHARED \
  "Notifications / send-as email (MAIL_FROM_ADDRESS)" \
  "$_mail_from_def"
echo "    MAIL_FROM_ADDRESS=$MAIL_FROM_ADDRESS_SHARED"

# Exchange Graph creds — needed for Exchange outbound and helpdesk mailbox intake
EXCHANGE_FROM_MS=0
if [[ "$MAIL_TRANSPORT" == "exchange" ]]; then
  EXCHANGE_FROM_MS=1
elif [[ -n "${TENANT_ID:-}" && -n "${CLIENT_ID:-}" ]]; then
  prompt_choice EXCHANGE_MS_CHOICE "Also sync EXCHANGE_* Graph creds (helpdesk intake / fallback)?" "1) Yes  2) No" "1"
  [[ "$EXCHANGE_MS_CHOICE" == "1" ]] && EXCHANGE_FROM_MS=1
fi
EXCHANGE_SCOPE="${EXCHANGE_SCOPE:-https://graph.microsoft.com/.default}"
EXCHANGE_AUTH_METHOD="${EXCHANGE_AUTH_METHOD:-client_credentials}"
_ex_scope="$(env_get "$ROOT_ENV" EXCHANGE_SCOPE)"
[[ -n "$_ex_scope" ]] && EXCHANGE_SCOPE="$_ex_scope"
_ex_auth="$(env_get "$ROOT_ENV" EXCHANGE_AUTH_METHOD)"
[[ -n "$_ex_auth" ]] && EXCHANGE_AUTH_METHOD="$_ex_auth"

EXCHANGE_REDIRECT_URI_PORTAL="${PUBLIC_BASE}/backend/oauth/callback"
EXCHANGE_REDIRECT_URI_APM="${PUBLIC_BASE}/apm/callback"
EXCHANGE_REDIRECT_URI_HELPDESK="${PUBLIC_BASE}/helpdesk/backend/oauth/callback"

# SMTP / Zoho shared credentials
MAIL_HOST="$(env_get "$ROOT_ENV" MAIL_HOST)"
MAIL_PORT="$(env_get "$ROOT_ENV" MAIL_PORT)"
MAIL_USERNAME="$(env_get "$ROOT_ENV" MAIL_USERNAME)"
MAIL_PASSWORD="$(env_get "$ROOT_ENV" MAIL_PASSWORD)"
MAIL_ENCRYPTION="$(env_get "$ROOT_ENV" MAIL_ENCRYPTION)"
MAIL_ENCRYPTION="${MAIL_ENCRYPTION:-tls}"
if [[ "$MAIL_TRANSPORT" == "smtp" || "$MAIL_TRANSPORT" == "zoho" ]]; then
  if [[ "$MAIL_TRANSPORT" == "zoho" ]]; then
    MAIL_HOST="${MAIL_HOST:-smtp.zoho.com}"
    MAIL_PORT="${MAIL_PORT:-587}"
  else
    MAIL_HOST="${MAIL_HOST:-smtp.office365.com}"
    MAIL_PORT="${MAIL_PORT:-587}"
  fi
  prompt_value MAIL_HOST "MAIL_HOST" "$MAIL_HOST"
  prompt_value MAIL_PORT "MAIL_PORT" "$MAIL_PORT"
  prompt_value MAIL_USERNAME "MAIL_USERNAME" "${MAIL_USERNAME:-$MAIL_FROM_ADDRESS_SHARED}"
  prompt_secret MAIL_PASSWORD "MAIL_PASSWORD" "$MAIL_PASSWORD"
  prompt_value MAIL_ENCRYPTION "MAIL_ENCRYPTION (tls|ssl|none)" "$MAIL_ENCRYPTION"
fi

# HTTP Africa CDC Email Server
MAIL_HTTP_BASE_URL="$(env_get "$ROOT_ENV" MAIL_HTTP_BASE_URL)"
MAIL_HTTP_CLIENT_ID="$(env_get "$ROOT_ENV" MAIL_HTTP_CLIENT_ID)"
MAIL_HTTP_CLIENT_SECRET="$(env_get "$ROOT_ENV" MAIL_HTTP_CLIENT_SECRET)"
MAIL_HTTP_BASE_URL="${MAIL_HTTP_BASE_URL:-https://notifications.africacdc.org/api/v1}"
if [[ "$MAIL_TRANSPORT" == "http" ]]; then
  prompt_value MAIL_HTTP_BASE_URL "MAIL_HTTP_BASE_URL" "$MAIL_HTTP_BASE_URL"
  prompt_value MAIL_HTTP_CLIENT_ID "MAIL_HTTP_CLIENT_ID (integration)" "$MAIL_HTTP_CLIENT_ID"
  prompt_secret MAIL_HTTP_CLIENT_SECRET "MAIL_HTTP_CLIENT_SECRET" "$MAIL_HTTP_CLIENT_SECRET"
fi

# Map to Laravel MAIL_MAILER name
case "$MAIL_TRANSPORT" in
  smtp|zoho) MAIL_MAILER=smtp ;;
  http) MAIL_MAILER=http ;;
  *) MAIL_MAILER=exchange ;;
esac
USE_EXCHANGE_EMAIL=false
[[ "$MAIL_TRANSPORT" == "exchange" ]] && USE_EXCHANGE_EMAIL=true

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
else
  _dh="$DB_HOST"
  [[ -z "$_dh" && "$DEPLOY_MODE" == "docker" ]] && _dh=host.docker.internal
  [[ -z "$_dh" ]] && _dh=127.0.0.1
  prompt_value DB_HOST "DB_HOST" "$_dh"
  prompt_value DB_PORT "DB_PORT" "${DB_PORT:-3306}"
  prompt_value DB_USER "DB_USER" "${DB_USER:-root}"
  prompt_secret DB_PASS "DB_PASS" "$DB_PASS"
  prompt_value DB_NAME "Root DB_NAME" "${DB_NAME:-staff}"
fi

DB_USERNAME="${DB_USER:-}"
DB_PASSWORD="${DB_PASS:-}"

echo
echo "==> Writing root .env"
env_set "$ROOT_ENV" SITE_KIND "$SITE_KIND"
env_set "$ROOT_ENV" APP_ENV "$APP_ENV"
env_set "$ROOT_ENV" APP_DEBUG "$APP_DEBUG"
env_set "$ROOT_ENV" LOG_CHANNEL "stack"
env_set "$ROOT_ENV" LOG_STACK "daily"
env_set "$ROOT_ENV" LOG_DAILY_DAYS "${LOG_DAILY_DAYS:-14}"
env_set "$ROOT_ENV" TELEMETRY_PROVIDER "${TELEMETRY_PROVIDER:-none}"
env_set "$ROOT_ENV" CRITICAL_ALERT_ENABLED "${CRITICAL_ALERT_ENABLED:-false}"
env_set "$ROOT_ENV" CRITICAL_ALERT_EMAILS "${CRITICAL_ALERT_EMAILS:-}"
env_set "$ROOT_ENV" CRITICAL_ALERT_MIN_LEVEL "${CRITICAL_ALERT_MIN_LEVEL:-error}"
env_set "$ROOT_ENV" CRITICAL_ALERT_COOLDOWN_MINUTES "${CRITICAL_ALERT_COOLDOWN_MINUTES:-30}"
env_set "$ROOT_ENV" DEPLOY_MODE "$DEPLOY_MODE"
env_set "$ROOT_ENV" BASE_URL "$BASE_URL"
env_set "$ROOT_ENV" CI_BASE_URL "$CI_BASE_URL"
env_set "$ROOT_ENV" APM_BASE_URL "$APM_BASE_URL"
env_set "$ROOT_ENV" WEB_ROOT "$WEB_ROOT"
env_set "$ROOT_ENV" STAFF_SITE_ID "$STAFF_SITE_ID"
env_set "$ROOT_ENV" STAFF_HOST_DATA_ROOT "$STAFF_HOST_DATA_ROOT"
env_set "$ROOT_ENV" STAFF_DATA_ROOT "$STAFF_DATA_ROOT"
env_set "$ROOT_ENV" STAFF_USE_HOST_STORAGE "true"
env_set "$ROOT_ENV" STAFF_PORTAL_UPLOADS_ROOT "$STAFF_PORTAL_UPLOADS_ROOT"
env_set "$ROOT_ENV" JWT_SECRET "$JWT_SECRET"
env_set "$ROOT_ENV" SESSION_SECRET "${SESSION_SECRET:-}"
env_set "$ROOT_ENV" STAFF_API_USERNAME "$STAFF_API_USERNAME"
env_set "$ROOT_ENV" STAFF_API_PASSWORD "$STAFF_API_PASSWORD"
env_set "$ROOT_ENV" STAFF_API_TOKEN "$STAFF_API_TOKEN"
env_set "$ROOT_ENV" ALLOW_ALTERNATIVE_LOGIN "$ALLOW_ALTERNATIVE_LOGIN"
env_set "$ROOT_ENV" MICROSOFT_REDIRECT_URI "$MICROSOFT_REDIRECT_URI_PORTAL"
# Canonical EXCHANGE_* in root .env (+ legacy aliases mirrored for older code).
if [[ -n "${EXCHANGE_TENANT_ID:-}" ]]; then
  env_set "$ROOT_ENV" EXCHANGE_TENANT_ID "$EXCHANGE_TENANT_ID"
  env_set "$ROOT_ENV" MICROSOFT_TENANT_ID "$EXCHANGE_TENANT_ID"
  env_set "$ROOT_ENV" TENANT_ID "$EXCHANGE_TENANT_ID"
fi
if [[ -n "${EXCHANGE_CLIENT_ID:-}" ]]; then
  env_set "$ROOT_ENV" EXCHANGE_CLIENT_ID "$EXCHANGE_CLIENT_ID"
  env_set "$ROOT_ENV" MICROSOFT_CLIENT_ID "$EXCHANGE_CLIENT_ID"
  env_set "$ROOT_ENV" CLIENT_ID "$EXCHANGE_CLIENT_ID"
fi
if [[ -n "${EXCHANGE_CLIENT_SECRET:-}" ]]; then
  env_set "$ROOT_ENV" EXCHANGE_CLIENT_SECRET "$EXCHANGE_CLIENT_SECRET"
  env_set "$ROOT_ENV" MICROSOFT_CLIENT_SECRET "$EXCHANGE_CLIENT_SECRET"
  env_set "$ROOT_ENV" CLIENT_SEC_VALUE "$EXCHANGE_CLIENT_SECRET"
fi
if [[ -n "${CLIENT_SEC_ID:-}" ]]; then
  env_set "$ROOT_ENV" CLIENT_SEC_ID "$CLIENT_SEC_ID"
fi
if [[ "$EXCHANGE_FROM_MS" == "1" ]]; then
  env_set "$ROOT_ENV" EXCHANGE_REDIRECT_URI "$EXCHANGE_REDIRECT_URI_PORTAL"
  env_set "$ROOT_ENV" EXCHANGE_SCOPE "$EXCHANGE_SCOPE"
  env_set "$ROOT_ENV" EXCHANGE_AUTH_METHOD "$EXCHANGE_AUTH_METHOD"
fi
env_set "$ROOT_ENV" MAIL_TRANSPORT "$MAIL_TRANSPORT"
env_set "$ROOT_ENV" MAIL_MAILER "$MAIL_MAILER"
env_set "$ROOT_ENV" USE_EXCHANGE_EMAIL "$USE_EXCHANGE_EMAIL"
env_set "$ROOT_ENV" MAIL_FROM_ADDRESS "$MAIL_FROM_ADDRESS_SHARED"
# Portal mail hub: modules POST to Share by default (auto = portal then local fallback)
_existing_dispatch="$(env_get "$ROOT_ENV" STAFF_MAIL_DISPATCH)"
env_set "$ROOT_ENV" STAFF_MAIL_DISPATCH "${_existing_dispatch:-auto}"
if [[ -z "$(env_get "$ROOT_ENV" STAFF_MAIL_CONFIG_KEY)" ]]; then
  env_set "$ROOT_ENV" STAFF_MAIL_CONFIG_KEY ""
fi
if [[ "$MAIL_TRANSPORT" == "smtp" || "$MAIL_TRANSPORT" == "zoho" ]]; then
  env_set "$ROOT_ENV" MAIL_HOST "$MAIL_HOST"
  env_set "$ROOT_ENV" MAIL_PORT "$MAIL_PORT"
  env_set "$ROOT_ENV" MAIL_USERNAME "$MAIL_USERNAME"
  [[ -n "${MAIL_PASSWORD:-}" ]] && env_set "$ROOT_ENV" MAIL_PASSWORD "$MAIL_PASSWORD"
  env_set "$ROOT_ENV" MAIL_ENCRYPTION "$MAIL_ENCRYPTION"
fi
if [[ "$MAIL_TRANSPORT" == "http" ]]; then
  env_set "$ROOT_ENV" MAIL_HTTP_BASE_URL "$MAIL_HTTP_BASE_URL"
  [[ -n "${MAIL_HTTP_CLIENT_ID:-}" ]] && env_set "$ROOT_ENV" MAIL_HTTP_CLIENT_ID "$MAIL_HTTP_CLIENT_ID"
  [[ -n "${MAIL_HTTP_CLIENT_SECRET:-}" ]] && env_set "$ROOT_ENV" MAIL_HTTP_CLIENT_SECRET "$MAIL_HTTP_CLIENT_SECRET"
fi
env_set "$ROOT_ENV" DB_HOST "$DB_HOST"
env_set "$ROOT_ENV" DB_PORT "$DB_PORT"
env_set "$ROOT_ENV" DB_USER "$DB_USER"
env_set "$ROOT_ENV" DB_PASS "$DB_PASS"
env_set "$ROOT_ENV" DB_NAME "$DB_NAME"

# APP_ENV / APP_DEBUG from site role (production → debug off on every module).
# Also force daily log files (laravel-YYYY-MM-DD.log) for settings log viewers.
apply_app_runtime_to_file() {
  local file="$1"
  [[ -f "$file" ]] || return 0
  env_set "$file" APP_ENV "${APP_ENV:-production}" || return 1
  env_set "$file" APP_DEBUG "${APP_DEBUG:-false}" || return 1
  env_set "$file" LOG_CHANNEL "stack" || return 1
  env_set "$file" LOG_STACK "daily" || return 1
  env_set "$file" LOG_DAILY_DAYS "${LOG_DAILY_DAYS:-14}" || return 1
  env_set "$file" TELEMETRY_PROVIDER "${TELEMETRY_PROVIDER:-none}" || return 1
  env_set "$file" CRITICAL_ALERT_ENABLED "${CRITICAL_ALERT_ENABLED:-false}" || return 1
  env_set "$file" CRITICAL_ALERT_MIN_LEVEL "${CRITICAL_ALERT_MIN_LEVEL:-error}" || return 1
  env_set "$file" CRITICAL_ALERT_COOLDOWN_MINUTES "${CRITICAL_ALERT_COOLDOWN_MINUTES:-30}" || return 1
  return 0
}

apply_db_to_file() {
  local file="$1" user_key="${2:-DB_USERNAME}" pass_key="${3:-DB_PASSWORD}"
  env_set "$file" DB_CONNECTION "mysql" || return 1
  env_set "$file" DB_HOST "${DB_HOST:-}" || return 1
  env_set "$file" DB_PORT "${DB_PORT:-}" || return 1
  env_set "$file" "$user_key" "${DB_USER:-}" || return 1
  env_set "$file" "$pass_key" "${DB_PASS:-}" || return 1
  return 0
}

apply_redis_to_file() {
  local file="$1"
  env_set "$file" REDIS_HOST "${REDIS_HOST:-}" || return 1
  env_set "$file" REDIS_PORT "${REDIS_PORT:-}" || return 1
  # Important: do not let a failed [[ ]] be the function's last status under set -e
  # when REDIS_PASSWORD is empty (that previously aborted ./setup.sh mid-module).
  if [[ -n "${REDIS_PASSWORD:-}" ]]; then
    env_set "$file" REDIS_PASSWORD "$REDIS_PASSWORD" || return 1
  fi
  return 0
}

# CI3 / host uploads identity — same site id on every module .env
apply_storage_to_file() {
  local file="$1"
  env_set "$file" STAFF_SITE_ID "$STAFF_SITE_ID" || return 1
  env_set "$file" STAFF_HOST_DATA_ROOT "$STAFF_HOST_DATA_ROOT" || return 1
  env_set "$file" STAFF_DATA_ROOT "$STAFF_DATA_ROOT" || return 1
  env_set "$file" STAFF_USE_HOST_STORAGE "true" || return 1
  env_set "$file" STAFF_PORTAL_UPLOADS_ROOT "$STAFF_PORTAL_UPLOADS_ROOT" || return 1
  return 0
}

# Per-app OAuth redirects only. Graph/JWT secrets stay in /staff/.env
# (shared/load-staff-root-env.php). Arg2 = MICROSOFT_REDIRECT_URI.
# Arg3 unused (kept for call-site compat). Arg4 = EXCHANGE_REDIRECT_URI.
apply_microsoft_sso_to_file() {
  local file="$1" redirect="${2:-}" _with_exchange="${3:-0}" exchange_redirect="${4:-}"
  if [[ -n "$redirect" ]]; then
    env_set "$file" MICROSOFT_REDIRECT_URI "$redirect" || return 1
  fi
  if [[ -n "$exchange_redirect" ]]; then
    env_set "$file" EXCHANGE_REDIRECT_URI "$exchange_redirect" || return 1
  fi
  return 0
}

# Shared outbound mail transport + credentials (all mail-sending apps).
# Per-app FROM is applied separately via apply_mail_from_to_file.
apply_mail_shared_to_file() {
  local file="$1"
  env_set "$file" MAIL_TRANSPORT "$MAIL_TRANSPORT" || return 1
  env_set "$file" MAIL_MAILER "$MAIL_MAILER" || return 1
  env_set "$file" USE_EXCHANGE_EMAIL "$USE_EXCHANGE_EMAIL" || return 1
  if [[ "$MAIL_TRANSPORT" == "smtp" || "$MAIL_TRANSPORT" == "zoho" ]]; then
    env_set "$file" MAIL_HOST "${MAIL_HOST:-}" || return 1
    env_set "$file" MAIL_PORT "${MAIL_PORT:-587}" || return 1
    env_set "$file" MAIL_USERNAME "${MAIL_USERNAME:-}" || return 1
    if [[ -n "${MAIL_PASSWORD:-}" ]]; then
      env_set "$file" MAIL_PASSWORD "$MAIL_PASSWORD" || return 1
    fi
    env_set "$file" MAIL_ENCRYPTION "${MAIL_ENCRYPTION:-tls}" || return 1
    # APM PHPMailer aliases
    env_set "$file" PHPMailer_HOST "${MAIL_HOST:-}" || return 1
    env_set "$file" PHPMailer_PORT "${MAIL_PORT:-587}" || return 1
    env_set "$file" PHPMailer_USERNAME "${MAIL_USERNAME:-}" || return 1
    if [[ -n "${MAIL_PASSWORD:-}" ]]; then
      env_set "$file" PHPMailer_PASSWORD "$MAIL_PASSWORD" || return 1
    fi
  fi
  if [[ "$MAIL_TRANSPORT" == "http" ]]; then
    env_set "$file" MAIL_HTTP_BASE_URL "${MAIL_HTTP_BASE_URL:-https://notifications.africacdc.org/api/v1}" || return 1
    if [[ -n "${MAIL_HTTP_CLIENT_ID:-}" ]]; then
      env_set "$file" MAIL_HTTP_CLIENT_ID "$MAIL_HTTP_CLIENT_ID" || return 1
    fi
    if [[ -n "${MAIL_HTTP_CLIENT_SECRET:-}" ]]; then
      env_set "$file" MAIL_HTTP_CLIENT_SECRET "$MAIL_HTTP_CLIENT_SECRET" || return 1
    fi
  fi
  return 0
}

apply_mail_from_to_file() {
  local file="$1" from_addr="$2" from_name="$3"
  env_set "$file" MAIL_FROM_ADDRESS "$from_addr" || return 1
  env_set "$file" MAIL_FROM_NAME "$from_name" || return 1
  env_set "$file" PHPMailer_FROM_ADDRESS "$from_addr" || return 1
  env_set "$file" PHPMailer_FROM_NAME "$from_name" || return 1
  return 0
}

# Portal SPA password login — staff-portal only
apply_password_login_to_file() {
  local file="$1"
  env_set "$file" ALLOW_ALTERNATIVE_LOGIN "$ALLOW_ALTERNATIVE_LOGIN" || return 1
  return 0
}

echo
echo "==> Updating .htaccess public path → /${WEB_ROOT}/"
setup_update_htaccess_tree "$ROOT" "$WEB_ROOT"

# ----- staff-portal -----
echo
echo "==> staff-portal"
SP_SETUP="$ROOT/modules/staff-portal/setup.env"
SP_ENV="$ROOT/modules/staff-portal/backend/.env"
env_ensure_file "$SP_SETUP" "$ROOT/modules/staff-portal/setup.env.example" \
  || setup_warn "staff-portal setup.env missing/unwritable"
env_ensure_file "$SP_ENV" "$ROOT/modules/staff-portal/backend/.env.example" \
  || setup_warn "staff-portal backend/.env missing/unwritable"
prompt_value SP_DB "staff-portal DB_DATABASE" "$(env_get "$SP_SETUP" DB_DATABASE)"
SP_DB="${SP_DB:-staff}"
_sp_from_name="$(env_get "$SP_ENV" MAIL_FROM_NAME)"
_sp_from_addr="$(env_get "$SP_ENV" MAIL_FROM_ADDRESS)"
prompt_value SP_MAIL_FROM_NAME "staff-portal MAIL_FROM_NAME" "${_sp_from_name:-Staff Portal}"
prompt_value SP_MAIL_FROM_ADDRESS "staff-portal MAIL_FROM_ADDRESS" \
  "${_sp_from_addr:-$MAIL_FROM_ADDRESS_SHARED}"
write_staff_portal_env() {
  local f
  for f in "$SP_SETUP" "$SP_ENV"; do
    apply_app_runtime_to_file "$f" || return 1
    env_set "$f" APP_URL "$STAFF_PORTAL_APP_URL" || return 1
    env_set "$f" STAFF_PORTAL_BASE_URL "$STAFF_PORTAL_APP_URL" || return 1
    env_set "$f" STAFF_PORTAL_SPA_URL "$STAFF_PORTAL_SPA_URL" || return 1
    env_set "$f" STAFF_PORTAL_SPA_ENABLED "true" || return 1
    env_set "$f" BASE_URL "$BASE_URL" || return 1
    env_set "$f" APM_BASE_URL "$APM_BASE_URL" || return 1
    env_set "$f" SESSION_PATH "${PUBLIC_PATH}/" || return 1
    env_set "$f" WEB_ROOT "$WEB_ROOT" || return 1
    # JWT_SECRET / EXCHANGE_* secrets: staff root .env only (load-staff-root-env.php)
    apply_password_login_to_file "$f" || return 1
    apply_microsoft_sso_to_file "$f" "$MICROSOFT_REDIRECT_URI_PORTAL" "$EXCHANGE_FROM_MS" \
      "$EXCHANGE_REDIRECT_URI_PORTAL" || return 1
    apply_mail_shared_to_file "$f" || return 1
    apply_mail_from_to_file "$f" "$SP_MAIL_FROM_ADDRESS" "$SP_MAIL_FROM_NAME" || return 1
    env_set "$f" DB_DATABASE "$SP_DB" || return 1
    apply_storage_to_file "$f" || return 1
    env_set "$f" STAFF_PORTAL_MODULE_FILES_ROOT "$STAFF_PORTAL_MODULE_FILES_ROOT" || return 1
    apply_db_to_file "$f" DB_USERNAME DB_PASSWORD || return 1
    apply_redis_to_file "$f" || return 1
  done
  env_set "$SP_SETUP" VITE_STAFF_PORTAL_API_BASE_URL "$VITE_STAFF_PORTAL_API_BASE_URL" || return 1
  env_set "$SP_SETUP" VITE_STAFF_PORTAL_BASE_PATH "$VITE_STAFF_PORTAL_BASE_PATH" || return 1
  env_set "$SP_SETUP" SITE_KIND "$SITE_KIND" || return 1
}
if write_staff_portal_env; then
  if "$ROOT/modules/staff-portal/scripts/configure-env.sh"; then
    echo "    configure-env OK"
  else
    setup_warn "staff-portal configure-env failed"
  fi
  write_staff_portal_env || setup_warn "staff-portal force env rewrite failed"
  echo "    forced URLs/JWT/Microsoft/password-login on setup.env + backend/.env"
else
  setup_warn "staff-portal env write failed — fix ownership (e.g. chown) and re-run"
fi

# ----- APM -----
echo
echo "==> APM"
APM_ENV="$ROOT/modules/apm/.env"
prompt_value APM_DB_DATABASE "APM DB_DATABASE" "$(env_get "$APM_ENV" DB_DATABASE)"
APM_DB_DATABASE="${APM_DB_DATABASE:-apm_local}"
_apm_from_name="$(env_get "$APM_ENV" MAIL_FROM_NAME)"
_apm_from_addr="$(env_get "$APM_ENV" MAIL_FROM_ADDRESS)"
prompt_value APM_MAIL_FROM_NAME "APM MAIL_FROM_NAME" "${_apm_from_name:-Africa CDC APM}"
prompt_value APM_MAIL_FROM_ADDRESS "APM MAIL_FROM_ADDRESS" \
  "${_apm_from_addr:-$MAIL_FROM_ADDRESS_SHARED}"
export APM_APP_URL BASE_URL CI_BASE_URL JWT_SECRET
export DB_HOST DB_PORT DB_USER DB_PASS DB_USERNAME DB_PASSWORD APM_DB_DATABASE
export REDIS_HOST REDIS_PORT REDIS_PASSWORD
export STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN STAFF_API_INTERNAL_BASE_URL
export TENANT_ID CLIENT_ID CLIENT_SEC_VALUE CLIENT_SEC_ID
export MICROSOFT_REDIRECT_URI_APM EXCHANGE_FROM_MS EXCHANGE_REDIRECT_URI_APM
export EXCHANGE_SCOPE EXCHANGE_AUTH_METHOD
export MAIL_TRANSPORT MAIL_MAILER USE_EXCHANGE_EMAIL
export MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_ENCRYPTION
export MAIL_HTTP_BASE_URL MAIL_HTTP_CLIENT_ID MAIL_HTTP_CLIENT_SECRET
export APM_MAIL_FROM_NAME APM_MAIL_FROM_ADDRESS
if "$ROOT/scripts/setup/configure-apm-env.sh"; then
  echo "    configure-apm-env OK"
else
  setup_warn "APM configure-apm-env failed"
fi
# Force again so empty-skip in configure-apm cannot leave stale APP_URL / debug
if apply_app_runtime_to_file "$APM_ENV" \
  && env_set "$APM_ENV" APP_URL "$APM_APP_URL" \
  && env_set "$APM_ENV" BASE_URL "$BASE_URL" \
  && env_set "$APM_ENV" CI_BASE_URL "$CI_BASE_URL" \
  && env_set "$APM_ENV" APM_BASE_URL "$APM_BASE_URL" \
  && env_set "$APM_ENV" DB_DATABASE "$APM_DB_DATABASE" \
  && env_set "$APM_ENV" STAFF_API_USERNAME "${STAFF_API_USERNAME:-}" \
  && env_set "$APM_ENV" STAFF_API_PASSWORD "${STAFF_API_PASSWORD:-}" \
  && env_set "$APM_ENV" STAFF_API_TOKEN "${STAFF_API_TOKEN:-}" \
  && env_set "$APM_ENV" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL" \
  && apply_storage_to_file "$APM_ENV" \
  && env_set "$APM_ENV" STAFF_APM_FILES_ROOT "$STAFF_APM_FILES_ROOT" \
  && apply_db_to_file "$APM_ENV" DB_USERNAME DB_PASSWORD \
  && apply_redis_to_file "$APM_ENV" \
  && apply_microsoft_sso_to_file "$APM_ENV" "$MICROSOFT_REDIRECT_URI_APM" "$EXCHANGE_FROM_MS" \
       "$EXCHANGE_REDIRECT_URI_APM" \
  && apply_mail_shared_to_file "$APM_ENV" \
  && apply_mail_from_to_file "$APM_ENV" "$APM_MAIL_FROM_ADDRESS" "$APM_MAIL_FROM_NAME"
then
  echo "    forced APP_ENV/APP_DEBUG/URLs/JWT/Redis/storage/Microsoft/mail on modules/apm/.env"
else
  setup_warn "APM env write failed — fix ownership and re-run"
fi

# ----- finance -----
echo
echo "==> finance"
FN_SETUP="$ROOT/modules/finance/setup.env"
FN_ENV="$ROOT/modules/finance/.env"
env_ensure_file "$FN_SETUP" "$ROOT/modules/finance/setup.env.example" \
  || setup_warn "finance setup.env missing/unwritable"
env_ensure_file "$FN_ENV" "$ROOT/modules/finance/.env.example" \
  || setup_warn "finance .env missing/unwritable"
prompt_value FN_DB "finance DB_DATABASE" "$(env_get "$FN_SETUP" DB_DATABASE)"
FN_DB="${FN_DB:-finance}"
write_finance_env() {
  local f
  for f in "$FN_SETUP" "$FN_ENV" "$ROOT/modules/finance/backend/.env"; do
    if [[ "$f" == "$ROOT/modules/finance/backend/.env" ]]; then
      [[ -d "$ROOT/modules/finance/backend" ]] || continue
      if [[ ! -f "$f" ]]; then
        env_ensure_file "$f" "$ROOT/modules/finance/backend/.env.example" || continue
      fi
    fi
    apply_app_runtime_to_file "$f" || return 1
    env_set "$f" APP_URL "$FINANCE_APP_URL" || return 1
    env_set "$f" BASE_URL "$BASE_URL" || return 1
    env_set "$f" FINANCE_STAFF_PORTAL_URL "$STAFF_PORTAL_SPA_URL" || return 1
    env_set "$f" VITE_APP_BASE_PATH "$VITE_FINANCE_BASE_PATH" || return 1
    env_set "$f" SESSION_PATH "$FINANCE_SESSION_PATH" || return 1
    # JWT_SECRET / SESSION_SECRET: staff root .env only
    env_set "$f" STAFF_API_USERNAME "${STAFF_API_USERNAME:-}" || return 1
    env_set "$f" STAFF_API_PASSWORD "${STAFF_API_PASSWORD:-}" || return 1
    env_set "$f" STAFF_API_TOKEN "${STAFF_API_TOKEN:-}" || return 1
    env_set "$f" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL" || return 1
    env_set "$f" DB_DATABASE "$FN_DB" || return 1
    apply_storage_to_file "$f" || return 1
    apply_db_to_file "$f" DB_USERNAME DB_PASSWORD || return 1
    apply_redis_to_file "$f" || return 1
  done
}
if write_finance_env; then
  if "$ROOT/modules/finance/scripts/configure-env.sh"; then
    echo "    configure-env OK"
  else
    setup_warn "finance configure-env failed"
  fi
  write_finance_env || setup_warn "finance force env rewrite failed"
  echo "    forced URLs/JWT/Redis on setup.env + .env"
else
  setup_warn "finance env write failed — fix ownership and re-run"
fi

# ----- helpdesk -----
echo
echo "==> helpdesk"
HD_SETUP="$ROOT/modules/helpdesk/setup.env"
HD_ENV="$ROOT/modules/helpdesk/backend/.env"
env_ensure_file "$HD_SETUP" "$ROOT/modules/helpdesk/setup.env.example" \
  || setup_warn "helpdesk setup.env missing/unwritable"
env_ensure_file "$HD_ENV" "$ROOT/modules/helpdesk/backend/.env.example" \
  || setup_warn "helpdesk backend/.env missing/unwritable"
prompt_value HD_DB "helpdesk DB_DATABASE" "$(env_get "$HD_SETUP" DB_DATABASE)"
HD_DB="${HD_DB:-helpdesk}"
_hd_from_name="$(env_get "$HD_ENV" MAIL_FROM_NAME)"
_hd_from_addr="$(env_get "$HD_ENV" MAIL_FROM_ADDRESS)"
prompt_value HD_MAIL_FROM_NAME "helpdesk MAIL_FROM_NAME" "${_hd_from_name:-Africa CDC Helpdesk}"
prompt_value HD_MAIL_FROM_ADDRESS "helpdesk MAIL_FROM_ADDRESS" \
  "${_hd_from_addr:-$MAIL_FROM_ADDRESS_SHARED}"
write_helpdesk_env() {
  local f
  for f in "$HD_SETUP" "$HD_ENV"; do
    apply_app_runtime_to_file "$f" || return 1
    env_set "$f" APP_URL "$HELPDESK_APP_URL" || return 1
    env_set "$f" BASE_URL "$BASE_URL" || return 1
    env_set "$f" HELPDESK_FRONTEND_URL "$HELPDESK_FRONTEND_URL" || return 1
    env_set "$f" HELPDESK_STAFF_PORTAL_URL "$STAFF_PORTAL_SPA_URL" || return 1
    env_set "$f" HELPDESK_APM_BASE_URL "$APM_BASE_URL" || return 1
    # JWT_SECRET / EXCHANGE_* secrets: staff root .env only
    env_set "$f" STAFF_API_USERNAME "${STAFF_API_USERNAME:-}" || return 1
    env_set "$f" STAFF_API_PASSWORD "${STAFF_API_PASSWORD:-}" || return 1
    env_set "$f" STAFF_API_TOKEN "${STAFF_API_TOKEN:-}" || return 1
    env_set "$f" HELPDESK_STAFF_API_INTERNAL_BASE_URL "$HELPDESK_STAFF_API_INTERNAL_BASE_URL" || return 1
    env_set "$f" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL" || return 1
    env_set "$f" DB_DATABASE "$HD_DB" || return 1
    apply_storage_to_file "$f" || return 1
    env_set "$f" STAFF_HELPDESK_FILES_ROOT "$STAFF_HELPDESK_FILES_ROOT" || return 1
    # Per-app Graph OAuth redirect only (credentials in staff root .env).
    if [[ "$EXCHANGE_FROM_MS" == "1" ]]; then
      env_set "$f" EXCHANGE_REDIRECT_URI "$EXCHANGE_REDIRECT_URI_HELPDESK" || return 1
    fi
    apply_mail_shared_to_file "$f" || return 1
    apply_mail_from_to_file "$f" "$HD_MAIL_FROM_ADDRESS" "$HD_MAIL_FROM_NAME" || return 1
    env_set "$f" HELPDESK_MAIL_BRAND_NAME "$HD_MAIL_FROM_NAME" || return 1
    apply_db_to_file "$f" DB_USERNAME DB_PASSWORD || return 1
    apply_redis_to_file "$f" || return 1
  done
}
if write_helpdesk_env; then
  if "$ROOT/modules/helpdesk/scripts/configure-env.sh"; then
    echo "    configure-env OK"
  else
    setup_warn "helpdesk configure-env failed"
  fi
  write_helpdesk_env || setup_warn "helpdesk force env rewrite failed"
  echo "    forced URLs/JWT/Redis/mail on setup.env + backend/.env"
else
  setup_warn "helpdesk env write failed — fix ownership and re-run"
fi

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
    apply_app_runtime_to_file "$f" || return 1
    env_set "$f" APP_URL "${RISK_REGISTER_APP_URL}/backend" || return 1
    env_set "$f" BASE_URL "$BASE_URL" || return 1
    env_set "$f" DB_DATABASE "$RR_DB" || return 1
    apply_storage_to_file "$f" || return 1
    apply_db_to_file "$f" DB_USERNAME DB_PASSWORD || return 1
    apply_redis_to_file "$f" || return 1
  done
}
if write_risk_register_env; then
  if [[ -x "$ROOT/modules/risk-register/scripts/configure-env.sh" ]]; then
    if "$ROOT/modules/risk-register/scripts/configure-env.sh"; then
      echo "    configure-env OK"
    else
      setup_warn "risk-register configure-env failed"
    fi
  fi
  write_risk_register_env || setup_warn "risk-register force env rewrite failed"
  echo "    forced URLs/DB/Redis on setup.env + backend/.env"
else
  setup_warn "risk-register env write failed — fix ownership and re-run"
fi

# Writable Laravel dirs + public/storage after .env paths are known.
setup_fix_laravel_storage

# Drop stale config cache so APP_DEBUG=false takes effect immediately (Ignition).
if [[ "$SITE_KIND" == "production" ]]; then
  echo
  echo "==> Clearing Laravel config cache (production APP_DEBUG=false)"
  for _cfg_dir in \
    "$ROOT/modules/apm" \
    "$ROOT/modules/staff-portal/backend" \
    "$ROOT/modules/helpdesk/backend" \
    "$ROOT/modules/finance/backend" \
    "$ROOT/modules/finance" \
    "$ROOT/modules/risk-register/backend"
  do
    [[ -f "$_cfg_dir/artisan" ]] || continue
    [[ -f "$_cfg_dir/vendor/autoload.php" ]] || continue
    (cd "$_cfg_dir" && php artisan config:clear --no-interaction 2>/dev/null) \
      || echo "    warn: config:clear failed in $_cfg_dir" >&2
  done
fi

echo
echo "=== Staff Share API connection ==="
# Host-reachable base (PUBLIC_BASE/backend). Compose hostname `web` is rewritten inside the probe.
SHARE_PROBE_BASE="${PUBLIC_BASE%/}/backend"
export STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN STAFF_API_INTERNAL_BASE_URL
env_set "$ROOT_ENV" STAFF_API_INTERNAL_BASE_URL "$STAFF_API_INTERNAL_BASE_URL" 2>/dev/null || true
if ! "$ROOT/scripts/setup/probe-staff-share-api.sh" "$SHARE_PROBE_BASE"; then
  # Retry loopback internal URL when public host differs (e.g. DNS only inside LAN).
  _retry_internal=1
  case "$STAFF_API_INTERNAL_BASE_URL" in
    "$SHARE_PROBE_BASE"|http://web/*|https://web/*) _retry_internal=0 ;;
  esac
  if [[ "$_retry_internal" -eq 1 ]]; then
    echo "    retrying via STAFF_API_INTERNAL_BASE_URL…"
    if ! "$ROOT/scripts/setup/probe-staff-share-api.sh" "$STAFF_API_INTERNAL_BASE_URL"; then
      setup_warn "Staff Share API unreachable with current credentials/token — Helpdesk/APM directory sync will fail"
    fi
  else
    setup_warn "Staff Share API unreachable with current credentials/token — Helpdesk/APM directory sync will fail"
  fi
  unset _retry_internal
fi

echo
echo "=== Summary ==="
echo "Site=$SITE_KIND  Deploy=$DEPLOY_MODE  DB=$DB_MODE  Base=$PUBLIC_BASE  WebRoot=/${WEB_ROOT}"
echo "STAFF_SITE_ID=$STAFF_SITE_ID"
echo "CI3 uploads=$STAFF_PORTAL_UPLOADS_ROOT"
echo "Microsoft portal redirect=$MICROSOFT_REDIRECT_URI_PORTAL"
echo "Microsoft APM redirect=$MICROSOFT_REDIRECT_URI_APM"
echo "Mail transport=$MAIL_TRANSPORT (MAIL_MAILER=$MAIL_MAILER) from=$MAIL_FROM_ADDRESS_SHARED"
echo "EXCHANGE sync=$EXCHANGE_FROM_MS"
echo "Password login=$ALLOW_ALTERNATIVE_LOGIN"
echo "Share internal=$STAFF_API_INTERNAL_BASE_URL"
echo "Share probe=$SHARE_PROBE_BASE"
echo "Redis=$REDIS_HOST:$REDIS_PORT"
echo "DB host=${DB_HOST:-unset}  databases: portal=$SP_DB apm=$APM_DB_DATABASE finance=$FN_DB helpdesk=$HD_DB risk=$RR_DB"
if [[ "$SETUP_ERRORS" -gt 0 ]]; then
  echo
  echo "Completed with $SETUP_ERRORS warning(s). Fix reported env permissions and re-run ./setup.sh if needed."
fi
if [[ "$DB_MODE" == "bundled" ]]; then
  echo
  echo "Bundled MySQL: docker compose --env-file docker/.env --profile bundled-db up -d"
fi
if [[ "$DEPLOY_MODE" == "docker" ]]; then
  echo "Stack: docker compose --env-file docker/.env up -d --build"
  echo "Workers (Compose): docker compose --env-file docker/.env --profile workers up -d"
  echo "Composer: via Compose web service (STAFF_COMPOSER_VIA_DOCKER=1)"
fi

INST_PROFILE_DEFAULT=2

# SPA assets bake WEB_ROOT into index.html — rebuild whenever the folder/URL changes.
SPA_BUILD_DEFAULT=1
if [[ -n "${PREV_WEB_ROOT:-}" && "$PREV_WEB_ROOT" != "$WEB_ROOT" ]]; then
  SPA_BUILD_DEFAULT=1
fi
prompt_choice RUN_SPA_BUILD "Rebuild staff-portal frontend for /${WEB_ROOT}/ ?" "1) Yes  2) No" "$SPA_BUILD_DEFAULT"
if [[ "$RUN_SPA_BUILD" == "1" ]]; then
  echo "==> Rebuilding SPA (Vite base /${WEB_ROOT}/)"
  export WEB_ROOT VITE_STAFF_PORTAL_BASE_PATH VITE_STAFF_PORTAL_API_BASE_URL
  if "$ROOT/scripts/setup/rebuild-spa.sh"; then
    echo "    SPA rebuild OK"
  else
    setup_warn "SPA rebuild failed — /${WEB_ROOT}/assets may 404 until fixed"
  fi
else
  echo "==> Skipping SPA rebuild (assets may still point at an old folder name)"
fi

# Always run module installers (no prompt). Production profile applies migrations;
# seed only on empty schemas. Existing installs skip full installer for unchanged modules.
RUN_INSTALL=1
INST_PROFILE="$INST_PROFILE_DEFAULT"
echo "==> Module installers: always on (production) — migrate every module; full install for changed modules"

# Resolve the git range used to detect "what changed" since last successful setup / pull.
setup_change_range_from() {
  local last=""
  if [[ -f "$SETUP_STATE_FILE" ]]; then
    last="$(tr -d '[:space:]' <"$SETUP_STATE_FILE" 2>/dev/null || true)"
  fi
  if [[ -n "$last" && "$last" != "unknown" ]]; then
    printf '%s' "$last"
    return 0
  fi
  if [[ -n "${SETUP_GIT_BEFORE:-}" && "$SETUP_GIT_BEFORE" != "unknown" ]]; then
    printf '%s' "$SETUP_GIT_BEFORE"
    return 0
  fi
  printf ''
}

# True when paths under $1 changed since the range start, or working tree is dirty there.
setup_paths_changed() {
  local path="$1"
  local from
  from="$(setup_change_range_from)"
  if ! command -v git >/dev/null 2>&1 \
    || ! git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    return 0
  fi
  if [[ -z "$from" ]]; then
    return 0
  fi
  if ! git -C "$ROOT" cat-file -e "${from}^{commit}" 2>/dev/null; then
    return 0
  fi
  if [[ -n "$(git -C "$ROOT" diff --name-only "$from" HEAD -- "$path" 2>/dev/null || true)" ]]; then
    return 0
  fi
  if [[ -n "$(git -C "$ROOT" diff --name-only -- "$path" 2>/dev/null || true)" ]]; then
    return 0
  fi
  if [[ -n "$(git -C "$ROOT" diff --cached --name-only -- "$path" 2>/dev/null || true)" ]]; then
    return 0
  fi
  return 1
}

# Shared PHP / setup scripts affect every module — force full installers.
setup_shared_changed() {
  setup_paths_changed setup.sh \
    || setup_paths_changed scripts/setup \
    || setup_paths_changed shared \
    || setup_paths_changed docker
}

setup_module_needs_full_install() {
  local name="$1"
  # New installation → always full installers.
  if [[ "${INSTALL_TYPE:-1}" == "1" ]]; then
    return 0
  fi
  if setup_shared_changed; then
    return 0
  fi
  case "$name" in
    staff-portal) setup_paths_changed modules/staff-portal ;;
    finance) setup_paths_changed modules/finance ;;
    helpdesk) setup_paths_changed modules/helpdesk ;;
    risk-register) setup_paths_changed modules/risk-register ;;
    apm) setup_paths_changed modules/apm ;;
    *) return 0 ;;
  esac
}

# Always attempt pending migrations (new migration files after git pull).
setup_migrate_laravel() {
  local label="$1" dir="$2"
  local artisan=""
  if [[ -f "$dir/backend/artisan" ]]; then
    artisan="$dir/backend/artisan"
    dir="$dir/backend"
  elif [[ -f "$dir/artisan" ]]; then
    artisan="$dir/artisan"
  else
    return 0
  fi
  if [[ ! -f "$(dirname "$artisan")/vendor/autoload.php" ]]; then
    echo "    $label: skip migrate (vendor missing — run full installer)"
    return 0
  fi
  echo "==> $label: php artisan migrate --force"
  (
    cd "$(dirname "$artisan")"
    php artisan migrate --force --no-interaction 2>/dev/null \
      || php artisan migrate --force --no-interaction \
      || echo "warn: $label migrate failed" >&2
    # Staff Portal nwidart modules (e.g. Settings portal_kv_settings).
    if [[ "$label" == "staff-portal" ]]; then
      php artisan module:migrate --force --no-interaction 2>/dev/null \
        || php artisan module:migrate --force 2>/dev/null \
        || true
    fi
  ) || echo "warn: $label migrate failed" >&2
}

setup_run_module_production() {
  local name="$1" dir="$2" database="$3"
  shift 3
  local is_new=0
  local probe_host="$DB_HOST"
  local extra=()
  # Host-side probe: Compose service name mysql is not resolvable on the host.
  if [[ "$probe_host" == "mysql" ]]; then
    probe_host="127.0.0.1"
  fi
  if setup_db_is_empty "$probe_host" "${DB_PORT:-3306}" "$DB_USER" "$DB_PASS" "$database"; then
    is_new=1
    echo "==> $name: new DB — migrate (+ seed when supported)"
  else
    echo "==> $name: existing DB — migrate only (skip seed)"
  fi
  # Helpdesk category seeder on new DBs; portal/RR skip demo DatabaseSeeder (needs faker, not for prod).
  case "$name" in
    helpdesk)
      [[ "$is_new" -eq 0 ]] && extra=(--skip-seed)
      ;;
  esac
  (cd "$dir" && ./setup-production.sh "$@" "${extra[@]}") \
    || echo "warn: $name setup-production failed" >&2
  # Finance has no seed flag — seed new schemas via artisan when present.
  if [[ "$name" == "finance" && "$is_new" -eq 1 && -f "$dir/artisan" ]]; then
    (cd "$dir" && php artisan db:seed --force --no-interaction) \
      || echo "warn: finance db:seed failed" >&2
  fi
}

if [[ "$RUN_INSTALL" == "1" ]]; then
  export STAFF_SITE_ID STAFF_DATA_ROOT STAFF_HOST_DATA_ROOT STAFF_USE_HOST_STORAGE
  export STAFF_PORTAL_UPLOADS_ROOT STAFF_APM_FILES_ROOT STAFF_HELPDESK_FILES_ROOT
  export STAFF_PORTAL_MODULE_FILES_ROOT BASE_URL SITE_KIND WEB_ROOT
  export VITE_STAFF_PORTAL_BASE_PATH VITE_STAFF_PORTAL_API_BASE_URL
  if [[ "$DEPLOY_MODE" == "docker" ]]; then
    # Prefer host Composer during installers so we do not build the image mid-setup.
    # Image is built once at the end with redis/web/workers.
    if command -v composer >/dev/null 2>&1; then
      export STAFF_COMPOSER_VIA_DOCKER=0
      echo "==> Docker deploy: using host Composer for installers (image builds once at the end)"
    else
      export STAFF_COMPOSER_VIA_DOCKER=1
      export PATH="$ROOT/scripts/setup/bin:$PATH"
      staff_ensure_docker_env || setup_warn "docker/.env missing"
      echo "==> Docker deploy: no host Composer — building runtime image once, then Compose Composer"
      staff_docker_build_once || setup_warn "docker image build failed"
    fi
  fi

  local_from="$(setup_change_range_from)"
  if [[ -n "$local_from" ]]; then
    echo "==> Change range for installers: ${local_from:0:9} → ${SETUP_GIT_AFTER:-HEAD}"
  else
    echo "==> No prior setup marker — full installers for all modules"
  fi

  if [[ "$INST_PROFILE" == "2" ]]; then
    if setup_module_needs_full_install staff-portal; then
      setup_run_module_production staff-portal "$ROOT/modules/staff-portal" "$SP_DB" --skip-build
    else
      echo "==> staff-portal: unchanged — migrate only"
      setup_migrate_laravel staff-portal "$ROOT/modules/staff-portal"
    fi
    if setup_module_needs_full_install finance; then
      setup_run_module_production finance "$ROOT/modules/finance" "$FN_DB"
    else
      echo "==> finance: unchanged — migrate only"
      setup_migrate_laravel finance "$ROOT/modules/finance"
    fi
    if setup_module_needs_full_install helpdesk; then
      setup_run_module_production helpdesk "$ROOT/modules/helpdesk" "$HD_DB"
    else
      echo "==> helpdesk: unchanged — migrate only"
      setup_migrate_laravel helpdesk "$ROOT/modules/helpdesk"
    fi
    if setup_module_needs_full_install risk-register; then
      setup_run_module_production risk-register "$ROOT/modules/risk-register" "${RR_DB:-risk_register}"
    else
      echo "==> risk-register: unchanged — migrate only"
      setup_migrate_laravel risk-register "$ROOT/modules/risk-register"
    fi
  else
    (cd "$ROOT/modules/staff-portal" && WEB_ROOT="$WEB_ROOT" ./setup.sh) || echo "warn: staff-portal setup failed" >&2
    (cd "$ROOT/modules/finance" && ./setup.sh) || echo "warn: finance setup failed" >&2
    (cd "$ROOT/modules/helpdesk" && ./setup.sh) || echo "warn: helpdesk setup failed" >&2
    (cd "$ROOT/modules/risk-register" && ./setup.sh) || echo "warn: risk-register setup failed" >&2
  fi
  if [[ -f "$ROOT/modules/apm/artisan" ]]; then
    if setup_module_needs_full_install apm; then
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
    else
      echo "==> apm: unchanged — migrate only"
      setup_migrate_laravel apm "$ROOT/modules/apm"
    fi
  fi

  # Safety net: always try pending migrations on every Laravel app (new files after pull).
  echo "==> Ensuring pending migrations on all modules"
  setup_migrate_laravel staff-portal "$ROOT/modules/staff-portal"
  setup_migrate_laravel finance "$ROOT/modules/finance"
  setup_migrate_laravel helpdesk "$ROOT/modules/helpdesk"
  setup_migrate_laravel risk-register "$ROOT/modules/risk-register"
  setup_migrate_laravel apm "$ROOT/modules/apm"

  # Remember HEAD so the next setup only full-installs modules that changed.
  if command -v git >/dev/null 2>&1 \
    && git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git -C "$ROOT" rev-parse HEAD >"$SETUP_STATE_FILE" 2>/dev/null \
      || true
  fi

  # Installers may recreate dirs as root — fix perms and relink again.
  setup_fix_laravel_storage
fi

# ----- Supervisor (queue / scheduler) -----
if [[ "$SITE_KIND" == "demo" ]]; then
  echo
  echo "==> Demo site: disabling/skipping Supervisor workers"
  for setupf in "$SP_SETUP" "$HD_SETUP" \
    "$ROOT/modules/finance/setup.env" \
    "$ROOT/modules/risk-register/setup.env"
  do
    [[ -f "$setupf" ]] || continue
    env_set "$setupf" INSTALL_SUPERVISOR "false" 2>/dev/null || true
  done
  if [[ "$(uname -s)" == "Linux" ]] && command -v systemctl >/dev/null 2>&1; then
    echo "    Retiring any existing CBP systemd units on this host (demo must not run queues)"
    _retire=(
      staff-portal.target staff-portal-queue.service staff-portal-scheduler.service
      staff-portal-scheduler.timer staff-portal-health.service staff-portal-health.timer
      helpdesk.target helpdesk-queue.service helpdesk-scheduler.service
      helpdesk-scheduler.timer helpdesk-health.service helpdesk-health.timer
      laravel-queue-apm.service laravel-scheduler.service laravel-queue-worker.service
      laravel-queue-cleanup.service laravel12-queue-apm.service
    )
    local_f=""
    for local_f in \
      /etc/systemd/system/staff-portal-*-"${WEB_ROOT}".service \
      /etc/systemd/system/staff-portal-*-"${WEB_ROOT}".timer \
      /etc/systemd/system/staff-portal-"${WEB_ROOT}".target \
      /etc/systemd/system/helpdesk-*-"${WEB_ROOT}".service \
      /etc/systemd/system/helpdesk-*-"${WEB_ROOT}".timer \
      /etc/systemd/system/helpdesk-"${WEB_ROOT}".target \
      /etc/systemd/system/laravel-*-"${WEB_ROOT}".service
    do
      [[ -e "$local_f" ]] || continue
      _retire+=("$(basename "$local_f")")
    done
    systemd_retire_units "${_retire[@]}" || true
  fi
  echo "==> Skipping Supervisor install (demo)"
elif [[ "$DEPLOY_MODE" == "docker" ]]; then
  echo
  echo "==> Docker deploy: PHP ${PHP_VERSION:-8.2} + extensions live in the Compose image; Composer packages (vendor/) install via Compose web"
  echo "    Host Supervisor must not run the same queues — retiring host CBP programs first"
  for setupf in "$SP_SETUP" "$HD_SETUP" \
    "$ROOT/modules/finance/setup.env" \
    "$ROOT/modules/risk-register/setup.env"
  do
    [[ -f "$setupf" ]] || continue
    env_set "$setupf" INSTALL_SUPERVISOR "false" 2>/dev/null || true
  done
  # Always stop/remove host supervisor confs when Docker owns workers (even if workers start is skipped).
  WEB_ROOT="$WEB_ROOT" staff_retire_host_supervisor || true
  prompt_choice RUN_DOCKER_WORKERS \
    "Start Compose stack now (build image once, then redis + web + workers)?" \
    "1) Yes  2) No" \
    "1"
  if [[ "$RUN_DOCKER_WORKERS" == "1" ]]; then
    if staff_docker_resolve_access; then
      # Single build + up for redis/web/workers (shared cbp-staff-runtime image).
      if staff_docker_up_stack 1; then
        # Fill any missing vendor/ after the image exists (Compose Composer, no rebuild).
        export STAFF_COMPOSER_VIA_DOCKER=1 STAFF_DOCKER_BUILT=1
        export PATH="$ROOT/scripts/setup/bin:$PATH"
        "$ROOT/scripts/setup/ensure-composer-vendors.sh" \
          || echo "warn: ensure-composer-vendors failed — workers may FATAL until fixed" >&2
        echo "==> Compose worker status (STARTING is normal for a few seconds):"
        staff_docker_workers_status || true
      else
        setup_warn "docker compose stack failed"
      fi
    else
      setup_warn "skipped Compose stack — fix Docker socket access, then re-run"
    fi
  else
    echo "==> Skipping Compose stack (later: docker compose --env-file docker/.env --profile workers up -d --build)"
    echo "    Host CBP Supervisor programs were still stopped/removed above."
  fi
else
  SUP_DEFAULT=2
  if [[ "$(uname -s)" == "Linux" ]] && [[ "$SITE_KIND" == "production" ]]; then
    if command -v supervisorctl >/dev/null 2>&1 || command -v apt-get >/dev/null 2>&1; then
      SUP_DEFAULT=1
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
    echo "==> Supervisor (WEB_ROOT=$WEB_ROOT)"
    WEB_ROOT="$WEB_ROOT" \
    PHP_BIN="$PHP_BIN_RESOLVED" \
    SUPERVISOR_USER="${SUPERVISOR_USER:-www-data}" \
    INSTALL_SUPERVISOR=true \
      "$ROOT/scripts/setup/install-supervisor.sh" \
      || echo "warn: Supervisor install failed" >&2
  else
    echo "==> Skipping Supervisor"
  fi
fi

echo
echo "Done. See docs/SETUP.md"
if [[ "$SETUP_ERRORS" -gt 0 ]]; then
  echo "Completed with $SETUP_ERRORS warning(s). Review messages above; setup did not hard-fail."
  # Soft warnings (e.g. systemd retire skipped, STARTING workers) must not fail CI/automation.
  exit 0
fi
exit 0
