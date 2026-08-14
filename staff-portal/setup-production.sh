#!/usr/bin/env bash
#
# Staff Portal — single production deploy script (beside Staff at /staff/staff-portal/).
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
#   --skip-migrate    Skip php artisan migrate / module:migrate
#   --skip-build      Skip npm run build
#   --skip-systemd    Skip systemd install/restart
#   --skip-optimize   Skip config/route/view cache
#   --with-demo-seed  Run DatabaseSeeder (NOT for production)
#   -h, --help        Show help
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/paths.sh
source "$ROOT/scripts/lib/paths.sh"
staff_paths_resolve_from_module "$ROOT"

SETUP_ENV="${STAFF_PORTAL_SETUP_ENV:-$ROOT/setup.env}"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"

SKIP_MIGRATE=0
SKIP_BUILD=0
SKIP_SYSTEMD=0
SKIP_OPTIMIZE=0
WITH_DEMO_SEED=0

usage() {
    sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-migrate) SKIP_MIGRATE=1 ;;
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

export STAFF_PORTAL_SETUP_ENV="$SETUP_ENV"
dotenv_load_file "$SETUP_ENV"

if [[ -n "${PHP_BIN:-}" && ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php 2>/dev/null || true)"
fi

APP_ENV=production
APP_DEBUG=false
export STAFF_PORTAL_PRODUCTION_SETUP=1

INSTALL_SYSTEMD="${INSTALL_SYSTEMD:-auto}"
STAFF_PORTAL_USER="${STAFF_PORTAL_USER:-www-data}"
STAFF_PORTAL_GROUP="${STAFF_PORTAL_GROUP:-www-data}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"

# URL prefix follows the parent folder name for demo deploys:
#   .../demo_staff/staff-portal → /demo_staff/staff-portal/
#   .../staff/staff-portal     → /staff/staff-portal/
staff_portal_web_prefix() {
    local parent
    parent="$(basename "$(cd "$ROOT/.." && pwd)")"
    if [[ "$parent" == "demo_staff" ]]; then
        printf '/demo_staff'
    else
        printf '/staff'
    fi
}
WEB_PREFIX="$(staff_portal_web_prefix)"
# Always align Vite paths to this deploy (stale .env.production.local was pointing
# /demo_staff HTML at /staff/staff-portal/assets → 500s on the wrong tree).
VITE_STAFF_PORTAL_API_BASE_URL="${VITE_STAFF_PORTAL_API_BASE_URL:-${WEB_PREFIX}/staff-portal/backend}"
VITE_STAFF_PORTAL_BASE_PATH="${VITE_STAFF_PORTAL_BASE_PATH:-${WEB_PREFIX}/staff-portal/}"
# If setup.env still has /staff/... but we are under demo_staff, override.
if [[ "$WEB_PREFIX" == "/demo_staff" ]]; then
    case "${VITE_STAFF_PORTAL_BASE_PATH}" in
        /staff/*)
            VITE_STAFF_PORTAL_BASE_PATH="/demo_staff/staff-portal/"
            VITE_STAFF_PORTAL_API_BASE_URL="/demo_staff/staff-portal/backend"
            warn "Deploy path is demo_staff — forcing Vite base to ${VITE_STAFF_PORTAL_BASE_PATH}"
            ;;
    esac
fi
log "Vite base: ${VITE_STAFF_PORTAL_BASE_PATH}  API: ${VITE_STAFF_PORTAL_API_BASE_URL}"

if [[ ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php || true)"
fi
[[ -n "$PHP_BIN" ]] || die "PHP not found — set PHP_BIN in setup.env"

command -v composer >/dev/null 2>&1 || die "composer not found on PATH"
command -v npm >/dev/null 2>&1 || die "npm not found on PATH"

# Composer disables plugins as root unless allowed — breaks Modules\* autoload (merge-plugin).
if [[ "$(id -u)" -eq 0 ]]; then
    export COMPOSER_ALLOW_SUPERUSER=1
    warn "running as root — COMPOSER_ALLOW_SUPERUSER=1 so module autoload plugins stay enabled"
fi

chmod +x "$ROOT/scripts/configure-env.sh" "$ROOT/scripts/install-systemd.sh" 2>/dev/null || true
chmod +x "$ROOT/deploy/systemd/install.sh" "$ROOT/deploy/bin/"*.sh 2>/dev/null || true

export APP_ENV APP_DEBUG STAFF_PORTAL_PRODUCTION_SETUP

log "Configuring backend .env from setup.env (production URLs auto-resolved when localhost)"
"$ROOT/scripts/configure-env.sh"

BACKEND_ENV="$BACKEND/.env"

# Hard-disable Redis for production unless USE_REDIS=true and Redis responds.
# Inherited REDIS_PASSWORD from staff CI .env causes: AUTH called without any password
# configured — and that must never abort migrate / deploy.
staff_portal_disable_redis_unless_ready() {
    local use_redis host port pass
    use_redis="$(printf '%s' "${USE_REDIS:-}" | tr '[:upper:]' '[:lower:]')"
    host="$(dotenv_get "$BACKEND_ENV" REDIS_HOST 2>/dev/null || true)"
    port="$(dotenv_get "$BACKEND_ENV" REDIS_PORT 2>/dev/null || true)"
    pass="$(dotenv_get "$BACKEND_ENV" REDIS_PASSWORD 2>/dev/null || true)"
    [[ -n "$host" ]] || host=127.0.0.1
    [[ -n "$port" ]] || port=6379

    local redis_ok=0
    if [[ "$use_redis" == "true" || "$use_redis" == "1" || "$use_redis" == "yes" ]] \
        && command -v redis-cli >/dev/null 2>&1; then
        if [[ -n "$pass" && "$pass" != "null" && "$pass" != "nil" ]]; then
            if redis-cli -h "$host" -p "$port" -a "$pass" --no-auth-warning ping 2>/dev/null | grep -qi PONG; then
                redis_ok=1
            fi
        elif redis-cli -h "$host" -p "$port" ping 2>/dev/null | grep -qi PONG; then
            redis_ok=1
        fi
    fi

    if [[ "$redis_ok" -eq 1 ]]; then
        log "Redis OK — leaving CACHE_STORE / REDIS_* as configured"
        return 0
    fi

    warn "Redis not required / not ready — forcing database cache, queue, session (test Redis later)"
    dotenv_set "$BACKEND_ENV" CACHE_STORE database
    dotenv_set "$BACKEND_ENV" QUEUE_CONNECTION database
    dotenv_set "$BACKEND_ENV" SESSION_DRIVER database
    dotenv_set "$BACKEND_ENV" REDIS_PASSWORD null
    rm -f "$BACKEND/bootstrap/cache/config.php" \
        "$BACKEND/bootstrap/cache/config.php.tmp" 2>/dev/null || true
}
staff_portal_disable_redis_unless_ready

jwt="$(dotenv_get "$BACKEND_ENV" JWT_SECRET 2>/dev/null || true)"
if [[ -z "$jwt" || "$jwt" == change-me* ]]; then
    die "JWT_SECRET is not set. Add it to setup.env or ensure $STAFF_ROOT/.env has JWT_SECRET (must match APM/Helpdesk)."
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
    composer dump-autoload -o --no-interaction
)

if ! (
    cd "$BACKEND"
    php -r 'require "vendor/autoload.php"; exit(class_exists("Modules\\AdManager\\Providers\\AdManagerServiceProvider") ? 0 : 1);'
); then
    die "Modules autoload missing (merge-plugin). Re-run as non-root, or: cd backend && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o"
fi

# Drop cached config so REDIS_PASSWORD / CACHE_STORE changes always apply
rm -f "$BACKEND/bootstrap/cache/config.php" 2>/dev/null || true

# Env overrides beat stale .env / config cache.
# CACHE_STORE=array during setup: database cache table may not exist yet; Redis may AUTH-fail.
artisan_safe() {
    (
        cd "$BACKEND"
        env \
            CACHE_STORE=array \
            QUEUE_CONNECTION=sync \
            SESSION_DRIVER=array \
            REDIS_PASSWORD= \
            "$PHP_BIN" artisan "$@"
    )
}

if [[ -z "$(dotenv_get "$BACKEND_ENV" APP_KEY 2>/dev/null || true)" ]]; then
    log "Generating Laravel APP_KEY"
    artisan_safe key:generate --no-interaction || true
fi

(cd "$BACKEND" && "$PHP_BIN" artisan config:clear --no-interaction 2>/dev/null || true) || true

if [[ "$SKIP_MIGRATE" -eq 0 ]]; then
    log "Running database migrations"
    set +e
    artisan_safe migrate --force --no-interaction
    migrate_rc=$?
    set -e
    if [[ "$migrate_rc" -ne 0 ]]; then
        warn "Core migrations failed or already applied — continuing (use --skip-migrate to silence)"
    fi
    log "Running module migrations"
    set +e
    artisan_safe module:migrate --force --no-interaction
    module_rc=$?
    if [[ "$module_rc" -ne 0 ]]; then
        artisan_safe module:migrate --force
        module_rc=$?
    fi
    set -e
    if [[ "$module_rc" -ne 0 ]]; then
        warn "Module migrations failed or already applied — continuing"
    fi
else
    log "Skipping migrations (--skip-migrate)"
fi

log "Storage link"
artisan_safe storage:link --no-interaction 2>/dev/null || true

if [[ "$WITH_DEMO_SEED" -eq 1 ]]; then
    warn "Running DatabaseSeeder (demo data — not for production)"
    artisan_safe db:seed --force --no-interaction || warn "db:seed failed — continuing"
fi

if [[ "$SKIP_BUILD" -eq 0 ]]; then
    log "Building frontend (Vite production)"
    PROD_ENV="$FRONTEND/.env.production.local"
    # Always rewrite Vite public paths for this deploy (do not keep stale /staff/ values
    # when serving under /demo_staff/).
    dotenv_set "$PROD_ENV" VITE_STAFF_PORTAL_API_BASE_URL "$VITE_STAFF_PORTAL_API_BASE_URL"
    dotenv_set "$PROD_ENV" VITE_STAFF_PORTAL_BASE_PATH "$VITE_STAFF_PORTAL_BASE_PATH"
    log "Wrote $PROD_ENV (base=${VITE_STAFF_PORTAL_BASE_PATH})"
    if [[ -d "$FRONTEND/dist-build" ]] && ! [[ -w "$FRONTEND/dist-build" ]]; then
        warn "frontend/dist-build is not writable — fixing ownership for $(id -un)"
        if chown -R "$(id -un):$(id -gn)" "$FRONTEND/dist-build" 2>/dev/null; then
            :
        elif command -v sudo >/dev/null 2>&1 && sudo chown -R "$(id -un):$(id -gn)" "$FRONTEND/dist-build"; then
            :
        else
            die "Cannot write to $FRONTEND/dist-build — run: sudo chown -R \$(whoami) $FRONTEND/dist-build"
        fi
    fi
    (
        cd "$FRONTEND"
        # Build needs vite / @vitejs/plugin-vue / typescript (devDependencies).
        # NODE_ENV=production would skip them and break `npm run build`.
        export NODE_ENV=development
        if [[ -f package-lock.json ]]; then
            if ! npm ci --include=dev --cache ./.npm-cache --legacy-peer-deps; then
                warn "npm ci failed (stale lock?) — running npm install --include=dev"
                npm install --include=dev --cache ./.npm-cache --legacy-peer-deps
            fi
        else
            npm install --include=dev --cache ./.npm-cache --legacy-peer-deps
        fi
        NODE_ENV=production npm run build
    )
    [[ -f "$FRONTEND/dist-build/index.html" ]] || die "Frontend build failed — missing frontend/dist-build/index.html"
else
    log "Skipping frontend build (--skip-build)"
fi

# Publish Vite build at staff-portal root so Apache serves real files.
# Parent CodeIgniter .htaccess rewrites missing paths to index.php (HTTP 500).
if [[ -x "$ROOT/scripts/publish-spa.sh" ]]; then
    chmod +x "$ROOT/scripts/publish-spa.sh"
    "$ROOT/scripts/publish-spa.sh" "$FRONTEND/dist-build" \
        || warn "publish-spa.sh failed — SPA assets may 500 until fixed"
else
    warn "missing scripts/publish-spa.sh"
fi

if [[ "$SKIP_OPTIMIZE" -eq 0 ]]; then
    log "Caching Laravel config / routes / views"
    # nwidart modules register views paths; empty dirs are not in git — create them
    # so `view:cache` does not abort deploy.
    while IFS= read -r -d '' mod_dir; do
        mkdir -p "$mod_dir/resources/views"
    done < <(find "$BACKEND/Modules" -mindepth 1 -maxdepth 1 -type d -print0 2>/dev/null)
    (
        cd "$BACKEND"
        "$PHP_BIN" artisan config:clear --no-interaction
        "$PHP_BIN" artisan config:cache --no-interaction
        "$PHP_BIN" artisan route:cache --no-interaction
        if ! "$PHP_BIN" artisan view:cache --no-interaction; then
            warn "view:cache failed — continuing (app runs without precompiled views)"
        fi
    )
fi

# Resolve web-server ownership (Linux www-data vs macOS Homebrew _www).
group_exists() {
    getent group "$1" >/dev/null 2>&1 || id -g "$1" >/dev/null 2>&1
}
if ! id -u "$STAFF_PORTAL_USER" >/dev/null 2>&1; then
    if id -u _www >/dev/null 2>&1; then
        STAFF_PORTAL_USER="_www"
    else
        STAFF_PORTAL_USER="$(id -un)"
    fi
fi
if ! group_exists "$STAFF_PORTAL_GROUP"; then
    if group_exists _www; then
        STAFF_PORTAL_GROUP="_www"
    else
        STAFF_PORTAL_GROUP="$(id -gn)"
    fi
fi

log "Fixing storage permissions ($STAFF_PORTAL_USER:$STAFF_PORTAL_GROUP)"
fix_perms() {
    local target="$1"
    mkdir -p "$target"
    if [[ "$(id -u)" -eq 0 ]]; then
        chown -R "$STAFF_PORTAL_USER:$STAFF_PORTAL_GROUP" "$target" || warn "chown failed for $target"
        chmod -R ug+rwx "$target" || warn "chmod failed for $target"
    elif command -v sudo >/dev/null 2>&1; then
        sudo chown -R "$STAFF_PORTAL_USER:$STAFF_PORTAL_GROUP" "$target" || warn "chown failed for $target"
        sudo chmod -R ug+rwx "$target" || warn "chmod failed for $target"
    else
        warn "Not root and no sudo — ensure $target is writable by the web server user"
        chmod -R ug+rwx "$target" 2>/dev/null || true
    fi
}
fix_perms "$BACKEND/storage"
fix_perms "$BACKEND/bootstrap/cache"

if [[ "$SKIP_SYSTEMD" -eq 0 ]]; then
    log "Installing / restarting systemd (queue + scheduler)"
    if [[ "$(id -u)" -eq 0 ]]; then
        "$ROOT/scripts/install-systemd.sh" || warn "systemd install failed — run: sudo $ROOT/scripts/install-systemd.sh"
    elif command -v sudo >/dev/null 2>&1; then
        sudo STAFF_PORTAL_SETUP_ENV="$SETUP_ENV" "$ROOT/scripts/install-systemd.sh" || warn "systemd install failed"
    else
        warn "Skipping systemd (no root/sudo). Run queue manually: cd backend && php artisan queue:work database"
    fi
else
    log "Skipping systemd (--skip-systemd)"
fi

log "Shared file storage (CI3 + APM → host path outside git)"
chmod +x "$ROOT/scripts/migrate-shared-storage.sh" 2>/dev/null || true
# Production defaults to migrate when unset; set MIGRATE_SHARED_STORAGE=false to skip.
export MIGRATE_SHARED_STORAGE="${MIGRATE_SHARED_STORAGE:-true}"
"$ROOT/scripts/migrate-shared-storage.sh" || warn "Shared storage migration skipped or failed — see docs/STORAGE.md"

dotenv_load_file "$BACKEND_ENV"
HEALTH_PATH="${STAFF_PORTAL_HEALTH_URL:-}"
if [[ -z "$HEALTH_PATH" ]]; then
  HEALTH_PATH="$(dotenv_get "$BACKEND_ENV" APP_URL 2>/dev/null || true)/up"
fi
SPA_URL="$(dotenv_get "$BACKEND_ENV" STAFF_PORTAL_SPA_URL 2>/dev/null || true)"
[[ -z "$SPA_URL" ]] && SPA_URL="${STAFF_PORTAL_SPA_URL:-}"

log "Smoke tests"
if [[ -n "$HEALTH_PATH" ]]; then
    if curl -fsS --max-time 15 "$HEALTH_PATH" >/dev/null 2>&1; then
        printf '    API health OK: %s\n' "$HEALTH_PATH"
    else
        warn "API health check failed: $HEALTH_PATH (Apache may need a reload, or URL is only reachable externally)"
    fi
fi
if [[ -n "$SPA_URL" ]]; then
    code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${SPA_URL%/}/" 2>/dev/null || echo '000')"
    if [[ "$code" == "200" ]]; then
        printf '    SPA OK: %s/\n' "${SPA_URL%/}"
    else
        warn "SPA returned HTTP $code for ${SPA_URL%/}/ — confirm Apache serves staff-portal/.htaccess"
    fi
    asset_sample="$(find "$ROOT/assets" -maxdepth 1 -type f \( -name '*.js' -o -name '*.css' \) 2>/dev/null | head -n 1 || true)"
    if [[ -n "$asset_sample" ]]; then
        asset_name="$(basename "$asset_sample")"
        asset_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${SPA_URL%/}/assets/${asset_name}" 2>/dev/null || echo '000')"
        if [[ "$asset_code" == "200" ]]; then
            printf '    SPA assets OK: %s/assets/%s\n' "${SPA_URL%/}" "$asset_name"
        else
            warn "SPA asset HTTP $asset_code for ${SPA_URL%/}/assets/${asset_name} — check permissions / .htaccess Options"
        fi
    fi
fi

echo ""
echo "Staff Portal production setup complete."
echo "  SPA:     ${SPA_URL:-/staff/staff-portal/}"
echo "  API:     ${APP_URL:-}/up"
echo ""
echo "Post-deploy:"
echo "  1. systemctl status staff-portal-queue.service staff-portal-scheduler.timer"
echo "  2. Confirm SPA login + Microsoft SSO (STAFF_PORTAL_SPA_ENABLED=true)"
echo "  3. JWT_SECRET must match APM / Helpdesk / Staff CI"
echo ""
echo "Re-deploy after git pull:  ./setup-production.sh"
echo "Skip slow steps:           ./setup-production.sh --skip-build"
