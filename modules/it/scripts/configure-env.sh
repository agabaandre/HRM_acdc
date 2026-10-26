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
BACKEND_ENV="$FINANCE_ROOT/backend/.env"

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

if [[ "${FINANCE_PRODUCTION_SETUP:-}" == "1" \
    || "${IT_PRODUCTION_SETUP:-}" == "1" \
    || "${SITE_KIND:-}" == "production" \
    || "${APP_ENV:-}" == "production" ]]; then
    APP_ENV=production
    APP_DEBUG=false
    export APP_ENV APP_DEBUG
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
inherit_if_empty STAFF_API_USERNAME "$STAFF_ENV"
inherit_if_empty STAFF_API_USERNAME "$APM_ENV"
inherit_if_empty STAFF_API_PASSWORD "$STAFF_ENV"
inherit_if_empty STAFF_API_PASSWORD "$APM_ENV"
inherit_if_empty STAFF_API_TOKEN "$STAFF_ENV"
inherit_if_empty STAFF_API_TOKEN "$APM_ENV"
inherit_if_empty BASE_URL "$STAFF_ENV"
inherit_if_empty BASE_URL "$APM_ENV"
inherit_staff_key_as FINANCE_ASSETS_BASE_URL APM_BASE_URL "$STAFF_ENV"

# APM org DB for division/funder mapping — never hard-code; inherit from APM module when blank.
inherit_if_empty APM_DB_HOST "$SETUP_ENV"
inherit_if_empty APM_DB_PORT "$SETUP_ENV"
inherit_if_empty APM_DB_DATABASE "$SETUP_ENV"
inherit_if_empty APM_DB_USERNAME "$SETUP_ENV"
inherit_if_empty APM_DB_PASSWORD "$SETUP_ENV"
inherit_staff_key_as APM_DB_HOST DB_HOST "$APM_ENV"
inherit_staff_key_as APM_DB_PORT DB_PORT "$APM_ENV"
inherit_staff_key_as APM_DB_DATABASE DB_DATABASE "$APM_ENV"
inherit_staff_key_as APM_DB_USERNAME DB_USERNAME "$APM_ENV"
inherit_staff_key_as APM_DB_PASSWORD DB_PASSWORD "$APM_ENV"
# APM may still use legacy DB_USER / DB_PASS keys.
inherit_staff_key_as APM_DB_USERNAME DB_USER "$APM_ENV"
inherit_staff_key_as APM_DB_PASSWORD DB_PASS "$APM_ENV"
# Fall back to Finance's own MySQL host/user when APM only set the database name.
if [[ -n "${APM_DB_DATABASE:-}" ]]; then
    inherit_if_empty APM_DB_HOST "$ENV_FILE"
    [[ -z "${APM_DB_HOST:-}" ]] && APM_DB_HOST="${DB_HOST:-127.0.0.1}"
    inherit_if_empty APM_DB_PORT "$ENV_FILE"
    [[ -z "${APM_DB_PORT:-}" ]] && APM_DB_PORT="${DB_PORT:-3306}"
    inherit_if_empty APM_DB_USERNAME "$ENV_FILE"
    [[ -z "${APM_DB_USERNAME:-}" ]] && APM_DB_USERNAME="${DB_USERNAME:-root}"
    inherit_if_empty APM_DB_PASSWORD "$ENV_FILE"
    [[ -z "${APM_DB_PASSWORD:-}" ]] && APM_DB_PASSWORD="${DB_PASSWORD:-}"
fi

# Generate shared secrets on production/local setup when still blank (never ship credentials).
if [[ -f "${STAFF_ROOT:-}/scripts/setup/secrets.sh" ]]; then
  # shellcheck source=/dev/null
  source "$STAFF_ROOT/scripts/setup/secrets.sh"
  JWT_SECRET="$(dotenv_ensure_shared_secret "$STAFF_ENV" "$SETUP_ENV" "$ENV_FILE" JWT_SECRET)"
  SESSION_SECRET="$(dotenv_ensure_shared_secret "$STAFF_ENV" "$SETUP_ENV" "$ENV_FILE" SESSION_SECRET)"
  # APM ↔ Finance session bridge — generate early so it survives later non-fatal failures.
  APM_ENV_FILE="${STAFF_ROOT:-}/modules/apm/.env"
  FINANCE_APM_BRIDGE_SECRET="$(dotenv_ensure_paired_secret FINANCE_APM_BRIDGE_SECRET \
    "$SETUP_ENV" "$ENV_FILE" "$APM_ENV_FILE")"
  export FINANCE_APM_BRIDGE_SECRET
fi

if [[ ! -w "$ENV_FILE" ]]; then
    echo "error: $ENV_FILE is not writable (often caused by running a previous setup with sudo)." >&2
    echo "Fix: sudo chown \$(whoami) \"$ENV_FILE\" \"$SETUP_ENV\" && ./setup.sh" >&2
    exit 1
fi

for key in \
    APP_URL BASE_URL FINANCE_ASSETS_BASE_URL FINANCE_STAFF_PORTAL_URL VITE_APP_BASE_PATH \
    APP_ENV APP_DEBUG DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
    APM_DB_HOST APM_DB_PORT APM_DB_DATABASE APM_DB_USERNAME APM_DB_PASSWORD \
    STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN \
    FINANCE_APM_BRIDGE_SECRET \
    AZURE_SAP_CONNECTION_STRING AZURE_SAP_ACCOUNT_NAME AZURE_SAP_ACCOUNT_KEY \
    AZURE_SAP_CONTAINER AZURE_SAP_PREFIX AZURE_SAP_TIMEOUT \
    AZURE_SAP_POLL_HOURS AZURE_SAP_DELETE_AFTER_FETCH \
    STAFF_SITE_ID STAFF_HOST_DATA_ROOT STAFF_DATA_ROOT STAFF_USE_HOST_STORAGE STAFF_PORTAL_UPLOADS_ROOT \
    STAFF_IT_FILES_ROOT STAFF_REGISTRY_FILES_ROOT \
    SESSION_PATH FINANCE_SSO_PERMISSION_ID; do
    apply_from_setup "$key"
done

# Production always disables debug even when .env already existed with APP_DEBUG=true.
if [[ "${APP_ENV:-}" == "production" ]]; then
    dotenv_set "$ENV_FILE" APP_ENV "production"
    dotenv_set "$ENV_FILE" APP_DEBUG "false"
    dotenv_set "$SETUP_ENV" APP_ENV "production"
    dotenv_set "$SETUP_ENV" APP_DEBUG "false"
fi

if [[ -z "${SESSION_PATH:-}" ]]; then
    SESSION_PATH="/staff/$(basename "$FINANCE_ROOT")"
fi
dotenv_apply_if_missing "$ENV_FILE" SESSION_PATH "$SESSION_PATH" "$ENV_PREEXISTED"
if [[ -z "${FINANCE_SSO_PERMISSION_ID:-}" ]]; then
    FINANCE_SSO_PERMISSION_ID=125
fi
dotenv_apply_if_missing "$ENV_FILE" FINANCE_SSO_PERMISSION_ID "$FINANCE_SSO_PERMISSION_ID" "$ENV_PREEXISTED"

if [[ "$(dotenv_get "$ENV_FILE" DB_CONNECTION 2>/dev/null || true)" == "sqlite" ]]; then
    mkdir -p "$FINANCE_ROOT/database" 2>/dev/null || true
    touch "$FINANCE_ROOT/database/database.sqlite" 2>/dev/null \
      || echo "Warning: could not create $FINANCE_ROOT/database/database.sqlite (permissions)." >&2
    dotenv_apply_if_missing "$ENV_FILE" DB_DATABASE \
        "$(cd "$FINANCE_ROOT/database" 2>/dev/null && pwd)/database.sqlite" "$ENV_PREEXISTED" || true
fi

# API backend (modules/finance/backend) is the live Laravel app — keep its DB_* in sync.
BACKEND_PREEXISTED=0
[[ -f "$BACKEND_ENV" ]] && BACKEND_PREEXISTED=1
if [[ -f "$FINANCE_ROOT/backend/.env.example" ]]; then
    [[ -f "$BACKEND_ENV" ]] || cp "$FINANCE_ROOT/backend/.env.example" "$BACKEND_ENV"
fi

# Sync bridge secret into Finance backend .env (generated earlier with APM).
if [[ -f "$BACKEND_ENV" ]] && [[ -f "${STAFF_ROOT:-}/scripts/setup/secrets.sh" ]]; then
  # shellcheck source=/dev/null
  source "$STAFF_ROOT/scripts/setup/secrets.sh"
  APM_ENV_FILE="${STAFF_ROOT:-}/modules/apm/.env"
  FINANCE_APM_BRIDGE_SECRET="$(dotenv_ensure_paired_secret FINANCE_APM_BRIDGE_SECRET \
    "$SETUP_ENV" "$ENV_FILE" "$BACKEND_ENV" "$APM_ENV_FILE")"
  export FINANCE_APM_BRIDGE_SECRET
fi

if [[ -f "$BACKEND_ENV" ]]; then
    for key in DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
               APM_DB_HOST APM_DB_PORT APM_DB_DATABASE APM_DB_USERNAME APM_DB_PASSWORD \
               APP_ENV APP_DEBUG APP_URL BASE_URL STAFF_API_TOKEN SESSION_PATH \
               FINANCE_APM_BRIDGE_SECRET \
               AZURE_SAP_CONNECTION_STRING AZURE_SAP_ACCOUNT_NAME AZURE_SAP_ACCOUNT_KEY \
               AZURE_SAP_CONTAINER AZURE_SAP_PREFIX AZURE_SAP_TIMEOUT \
               AZURE_SAP_POLL_HOURS AZURE_SAP_DELETE_AFTER_FETCH; do
        val="${!key:-}"
        [[ -z "$val" ]] && val="$(dotenv_get "$ENV_FILE" "$key" 2>/dev/null || true)"
        [[ -z "$val" ]] && val="$(dotenv_get "$SETUP_ENV" "$key" 2>/dev/null || true)"
        # Force-sync bridge secret so Finance backend always matches APM / root .env.
        if [[ "$key" == "FINANCE_APM_BRIDGE_SECRET" ]] && dotenv_value_present "$val"; then
            dotenv_set "$BACKEND_ENV" "$key" "$val"
            continue
        fi
        if [[ "$key" == "APP_ENV" || "$key" == "APP_DEBUG" ]] && [[ "${APP_ENV:-}" == "production" ]]; then
            dotenv_set "$BACKEND_ENV" "$key" "$val"
            continue
        fi
        dotenv_apply_if_missing "$BACKEND_ENV" "$key" "$val" "$BACKEND_PREEXISTED"
    done
    if [[ "${APP_ENV:-}" == "production" ]]; then
        dotenv_set "$BACKEND_ENV" APP_ENV "production"
        dotenv_set "$BACKEND_ENV" APP_DEBUG "false"
    fi
    # Never leave the API on staff when setup.env / root .env say finance.
    target_db="$(dotenv_get "$SETUP_ENV" DB_DATABASE 2>/dev/null || true)"
    [[ -z "$target_db" ]] && target_db="$(dotenv_get "$ENV_FILE" DB_DATABASE 2>/dev/null || true)"
    if [[ -n "$target_db" && "$target_db" != "staff" && "$target_db" != /* ]]; then
        dotenv_set "$BACKEND_ENV" DB_DATABASE "$target_db"
        dotenv_set "$BACKEND_ENV" DB_CONNECTION mysql
    fi
    # Keep APM_DB_DATABASE in sync with the resolved value (from setup.env / APM module).
    if dotenv_value_present "${APM_DB_DATABASE:-}"; then
        dotenv_set "$BACKEND_ENV" APM_DB_DATABASE "$APM_DB_DATABASE"
        dotenv_value_present "${APM_DB_HOST:-}" && dotenv_set "$BACKEND_ENV" APM_DB_HOST "$APM_DB_HOST"
        dotenv_value_present "${APM_DB_PORT:-}" && dotenv_set "$BACKEND_ENV" APM_DB_PORT "$APM_DB_PORT"
        dotenv_value_present "${APM_DB_USERNAME:-}" && dotenv_set "$BACKEND_ENV" APM_DB_USERNAME "$APM_DB_USERNAME"
        # Password may be empty on socket/auth_socket installs — still write when key was resolved.
        if [[ -n "${APM_DB_PASSWORD+x}" ]]; then
            dotenv_set "$BACKEND_ENV" APM_DB_PASSWORD "$APM_DB_PASSWORD"
        fi
    fi
fi

# Generate APP_KEY when missing (module root and/or backend API).
# shellcheck source=/dev/null
source "${STAFF_ROOT:-$FINANCE_ROOT/../..}/scripts/setup/ensure-app-key.sh"
ensure_app_key_in_env "$FINANCE_ROOT" "$ENV_FILE" || true
ensure_app_key_in_env "$FINANCE_ROOT/backend" "$BACKEND_ENV" || true

mysql_check_file="$ENV_FILE"
if [[ -f "$BACKEND_ENV" ]] \
    && [[ "$(dotenv_get "$BACKEND_ENV" DB_CONNECTION 2>/dev/null || true)" == "mysql" ]]; then
    mysql_check_file="$BACKEND_ENV"
fi
if [[ "$(dotenv_get "$mysql_check_file" DB_CONNECTION 2>/dev/null || true)" == "mysql" ]]; then
    for key in DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
        val="$(dotenv_get "$mysql_check_file" "$key" 2>/dev/null || true)"
        if ! dotenv_value_present "$val"; then
            echo "error: MySQL $key is not set — set it in backend/.env or setup.env (or Staff ../.env DB_PASS)" >&2
            exit 1
        fi
    done
fi

jwt="$(dotenv_get "$STAFF_ENV" JWT_SECRET 2>/dev/null || true)"
if ! dotenv_value_present "$jwt"; then
    echo "Warning: JWT_SECRET is not set in staff root .env — inherited at runtime via shared/load-staff-root-env.php." >&2
fi

if [[ "$ENV_PREEXISTED" == "1" ]]; then
    echo "Updated $ENV_FILE (existing values preserved; only missing keys filled from $SETUP_ENV)"
else
    echo "Configured $ENV_FILE from $SETUP_ENV"
fi
if [[ -f "$BACKEND_ENV" ]]; then
    echo "Synced DB_* on $BACKEND_ENV (DB_DATABASE=$(dotenv_get "$BACKEND_ENV" DB_DATABASE 2>/dev/null || true) APM_DB_DATABASE=$(dotenv_get "$BACKEND_ENV" APM_DB_DATABASE 2>/dev/null || true))"
fi
