#!/usr/bin/env bash
# shellcheck shell=bash
# Staff Portal production URL resolution.

_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=paths.sh
source "$_LIB_DIR/paths.sh"
if [[ -z "${STAFF_ROOT:-}" ]]; then
    staff_paths_resolve_from_module "$(cd "$_LIB_DIR/../.." && pwd)"
fi
# shellcheck source=staff-portal-urls.sh
source "$_LIB_DIR/staff-portal-urls.sh"

staff_portal_resolve_production_urls() {
    local staff_base origin mount
    [[ "${APP_ENV:-}" == "production" ]] || return 0

    # Detect /demo_staff vs /staff from filesystem (…/demo_staff/staff-portal).
    mount="/staff"
    if [[ -n "${PORTAL_ROOT:-}" && "$(basename "$(cd "$PORTAL_ROOT/.." && pwd)")" == "demo_staff" ]]; then
        mount="/demo_staff"
    elif [[ -n "${STAFF_ROOT:-}" && "$(basename "$STAFF_ROOT")" == "demo_staff" ]]; then
        mount="/demo_staff"
    fi

    if url_needs_resolve "${APP_URL:-}" || url_needs_resolve "${BASE_URL:-}" \
        || url_needs_resolve "${STAFF_PORTAL_SPA_URL:-}" \
        || url_needs_resolve "${STAFF_PORTAL_BASE_URL:-}" \
        || url_needs_resolve "${STAFF_PORTAL_HEALTH_URL:-}" \
        || url_needs_resolve "${APM_BASE_URL:-}"; then
        staff_base="$(resolve_staff_portal_base_url)" || return 0
        staff_base="${staff_base%/}/"
        origin="${staff_base%/staff/}"
        origin="${origin%/}"
        # Prefer filesystem mount for SPA/API paths when under demo_staff
        if [[ "$mount" == "/demo_staff" ]]; then
            staff_base="${origin}/demo_staff/"
        fi

        if url_needs_resolve "${BASE_URL:-}"; then
            BASE_URL="$staff_base"
        fi
        if url_needs_resolve "${STAFF_PORTAL_SPA_URL:-}"; then
            STAFF_PORTAL_SPA_URL="${origin}${mount}/staff-portal/"
        fi
        if url_needs_resolve "${APP_URL:-}"; then
            APP_URL="${origin}${mount}/staff-portal/backend"
        fi
        if url_needs_resolve "${STAFF_PORTAL_BASE_URL:-}"; then
            STAFF_PORTAL_BASE_URL="${origin}${mount}/staff-portal/backend/"
        fi
        if url_needs_resolve "${STAFF_PORTAL_HEALTH_URL:-}"; then
            STAFF_PORTAL_HEALTH_URL="${origin}${mount}/staff-portal/backend/up"
        fi
        if url_needs_resolve "${APM_BASE_URL:-}"; then
            APM_BASE_URL="${origin}/staff/apm"
        fi
        if [[ -z "${VITE_STAFF_PORTAL_API_BASE_URL:-}" || "$mount" == "/demo_staff" ]]; then
            VITE_STAFF_PORTAL_API_BASE_URL="${mount}/staff-portal/backend"
        fi
        if [[ -z "${VITE_STAFF_PORTAL_BASE_PATH:-}" || "$mount" == "/demo_staff" ]]; then
            VITE_STAFF_PORTAL_BASE_PATH="${mount}/staff-portal/"
        fi
    fi
}

staff_portal_inherit_database_from_staff() {
    inherit_database_from_staff
    # Prefer shared staff schema name when unset
    if [[ -z "${DB_DATABASE:-}" ]]; then
        local val
        val="$(staff_env_get DB_NAME 2>/dev/null || true)"
        [[ -z "$val" ]] && val="$(staff_env_get DB_DATABASE 2>/dev/null || true)"
        [[ -n "$val" ]] && DB_DATABASE="$val"
        [[ -z "${DB_DATABASE:-}" ]] && DB_DATABASE=staff
    fi
}
