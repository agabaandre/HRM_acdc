#!/usr/bin/env bash
# shellcheck shell=bash
# Finance production URL resolution.

_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=paths.sh
source "$_LIB_DIR/paths.sh"
if [[ -z "${STAFF_ROOT:-}" ]]; then
    staff_paths_resolve_from_module "$(cd "$_LIB_DIR/../.." && pwd)"
fi
# shellcheck source=staff-portal-urls.sh
source "$_LIB_DIR/staff-portal-urls.sh"

finance_resolve_production_urls() {
    local staff_base origin
    [[ "${APP_ENV:-}" == "production" ]] || return 0

    if url_needs_resolve "${APP_URL:-}" || url_needs_resolve "${BASE_URL:-}" \
        || url_needs_resolve "${FINANCE_STAFF_PORTAL_URL:-}" \
        || url_needs_resolve "${FINANCE_ASSETS_BASE_URL:-}"; then
        staff_base="$(resolve_staff_portal_base_url)" || return 0
        staff_base="${staff_base%/}/"
        origin="${staff_base%/staff/}"
        origin="${origin%/}"

        if url_needs_resolve "${BASE_URL:-}"; then
            BASE_URL="$staff_base"
        fi
        if url_needs_resolve "${FINANCE_STAFF_PORTAL_URL:-}"; then
            FINANCE_STAFF_PORTAL_URL="${origin}/staff"
        fi
        if url_needs_resolve "${APP_URL:-}"; then
            APP_URL="${origin}/staff/finance"
        fi
        if url_needs_resolve "${FINANCE_ASSETS_BASE_URL:-}"; then
            FINANCE_ASSETS_BASE_URL="${origin}/staff/apm"
        fi
        if [[ -z "${VITE_APP_BASE_PATH:-}" ]]; then
            VITE_APP_BASE_PATH="/staff/finance/"
        fi
    fi
}

finance_inherit_database_from_staff() {
    inherit_database_from_staff
}
