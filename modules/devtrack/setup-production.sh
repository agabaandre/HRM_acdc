#!/usr/bin/env bash
#
# DevTrack — host Apache production deploy (beside Staff at /staff/devtrack/).
#
# First time:
#   cp setup.env.example setup.env
#   # DB_DATABASE=cdc_devtrack (default); inherit DB user/pass from /staff/.env
#   ./setup-production.sh
#
# Re-deploy after git pull:
#   ./setup-production.sh
#
# Options:
#   --skip-migrate    Skip php artisan migrate
#   --skip-build      Skip frontend npm build
#   --skip-optimize   Skip config/route/view cache
#   -h, --help
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"
STAFF_ROOT="$(cd "$ROOT/../.." && pwd)"

SKIP_MIGRATE=0
SKIP_BUILD=0
SKIP_OPTIMIZE=0

usage() { sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'; }

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-migrate) SKIP_MIGRATE=1 ;;
    --skip-build) SKIP_BUILD=1 ;;
    --skip-optimize) SKIP_OPTIMIZE=1 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage >&2; exit 1 ;;
  esac
  shift
done

log() { printf '==> %s\n' "$*"; }
warn() { printf 'warning: %s\n' "$*" >&2; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

[[ -f "$BACKEND/artisan" ]] || die "Missing $BACKEND/artisan — deploy full modules/devtrack from git"

SETUP_ENV="$ROOT/setup.env"
[[ -f "$SETUP_ENV" ]] || {
  cp "$ROOT/setup.env.example" "$SETUP_ENV"
  warn "Created $SETUP_ENV — defaults use cdc_devtrack; re-run after reviewing"
}

# Minimal env upsert without requiring the finance-shaped scripts/lib tree.
env_set() {
  local file="$1" key="$2" val="$3"
  [[ -f "$file" ]] || touch "$file"
  if grep -qE "^${key}=" "$file" 2>/dev/null; then
    local tmp
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$val" 'BEGIN{done=0} $0 ~ "^"k"=" {print k"="v; done=1; next} {print} END{if(!done) print k"="v}' "$file" >"$tmp"
    mv "$tmp" "$file"
  else
    printf '%s=%s\n' "$key" "$val" >>"$file"
  fi
}

env_get() {
  local file="$1" key="$2" line
  line="$(grep -E "^${key}=" "$file" 2>/dev/null | tail -1 || true)"
  [[ -n "$line" ]] || return 0
  printf '%s' "${line#*=}"
}

inherit_from() {
  local key="$1" from="$2" cur
  cur="$(env_get "$BACKEND/.env" "$key" 2>/dev/null || true)"
  [[ -z "$cur" ]] || return 0
  [[ -f "$from" ]] || return 0
  local val
  val="$(env_get "$from" "$key" 2>/dev/null || true)"
  [[ -n "$val" ]] || return 0
  env_set "$BACKEND/.env" "$key" "$val"
}

[[ -f "$BACKEND/.env" ]] || cp "$BACKEND/.env.example" "$BACKEND/.env"

# Production runtime
env_set "$BACKEND/.env" APP_ENV production
env_set "$BACKEND/.env" APP_DEBUG false
env_set "$BACKEND/.env" APP_NAME DevTrack
env_set "$BACKEND/.env" DB_CONNECTION mysql
env_set "$BACKEND/.env" DB_DATABASE "$(env_get "$SETUP_ENV" DB_DATABASE 2>/dev/null || echo cdc_devtrack)"

# Prefer staff root public URL
BASE_URL="$(env_get "$STAFF_ROOT/.env" BASE_URL 2>/dev/null || true)"
WEB_ROOT="$(env_get "$STAFF_ROOT/.env" WEB_ROOT 2>/dev/null || echo staff)"
BASE_URL="${BASE_URL:-https://cbp.africacdc.org/${WEB_ROOT}/}"
APP_URL="${BASE_URL%/}/devtrack"
env_set "$BACKEND/.env" APP_URL "$APP_URL"
env_set "$BACKEND/.env" BASE_URL "$BASE_URL"
env_set "$BACKEND/.env" WEB_ROOT "$WEB_ROOT"
env_set "$BACKEND/.env" SESSION_PATH "/${WEB_ROOT}/devtrack"
env_set "$ROOT/setup.env" APP_URL "$APP_URL"
env_set "$ROOT/setup.env" BASE_URL "$BASE_URL"
env_set "$ROOT/setup.env" DB_DATABASE "$(env_get "$BACKEND/.env" DB_DATABASE)"

# Shared DB / Redis / JWT from staff root (or IT as fallback)
for key in DB_HOST DB_PORT DB_USERNAME DB_PASSWORD REDIS_HOST REDIS_PORT REDIS_PASSWORD JWT_SECRET \
  STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN STAFF_API_INTERNAL_BASE_URL; do
  inherit_from "$key" "$STAFF_ROOT/.env"
  inherit_from "$key" "$ROOT/../it/backend/.env"
  inherit_from "$key" "$SETUP_ENV"
done

# Map root DB_USER/DB_PASS aliases if present
if [[ -z "$(env_get "$BACKEND/.env" DB_USERNAME)" ]]; then
  inherit_from DB_USERNAME "$STAFF_ROOT/.env"
  val="$(env_get "$STAFF_ROOT/.env" DB_USER 2>/dev/null || true)"
  [[ -n "$val" ]] && env_set "$BACKEND/.env" DB_USERNAME "$val"
fi
if [[ -z "$(env_get "$BACKEND/.env" DB_PASSWORD)" ]]; then
  val="$(env_get "$STAFF_ROOT/.env" DB_PASS 2>/dev/null || true)"
  [[ -n "$val" ]] && env_set "$BACKEND/.env" DB_PASSWORD "$val"
fi

printf '/staff/devtrack\n' >"$ROOT/app-base"

PHP_BIN="${PHP_BIN:-$(command -v php)}"
[[ -n "$PHP_BIN" ]] || die "php not found"
command -v composer >/dev/null 2>&1 || die "composer not found"
command -v npm >/dev/null 2>&1 || die "npm not found"

log "Installing backend PHP dependencies"
mkdir -p "$BACKEND/storage/framework/"{cache,sessions,views} "$BACKEND/storage/logs" "$BACKEND/bootstrap/cache"
chmod -R ug+rwx "$BACKEND/storage" "$BACKEND/bootstrap/cache" 2>/dev/null || true
composer install --no-dev --optimize-autoloader --no-interaction --working-dir="$BACKEND" \
  || die "composer install failed in $BACKEND"
[[ -f "$BACKEND/vendor/autoload.php" ]] || die "backend/vendor/autoload.php missing"

# shellcheck source=/dev/null
source "$STAFF_ROOT/scripts/setup/ensure-app-key.sh"
PHP_BIN="$PHP_BIN" ensure_app_key_in_env "$BACKEND" || warn "APP_KEY missing"

if [[ "$SKIP_MIGRATE" -eq 0 ]]; then
  log "Running migrations on $(env_get "$BACKEND/.env" DB_DATABASE)"
  (cd "$BACKEND" && "$PHP_BIN" artisan migrate --force --no-interaction) \
    || warn "migrate failed — check DB_* in backend/.env"
else
  log "Skipping migrations (--skip-migrate)"
fi

if [[ "$SKIP_BUILD" -eq 0 ]]; then
  log "Building DevTrack UI (Vite → ui/)"
  (
    cd "$FRONTEND"
    if [[ -f package-lock.json ]]; then
      npm ci --legacy-peer-deps
    else
      npm install --legacy-peer-deps
    fi
    npm run build
  ) || die "frontend build failed"
  [[ -f "$ROOT/ui/index.html" ]] || die "missing ui/index.html after build"
else
  log "Skipping frontend build (--skip-build)"
fi

if [[ "$SKIP_OPTIMIZE" -eq 0 ]]; then
  log "Caching Laravel config / routes / views"
  (cd "$BACKEND" && "$PHP_BIN" artisan config:cache --no-interaction) || true
  (cd "$BACKEND" && "$PHP_BIN" artisan route:cache --no-interaction) || true
  (cd "$BACKEND" && "$PHP_BIN" artisan view:cache --no-interaction) || true
fi

(cd "$BACKEND" && "$PHP_BIN" artisan storage:link --force --no-interaction 2>/dev/null) || true

log "Smoke test"
code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${APP_URL}/" 2>/dev/null || echo 000)"
if [[ "$code" == "200" || "$code" == "302" || "$code" == "503" ]]; then
  printf '    DevTrack HTTP %s at %s/\n' "$code" "$APP_URL"
else
  warn "DevTrack returned HTTP $code for ${APP_URL}/"
fi

echo ""
echo "DevTrack production setup complete."
echo "  App:  $APP_URL"
echo "  DB:   $(env_get "$BACKEND/.env" DB_DATABASE)"
echo "  UI:   $ROOT/ui"
