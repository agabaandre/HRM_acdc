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

ENV_PREEXISTED=0
[[ -f "$ENV_FILE" ]] && ENV_PREEXISTED=1
[[ -f "$ENV_FILE" ]] || cp "$FINANCE_ROOT/.env.example" "$ENV_FILE"

if [[ "${FINANCE_PRODUCTION_SETUP:-}" == "1" ]]; then
    if [[ "$ENV_PREEXISTED" != "1" ]] \
        || ! dotenv_value_present "$(dotenv_get "$ENV_FILE" APP_ENV 2>/dev/null || true)"; then
        APP_ENV=production
        APP_DEBUG=false
    fi
fi

if [[ "${APP_ENV:-}" == "production" ]]; then
    finance_resolve_production_urls
fi

finance_inherit_database_from_staff

apply_from_setup() {
    local key="$1" val="${!1:-}"
    dotenv_apply_if_missing "$ENV_FILE" "$key" "$val" "$ENV_PREEXISTED"
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
    apply_from_setup "$key"
done

if [[ -z "${SESSION_PATH:-}" ]]; then
    SESSION_PATH=/staff/finance
fi
dotenv_apply_if_missing "$ENV_FILE" SESSION_PATH "$SESSION_PATH" "$ENV_PREEXISTED"

if [[ "$(dotenv_get "$ENV_FILE" DB_CONNECTION 2>/dev/null || true)" == "sqlite" ]]; then
    mkdir -p "$FINANCE_ROOT/database"
    touch "$FINANCE_ROOT/database/database.sqlite"
    dotenv_apply_if_missing "$ENV_FILE" DB_DATABASE \
        "$(cd "$FINANCE_ROOT/database" && pwd)/database.sqlite" "$ENV_PREEXISTED"
fi

if [[ -z "$(dotenv_get "$ENV_FILE" APP_KEY 2>/dev/null || true)" \
    && -f "$FINANCE_ROOT/vendor/autoload.php" ]]; then
    (cd "$FINANCE_ROOT" && php artisan key:generate --no-interaction)
fi

if [[ "$(dotenv_get "$ENV_FILE" DB_CONNECTION 2>/dev/null || true)" == "mysql" ]]; then
    for key in DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
        val="$(dotenv_get "$ENV_FILE" "$key" 2>/dev/null || true)"
        if ! dotenv_value_present "$val"; then
            echo "error: MySQL $key is not set — set it in .env or setup.env (or Staff ../.env DB_PASS)" >&2
            exit 1
        fi
    done
fi

jwt="$(dotenv_get "$ENV_FILE" JWT_SECRET 2>/dev/null || true)"
if ! dotenv_value_present "$jwt"; then
    echo "Warning: JWT_SECRET is not set — copy from $STAFF_ROOT/.env for Staff SSO." >&2
fi

if [[ "$ENV_PREEXISTED" == "1" ]]; then
    echo "Updated $ENV_FILE (existing values preserved; only missing keys filled from $SETUP_ENV)"
else
    echo "Configured $ENV_FILE from $SETUP_ENV"
fi
