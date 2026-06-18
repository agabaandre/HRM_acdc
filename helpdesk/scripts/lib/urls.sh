#!/usr/bin/env bash
# shellcheck shell=bash
# Helpdesk production URL resolution.

_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=paths.sh
source "$_LIB_DIR/paths.sh"
if [[ -z "${STAFF_ROOT:-}" ]]; then
    staff_paths_resolve_from_module "$(cd "$_LIB_DIR/../.." && pwd)"
fi
# shellcheck source=staff-portal-urls.sh
source "$_LIB_DIR/staff-portal-urls.sh"

helpdesk_resolve_production_urls() {
    local staff_base origin
    [[ "${APP_ENV:-}" == "production" ]] || return 0

    if url_needs_resolve "${APP_URL:-}" || url_needs_resolve "${BASE_URL:-}" \
        || url_needs_resolve "${HELPDESK_FRONTEND_URL:-}" \
        || url_needs_resolve "${HELPDESK_STAFF_PORTAL_URL:-}" \
        || url_needs_resolve "${HELPDESK_APM_BASE_URL:-}" \
        || url_needs_resolve "${HELPDESK_HEALTH_URL:-}"; then
        staff_base="$(resolve_staff_portal_base_url)" || return 0
        staff_base="${staff_base%/}/"
        origin="${staff_base%/staff/}"
        origin="${origin%/}"

        if url_needs_resolve "${BASE_URL:-}"; then
            BASE_URL="$staff_base"
        fi
        if url_needs_resolve "${HELPDESK_STAFF_PORTAL_URL:-}"; then
            HELPDESK_STAFF_PORTAL_URL="${origin}/staff"
        fi
        if url_needs_resolve "${HELPDESK_APM_BASE_URL:-}"; then
            HELPDESK_APM_BASE_URL="${origin}/staff/apm"
        fi
        if url_needs_resolve "${HELPDESK_FRONTEND_URL:-}"; then
            HELPDESK_FRONTEND_URL="${origin}/staff/helpdesk"
        fi
        if url_needs_resolve "${APP_URL:-}"; then
            APP_URL="${origin}/staff/helpdesk/backend"
        fi
        if url_needs_resolve "${HELPDESK_HEALTH_URL:-}"; then
            HELPDESK_HEALTH_URL="${origin}/staff/helpdesk/backend/api/v1/health"
        fi
        if url_needs_resolve "${HELPDESK_API_PUBLIC_URL:-}"; then
            HELPDESK_API_PUBLIC_URL="${origin}/staff/helpdesk/backend"
        fi
        if [[ -z "${VITE_HELPDESK_API_BASE_URL:-}" ]]; then
            VITE_HELPDESK_API_BASE_URL="/staff/helpdesk/backend"
        fi
    fi
}

helpdesk_inherit_database_from_staff() {
    inherit_database_from_staff
}
