#!/usr/bin/env bash
set -euo pipefail

HELPDESK_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib/paths.sh
source "$HELPDESK_ROOT/scripts/lib/paths.sh"
staff_paths_resolve_from_module "$HELPDESK_ROOT"

# shellcheck source=lib/dotenv.sh
source "$HELPDESK_ROOT/scripts/lib/dotenv.sh"

SETUP_ENV="${HELPDESK_SETUP_ENV:-$HELPDESK_ROOT/setup.env}"
BACKEND_ENV="$HELPDESK_ROOT/backend/.env"

# shellcheck source=lib/urls.sh
source "$HELPDESK_ROOT/scripts/lib/urls.sh"

if [[ ! -f "$SETUP_ENV" ]]; then
    echo "Missing $SETUP_ENV — copy setup.env.example to setup.env and set DB_* / JWT_SECRET." >&2
    exit 1
fi

dotenv_load_file "$SETUP_ENV"

ENV_PREEXISTED=0
[[ -f "$BACKEND_ENV" ]] && ENV_PREEXISTED=1
[[ -f "$BACKEND_ENV" ]] || cp "$HELPDESK_ROOT/backend/.env.example" "$BACKEND_ENV"

if [[ "${HELPDESK_PRODUCTION_SETUP:-}" == "1" ]]; then
    if [[ "$ENV_PREEXISTED" != "1" ]] \
        || ! dotenv_value_present "$(dotenv_get "$BACKEND_ENV" APP_ENV 2>/dev/null || true)"; then
        APP_ENV=production
        APP_DEBUG=false
    fi
fi

if [[ "${APP_ENV:-}" == "production" ]]; then
    helpdesk_resolve_production_urls
fi

helpdesk_inherit_database_from_staff

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

# Inherit secrets from Staff / APM when setup.env leaves them blank.
inherit_if_empty JWT_SECRET "$STAFF_ENV"
inherit_if_empty JWT_SECRET "$APM_ENV"
inherit_if_empty SESSION_SECRET "$STAFF_ENV"
inherit_if_empty STAFF_API_USERNAME "$APM_ENV"
inherit_if_empty STAFF_API_PASSWORD "$APM_ENV"
inherit_if_empty STAFF_API_TOKEN "$APM_ENV"
inherit_if_empty BASE_URL "$APM_ENV"

if [[ ! -w "$BACKEND_ENV" ]]; then
    echo "error: $BACKEND_ENV is not writable (often caused by running a previous setup with sudo)." >&2
    echo "Fix: sudo chown \$(whoami) \"$BACKEND_ENV\" \"$SETUP_ENV\" && ./setup.sh" >&2
    exit 1
fi

# --- Critical runtime keys ---
for key in \
    APP_NAME APP_ENV APP_DEBUG APP_URL APP_TIMEZONE \
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
    JWT_SECRET SESSION_SECRET JWT_TTL \
    BASE_URL STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN \
    HELPDESK_STAFF_PORTAL_URL HELPDESK_APM_BASE_URL HELPDESK_FRONTEND_URL HELPDESK_HEALTH_URL \
    HELPDESK_SSO_PERMISSION_CODES HELPDESK_BRIDGE_SECRET \
    QUEUE_CONNECTION CACHE_STORE SESSION_DRIVER \
    SANCTUM_STATEFUL_DOMAINS; do
    apply_from_setup "$key"
done

# SQLite quick path: ensure database file exists.
if [[ "${DB_CONNECTION:-}" == "sqlite" ]]; then
    mkdir -p "$HELPDESK_ROOT/backend/database"
    touch "$HELPDESK_ROOT/backend/database/database.sqlite"
    dotenv_apply_if_missing "$BACKEND_ENV" DB_DATABASE \
        "$(cd "$HELPDESK_ROOT/backend/database" && pwd)/database.sqlite" "$ENV_PREEXISTED"
fi

# Sanctum: include SPA origin when not already configured.
if [[ -n "${HELPDESK_FRONTEND_URL:-}" ]]; then
    host_port="$(printf '%s' "$HELPDESK_FRONTEND_URL" | sed -E 's#^https?://([^/]+).*#\1#')"
    dotenv_apply_if_missing "$BACKEND_ENV" SANCTUM_STATEFUL_DOMAINS \
        "localhost,127.0.0.1,${host_port}" "$ENV_PREEXISTED"
fi

# APP_KEY requires vendor/ — setup.sh / setup-production.sh run key:generate after composer install.
if [[ -z "$(dotenv_get "$BACKEND_ENV" APP_KEY 2>/dev/null || true)" \
    && -f "$HELPDESK_ROOT/backend/vendor/autoload.php" ]]; then
    (cd "$HELPDESK_ROOT/backend" && php artisan key:generate --no-interaction)
fi

if [[ "$(dotenv_get "$BACKEND_ENV" DB_CONNECTION 2>/dev/null || true)" == "mysql" ]]; then
    for key in DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
        val="$(dotenv_get "$BACKEND_ENV" "$key" 2>/dev/null || true)"
        if ! dotenv_value_present "$val"; then
            echo "error: MySQL $key is not set — set it in backend/.env or setup.env (or Staff ../.env DB_PASS)" >&2
            exit 1
        fi
    done
fi

jwt="$(dotenv_get "$BACKEND_ENV" JWT_SECRET 2>/dev/null || true)"
if ! dotenv_value_present "$jwt"; then
    echo "Warning: JWT_SECRET is not set — copy from $STAFF_ROOT/.env for Staff SSO." >&2
fi

if [[ "$ENV_PREEXISTED" == "1" ]]; then
    echo "Updated $BACKEND_ENV (existing values preserved; only missing keys filled from $SETUP_ENV)"
else
    echo "Configured $BACKEND_ENV from $SETUP_ENV"
fi
