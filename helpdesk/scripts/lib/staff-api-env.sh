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

helpdesk_inherit_sensitive_from_portal_env() {
    local backend_env="$1"
    local staff_root="${2:-${STAFF_ROOT:-}}"
    local apm_env="$staff_root/apm/.env"
    local staff_env="$staff_root/.env"
    local key from

    for key in STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN BASE_URL \
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
            printf 'error: %s is not set in %s — add it to %s/apm/.env (same values as `php artisan staff:sync`).\n' \
                "$key" "$backend_env" "$staff_root" >&2
            return 1
        fi
    done
    return 0
}
