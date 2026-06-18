#!/usr/bin/env bash
#
# Finance — production deploy script (beside running Staff at /staff/finance/).
#
# First time on a server:
#   cp setup.env.example setup.env
#   nano setup.env          # production URLs, DB_*, JWT_SECRET (or leave blank to inherit from ../.env)
#   ./setup-production.sh
#
# Re-deploy after git pull:
#   ./setup-production.sh
#
# Options:
#   --skip-migrate    Skip php artisan migrate
#   --skip-build      Skip npm run build
#   --skip-optimize   Skip config/route/view cache
#   -h, --help        Show help
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/paths.sh
source "$ROOT/scripts/lib/paths.sh"
staff_paths_resolve_from_module "$ROOT"

SETUP_ENV="$ROOT/setup.env"
ENV_FILE="$ROOT/.env"

SKIP_MIGRATE=0
SKIP_BUILD=0
SKIP_OPTIMIZE=0

usage() {
    sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'
}

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

# shellcheck source=scripts/lib/dotenv.sh
source "$ROOT/scripts/lib/dotenv.sh"

log() { printf '==> %s\n' "$*"; }
warn() { printf 'warning: %s\n' "$*" >&2; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

if [[ ! -f "$SETUP_ENV" ]]; then
    if [[ -f "$ROOT/setup.env.example" ]]; then
        cp "$ROOT/setup.env.example" "$SETUP_ENV"
        die "Created $SETUP_ENV — set DB_DATABASE and re-run: ./setup-production.sh"
    fi
    die "Missing $SETUP_ENV — copy setup.env.example and configure it first."
fi

export FINANCE_SETUP_ENV="$SETUP_ENV"
export FINANCE_PRODUCTION_SETUP=1
chmod +x "$ROOT/scripts/configure-env.sh" "$ROOT/fix-storage-permissions.sh" 2>/dev/null || true

log "Configuring .env from setup.env (production URLs auto-resolved when localhost)"
"$ROOT/scripts/configure-env.sh"

if [[ -n "$(dotenv_get "$ENV_FILE" APP_URL 2>/dev/null || true)" ]]; then
    printf '    APP_URL=%s\n' "$(dotenv_get "$ENV_FILE" APP_URL)"
    printf '    BASE_URL=%s\n' "$(dotenv_get "$ENV_FILE" BASE_URL 2>/dev/null || true)"
fi

FINANCE_USER="${FINANCE_USER:-www-data}"
FINANCE_GROUP="${FINANCE_GROUP:-www-data}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"

if [[ ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php || true)"
fi
[[ -n "$PHP_BIN" ]] || die "PHP not found — set PHP_BIN in setup.env"

command -v composer >/dev/null 2>&1 || die "composer not found on PATH"
command -v npm >/dev/null 2>&1 || die "npm not found on PATH"

jwt="$(dotenv_get "$ENV_FILE" JWT_SECRET 2>/dev/null || true)"
if [[ -z "$jwt" || "$jwt" == change-me* ]]; then
    die "JWT_SECRET is not set. Add it to setup.env or ensure $STAFF_ROOT/.env has JWT_SECRET (must match Staff portal)."
fi

log "Installing PHP dependencies (production)"
composer install --no-dev --optimize-autoloader --no-interaction

if [[ -z "$(dotenv_get "$ENV_FILE" APP_KEY 2>/dev/null || true)" ]]; then
    log "Generating Laravel APP_KEY"
    "$PHP_BIN" artisan key:generate --no-interaction
fi

if [[ "$SKIP_MIGRATE" -eq 0 ]]; then
    log "Running database migrations"
    "$PHP_BIN" artisan migrate --force --no-interaction
else
    log "Skipping migrations (--skip-migrate)"
fi

chmod +x "$ROOT/fix-storage-permissions.sh"
"$ROOT/fix-storage-permissions.sh" || warn "Run ./fix-storage-permissions.sh with sudo if Apache cannot write sessions/logs."

if [[ "$SKIP_BUILD" -eq 0 ]]; then
    log "Building frontend assets (Vite production)"
    if [[ -f package-lock.json ]]; then
        npm ci --legacy-peer-deps --cache ./.npm-cache
    else
        npm install --legacy-peer-deps --cache ./.npm-cache
    fi
    npm run build
    [[ -d "$ROOT/public/build" ]] || die "Frontend build failed — missing public/build/"
else
    log "Skipping frontend build (--skip-build)"
fi

if [[ "$SKIP_OPTIMIZE" -eq 0 ]]; then
    log "Caching Laravel config / routes / views"
    "$PHP_BIN" artisan config:cache --no-interaction
    "$PHP_BIN" artisan route:cache --no-interaction
    "$PHP_BIN" artisan view:cache --no-interaction
fi

log "Fixing storage permissions ($FINANCE_USER:$FINANCE_GROUP)"
fix_perms() {
    local target="$1"
    if [[ "$(id -u)" -eq 0 ]]; then
        chown -R "$FINANCE_USER:$FINANCE_GROUP" "$target"
        chmod -R ug+rwx "$target"
    elif command -v sudo >/dev/null 2>&1; then
        sudo chown -R "$FINANCE_USER:$FINANCE_GROUP" "$target"
        sudo chmod -R ug+rwx "$target"
    else
        warn "Not root and no sudo — ensure $target is writable by the web server user"
    fi
}
fix_perms "$ROOT/storage"
fix_perms "$ROOT/bootstrap/cache"

APP_URL_VAL="$(dotenv_get "$ENV_FILE" APP_URL 2>/dev/null || true)"
log "Smoke test"
if [[ -n "$APP_URL_VAL" ]]; then
    code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${APP_URL_VAL}/" 2>/dev/null || echo '000')"
    if [[ "$code" == "200" || "$code" == "302" ]]; then
        printf '    Finance OK: %s/ (HTTP %s)\n' "$APP_URL_VAL" "$code"
    else
        warn "Finance returned HTTP $code for ${APP_URL_VAL}/ — confirm Apache serves finance/.htaccess"
    fi
fi

echo ""
echo "Finance production setup complete."
echo "  App:     ${APP_URL_VAL:-/staff/finance}"
echo "  Staff:   ${FINANCE_STAFF_PORTAL_URL:-/staff} (open Finance tile; permission 92)"
echo ""
echo "Re-deploy after git pull:  ./setup-production.sh"
