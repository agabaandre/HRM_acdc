#!/usr/bin/env bash
set -euo pipefail

PORTAL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib/paths.sh
source "$PORTAL_ROOT/scripts/lib/paths.sh"
staff_paths_resolve_from_module "$PORTAL_ROOT"

# shellcheck source=lib/dotenv.sh
source "$PORTAL_ROOT/scripts/lib/dotenv.sh"
# shellcheck source=lib/urls.sh
source "$PORTAL_ROOT/scripts/lib/urls.sh"

SETUP_ENV="${STAFF_PORTAL_SETUP_ENV:-$PORTAL_ROOT/setup.env}"
BACKEND_ENV="$PORTAL_ROOT/backend/.env"

if [[ ! -f "$SETUP_ENV" ]]; then
    echo "Missing $SETUP_ENV — copy setup.env.example to setup.env and set DB_* / JWT_SECRET." >&2
    exit 1
fi

dotenv_load_file "$SETUP_ENV"

if [[ -n "${PHP_BIN:-}" && ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php 2>/dev/null || true)"
fi
export PHP_BIN

ENV_PREEXISTED=0
[[ -f "$BACKEND_ENV" ]] && ENV_PREEXISTED=1
[[ -f "$BACKEND_ENV" ]] || cp "$PORTAL_ROOT/backend/.env.example" "$BACKEND_ENV"

if [[ "${STAFF_PORTAL_PRODUCTION_SETUP:-}" == "1" ]]; then
    if [[ "$ENV_PREEXISTED" != "1" ]] \
        || ! dotenv_value_present "$(dotenv_get "$BACKEND_ENV" APP_ENV 2>/dev/null || true)"; then
        APP_ENV=production
        APP_DEBUG=false
    fi
fi

if [[ "${APP_ENV:-}" == "production" ]]; then
    staff_portal_resolve_production_urls
fi

staff_portal_inherit_database_from_staff

apply_from_setup() {
    local key="$1" val="${!1:-}"
    dotenv_apply_if_missing "$BACKEND_ENV" "$key" "$val" "$ENV_PREEXISTED"
}

inherit_if_empty() {
    local key="$1" from_file="$2"
    local current="${!key:-}"
    if [[ -n "$current" ]]; then
        return 0
    fi
    local inherited
    inherited="$(dotenv_get "$from_file" "$key" 2>/dev/null || true)"
    if [[ -n "$inherited" ]]; then
        printf -v "$key" '%s' "$inherited"
    fi
}

inherit_if_empty JWT_SECRET "$STAFF_ENV"
inherit_if_empty JWT_SECRET "$APM_ENV"

# Microsoft SSO — same Azure app as CI3 staff portal
for key in TENANT_ID CLIENT_ID CLIENT_SEC_VALUE CLIENT_SEC_ID \
    MICROSOFT_TENANT_ID MICROSOFT_CLIENT_ID MICROSOFT_CLIENT_SECRET; do
    inherit_if_empty "$key" "$STAFF_ENV"
done

for key in REDIS_CLIENT REDIS_HOST REDIS_PASSWORD REDIS_PORT REDIS_URL; do
    inherit_if_empty "$key" "$STAFF_ENV"
    inherit_if_empty "$key" "$APM_ENV"
done

if [[ ! -w "$BACKEND_ENV" ]]; then
    echo "error: $BACKEND_ENV is not writable (often caused by running a previous setup with sudo)." >&2
    echo "Fix: sudo chown \$(whoami) \"$BACKEND_ENV\" \"$SETUP_ENV\" && ./setup.sh" >&2
    exit 1
fi

for key in \
    APP_NAME APP_ENV APP_DEBUG APP_URL \
    STAFF_PORTAL_BASE_URL STAFF_PORTAL_SPA_URL STAFF_PORTAL_SPA_ENABLED \
    BASE_URL APM_BASE_URL \
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
    JWT_SECRET STAFF_SSO_TOKEN_TTL \
    TENANT_ID CLIENT_ID CLIENT_SEC_VALUE CLIENT_SEC_ID \
    MICROSOFT_TENANT_ID MICROSOFT_CLIENT_ID MICROSOFT_CLIENT_SECRET MICROSOFT_REDIRECT_URI \
    QUEUE_CONNECTION DB_QUEUE_TABLE DB_QUEUE_BATCHES_TABLE DB_QUEUE_FAILED_TABLE \
    CACHE_STORE SESSION_DRIVER \
    REDIS_CLIENT REDIS_HOST REDIS_PASSWORD REDIS_PORT REDIS_URL \
    SANCTUM_STATEFUL_DOMAINS \
    STAFF_DATA_ROOT STAFF_USE_HOST_STORAGE STAFF_SITE_ID \
    STAFF_PORTAL_UPLOADS_ROOT STAFF_PORTAL_MODULE_FILES_ROOT \
    STAFF_APM_FILES_ROOT STAFF_HELPDESK_FILES_ROOT; do
    apply_from_setup "$key"
done

# Force URL/DB keys from production resolution when set in this shell
if [[ "${STAFF_PORTAL_PRODUCTION_SETUP:-}" == "1" ]]; then
    for key in APP_ENV APP_DEBUG APP_URL STAFF_PORTAL_BASE_URL STAFF_PORTAL_SPA_URL BASE_URL APM_BASE_URL; do
        val="${!key:-}"
        if dotenv_value_present "$val"; then
            dotenv_set "$BACKEND_ENV" "$key" "$val"
        fi
    done
fi

if [[ -n "${STAFF_PORTAL_SPA_URL:-}" ]]; then
    host_port="$(printf '%s' "$STAFF_PORTAL_SPA_URL" | sed -E 's#^https?://([^/]+).*#\1#')"
    dotenv_apply_if_missing "$BACKEND_ENV" SANCTUM_STATEFUL_DOMAINS \
        "localhost,127.0.0.1,${host_port}" "$ENV_PREEXISTED"
fi

if [[ -z "$(dotenv_get "$BACKEND_ENV" APP_KEY 2>/dev/null || true)" \
    && -f "$PORTAL_ROOT/backend/vendor/autoload.php" ]]; then
    (cd "$PORTAL_ROOT/backend" && php artisan key:generate --no-interaction)
fi

if [[ "$(dotenv_get "$BACKEND_ENV" DB_CONNECTION 2>/dev/null || true)" == "mysql" ]]; then
    for key in DB_HOST DB_DATABASE DB_USERNAME; do
        val="$(dotenv_get "$BACKEND_ENV" "$key" 2>/dev/null || true)"
        if ! dotenv_value_present "$val"; then
            echo "error: MySQL $key is not set — set it in backend/.env or setup.env (or Staff ../.env)" >&2
            exit 1
        fi
    done
    db_pass="$(dotenv_get "$BACKEND_ENV" DB_PASSWORD 2>/dev/null || true)"
    if ! dotenv_value_present "$db_pass"; then
        echo "error: MySQL DB_PASSWORD is not set — set it in setup.env or ensure $STAFF_ROOT/.env has DB_PASS" >&2
        exit 1
    fi
fi

jwt="$(dotenv_get "$BACKEND_ENV" JWT_SECRET 2>/dev/null || true)"
if ! dotenv_value_present "$jwt"; then
    echo "Warning: JWT_SECRET is not set — copy from $STAFF_ROOT/.env for CBP SSO." >&2
fi

# Ensure assets symlink
ASSETS_LINK="$PORTAL_ROOT/backend/public/cbp-assets"
if [[ ! -e "$ASSETS_LINK" ]]; then
    ln -sfn ../../../assets "$ASSETS_LINK"
    echo "Linked public/cbp-assets → ../../../assets"
fi

# Compat for legacy DocumentRoot /staff/staff-portal/public
if [[ ! -e "$PORTAL_ROOT/public" ]]; then
    ln -sfn backend/public "$PORTAL_ROOT/public"
    echo "Linked staff-portal/public → backend/public (legacy URL compat)"
fi

# Prefer /backend Microsoft callback (rewrite legacy /public/ or artisan-serve :8xxx URIs)
app_url="$(dotenv_get "$BACKEND_ENV" APP_URL 2>/dev/null || true)"
ms_redirect="$(dotenv_get "$BACKEND_ENV" MICROSOFT_REDIRECT_URI 2>/dev/null || true)"
if [[ -n "$app_url" ]]; then
    desired_ms="${app_url%/}/auth/microsoft/callback"
    needs_ms_fix=0
    if [[ -z "$ms_redirect" || "$ms_redirect" == *"/public/"* ]]; then
        needs_ms_fix=1
    elif [[ "$ms_redirect" =~ :8[0-9]{3}/ ]]; then
        # php artisan serve leftovers (e.g. localhost:8081)
        needs_ms_fix=1
    elif [[ "${STAFF_PORTAL_PRODUCTION_SETUP:-}" == "1" && "$ms_redirect" == *localhost* ]]; then
        needs_ms_fix=1
    fi
    if [[ "$needs_ms_fix" == "1" ]]; then
        dotenv_set "$BACKEND_ENV" MICROSOFT_REDIRECT_URI "$desired_ms"
        echo "Set MICROSOFT_REDIRECT_URI=$desired_ms"
    fi
fi

if [[ "$ENV_PREEXISTED" == "1" ]]; then
    echo "Updated $BACKEND_ENV (existing values preserved; only missing keys filled from $SETUP_ENV)"
else
    echo "Configured $BACKEND_ENV from $SETUP_ENV"
fi
