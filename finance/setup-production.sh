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

STAFF_ROOT="$(cd "$ROOT/.." && pwd)"
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
# shellcheck source=scripts/lib/urls.sh
source "$ROOT/scripts/lib/urls.sh"

log() { printf '==> %s\n' "$*"; }
warn() { printf 'warning: %s\n' "$*" >&2; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

if [[ ! -f "$SETUP_ENV" ]]; then
    if [[ -f "$ROOT/setup.env.example" ]]; then
        cp "$ROOT/setup.env.example" "$SETUP_ENV"
        die "Created $SETUP_ENV — set production URLs, DB_* and JWT_SECRET, then re-run: ./setup-production.sh"
    fi
    die "Missing $SETUP_ENV — copy setup.env.example and configure it first."
fi

dotenv_load_file "$SETUP_ENV"

# Production deploy always runs as production (overrides APP_ENV=local in setup.env).
APP_ENV=production
APP_DEBUG=false

log "Resolving production URLs (Staff ../.env or server hostname; skips localhost placeholders)"
finance_resolve_production_urls
finance_inherit_database_from_staff
if [[ -n "${APP_URL:-}" ]]; then
    printf '    APP_URL=%s\n' "$APP_URL"
    printf '    BASE_URL=%s\n' "${BASE_URL:-}"
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

if [[ ! -f "$ENV_FILE" ]]; then
    cp "$ROOT/.env.example" "$ENV_FILE"
    "$PHP_BIN" artisan key:generate --no-interaction
fi

log "Applying setup.env to finance/.env"
for key in APP_URL BASE_URL FINANCE_ASSETS_BASE_URL FINANCE_STAFF_PORTAL_URL VITE_APP_BASE_PATH \
    APP_ENV APP_DEBUG DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
    JWT_SECRET SESSION_SECRET STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN \
    SESSION_PATH FINANCE_SSO_PERMISSION_ID; do
    val="${!key:-}"
    [[ -n "$val" ]] || continue
    dotenv_set "$ENV_FILE" "$key" "$val"
done

inherit_from_file() {
    local key="$1" from="$2"
    local current inherited
    current="$(dotenv_get "$ENV_FILE" "$key" 2>/dev/null || true)"
    [[ -n "$current" && "$current" != change-me* ]] && return 0
    inherited="$(dotenv_get "$from" "$key" 2>/dev/null || true)"
    [[ -n "$inherited" ]] || return 0
    dotenv_set "$ENV_FILE" "$key" "$inherited"
}

log "Inheriting SSO / Staff API secrets from Staff and APM when missing"
for key in JWT_SECRET SESSION_SECRET STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN BASE_URL; do
    inherit_from_file "$key" "$STAFF_ROOT/.env"
    inherit_from_file "$key" "$STAFF_ROOT/apm/.env"
done

dotenv_set "$ENV_FILE" APP_ENV "$APP_ENV"
dotenv_set "$ENV_FILE" APP_DEBUG "$APP_DEBUG"
dotenv_set "$ENV_FILE" SESSION_PATH "${SESSION_PATH:-/staff/finance}"

jwt="$(dotenv_get "$ENV_FILE" JWT_SECRET 2>/dev/null || true)"
if [[ -z "$jwt" || "$jwt" == change-me* ]]; then
    die "JWT_SECRET is not set. Add it to setup.env or ensure $STAFF_ROOT/.env has JWT_SECRET (must match Staff portal)."
fi

if [[ "$(dotenv_get "$ENV_FILE" DB_CONNECTION 2>/dev/null || true)" == "mysql" ]]; then
    for key in DB_HOST DB_DATABASE DB_USERNAME; do
        val="$(dotenv_get "$ENV_FILE" "$key" 2>/dev/null || true)"
        [[ -n "$val" ]] || die "MySQL $key is not set — set it in setup.env or ensure $STAFF_ROOT/.env has DB_HOST / DB_USER"
    done
    db_pass="$(dotenv_get "$ENV_FILE" DB_PASSWORD 2>/dev/null || true)"
    if [[ -z "$db_pass" ]]; then
        die "MySQL DB_PASSWORD is not set — set it in setup.env or ensure $STAFF_ROOT/.env has DB_PASS"
    fi
fi

log "Installing PHP dependencies (production)"
composer install --no-dev --optimize-autoloader --no-interaction

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
