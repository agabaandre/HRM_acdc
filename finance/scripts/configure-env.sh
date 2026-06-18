#!/usr/bin/env bash
set -euo pipefail

FINANCE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib/paths.sh
source "$FINANCE_ROOT/scripts/lib/paths.sh"
staff_paths_resolve_from_module "$FINANCE_ROOT"

# shellcheck source=lib/dotenv.sh
source "$FINANCE_ROOT/scripts/lib/dotenv.sh"

SETUP_ENV="${FINANCE_SETUP_ENV:-$FINANCE_ROOT/setup.env}"
ENV_FILE="$FINANCE_ROOT/.env"

# shellcheck source=lib/urls.sh
source "$FINANCE_ROOT/scripts/lib/urls.sh"

if [[ ! -f "$SETUP_ENV" ]]; then
    echo "Missing $SETUP_ENV — copy setup.env.example to setup.env and set DB_* / JWT_SECRET." >&2
    exit 1
fi

dotenv_load_file "$SETUP_ENV"

if [[ "${FINANCE_PRODUCTION_SETUP:-}" == "1" ]]; then
    APP_ENV=production
    APP_DEBUG=false
fi

if [[ "${APP_ENV:-}" == "production" ]]; then
    finance_resolve_production_urls
fi

# Local setup.sh and production: copy Staff DB_* when setup.env leaves them blank.
finance_inherit_database_from_staff

apply_if_set() {
    local key="$1" val="${!1:-}"
    if [[ -z "$val" ]]; then
        return 0
    fi
    dotenv_set "$ENV_FILE" "$key" "$val"
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

inherit_staff_key_as() {
    local target="$1" source_key="$2" from_file="$3"
    local current="${!target:-}"
    if [[ -n "$current" ]]; then
        return 0
    fi
    local inherited
    inherited="$(dotenv_get "$from_file" "$source_key" 2>/dev/null || true)"
    if [[ -z "$inherited" ]]; then
        line="$(grep -E "^${source_key}[[:space:]]*=" "$from_file" 2>/dev/null | tail -n 1 || true)"
        if [[ -n "$line" ]]; then
            inherited="${line#*=}"
            inherited="${inherited#"${inherited%%[![:space:]]*}"}"
            inherited="${inherited%"${inherited##*[![:space:]]}"}"
        fi
    fi
    if [[ -n "$inherited" ]]; then
        printf -v "$target" '%s' "$inherited"
    fi
}

inherit_if_empty JWT_SECRET "$STAFF_ENV"
inherit_if_empty JWT_SECRET "$APM_ENV"
inherit_if_empty SESSION_SECRET "$STAFF_ENV"
inherit_if_empty STAFF_API_USERNAME "$APM_ENV"
inherit_if_empty STAFF_API_PASSWORD "$APM_ENV"
inherit_if_empty STAFF_API_TOKEN "$APM_ENV"
inherit_if_empty BASE_URL "$STAFF_ENV"
inherit_if_empty BASE_URL "$APM_ENV"
inherit_staff_key_as FINANCE_ASSETS_BASE_URL APM_BASE_URL "$STAFF_ENV"

[[ -f "$ENV_FILE" ]] || cp "$FINANCE_ROOT/.env.example" "$ENV_FILE"

if [[ ! -w "$ENV_FILE" ]]; then
    echo "error: $ENV_FILE is not writable (often caused by running a previous setup with sudo)." >&2
    echo "Fix: sudo chown \$(whoami) \"$ENV_FILE\" \"$SETUP_ENV\" && ./setup.sh" >&2
    exit 1
fi

for key in \
    APP_URL BASE_URL FINANCE_ASSETS_BASE_URL FINANCE_STAFF_PORTAL_URL VITE_APP_BASE_PATH \
    APP_ENV APP_DEBUG DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
    JWT_SECRET SESSION_SECRET STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN \
    SESSION_PATH FINANCE_SSO_PERMISSION_ID; do
    apply_if_set "$key"
done

if [[ -z "${SESSION_PATH:-}" ]]; then
    SESSION_PATH=/staff/finance
fi
dotenv_set "$ENV_FILE" SESSION_PATH "$SESSION_PATH"

if [[ "${DB_CONNECTION:-}" == "sqlite" ]]; then
    mkdir -p "$FINANCE_ROOT/database"
    touch "$FINANCE_ROOT/database/database.sqlite"
    dotenv_set "$ENV_FILE" DB_DATABASE "$(cd "$FINANCE_ROOT/database" && pwd)/database.sqlite"
fi

if [[ -z "$(dotenv_get "$ENV_FILE" APP_KEY 2>/dev/null || true)" \
    && -f "$FINANCE_ROOT/vendor/autoload.php" ]]; then
    (cd "$FINANCE_ROOT" && php artisan key:generate --no-interaction)
fi

if [[ "${DB_CONNECTION:-}" == "mysql" ]]; then
    for key in DB_HOST DB_DATABASE DB_USERNAME; do
        val="${!key:-}"
        if [[ -z "$val" ]]; then
            echo "error: MySQL $key is not set — set it in setup.env or ensure $STAFF_ROOT/.env has DB_HOST / DB_USER" >&2
            exit 1
        fi
    done
    if [[ -z "${DB_PASSWORD:-}" ]]; then
        echo "error: MySQL DB_PASSWORD is not set — set it in setup.env or ensure $STAFF_ROOT/.env has DB_PASS" >&2
        exit 1
    fi
fi

if [[ -z "${JWT_SECRET:-}" || "${JWT_SECRET}" == change-me* ]]; then
    echo "Warning: JWT_SECRET is not set — copy from $STAFF_ROOT/.env for Staff SSO." >&2
fi

echo "Configured $ENV_FILE from $SETUP_ENV"
