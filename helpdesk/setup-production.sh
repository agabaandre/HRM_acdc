#!/usr/bin/env bash
#
# Helpdesk — single production deploy script (beside running Staff at /staff/helpdesk/).
#
# First time on a server:
#   cp setup.env.example setup.env
#   nano setup.env          # production URLs, MySQL, JWT_SECRET (or leave blank to inherit from ../.env)
#   ./setup-production.sh
#
# Re-deploy after git pull:
#   ./setup-production.sh
#
# Options:
#   --skip-migrate    Skip php artisan migrate
#   --skip-seed       Skip category seeder
#   --skip-build      Skip npm run build
#   --skip-systemd    Skip systemd install/restart
#   --skip-optimize   Skip config/route/view cache
#   --with-demo-seed  Run full DatabaseSeeder (NOT for production)
#   -h, --help        Show help
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/paths.sh
source "$ROOT/scripts/lib/paths.sh"
staff_paths_resolve_from_module "$ROOT"

SETUP_ENV="${HELPDESK_SETUP_ENV:-$ROOT/setup.env}"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"

SKIP_MIGRATE=0
SKIP_SEED=0
SKIP_BUILD=0
SKIP_SYSTEMD=0
SKIP_OPTIMIZE=0
WITH_DEMO_SEED=0

usage() {
    sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-migrate) SKIP_MIGRATE=1 ;;
        --skip-seed) SKIP_SEED=1 ;;
        --skip-build) SKIP_BUILD=1 ;;
        --skip-systemd) SKIP_SYSTEMD=1 ;;
        --skip-optimize) SKIP_OPTIMIZE=1 ;;
        --with-demo-seed) WITH_DEMO_SEED=1 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 1 ;;
    esac
    shift
done

# shellcheck source=scripts/lib/dotenv.sh
source "$ROOT/scripts/lib/dotenv.sh"
# shellcheck source=scripts/lib/staff-api-env.sh
source "$ROOT/scripts/lib/staff-api-env.sh"

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

export HELPDESK_SETUP_ENV="$SETUP_ENV"
dotenv_load_file "$SETUP_ENV"

# Prefer a working PHP binary over a stale setup.env path (e.g. /usr/bin/php on macOS Homebrew).
if [[ -n "${PHP_BIN:-}" && ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php 2>/dev/null || true)"
fi

# Production deploy always runs as production (overrides APP_ENV=local in setup.env).
APP_ENV=production
APP_DEBUG=false
export HELPDESK_PRODUCTION_SETUP=1

INSTALL_SYSTEMD="${INSTALL_SYSTEMD:-auto}"
HELPDESK_USER="${HELPDESK_USER:-www-data}"
HELPDESK_GROUP="${HELPDESK_GROUP:-www-data}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
VITE_HELPDESK_API_BASE_URL="${VITE_HELPDESK_API_BASE_URL:-/staff/helpdesk/backend}"
VITE_STAFF_PORTAL_HOME_URL="${VITE_STAFF_PORTAL_HOME_URL:-}"

if [[ ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php || true)"
fi
[[ -n "$PHP_BIN" ]] || die "PHP not found — set PHP_BIN in setup.env"

command -v composer >/dev/null 2>&1 || die "composer not found on PATH"
command -v npm >/dev/null 2>&1 || die "npm not found on PATH"

chmod +x "$ROOT/scripts/configure-env.sh" "$ROOT/scripts/install-systemd.sh" 2>/dev/null || true
chmod +x "$ROOT/deploy/systemd/install.sh" "$ROOT/deploy/bin/"*.sh 2>/dev/null || true

# Force production flags into setup.env snapshot for configure-env.sh
export APP_ENV APP_DEBUG HELPDESK_PRODUCTION_SETUP

log "Configuring backend .env from setup.env (production URLs auto-resolved when localhost)"
"$ROOT/scripts/configure-env.sh"

BACKEND_ENV="$BACKEND/.env"

staff_portal_url="$(dotenv_get "$BACKEND_ENV" HELPDESK_STAFF_PORTAL_URL 2>/dev/null || true)"
if [[ -z "${VITE_STAFF_PORTAL_HOME_URL:-}" && -n "$staff_portal_url" ]]; then
    VITE_STAFF_PORTAL_HOME_URL="${staff_portal_url%/}/home/index"
fi

inherit_from_file() {
    local key="$1" from="$2"
    local current inherited
    current="$(dotenv_get "$BACKEND_ENV" "$key" 2>/dev/null || true)"
    [[ -n "$current" && "$current" != change-me* ]] && return 0
    inherited="$(dotenv_get "$from" "$key" 2>/dev/null || true)"
    [[ -n "$inherited" ]] || return 0
    dotenv_set "$BACKEND_ENV" "$key" "$inherited"
}

log "Inheriting sensitive credentials from Staff / APM (not stored in git)"
helpdesk_inherit_sensitive_from_portal_env "$BACKEND_ENV" "$STAFF_ROOT"
helpdesk_validate_staff_api_env "$BACKEND_ENV" "$STAFF_ROOT" || die "Staff Share API credentials are required. Set STAFF_API_* in $STAFF_ROOT/apm/.env then re-run ./setup-production.sh"

jwt="$(dotenv_get "$BACKEND_ENV" JWT_SECRET 2>/dev/null || true)"
if [[ -z "$jwt" || "$jwt" == change-me* ]]; then
    die "JWT_SECRET is not set. Add it to setup.env or ensure $STAFF_ROOT/.env has JWT_SECRET (must match Staff portal)."
fi

if [[ "${DB_CONNECTION:-mysql}" == "mysql" ]]; then
    for key in DB_HOST DB_DATABASE DB_USERNAME; do
        val="$(dotenv_get "$BACKEND_ENV" "$key" 2>/dev/null || true)"
        [[ -n "$val" ]] || die "MySQL $key is not set — set it in setup.env or ensure $STAFF_ROOT/.env has DB_HOST / DB_USER"
    done
    db_pass="$(dotenv_get "$BACKEND_ENV" DB_PASSWORD 2>/dev/null || true)"
    if [[ -z "$db_pass" ]]; then
        die "MySQL DB_PASSWORD is not set — set it in setup.env or ensure $STAFF_ROOT/.env has DB_PASS"
    fi
fi

log "Installing PHP dependencies (production)"
(
    cd "$BACKEND"
    composer install --no-dev --optimize-autoloader --no-interaction
)

if [[ -z "$(dotenv_get "$BACKEND_ENV" APP_KEY 2>/dev/null || true)" ]]; then
    log "Generating Laravel APP_KEY"
    (cd "$BACKEND" && "$PHP_BIN" artisan key:generate --no-interaction)
fi

if [[ "$SKIP_MIGRATE" -eq 0 ]]; then
    log "Running database migrations"
    (cd "$BACKEND" && "$PHP_BIN" artisan migrate --force --no-interaction)
else
    log "Skipping migrations (--skip-migrate)"
fi

log "Storage link"
(cd "$BACKEND" && "$PHP_BIN" artisan storage:link --no-interaction 2>/dev/null || true)

if [[ "$SKIP_SEED" -eq 0 ]]; then
    if [[ "$WITH_DEMO_SEED" -eq 1 ]]; then
        warn "Running full DatabaseSeeder (includes demo admin user)"
        (cd "$BACKEND" && "$PHP_BIN" artisan db:seed --force --no-interaction)
    else
        log "Seeding helpdesk categories only (safe for production)"
        (cd "$BACKEND" && "$PHP_BIN" artisan db:seed --class=HelpdeskCategorySeeder --force --no-interaction)
    fi
else
    log "Skipping seed (--skip-seed)"
fi

if [[ "$SKIP_BUILD" -eq 0 ]]; then
    log "Building frontend (Vite production)"
    PROD_ENV="$FRONTEND/.env.production.local"
    VITE_ENV_PREEXISTED=0
    [[ -f "$PROD_ENV" ]] && VITE_ENV_PREEXISTED=1
    dotenv_apply_if_missing "$PROD_ENV" VITE_HELPDESK_API_BASE_URL \
        "$VITE_HELPDESK_API_BASE_URL" "$VITE_ENV_PREEXISTED"
    if [[ -n "${VITE_STAFF_PORTAL_HOME_URL:-}" ]]; then
        dotenv_apply_if_missing "$PROD_ENV" VITE_STAFF_PORTAL_HOME_URL \
            "$VITE_STAFF_PORTAL_HOME_URL" "$VITE_ENV_PREEXISTED"
    fi
    if [[ -d "$FRONTEND/dist" ]] && ! [[ -w "$FRONTEND/dist" ]]; then
        warn "frontend/dist is not writable — fixing ownership for $(id -un)"
        if chown -R "$(id -un):$(id -gn)" "$FRONTEND/dist" 2>/dev/null; then
            :
        elif command -v sudo >/dev/null 2>&1 && sudo chown -R "$(id -un):$(id -gn)" "$FRONTEND/dist"; then
            :
        else
            die "Cannot write to $FRONTEND/dist — run: sudo chown -R \$(whoami) $FRONTEND/dist"
        fi
    fi
    (
        cd "$FRONTEND"
        # package-lock.json is committed for helpdesk/frontend; fall back to install when missing.
        if [[ -f package-lock.json ]]; then
            if ! npm ci --legacy-peer-deps; then
                warn "npm ci failed (stale lock?) — running npm install --legacy-peer-deps"
                npm install --legacy-peer-deps
            fi
        else
            npm install --legacy-peer-deps
        fi
        npm run build
    )
    [[ -f "$FRONTEND/dist/index.html" ]] || die "Frontend build failed — missing frontend/dist/index.html"
else
    log "Skipping frontend build (--skip-build)"
fi

if [[ "$SKIP_OPTIMIZE" -eq 0 ]]; then
    log "Caching Laravel config / routes / views"
    (
        cd "$BACKEND"
        "$PHP_BIN" artisan config:clear --no-interaction
        "$PHP_BIN" artisan config:cache --no-interaction
        "$PHP_BIN" artisan route:cache --no-interaction
        "$PHP_BIN" artisan view:cache --no-interaction
    )
fi

verify_queue_table() {
    local queue_table expected
    queue_table="$(
        cd "$BACKEND" && "$PHP_BIN" artisan tinker --execute="echo config('queue.connections.database.table');" 2>/dev/null \
            | tail -1 | tr -d '\r'
    )"
    expected="$(dotenv_get "$BACKEND_ENV" DB_QUEUE_TABLE 2>/dev/null || true)"
    [[ -n "$expected" ]] || expected="helpdesk_queue_jobs"
    if [[ -z "$queue_table" ]]; then
        warn "Could not read queue table from Laravel config — check backend/.env DB_QUEUE_TABLE"
        return 0
    fi
    if [[ "$queue_table" != "$expected" ]]; then
        warn "Queue table mismatch: config uses '$queue_table' but expected '$expected' — run: cd backend && php artisan config:clear && php artisan config:cache"
    fi
}

verify_queue_table

log "Fixing storage permissions ($HELPDESK_USER:$HELPDESK_GROUP)"
fix_perms() {
    local target="$1"
    if [[ "$(id -u)" -eq 0 ]]; then
        chown -R "$HELPDESK_USER:$HELPDESK_GROUP" "$target"
        chmod -R ug+rwx "$target"
    elif command -v sudo >/dev/null 2>&1; then
        sudo chown -R "$HELPDESK_USER:$HELPDESK_GROUP" "$target"
        sudo chmod -R ug+rwx "$target"
    else
        warn "Not root and no sudo — ensure $target is writable by the web server user"
    fi
}
fix_perms "$BACKEND/storage"
fix_perms "$BACKEND/bootstrap/cache"

if [[ "$SKIP_SYSTEMD" -eq 0 ]]; then
    log "Installing / restarting systemd (queue + scheduler)"
    if [[ "$(id -u)" -eq 0 ]]; then
        "$ROOT/scripts/install-systemd.sh" || warn "systemd install failed — run: sudo $ROOT/scripts/install-systemd.sh"
    elif command -v sudo >/dev/null 2>&1; then
        sudo HELPDESK_SETUP_ENV="$SETUP_ENV" "$ROOT/scripts/install-systemd.sh" || warn "systemd install failed"
    else
        warn "Skipping systemd (no root/sudo). Run queue manually: cd backend && php artisan queue:work database"
    fi
else
    log "Skipping systemd (--skip-systemd)"
fi

dotenv_load_file "$BACKEND_ENV"
HEALTH_PATH="${HELPDESK_HEALTH_URL:-}"
if [[ -z "$HEALTH_PATH" ]]; then
  HEALTH_PATH="$(dotenv_get "$BACKEND_ENV" APP_URL 2>/dev/null || true)/api/v1/health"
fi
SPA_URL="${HELPDESK_FRONTEND_URL:-}"

log "Smoke tests"
if [[ -n "$HEALTH_PATH" ]]; then
    if curl -fsS --max-time 15 "$HEALTH_PATH" >/dev/null 2>&1; then
        printf '    API health OK: %s\n' "$HEALTH_PATH"
    else
        warn "API health check failed: $HEALTH_PATH (Apache may need a reload, or URL is only reachable externally)"
    fi
fi
if [[ -n "$SPA_URL" ]]; then
    code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$SPA_URL/" 2>/dev/null || echo '000')"
    if [[ "$code" == "200" ]]; then
        printf '    SPA OK: %s/\n' "$SPA_URL"
    else
        warn "SPA returned HTTP $code for $SPA_URL/ — confirm Apache serves helpdesk/.htaccess"
    fi
fi

echo ""
echo "Helpdesk production setup complete."
echo "  SPA:     ${HELPDESK_FRONTEND_URL:-/staff/helpdesk}"
echo "  API:     ${APP_URL:-}/api/v1/health"
echo "  Staff:   ${HELPDESK_STAFF_PORTAL_URL:-/staff} (open IT Service Desk tile; permissions 85, 92, 93)"
echo ""
echo "Re-deploy after git pull:  ./setup-production.sh"
echo "Skip slow steps:           ./setup-production.sh --skip-seed"
