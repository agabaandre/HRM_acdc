#!/usr/bin/env bash
# Email Server — production deploy script
#
# Primary workflow (recommended on the server):
#   1) Edit secrets in docker/.env and backend/.env yourself (never commit them)
#   2) Run:  ./setup.sh
#
# First time only (creates empty templates if missing):
#   cp docker/.env.example docker/.env
#   cp backend/.env.example backend/.env
#   # edit both files, then:
#   ./setup.sh
#
# Optional: still accepts --env-file / CLI flags to *seed* missing docker/.env
# values, but existing .env files are never overwritten.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

# ---------------------------------------------------------------------------
# Defaults (non-secret) — overridden by docker/.env once loaded
# ---------------------------------------------------------------------------
DOMAIN="${DOMAIN:-notifications.africacdc.org}"
ADMIN_EMAIL="${ADMIN_EMAIL:-andrewa@africacdc.org}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
JWT_SECRET="${JWT_SECRET:-}"
JWT_TTL="${JWT_TTL:-60}"
DATA_PATH="${EMAIL_SERVER_DATA_PATH:-/home/email_serverdata}"
MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-}"
MAIL_FROM_NAME="${MAIL_FROM_NAME:-Africa CDC Notifications}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"
EXCHANGE_TENANT_ID="${EXCHANGE_TENANT_ID:-}"
EXCHANGE_CLIENT_ID="${EXCHANGE_CLIENT_ID:-}"
EXCHANGE_CLIENT_SECRET="${EXCHANGE_CLIENT_SECRET:-}"
EXCHANGE_AUTH_METHOD="${EXCHANGE_AUTH_METHOD:-client_credentials}"
EXCHANGE_SCOPE="${EXCHANGE_SCOPE:-https://graph.microsoft.com/.default}"
INTEGRATION_CLIENT_SECRET="${INTEGRATION_CLIENT_SECRET:-}"
QUEUE_SCALE="${QUEUE_SCALE:-1}"
MYSQL_HOST_PORT="${MYSQL_HOST_PORT:-3309}"
FORCE_VENDOR_REINSTALL="${FORCE_VENDOR_REINSTALL:-false}"
RESET_MYSQL="${RESET_MYSQL:-false}"
RESET_REDIS="${RESET_REDIS:-false}"
RUN_SEEDER="${RUN_SEEDER:-true}"
SKIP_SSL="${SKIP_SSL:-false}"
SKIP_FRONTEND_BUILD="${SKIP_FRONTEND_BUILD:-false}"
FRONTEND_BUILD="${FRONTEND_BUILD:-auto}"
SKIP_NGINX="${SKIP_NGINX:-false}"
APP_ENV="${APP_ENV:-production}"
APP_DEBUG="${APP_DEBUG:-false}"
ENV_FILE=""
WRITE_ENV="${WRITE_ENV:-false}"

usage() {
  cat <<'EOF'
Usage: ./setup.sh [options]

Recommended (production):
  1. Fill in docker/.env and backend/.env manually (gitignored)
  2. ./setup.sh

First-time templates:
  cp docker/.env.example docker/.env
  cp backend/.env.example backend/.env
  # edit passwords/secrets, then run ./setup.sh

setup.sh NEVER overwrites existing docker/.env / backend/.env unless you pass
  --write-env   (rebuilds them from CLI / --env-file — only for fresh boxes)

Optional flags:
  --env-file=PATH               Load KEY=VALUE into this shell (does not overwrite .env unless --write-env)
  --domain=HOST                 Used with --write-env / nginx site name
  --data-path=PATH              Persistent MySQL/Redis/storage path
  --queue-scale=N               docker compose --scale queue=N (default: 1)
  --mysql-host-port=PORT        Host MySQL port (default: 3309)
  --force-vendor                Wipe backend/vendor and reinstall via composer
  --reset-mysql                 OPTIONAL wipe of MySQL data (DESTROYS DATA)
  --reset-redis                 Wipe Redis data dir (queues/cache only — safe vs MySQL)
  --run-seeder=true|false       Seed admin/providers (default: true)
  --write-env                   Rewrite docker/.env + backend/.env from flags/--env-file
  --skip-ssl                    Skip Certbot TLS setup
  --skip-frontend-build         Skip frontend build
  --frontend-build=auto|docker|host
  --skip-nginx                  Skip installing host Nginx site
  -h, --help

Required keys in docker/.env (edit manually):
  ADMIN_PASSWORD, DB_PASSWORD, MYSQL_ROOT_PASSWORD, JWT_SECRET (>=32 chars)
EOF
}

log()  { printf '==> %s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*" >&2; }
die()  { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Missing required command: $1"
}

run_root() {
  if [[ "${EUID}" -eq 0 ]]; then
    "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo "$@"
  else
    die "Root privileges required for: $*"
  fi
}

gen_secret() {
  local bytes="${1:-48}"
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -base64 "$bytes" | tr -d '\n'
  else
    head -c "$bytes" /dev/urandom | base64 | tr -d '\n'
  fi
}

# Load an external KEY=VALUE file without executing it.
load_env_file() {
  local file="$1"
  [[ -f "$file" ]] || die "Env file not found: $file"
  while IFS= read -r line || [[ -n "$line" ]]; do
    [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
    if [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
      local key="${BASH_REMATCH[1]}"
      local val="${BASH_REMATCH[2]}"
      if [[ "$val" =~ ^\"(.*)\"$ ]] || [[ "$val" =~ ^\'(.*)\'$ ]]; then
        val="${BASH_REMATCH[1]}"
      fi
      printf -v "$key" '%s' "$val"
      export "$key"
    fi
  done < "$file"
}

# Read KEY from a .env file (strips surrounding quotes).
# Missing key → empty string (must not fail under set -euo pipefail).
env_file_get() {
  local file="$1"
  local key="$2"
  local line=""
  [[ -f "$file" ]] || { printf ''; return 0; }
  line="$(grep -E "^${key}=" "$file" 2>/dev/null | head -1 || true)"
  [[ -n "$line" ]] || { printf ''; return 0; }
  printf '%s' "${line#*=}" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

write_env_file() {
  local target="$1"
  shift
  umask 077
  {
    printf '# Generated by setup.sh on %s — DO NOT COMMIT\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    for pair in "$@"; do
      printf '%s\n' "$pair"
    done
  } > "$target"
  chmod 600 "$target"
}

set_backend_env() {
  local key="$1"
  local value="$2"
  local escaped="${value//\\/\\\\}"
  escaped="${escaped//\"/\\\"}"
  local line="${key}=\"${escaped}\""

  if grep -q "^${key}=" "$ROOT/backend/.env"; then
    awk -v k="$key" -v line="$line" '
      BEGIN { done=0 }
      index($0, k "=") == 1 {
        print line
        done=1
        next
      }
      { print }
      END { if (!done) print line }
    ' "$ROOT/backend/.env" > "$ROOT/backend/.env.tmp"
    mv "$ROOT/backend/.env.tmp" "$ROOT/backend/.env"
    chmod 600 "$ROOT/backend/.env"
  else
    printf '%s\n' "$line" >> "$ROOT/backend/.env"
  fi
}

# ---------------------------------------------------------------------------
# Parse args
# ---------------------------------------------------------------------------
for arg in "$@"; do
  case "$arg" in
    --env-file=*) ENV_FILE="${arg#*=}" ;;
  esac
done

if [[ -n "$ENV_FILE" ]]; then
  log "Loading values from $ENV_FILE (into this process only)"
  load_env_file "$ENV_FILE"
  DOMAIN="${DOMAIN:-notifications.africacdc.org}"
  ADMIN_EMAIL="${ADMIN_EMAIL:-andrewa@africacdc.org}"
  DATA_PATH="${EMAIL_SERVER_DATA_PATH:-${DATA_PATH:-/home/email_serverdata}}"
  JWT_TTL="${JWT_TTL:-60}"
  QUEUE_SCALE="${QUEUE_SCALE:-1}"
  MYSQL_HOST_PORT="${MYSQL_HOST_PORT:-3309}"
  RUN_SEEDER="${RUN_SEEDER:-true}"
  SKIP_SSL="${SKIP_SSL:-false}"
fi

while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--help) usage; exit 0 ;;
    --env-file=*) ;; # already processed
    --domain=*) DOMAIN="${1#*=}" ;;
    --admin-email=*) ADMIN_EMAIL="${1#*=}" ;;
    --admin-password=*) ADMIN_PASSWORD="${1#*=}" ;;
    --db-password=*) DB_PASSWORD="${1#*=}" ;;
    --mysql-root-password=*) MYSQL_ROOT_PASSWORD="${1#*=}" ;;
    --jwt-secret=*) JWT_SECRET="${1#*=}" ;;
    --jwt-ttl=*) JWT_TTL="${1#*=}" ;;
    --data-path=*) DATA_PATH="${1#*=}" ;;
    --mail-from-address=*) MAIL_FROM_ADDRESS="${1#*=}" ;;
    --mail-from-name=*) MAIL_FROM_NAME="${1#*=}" ;;
    --certbot-email=*) CERTBOT_EMAIL="${1#*=}" ;;
    --exchange-tenant-id=*) EXCHANGE_TENANT_ID="${1#*=}" ;;
    --exchange-client-id=*) EXCHANGE_CLIENT_ID="${1#*=}" ;;
    --exchange-client-secret=*) EXCHANGE_CLIENT_SECRET="${1#*=}" ;;
    --integration-client-secret=*) INTEGRATION_CLIENT_SECRET="${1#*=}" ;;
    --queue-scale=*) QUEUE_SCALE="${1#*=}" ;;
    --mysql-host-port=*) MYSQL_HOST_PORT="${1#*=}" ;;
    --force-vendor) FORCE_VENDOR_REINSTALL=true ;;
    --reset-mysql) RESET_MYSQL=true ;;
    --reset-redis) RESET_REDIS=true ;;
    --run-seeder=*) RUN_SEEDER="${1#*=}" ;;
    --write-env) WRITE_ENV=true ;;
    --skip-ssl) SKIP_SSL=true ;;
    --skip-frontend-build) SKIP_FRONTEND_BUILD=true ;;
    --frontend-build=*) FRONTEND_BUILD="${1#*=}" ;;
    --skip-nginx) SKIP_NGINX=true ;;
    *) die "Unknown option: $1 (try --help)" ;;
  esac
  shift
done

# ---------------------------------------------------------------------------
# Ensure .env templates exist (never overwrite existing files)
# ---------------------------------------------------------------------------
if [[ ! -f "$ROOT/docker/.env" ]]; then
  [[ -f "$ROOT/docker/.env.example" ]] || die "docker/.env.example missing"
  cp "$ROOT/docker/.env.example" "$ROOT/docker/.env"
  chmod 600 "$ROOT/docker/.env"
  warn "Created docker/.env from example — edit secrets, then re-run ./setup.sh"
  die "Stopped: fill ADMIN_PASSWORD, DB_PASSWORD, MYSQL_ROOT_PASSWORD, JWT_SECRET in docker/.env"
fi

if [[ ! -f "$ROOT/backend/.env" ]]; then
  [[ -f "$ROOT/backend/.env.example" ]] || die "backend/.env.example missing"
  umask 077
  cp "$ROOT/backend/.env.example" "$ROOT/backend/.env"
  chmod 600 "$ROOT/backend/.env"
  warn "Created backend/.env from example — edit DB/JWT/Exchange values to match docker/.env"
  die "Stopped: fill backend/.env (at least DB_PASSWORD, JWT_SECRET, APP_KEY after first boot), then re-run ./setup.sh"
fi

# Load operator-edited docker/.env as source of truth
log "Using existing docker/.env (not overwritten)"
load_env_file "$ROOT/docker/.env"

# Refresh locals from docker/.env / environment
DOMAIN="${DOMAIN:-notifications.africacdc.org}"
# Prefer DOMAIN; else strip host from APP_URL
if [[ -z "${DOMAIN}" || "$DOMAIN" == "notifications.africacdc.org" ]]; then
  _app_url="$(env_file_get "$ROOT/docker/.env" APP_URL)"
  if [[ -n "$_app_url" ]]; then
    DOMAIN="$(printf '%s' "$_app_url" | sed -e 's|^https\?://||' -e 's|/.*||')"
  fi
fi
ADMIN_EMAIL="$(env_file_get "$ROOT/docker/.env" ADMIN_EMAIL)"; ADMIN_EMAIL="${ADMIN_EMAIL:-andrewa@africacdc.org}"
ADMIN_PASSWORD="$(env_file_get "$ROOT/docker/.env" ADMIN_PASSWORD)"
DB_PASSWORD="$(env_file_get "$ROOT/docker/.env" DB_PASSWORD)"
MYSQL_ROOT_PASSWORD="$(env_file_get "$ROOT/docker/.env" MYSQL_ROOT_PASSWORD)"
JWT_SECRET="$(env_file_get "$ROOT/docker/.env" JWT_SECRET)"
JWT_TTL="$(env_file_get "$ROOT/docker/.env" JWT_TTL)"; JWT_TTL="${JWT_TTL:-60}"
DATA_PATH="$(env_file_get "$ROOT/docker/.env" EMAIL_SERVER_DATA_PATH)"; DATA_PATH="${DATA_PATH:-/home/email_serverdata}"
MYSQL_HOST_PORT="$(env_file_get "$ROOT/docker/.env" MYSQL_HOST_PORT)"; MYSQL_HOST_PORT="${MYSQL_HOST_PORT:-3309}"
API_HOST_PORT="$(env_file_get "$ROOT/docker/.env" API_HOST_PORT)"; API_HOST_PORT="${API_HOST_PORT:-8089}"
RUN_SEEDER="$(env_file_get "$ROOT/docker/.env" RUN_SEEDER)"; RUN_SEEDER="${RUN_SEEDER:-true}"
APP_ENV="$(env_file_get "$ROOT/docker/.env" APP_ENV)"; APP_ENV="${APP_ENV:-production}"
APP_DEBUG="$(env_file_get "$ROOT/docker/.env" APP_DEBUG)"; APP_DEBUG="${APP_DEBUG:-false}"
INTEGRATION_CLIENT_SECRET="$(env_file_get "$ROOT/docker/.env" INTEGRATION_CLIENT_SECRET)"
QUEUE_SCALE="${QUEUE_SCALE:-1}"

# Backend-held values (operator may edit backend/.env for Exchange, etc.)
MAIL_FROM_ADDRESS="$(env_file_get "$ROOT/backend/.env" MAIL_FROM_ADDRESS)"
MAIL_FROM_NAME="$(env_file_get "$ROOT/backend/.env" MAIL_FROM_NAME)"
MAIL_FROM_NAME="${MAIL_FROM_NAME:-Africa CDC Notifications}"
EXCHANGE_TENANT_ID="$(env_file_get "$ROOT/backend/.env" EXCHANGE_TENANT_ID)"
EXCHANGE_CLIENT_ID="$(env_file_get "$ROOT/backend/.env" EXCHANGE_CLIENT_ID)"
EXCHANGE_CLIENT_SECRET="$(env_file_get "$ROOT/backend/.env" EXCHANGE_CLIENT_SECRET)"
EXCHANGE_AUTH_METHOD="$(env_file_get "$ROOT/backend/.env" EXCHANGE_AUTH_METHOD)"
EXCHANGE_AUTH_METHOD="${EXCHANGE_AUTH_METHOD:-client_credentials}"
EXCHANGE_SCOPE="$(env_file_get "$ROOT/backend/.env" EXCHANGE_SCOPE)"
EXCHANGE_SCOPE="${EXCHANGE_SCOPE:-https://graph.microsoft.com/.default}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-$ADMIN_EMAIL}"

[[ -n "$MAIL_FROM_ADDRESS" ]] || MAIL_FROM_ADDRESS="notifications@${DOMAIN}"

is_placeholder() {
  case "$1" in
    ""|change-me*|CHANGE_ME*|changeme*) return 0 ;;
    *) return 1 ;;
  esac
}

is_placeholder "$ADMIN_PASSWORD" && die "Set a real ADMIN_PASSWORD in docker/.env (not a placeholder), then re-run ./setup.sh"
is_placeholder "$DB_PASSWORD" && die "Set a real DB_PASSWORD in docker/.env, then re-run ./setup.sh"
is_placeholder "$MYSQL_ROOT_PASSWORD" && die "Set a real MYSQL_ROOT_PASSWORD in docker/.env, then re-run ./setup.sh"
[[ -n "$JWT_SECRET" ]] || die "Set JWT_SECRET in docker/.env (>=32 chars)"
[[ "${#JWT_SECRET}" -ge 32 ]] || die "JWT_SECRET in docker/.env must be at least 32 characters"
[[ "$SKIP_SSL" == "true" ]] || [[ -n "$CERTBOT_EMAIL" ]] || die "Set CERTBOT_EMAIL in the environment or use --skip-ssl (default: ADMIN_EMAIL)"

APP_URL="$(env_file_get "$ROOT/docker/.env" APP_URL)"
FRONTEND_URL="$(env_file_get "$ROOT/docker/.env" FRONTEND_URL)"
[[ -n "$APP_URL" ]] || APP_URL="https://${DOMAIN}"
[[ -n "$FRONTEND_URL" ]] || FRONTEND_URL="https://${DOMAIN}"

need_cmd docker
need_cmd openssl
if [[ "$SKIP_NGINX" != "true" ]]; then
  need_cmd nginx
fi
if [[ "$SKIP_SSL" != "true" ]]; then
  need_cmd certbot
fi

if docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose -f "$ROOT/docker/docker-compose.yml" --env-file "$ROOT/docker/.env")
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE=(docker-compose -f "$ROOT/docker/docker-compose.yml" --env-file "$ROOT/docker/.env")
else
  die "Docker Compose is required"
fi

# Optional: only rewrite .env files when explicitly requested
if [[ "$WRITE_ENV" == "true" ]]; then
  warn "--write-env: rewriting docker/.env and backend/.env from current variables"
  [[ -n "$INTEGRATION_CLIENT_SECRET" ]] || INTEGRATION_CLIENT_SECRET="$(gen_secret 32)"
  write_env_file "$ROOT/docker/.env" \
    "APP_ENV=${APP_ENV}" \
    "APP_DEBUG=${APP_DEBUG}" \
    "RUN_SEEDER=${RUN_SEEDER}" \
    "EMAIL_SERVER_DATA_PATH=${DATA_PATH}" \
    "APP_URL=${APP_URL}" \
    "FRONTEND_URL=${FRONTEND_URL}" \
    "API_HOST_PORT=${API_HOST_PORT}" \
    "MYSQL_HOST_PORT=${MYSQL_HOST_PORT}" \
    "REDIS_CLIENT=predis" \
    "ADMIN_EMAIL=${ADMIN_EMAIL}" \
    "ADMIN_PASSWORD=${ADMIN_PASSWORD}" \
    "ADMIN_RESET_PASSWORD=true" \
    "DB_PASSWORD=${DB_PASSWORD}" \
    "MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}" \
    "JWT_SECRET=${JWT_SECRET}" \
    "JWT_TTL=${JWT_TTL}" \
    "API_DOCS_ENABLED=${API_DOCS_ENABLED:-true}" \
    "INTEGRATION_CLIENT_SECRET=${INTEGRATION_CLIENT_SECRET}"

  PRESERVED_APP_KEY="$(env_file_get "$ROOT/backend/.env" APP_KEY)"
  umask 077
  cp "$ROOT/backend/.env.example" "$ROOT/backend/.env"
  chmod 600 "$ROOT/backend/.env"
  set_backend_env "APP_NAME" "Email Server"
  set_backend_env "APP_ENV" "$APP_ENV"
  set_backend_env "APP_DEBUG" "$APP_DEBUG"
  set_backend_env "APP_URL" "$APP_URL"
  set_backend_env "FRONTEND_URL" "$FRONTEND_URL"
  set_backend_env "DB_HOST" "mysql"
  set_backend_env "DB_DATABASE" "email_server"
  set_backend_env "DB_USERNAME" "email_server"
  set_backend_env "DB_PASSWORD" "$DB_PASSWORD"
  set_backend_env "REDIS_CLIENT" "predis"
  set_backend_env "MAIL_MAILER" "log"
  set_backend_env "ADMIN_EMAIL" "$ADMIN_EMAIL"
  set_backend_env "ADMIN_PASSWORD" "$ADMIN_PASSWORD"
  set_backend_env "JWT_SECRET" "$JWT_SECRET"
  set_backend_env "JWT_TTL" "$JWT_TTL"
  set_backend_env "API_DOCS_ENABLED" "${API_DOCS_ENABLED:-true}"
  set_backend_env "INTEGRATION_CLIENT_SECRET" "$INTEGRATION_CLIENT_SECRET"
  set_backend_env "SANCTUM_STATEFUL_DOMAINS" "localhost,localhost:3006,127.0.0.1"
  if [[ "$PRESERVED_APP_KEY" == base64:* ]]; then
    set_backend_env "APP_KEY" "$PRESERVED_APP_KEY"
  else
    set_backend_env "APP_KEY" "base64:$(openssl rand -base64 32 | tr -d '\n')"
  fi
else
  log "Leaving docker/.env and backend/.env unchanged (manual edit mode)"
  # Keep Laravel DB password in sync with docker/.env only (safe one-key patch)
  _be_db="$(env_file_get "$ROOT/backend/.env" DB_PASSWORD)"
  if [[ "$_be_db" != "$DB_PASSWORD" ]]; then
    log "Syncing DB_PASSWORD from docker/.env → backend/.env"
    set_backend_env "DB_PASSWORD" "$DB_PASSWORD"
  fi
  _be_jwt="$(env_file_get "$ROOT/backend/.env" JWT_SECRET)"
  if [[ -z "$_be_jwt" || "$_be_jwt" != "$JWT_SECRET" ]]; then
    log "Syncing JWT_SECRET from docker/.env → backend/.env"
    set_backend_env "JWT_SECRET" "$JWT_SECRET"
  fi

  # Swagger: Compose injects API_DOCS_ENABLED into the app container (overrides backend/.env)
  _api_docs="$(env_file_get "$ROOT/docker/.env" API_DOCS_ENABLED)"
  if [[ -z "$_api_docs" ]]; then
    log "API_DOCS_ENABLED missing in docker/.env — adding API_DOCS_ENABLED=true"
    if printf '\nAPI_DOCS_ENABLED=true\n' >> "$ROOT/docker/.env" 2>/dev/null; then
      :
    else
      run_root bash -c "printf '\\nAPI_DOCS_ENABLED=true\\n' >> '$ROOT/docker/.env'"
    fi
    _api_docs=true
  fi
  log "API_DOCS_ENABLED=${_api_docs} (from docker/.env)"
  _be_docs="$(env_file_get "$ROOT/backend/.env" API_DOCS_ENABLED)"
  if [[ "$_be_docs" != "$_api_docs" ]]; then
    log "Syncing API_DOCS_ENABLED=${_api_docs} → backend/.env"
    set_backend_env "API_DOCS_ENABLED" "$_api_docs"
  fi
  if [[ "$_api_docs" != "true" && "$_api_docs" != "1" ]]; then
    warn "Swagger is OFF. To enable /api/documentation set API_DOCS_ENABLED=true in docker/.env, then:
  cd $ROOT/docker && docker compose up -d --force-recreate --no-deps app"
  fi

  # Ensure APP_KEY exists
  _be_key="$(env_file_get "$ROOT/backend/.env" APP_KEY)"
  if [[ "$_be_key" != base64:* ]]; then
    log "Generating APP_KEY in backend/.env"
    set_backend_env "APP_KEY" "base64:$(openssl rand -base64 32 | tr -d '\n')"
  fi
fi

# ---------------------------------------------------------------------------
# 1. Persistent data dirs (MySQL, Redis, Laravel storage)
# ---------------------------------------------------------------------------
log "Creating data directories under $DATA_PATH"
ensure_data_dirs() {
  mkdir -p \
    "$DATA_PATH/mysql" \
    "$DATA_PATH/redis" \
    "$DATA_PATH/storage/app/public" \
    "$DATA_PATH/storage/app/private" \
    "$DATA_PATH/storage/framework/cache/data" \
    "$DATA_PATH/storage/framework/sessions" \
    "$DATA_PATH/storage/framework/testing" \
    "$DATA_PATH/storage/framework/views" \
    "$DATA_PATH/storage/logs" \
    "$DATA_PATH/storage/api-docs"
}

if ensure_data_dirs 2>/dev/null; then
  :
else
  run_root bash -c "mkdir -p \
    '$DATA_PATH/mysql' \
    '$DATA_PATH/redis' \
    '$DATA_PATH/storage/app/public' \
    '$DATA_PATH/storage/app/private' \
    '$DATA_PATH/storage/framework/cache/data' \
    '$DATA_PATH/storage/framework/sessions' \
    '$DATA_PATH/storage/framework/testing' \
    '$DATA_PATH/storage/framework/views' \
    '$DATA_PATH/storage/logs' \
    '$DATA_PATH/storage/api-docs'"
fi

# One-time copy of existing repo storage into the persistent path (branding uploads, etc.)
if [[ ! -f "$DATA_PATH/storage/.initialized" ]]; then
  log "Initializing persistent storage from backend/storage (one-time)"
  if [[ -d "$ROOT/backend/storage/app" ]]; then
    if cp -a "$ROOT/backend/storage/app/." "$DATA_PATH/storage/app/" 2>/dev/null; then
      :
    else
      run_root cp -a "$ROOT/backend/storage/app/." "$DATA_PATH/storage/app/"
    fi
  fi
  if touch "$DATA_PATH/storage/.initialized" 2>/dev/null; then
    :
  else
    run_root touch "$DATA_PATH/storage/.initialized"
  fi
fi

# php-fpm in the app image runs as www-data (uid 33)
if chown -R 33:33 "$DATA_PATH/storage" 2>/dev/null; then
  :
else
  run_root chown -R 33:33 "$DATA_PATH/storage" || true
fi
run_root chmod -R ug+rwX "$DATA_PATH/storage" 2>/dev/null || chmod -R ug+rwX "$DATA_PATH/storage" || true
log "Laravel storage → ${DATA_PATH}/storage (bind-mounted in app/queue/nginx)"

ensure_storage_link() {
  mkdir -p "$DATA_PATH/storage/app/public/branding" 2>/dev/null \
    || run_root mkdir -p "$DATA_PATH/storage/app/public/branding"
  local link="$ROOT/backend/public/storage"
  rm -f "$link" 2>/dev/null || run_root rm -f "$link" || true
  if ln -sfn "$DATA_PATH/storage/app/public" "$link" 2>/dev/null; then
    :
  else
    run_root ln -sfn "$DATA_PATH/storage/app/public" "$link" \
      || die "Could not create storage link at backend/public/storage"
  fi
  log "Storage link: backend/public/storage → ${DATA_PATH}/storage/app/public"
}

# Seed default logos into persistent public disk if missing (uploads create branding/ later)
ensure_default_branding_assets() {
  local dest="$DATA_PATH/storage/app/public/branding"
  local src="$ROOT/backend/storage/app/public/branding"
  mkdir -p "$dest" 2>/dev/null || run_root mkdir -p "$dest"
  if [[ -d "$src" ]]; then
    for f in logo.png logo-dark.png; do
      if [[ -f "$src/$f" && ! -f "$dest/$f" ]]; then
        cp -a "$src/$f" "$dest/$f" 2>/dev/null || run_root cp -a "$src/$f" "$dest/$f" || true
        log "Seeded default branding asset: $f"
      fi
    done
  fi
  if [[ -f "$ROOT/backend/storage/app/public/branding-logo.png" && ! -f "$DATA_PATH/storage/app/public/branding-logo.png" ]]; then
    cp -a "$ROOT/backend/storage/app/public/branding-logo.png" \
      "$DATA_PATH/storage/app/public/branding-logo.png" 2>/dev/null \
      || run_root cp -a "$ROOT/backend/storage/app/public/branding-logo.png" \
        "$DATA_PATH/storage/app/public/branding-logo.png" || true
  fi
  chown -R 33:33 "$DATA_PATH/storage/app/public" 2>/dev/null \
    || run_root chown -R 33:33 "$DATA_PATH/storage/app/public" || true
}

ensure_storage_link
ensure_default_branding_assets

# Redis official image runs as uid 999 — wrong ownership causes crash / unhealthy
if chown -R 999:999 "$DATA_PATH/redis" 2>/dev/null; then
  :
else
  run_root chown -R 999:999 "$DATA_PATH/redis" || true
fi

# ---------------------------------------------------------------------------
# 3. Frontend build (host npm OR Docker node — npm is NOT required on the server)
# ---------------------------------------------------------------------------
build_frontend_host() {
  need_cmd npm
  log "Building frontend with host npm"
  (
    cd "$ROOT/frontend"
    npm ci --legacy-peer-deps
    npm run build
  )
}

build_frontend_docker() {
  local node_image="${FRONTEND_NODE_IMAGE:-node:22-alpine}"
  log "Building frontend with Docker ($node_image) — no host npm required"
  docker run --rm \
    -v "$ROOT/frontend:/app" \
    -w /app \
    "$node_image" \
    sh -c "npm ci --legacy-peer-deps && npm run build"

  # Ensure dist is readable by the nginx container user
  if [[ -d "$ROOT/frontend/dist" ]]; then
    chmod -R a+rX "$ROOT/frontend/dist" || true
  fi
}

if [[ "$SKIP_FRONTEND_BUILD" != "true" ]]; then
  case "$FRONTEND_BUILD" in
    host)
      build_frontend_host
      ;;
    docker)
      build_frontend_docker
      ;;
    auto|"")
      if command -v npm >/dev/null 2>&1; then
        build_frontend_host
      else
        warn "npm not found on host — building frontend via Docker node image"
        build_frontend_docker
      fi
      ;;
    *)
      die "Invalid --frontend-build=$FRONTEND_BUILD (use auto|docker|host)"
      ;;
  esac
  [[ -d "$ROOT/frontend/dist" ]] || die "frontend/dist missing after build"
else
  warn "Skipping frontend build"
  [[ -d "$ROOT/frontend/dist" ]] || die "frontend/dist missing — run without --skip-frontend-build"
fi

# ---------------------------------------------------------------------------
# 4. Docker stack
# ---------------------------------------------------------------------------
API_HOST_PORT="${API_HOST_PORT:-8089}"
API_HEALTH_URL="http://127.0.0.1:${API_HOST_PORT}/api/v1/health"
API_UP_URL="http://127.0.0.1:${API_HOST_PORT}/up"

ensure_backend_vendor() {
  # Always run before compose up. Incomplete vendor/ (autoload present but packages
  # missing) has caused production 500s — never trust a partial tree.
  local vendor_dir="$ROOT/backend/vendor"
  local autoload="$vendor_dir/autoload.php"
  local sentinel="$vendor_dir/symfony/deprecation-contracts/function.php"
  local force="${FORCE_VENDOR_REINSTALL:-false}"

  vendor_is_healthy() {
    [[ -f "$autoload" ]] || return 1
    [[ -f "$sentinel" ]] || return 1
    [[ -f "$vendor_dir/predis/predis/composer.json" ]] || return 1
    [[ -d "$vendor_dir/laravel/framework" ]] || return 1
    docker run --rm -v "$ROOT/backend:/app" -w /app composer:2 \
      php -r 'require "vendor/autoload.php"; echo "ok";' >/dev/null 2>&1
  }

  run_composer_install() {
    log "Running: composer install --no-dev --optimize-autoloader"
    docker run --rm \
      -v "$ROOT/backend:/app" \
      -w /app \
      composer:2 \
      composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
  }

  if [[ "$force" == "true" ]] || ! vendor_is_healthy; then
    if [[ -d "$vendor_dir" ]]; then
      warn "Removing incomplete/stale backend/vendor before reinstall"
      rm -rf "$vendor_dir"
    else
      log "backend/vendor missing — installing PHP dependencies"
    fi
    run_composer_install
  else
    log "PHP vendor/ healthy — refreshing with composer install"
    run_composer_install
  fi

  [[ -f "$autoload" ]] || die "composer install did not create vendor/autoload.php"
  [[ -f "$sentinel" ]] || die "composer install incomplete (missing symfony/deprecation-contracts)"
  [[ -f "$vendor_dir/predis/predis/composer.json" ]] || die "composer install incomplete (missing predis/predis)"
  vendor_is_healthy || die "vendor/autoload.php still fails to load after composer install"
  log "PHP vendor/ OK"
}

sync_backend_db_password() {
  local db_pass
  db_pass="$(env_file_get "$ROOT/docker/.env" DB_PASSWORD)"
  [[ -n "$db_pass" ]] || return 0
  if [[ -f "$ROOT/backend/.env" ]]; then
    if grep -q '^DB_PASSWORD=' "$ROOT/backend/.env"; then
      awk -v p="$db_pass" 'BEGIN{done=0} /^DB_PASSWORD=/{print "DB_PASSWORD=\"" p "\""; done=1; next} {print} END{if(!done) print "DB_PASSWORD=\"" p "\""}' \
        "$ROOT/backend/.env" > "$ROOT/backend/.env.tmp"
      mv "$ROOT/backend/.env.tmp" "$ROOT/backend/.env"
      chmod 600 "$ROOT/backend/.env"
    else
      printf 'DB_PASSWORD="%s"\n' "$db_pass" >> "$ROOT/backend/.env"
    fi
    log "Synced DB_PASSWORD into backend/.env"
  fi
}

docker_env_get() {
  env_file_get "$ROOT/docker/.env" "$1"
}

sql_escape() {
  # Escape \ and ' for MySQL string literals
  local s="$1"
  s="${s//\\/\\\\}"
  s="${s//\'/\\\'}"
  printf '%s' "$s"
}

wait_for_mysql_healthy() {
  local i
  log "Waiting for MySQL container to be healthy"
  for i in $(seq 1 36); do
    if "${COMPOSE[@]}" ps mysql 2>/dev/null | grep -qi 'healthy'; then
      return 0
    fi
    sleep 5
  done
  warn "MySQL did not report healthy — continuing anyway"
  return 0
}

redis_is_healthy() {
  "${COMPOSE[@]}" ps redis 2>/dev/null | grep -qi 'healthy'
}

reset_redis_data_volume() {
  local data_path="${EMAIL_SERVER_DATA_PATH:-$DATA_PATH}"
  local env_path
  env_path="$(docker_env_get EMAIL_SERVER_DATA_PATH || true)"
  [[ -n "$env_path" ]] && data_path="$env_path"

  log "RESET REDIS: stopping redis and wiping ${data_path}/redis (queues/cache only)"
  (
    cd "$ROOT/docker"
    "${COMPOSE[@]}" stop redis || true
    "${COMPOSE[@]}" rm -f redis || true
  )
  if [[ -d "${data_path}/redis" ]]; then
    if rm -rf "${data_path}/redis"/* 2>/dev/null; then
      :
    else
      run_root rm -rf "${data_path}/redis"/*
    fi
  else
    mkdir -p "${data_path}/redis" 2>/dev/null || run_root mkdir -p "${data_path}/redis"
  fi
  chown -R 999:999 "${data_path}/redis" 2>/dev/null || run_root chown -R 999:999 "${data_path}/redis"
  (
    cd "$ROOT/docker"
    "${COMPOSE[@]}" up -d --force-recreate redis
  )
}

wait_for_redis_healthy() {
  local i
  log "Waiting for Redis container to be healthy"
  for i in $(seq 1 24); do
    if redis_is_healthy; then
      return 0
    fi
    sleep 2
  done
  return 1
}

ensure_redis_ready() {
  if [[ "$RESET_REDIS" == "true" ]]; then
    reset_redis_data_volume
    wait_for_redis_healthy && return 0
    die "Redis still unhealthy after --reset-redis. Check: cd $ROOT/docker && docker compose logs redis --tail 80"
  fi

  (
    cd "$ROOT/docker"
    "${COMPOSE[@]}" up -d redis
  )

  if wait_for_redis_healthy; then
    log "Redis OK"
    return 0
  fi

  warn "Redis unhealthy — trying ownership fix (uid 999) and recreate"
  chown -R 999:999 "$DATA_PATH/redis" 2>/dev/null || run_root chown -R 999:999 "$DATA_PATH/redis" || true
  (
    cd "$ROOT/docker"
    "${COMPOSE[@]}" up -d --force-recreate redis
  )
  if wait_for_redis_healthy; then
    log "Redis OK after permission fix"
    return 0
  fi

  warn "Redis still unhealthy — logs:"
  (
    cd "$ROOT/docker"
    "${COMPOSE[@]}" logs redis --tail 40
  ) >&2 || true

  die "Redis container is unhealthy (blocks app + queue).

Fix on the server:
  cd $ROOT/docker
  docker compose logs redis --tail 80

Usually caused by bad permissions or corrupt AOF in ${DATA_PATH}/redis.
Safe recovery (queues/cache only — does NOT touch MySQL):
  cd $ROOT && ./setup.sh --reset-redis

Or manually:
  cd $ROOT/docker && docker compose stop redis
  sudo rm -rf ${DATA_PATH}/redis/*
  sudo chown -R 999:999 ${DATA_PATH}/redis
  docker compose up -d --remove-orphans redis
"
}

# Must test with the SAME credentials Laravel uses (container env + backend/.env).
# Do NOT inject a different password here — that caused false "auth OK" while login 500'd.
mysql_app_auth_ok() {
  "${COMPOSE[@]}" exec -T app php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    try {
      Illuminate\Support\Facades\DB::connection()->getPdo();
      Illuminate\Support\Facades\DB::select("select 1");
      exit(0);
    } catch (Throwable $e) {
      fwrite(STDERR, $e->getMessage() . PHP_EOL);
      exit(1);
    }
  ' >/dev/null 2>&1
}

mysql_root_auth_ok() {
  local root_pass="$1"
  # Real query — mysqladmin ping can be misleading with auth failures
  "${COMPOSE[@]}" exec -T -e MYSQL_PWD="$root_pass" mysql \
    mysql -u root -h 127.0.0.1 -e 'SELECT 1;' >/dev/null 2>&1
}

reset_mysql_app_user() {
  local root_pass="$1"
  local esc_pass="$2"
  local hosts host

  "${COMPOSE[@]}" exec -T -e MYSQL_PWD="$root_pass" mysql \
    mysql -u root -h 127.0.0.1 -e "
CREATE DATABASE IF NOT EXISTS email_server CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'email_server'@'%' IDENTIFIED BY '${esc_pass}';
ALTER USER 'email_server'@'%' IDENTIFIED BY '${esc_pass}';
GRANT ALL PRIVILEGES ON email_server.* TO 'email_server'@'%';
FLUSH PRIVILEGES;
" || return 1

  # Sync password for every host the user already exists on (localhost, %, etc.)
  hosts="$("${COMPOSE[@]}" exec -T -e MYSQL_PWD="$root_pass" mysql \
    mysql -u root -h 127.0.0.1 -N -e "SELECT Host FROM mysql.user WHERE User='email_server';" 2>/dev/null | tr -d '\r')"
  while IFS= read -r host; do
    [[ -z "$host" ]] && continue
    "${COMPOSE[@]}" exec -T -e MYSQL_PWD="$root_pass" mysql \
      mysql -u root -h 127.0.0.1 -e "ALTER USER 'email_server'@'${host}' IDENTIFIED BY '${esc_pass}'; GRANT ALL PRIVILEGES ON email_server.* TO 'email_server'@'${host}';" \
      || warn "Could not ALTER email_server@${host}"
  done <<< "$hosts"

  "${COMPOSE[@]}" exec -T -e MYSQL_PWD="$root_pass" mysql \
    mysql -u root -h 127.0.0.1 -e "FLUSH PRIVILEGES;" || true
}

# Wipe bind-mounted MySQL data and recreate the container so MYSQL_* env
# passwords from docker/.env are applied on first initialization.
reset_mysql_data_volume() {
  local data_path="${EMAIL_SERVER_DATA_PATH:-$DATA_PATH}"
  # Prefer path written into docker/.env (what the container actually mounts)
  local env_path
  env_path="$(docker_env_get EMAIL_SERVER_DATA_PATH || true)"
  [[ -n "$env_path" ]] && data_path="$env_path"

  log "RESET MYSQL: stopping mysql and wiping ${data_path}/mysql (DESTROYS DB DATA)"
  (
    cd "$ROOT/docker"
    "${COMPOSE[@]}" stop mysql || true
    "${COMPOSE[@]}" rm -f mysql || true
  )
  if [[ -d "${data_path}/mysql" ]]; then
    run_root rm -rf "${data_path}/mysql"
  fi
  run_root mkdir -p "${data_path}/mysql"
  # MySQL image needs write access as mysql uid (typically 999)
  run_root chown -R 999:999 "${data_path}/mysql" 2>/dev/null || true

  log "Starting fresh MySQL with passwords from docker/.env"
  (
    cd "$ROOT/docker"
    "${COMPOSE[@]}" up -d --force-recreate mysql
  )
  wait_for_mysql_healthy

  local db_pass root_pass tries
  db_pass="$(docker_env_get DB_PASSWORD)"
  root_pass="$(docker_env_get MYSQL_ROOT_PASSWORD)"

  # First-boot init can take a while on empty datadir
  tries=1
  while [[ "$tries" -le 30 ]]; do
    if mysql_root_auth_ok "$root_pass"; then
      break
    fi
    tries=$((tries + 1))
    sleep 2
  done

  if ! mysql_root_auth_ok "$root_pass"; then
    die "Fresh MySQL still rejects MYSQL_ROOT_PASSWORD — check docker/.env secrets match MYSQL_ROOT_PASSWORD used to create the container"
  fi
  if ! mysql_app_auth_ok; then
    warn "Fresh MySQL app user not ready yet — ensuring email_server@%"
    reset_mysql_app_user "$root_pass" "$(sql_escape "$db_pass")" || true
    sleep 3
    mysql_app_auth_ok || die "Fresh MySQL email_server auth still failing"
  fi

  # Recreate app/queue so container env matches docker/.env after a wipe
  log "Recreating app + queue after MySQL reset"
  (
    cd "$ROOT/docker"
    "${COMPOSE[@]}" up -d --force-recreate --no-deps app
    "${COMPOSE[@]}" up -d --force-recreate --no-deps --scale "queue=${QUEUE_SCALE:-1}" queue
  )
  sleep 5
  mysql_app_auth_ok || die "Laravel still cannot connect to MySQL after app recreate"
  log "Fresh MySQL ready with current DB_PASSWORD / MYSQL_ROOT_PASSWORD"
}

# MySQL only applies MYSQL_PASSWORD on first volume init. If secrets change later,
# the volume keeps old passwords. Prefer ALTER via root (keeps data).
# Never auto-wipe — that destroyed admins on every re-setup. Use --reset-mysql explicitly.
sync_mysql_volume_password() {
  local db_pass root_pass esc_pass
  db_pass="$(docker_env_get DB_PASSWORD)"
  root_pass="$(docker_env_get MYSQL_ROOT_PASSWORD)"
  [[ -n "$db_pass" ]] || die "DB_PASSWORD missing from docker/.env"
  [[ -n "$root_pass" ]] || die "MYSQL_ROOT_PASSWORD missing from docker/.env"

  if [[ "$RESET_MYSQL" == "true" ]]; then
    warn "--reset-mysql set: wiping MySQL data and re-initializing (DESTROYS DB DATA)"
    reset_mysql_data_volume
    sync_backend_db_password
    log "Running migrations after MySQL reset"
    "${COMPOSE[@]}" exec -T app php artisan migrate --force \
      || warn "migrate still failing — check app logs"
    return 0
  fi

  wait_for_mysql_healthy

  # Ensure app container is up enough for the PDO network check
  if ! "${COMPOSE[@]}" ps app 2>/dev/null | grep -qi 'Up'; then
    warn "App container not Up yet — waiting briefly for network auth check"
    sleep 5
  fi

  if mysql_app_auth_ok; then
    log "MySQL app user auth OK (Laravel → mysql:3306)"
    return 0
  fi

  warn "Laravel cannot connect to MySQL with current DB_PASSWORD"

  if mysql_root_auth_ok "$root_pass"; then
    warn "Resetting email_server password via root (data preserved)"
    esc_pass="$(sql_escape "$db_pass")"
    if reset_mysql_app_user "$root_pass" "$esc_pass"; then
      sync_backend_db_password
      (
        cd "$ROOT/docker"
        "${COMPOSE[@]}" up -d --force-recreate --no-deps app
      )
      sleep 4
      if mysql_app_auth_ok; then
        log "MySQL email_server@% password reset to match DB_PASSWORD (no data wipe)"
        log "Running migrations after MySQL password sync"
        "${COMPOSE[@]}" exec -T app php artisan migrate --force \
          || warn "migrate still failing — check app logs"
        return 0
      fi
    fi
    warn "Root ALTER did not fix app auth"
  else
    warn "MYSQL_ROOT_PASSWORD also does not match the volume"
  fi

  die "MySQL credentials in docker/.env do not match the existing data volume.

Data was NOT wiped. Fix (pick one):

  A) Put the ORIGINAL DB_PASSWORD + MYSQL_ROOT_PASSWORD back into docker/.env
     and backend/.env, then re-run ./setup.sh (preserves data).

  B) Explicitly wipe and re-seed (DESTROYS DATA — only if you accept losing DB):
       ./setup.sh --reset-mysql

  C) Manual wipe:
       cd $ROOT/docker && docker compose stop mysql
       sudo rm -rf ${DATA_PATH}/mysql && sudo mkdir -p ${DATA_PATH}/mysql
       docker compose up -d
"
}

app_is_crash_looping() {
  "${COMPOSE[@]}" ps 2>/dev/null | grep -E 'email-server-app' | grep -qi 'Restarting'
}

app_is_up() {
  "${COMPOSE[@]}" ps 2>/dev/null | grep -E 'email-server-app' | grep -qi 'Up' \
    && ! "${COMPOSE[@]}" ps 2>/dev/null | grep -E 'email-server-app' | grep -qi 'Restarting'
}

wait_for_api() {
  local max_attempts="${1:-45}"
  local i code nginx_recreated=0 queue_warned=0
  log "Waiting for API on :${API_HOST_PORT} (up to ~$((max_attempts * 2))s)"
  log "Liveness check: GET ${API_UP_URL}"

  for i in $(seq 1 "$max_attempts"); do
    # Only fail-fast on the API container — a bad queue must not abort /up checks
    if app_is_crash_looping; then
      warn "App container is crash-looping — dumping logs"
      "${COMPOSE[@]}" ps || true
      "${COMPOSE[@]}" logs app --tail 100 || true
      return 1
    fi

    if [[ "$queue_warned" -eq 0 ]] \
      && "${COMPOSE[@]}" ps 2>/dev/null | grep -E 'docker-queue|queue-' | grep -qi 'Restarting'; then
      warn "Queue is Restarting (does not block API health) — see: docker compose logs queue --tail 50"
      queue_warned=1
    fi

    code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 2 --max-time 5 "$API_UP_URL" 2>/dev/null || true)"
    code="$(printf '%s' "${code:-000}" | tr -cd '0-9')"
    # normalize 000000 -> 000
    if [[ "$code" =~ 000+$ ]]; then code="000"; fi
    if [[ ${#code} -gt 3 ]]; then code="${code: -3}"; fi
    [[ -z "$code" ]] && code="000"

    if [[ "$code" == "200" ]]; then
      # /up does NOT check MySQL — also require API health DB=ok before continuing
      health="$(curl -fsS --connect-timeout 2 --max-time 5 "$API_HEALTH_URL" 2>/dev/null || true)"
      if printf '%s' "$health" | grep -q '"database":{"status":"ok"}'; then
        log "API is up (attempt ${i}/${max_attempts}) — /up=200 and database ok"
        return 0
      fi
      if [[ "$i" -eq 1 ]] || (( i % 3 == 0 )); then
        warn "/up=200 but database not ok yet — Laravel may still be rejecting DB_PASSWORD"
      fi
    fi

    # App up but nginx still 000/502 — bounce nginx once
    if [[ "$nginx_recreated" -eq 0 ]] && app_is_up && [[ "$i" -ge 6 ]]; then
      warn "App is Up but /up=${code} — recreating nginx"
      "${COMPOSE[@]}" up -d --force-recreate --no-deps nginx || true
      nginx_recreated=1
      sleep 3
      continue
    fi

    if (( i % 3 == 0 )); then
      printf '    … still starting (%s/%s) /up=%s\n' "$i" "$max_attempts" "$code"
      "${COMPOSE[@]}" ps --format 'table {{.Name}}\t{{.Status}}' 2>/dev/null | sed 's/^/       /' || true
    fi
    sleep 2
  done

  warn "API liveness timed out on ${API_UP_URL} (or database still unhealthy)"
  "${COMPOSE[@]}" ps || true
  "${COMPOSE[@]}" logs app --tail 100 || true
  "${COMPOSE[@]}" logs nginx --tail 40 || true
  "${COMPOSE[@]}" logs queue --tail 40 || true
  curl -sS "$API_HEALTH_URL" || true
  echo
  return 1
}

log "Preparing backend vendor + DB password sync"
ensure_backend_vendor
sync_backend_db_password
rm -f "$ROOT/backend/bootstrap/cache/config.php" \
  "$ROOT/backend/bootstrap/cache/routes-v7.php" \
  "$ROOT/backend/bootstrap/cache/routes.php" 2>/dev/null || true

log "Starting Docker stack"
ensure_redis_ready
(
  cd "$ROOT/docker"
  # --remove-orphans drops leftover containers from old compose project names / scale changes
  RUN_SEEDER=false "${COMPOSE[@]}" up -d --build --remove-orphans --scale "queue=${QUEUE_SCALE}"
)

# Align MySQL volume passwords with docker/.env BEFORE health/seed
sync_mysql_volume_password

if ! wait_for_api 45; then
  die "API did not become healthy.

Try these on the server:
  cd $ROOT/docker
  docker compose logs app --tail 100
  docker compose ps

If DB auth fails, either set DB_PASSWORD / MYSQL_ROOT_PASSWORD in docker/.env +
backend/.env to the ORIGINAL MySQL volume passwords, or reset the volume
(DESTROYS DATA):
  docker compose down
  sudo rm -rf ${DATA_PATH}/mysql/*
  docker compose up -d
"
fi

log "Ensuring Laravel storage:link in app container"
"${COMPOSE[@]}" exec -T app php artisan storage:link --force \
  && log "storage:link OK" \
  || warn "storage:link failed in container — check app logs"

# Pre-generate OpenAPI JSON for Swagger UI (non-fatal)
log "Generating OpenAPI spec for /api/docs.json"
"${COMPOSE[@]}" exec -T app php -r '
  require "vendor/autoload.php";
  $app = require "bootstrap/app.php";
  $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  try {
    $openapi = OpenApi\Generator::scan([app_path("OpenApi")]);
    $dir = storage_path("api-docs");
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    file_put_contents($dir."/openapi.json", $openapi->toJson());
    echo "openapi_ok\n";
  } catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(1);
  }
' && log "OpenAPI cached at storage/api-docs/openapi.json" \
  || warn "OpenAPI generation failed — /api/docs.json will try live scan"

# Seed / ensure admin after API is alive
if [[ "$RUN_SEEDER" == "true" ]]; then
  log "Seeding database / ensuring admin user (password = ADMIN_PASSWORD from secrets)"
  if ! "${COMPOSE[@]}" exec -T \
    -e ADMIN_EMAIL="$ADMIN_EMAIL" \
    -e ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    -e ADMIN_RESET_PASSWORD=true \
    -e RUN_SEEDER=true \
    app php artisan db:seed --force; then
    warn "db:seed failed — trying direct admin upsert"
    if ! "${COMPOSE[@]}" exec -T \
      -e ADMIN_EMAIL="$ADMIN_EMAIL" \
      -e ADMIN_PASSWORD="$ADMIN_PASSWORD" \
      app php artisan tinker --execute="
\$email = getenv('ADMIN_EMAIL');
\$pass = getenv('ADMIN_PASSWORD');
\$u = App\Models\User::query()->updateOrCreate(
  ['email' => \$email],
  [
    'name' => 'Super Admin',
    'password' => Illuminate\Support\Facades\Hash::make(\$pass),
    'is_admin' => true,
    'is_active' => true,
  ]
);
echo 'admin='.\$u->email.PHP_EOL;
"; then
      die "Could not seed/upsert admin — MySQL auth or migrate likely still failing.
Re-run with matching DB_PASSWORD/MYSQL_ROOT_PASSWORD, or:
  cd $ROOT && ./setup.sh --env-file=... --reset-mysql
"
    fi
  fi

  # Prove the seeded password works (same path curl uses)
  log "Verifying admin login with seeded password"
  verify_code="$("${COMPOSE[@]}" exec -T \
    -e ADMIN_EMAIL="$ADMIN_EMAIL" \
    -e ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    app php -r '
      require "vendor/autoload.php";
      $app = require "bootstrap/app.php";
      $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      $u = App\Models\User::query()->where("email", getenv("ADMIN_EMAIL"))->first();
      if (!$u) { fwrite(STDERR, "admin user missing\n"); exit(2); }
      if (!Illuminate\Support\Facades\Hash::check(getenv("ADMIN_PASSWORD"), $u->password)) {
        fwrite(STDERR, "ADMIN_PASSWORD hash mismatch\n"); exit(3);
      }
      echo "admin_password_ok\n";
    ' 2>&1)" || true
  if ! printf '%s' "$verify_code" | grep -q 'admin_password_ok'; then
    die "Admin password verification failed after seed:
$verify_code

The password that works is ADMIN_PASSWORD from docker/.env —
not a placeholder like change-me-in-production."
  fi
  log "Admin password verified for ${ADMIN_EMAIL}"
fi

# Disable reseed for subsequent boots
if [[ "$RUN_SEEDER" == "true" ]]; then
  log "Setting RUN_SEEDER=false for subsequent starts"
  awk 'BEGIN{done=0} /^RUN_SEEDER=/{print "RUN_SEEDER=false"; done=1; next} {print} END{if(!done) print "RUN_SEEDER=false"}' \
    "$ROOT/docker/.env" > "$ROOT/docker/.env.tmp"
  mv "$ROOT/docker/.env.tmp" "$ROOT/docker/.env"
  chmod 600 "$ROOT/docker/.env"
  awk 'BEGIN{done=0} /^ADMIN_RESET_PASSWORD=/{print "ADMIN_RESET_PASSWORD=false"; done=1; next} {print} END{if(!done) print "ADMIN_RESET_PASSWORD=false"}' \
    "$ROOT/docker/.env" > "$ROOT/docker/.env.tmp"
  mv "$ROOT/docker/.env.tmp" "$ROOT/docker/.env"
  chmod 600 "$ROOT/docker/.env"
fi

# ---------------------------------------------------------------------------
# 5. Host Nginx
# ---------------------------------------------------------------------------
if [[ "$SKIP_NGINX" != "true" ]]; then
  log "Installing Nginx site + security headers for ${DOMAIN}"
  run_root mkdir -p /etc/nginx/snippets /etc/nginx/conf.d

  # Shared memory zones (http{}) — required before any site uses limit_req zone=api_limit
  run_root cp "$ROOT/deploy/configs/nginx-http-rate-limit.conf" \
    /etc/nginx/conf.d/email-server-rate-limit.conf

  run_root cp "$ROOT/deploy/configs/nginx-security-headers.conf" \
    /etc/nginx/snippets/email-server-security-headers.conf

  SRC="$ROOT/deploy/configs/nginx-notifications.africacdc.org.conf"
  TMP="$(mktemp)"
  sed "s/notifications\.africacdc\.org/${DOMAIN}/g" "$SRC" > "$TMP"
  # Preserve an existing Certbot-managed SSL server block if present; replace HTTP+shared bits carefully.
  # Always install the hardened template; Certbot --keep-until-expiring will re-attach SSL afterward.
  run_root cp "$TMP" "/etc/nginx/sites-available/${DOMAIN}.conf"
  rm -f "$TMP"
  run_root ln -sfn "/etc/nginx/sites-available/${DOMAIN}.conf" "/etc/nginx/sites-enabled/${DOMAIN}.conf"
  if run_root nginx -t; then
    run_root systemctl reload nginx
  else
    warn "nginx -t failed after installing hardened site — check /etc/nginx/sites-available/${DOMAIN}.conf"
    warn "Also check: sudo grep -R api_limit /etc/nginx/"
  fi
else
  warn "Skipping Nginx site install"
fi

# ---------------------------------------------------------------------------
# 6. Certbot SSL
# ---------------------------------------------------------------------------
if [[ "$SKIP_SSL" != "true" ]]; then
  log "Issuing/installing SSL certificate with Certbot for ${DOMAIN}"

  # Ensure rate-limit zones exist even if --skip-nginx was used earlier on a broken host
  if [[ -f "$ROOT/deploy/configs/nginx-http-rate-limit.conf" ]]; then
    run_root mkdir -p /etc/nginx/conf.d
    run_root cp "$ROOT/deploy/configs/nginx-http-rate-limit.conf" \
      /etc/nginx/conf.d/email-server-rate-limit.conf
    run_root nginx -t && run_root systemctl reload nginx || warn "nginx -t still failing before certbot"
  fi

  if ! run_root certbot --nginx \
    -d "$DOMAIN" \
    --agree-tos \
    --redirect \
    -m "$CERTBOT_EMAIL" \
    --non-interactive \
    --keep-until-expiring; then
    warn "Certbot --nginx failed — trying webroot HTTP-01 instead"
    run_root mkdir -p /var/www/html
    run_root certbot certonly --webroot \
      -w /var/www/html \
      -d "$DOMAIN" \
      --agree-tos \
      -m "$CERTBOT_EMAIL" \
      --non-interactive \
      --keep-until-expiring \
      || warn "Certbot webroot also failed — fix nginx (api_limit zone) then re-run certbot"
  fi

  log "Verifying HTTPS"
  curl -fsSI "https://${DOMAIN}/api/v1/health" | head -n 1 || warn "HTTPS health check failed — DNS/firewall may need attention"

  # Browser login uses Origin: https://DOMAIN — must NOT be CSRF 419 / Server Error
  login_probe="$(curl -sS -o /tmp/email_server_login_probe.json -w '%{http_code}' \
    -X POST "https://${DOMAIN}/api/v1/admin/auth/login" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    -H "Origin: https://${DOMAIN}" \
    -H "Referer: https://${DOMAIN}/login" \
    -d '{"email":"probe@example.com","password":"invalid-password-probe"}' 2>/dev/null || true)"
  if [[ "$login_probe" == "422" ]] || [[ "$login_probe" == "401" ]]; then
    log "HTTPS login endpoint OK (HTTP ${login_probe} with browser Origin)"
  else
    warn "HTTPS login probe returned HTTP ${login_probe} (expected 422). Body:"
    cat /tmp/email_server_login_probe.json 2>/dev/null | head -c 400 || true
    echo
    warn "If you see CSRF / Server Error, ensure backend/bootstrap/app.php has no statefulApi() and restart app."
  fi

  run_root certbot renew --dry-run || warn "Certbot renew dry-run reported issues"
else
  warn "Skipping SSL (--skip-ssl)"
fi

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------
cat <<EOF

========================================================================
 Email Server deploy complete
========================================================================
  Admin UI : https://${DOMAIN}
  API      : https://${DOMAIN}/api
  Health   : https://${DOMAIN}/api/v1/health

  Admin email : ${ADMIN_EMAIL}
  Admin pass  : (value from docker/.env ADMIN_PASSWORD — not printed)

  Env files used (gitignored — edit these manually on the server):
    ${ROOT}/docker/.env
    ${ROOT}/backend/.env

  Data path:
    ${DATA_PATH}/{mysql,redis,storage}
    (Laravel storage is bind-mounted from ${DATA_PATH}/storage)

  Certbot certificate (if SSL enabled):
    /etc/letsencrypt/live/${DOMAIN}/fullchain.pem
    /etc/letsencrypt/live/${DOMAIN}/privkey.pem

Next steps:
  1. Sign in with ADMIN_EMAIL / ADMIN_PASSWORD from docker/.env
  2. Enable 2FA
  3. Keep .env files out of git
========================================================================
EOF
