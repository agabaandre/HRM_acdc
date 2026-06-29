#!/usr/bin/env bash
# shellcheck shell=bash
# Sensitive Helpdesk credentials are never committed — copied from Staff / APM .env at setup.

helpdesk_inherit_env_key() {
    local backend_env="$1" key="$2" from_file="$3"
    local current inherited

    [[ -f "$from_file" ]] || return 0
    current="$(dotenv_get "$backend_env" "$key" 2>/dev/null || true)"
    if dotenv_value_present "$current"; then
        return 0
    fi
    inherited="$(dotenv_get "$from_file" "$key" 2>/dev/null || true)"
    if dotenv_value_present "$inherited"; then
        dotenv_set "$backend_env" "$key" "$inherited"
    fi
}

# APM often omits STAFF_API_TOKEN from apm/.env and relies on config('services.staff_api.token').
# Resolve the effective token from a working APM install — nothing is written to the Helpdesk git tree.
helpdesk_resolve_staff_api_token_from_apm() {
    local backend_env="$1"
    local staff_root="$2"
    local php_bin="${3:-${PHP_BIN:-php}}"
    if [[ ! -x "$php_bin" ]]; then
        php_bin="$(command -v php 2>/dev/null || echo php)"
    fi
    local apm_dir current token

    current="$(dotenv_get "$backend_env" STAFF_API_TOKEN 2>/dev/null || true)"
    if dotenv_value_present "$current"; then
        return 0
    fi

    apm_dir="$staff_root/apm"
    if [[ ! -f "$apm_dir/artisan" || ! -f "$apm_dir/vendor/autoload.php" ]]; then
        return 0
    fi

    token="$(
        cd "$apm_dir" && "$php_bin" artisan tinker --execute="echo (string) config('services.staff_api.token');" 2>/dev/null \
            | tail -1 | tr -d '\r'
    )"
    if dotenv_value_present "$token"; then
        dotenv_set "$backend_env" STAFF_API_TOKEN "$token"
    fi
}

helpdesk_inherit_sensitive_from_portal_env() {
    local backend_env="$1"
    local staff_root="${2:-${STAFF_ROOT:-}}"
    local apm_env="$staff_root/apm/.env"
    local staff_env="$staff_root/.env"
    local key from

    for key in STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN BASE_URL \
        APP_LOGO_URL STAFF_MAIL_LOGO_URL \
        HELPDESK_BRIDGE_SECRET \
        MAIL_MAILER USE_EXCHANGE_EMAIL MAIL_FROM_ADDRESS MAIL_FROM_NAME \
        EXCHANGE_TENANT_ID EXCHANGE_CLIENT_ID EXCHANGE_CLIENT_SECRET \
        EXCHANGE_REDIRECT_URI EXCHANGE_SCOPE EXCHANGE_AUTH_METHOD; do
        for from in "$apm_env" "$staff_env"; do
            helpdesk_inherit_env_key "$backend_env" "$key" "$from"
        done
    done

    for key in JWT_SECRET SESSION_SECRET REDIS_PASSWORD; do
        for from in "$staff_env" "$apm_env"; do
            helpdesk_inherit_env_key "$backend_env" "$key" "$from"
        done
    done

    if [[ "${APP_ENV:-}" == "production" ]]; then
        local internal
        internal="$(dotenv_get "$backend_env" HELPDESK_STAFF_API_INTERNAL_BASE_URL 2>/dev/null || true)"
        if ! dotenv_value_present "$internal"; then
            dotenv_set "$backend_env" HELPDESK_STAFF_API_INTERNAL_BASE_URL "http://127.0.0.1/staff"
        fi
    fi

    helpdesk_resolve_staff_api_token_from_apm "$backend_env" "$staff_root" "${PHP_BIN:-php}"
    helpdesk_ensure_app_logo_url "$backend_env"
}

helpdesk_ensure_app_logo_url() {
    local backend_env="$1"
    local current base

    for key in APP_LOGO_URL STAFF_MAIL_LOGO_URL HELPDESK_MAIL_LOGO_URL; do
        current="$(dotenv_get "$backend_env" "$key" 2>/dev/null || true)"
        if dotenv_value_present "$current"; then
            if [[ "$key" != "APP_LOGO_URL" ]]; then
                dotenv_set "$backend_env" APP_LOGO_URL "$current"
            fi
            return 0
        fi
    done

    base="$(dotenv_get "$backend_env" BASE_URL 2>/dev/null || true)"
    base="${base%/}"
    if dotenv_value_present "$base"; then
        dotenv_set "$backend_env" APP_LOGO_URL "${base}/assets/images/AU_CDC_Logo-800.png"
    fi
}

# Backward-compatible alias used by configure-env.sh / setup-production.sh
helpdesk_ensure_staff_api_env() {
    helpdesk_inherit_sensitive_from_portal_env "$@"
}

helpdesk_validate_staff_api_env() {
    local backend_env="$1"
    local staff_root="${2:-${STAFF_ROOT:-}}"
    local key val

    for key in STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN; do
        val="$(dotenv_get "$backend_env" "$key" 2>/dev/null || true)"
        if ! dotenv_value_present "$val"; then
            if [[ "$key" == "STAFF_API_TOKEN" ]]; then
                printf 'error: %s is not set in %s — add it to %s/apm/.env or ensure APM vendor is installed so setup can resolve config(services.staff_api.token).\n' \
                    "$key" "$backend_env" "$staff_root" >&2
            else
                printf 'error: %s is not set in %s — add it to %s/apm/.env (same values as `php artisan staff:sync`).\n' \
                    "$key" "$backend_env" "$staff_root" >&2
            fi
            return 1
        fi
    done
    return 0
}
