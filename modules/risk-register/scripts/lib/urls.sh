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
    local staff_base origin mount web_root
    [[ "${APP_ENV:-}" == "production" ]] || return 0

    # Public mount = checkout folder name (staff, demo_staff, cbpdemo, …).
    web_root="${WEB_ROOT:-}"
    if [[ -z "$web_root" && -n "${STAFF_ROOT:-}" ]]; then
        web_root="$(basename "$STAFF_ROOT")"
    fi
    if [[ -z "$web_root" && -n "${PORTAL_ROOT:-}" ]]; then
        # modules/staff-portal → repo root
        web_root="$(basename "$(cd "$PORTAL_ROOT/../.." && pwd)")"
    fi
    web_root="$(printf '%s' "${web_root:-staff}" | sed -E 's#^/##; s#/$##')"
    mount="/${web_root}"

    if url_needs_resolve "${APP_URL:-}" || url_needs_resolve "${BASE_URL:-}" \
        || url_needs_resolve "${STAFF_PORTAL_SPA_URL:-}" \
        || url_needs_resolve "${STAFF_PORTAL_BASE_URL:-}" \
        || url_needs_resolve "${STAFF_PORTAL_HEALTH_URL:-}" \
        || url_needs_resolve "${APM_BASE_URL:-}"; then
        staff_base="$(resolve_staff_portal_base_url)" || return 0
        staff_base="${staff_base%/}/"
        origin="$(url_origin_from_base "$staff_base")" || origin="${staff_base%/staff/}"
        origin="${origin%/}"
        staff_base="${origin}${mount}/"

        if url_needs_resolve "${BASE_URL:-}"; then
            BASE_URL="$staff_base"
        fi
        # SPA is served at /{web_root}/ (modules layout — not /{web_root}/staff-portal/).
        if url_needs_resolve "${STAFF_PORTAL_SPA_URL:-}"; then
            STAFF_PORTAL_SPA_URL="${origin}${mount}/"
        fi
        if url_needs_resolve "${APP_URL:-}"; then
            APP_URL="${origin}${mount}/backend"
        fi
        if url_needs_resolve "${STAFF_PORTAL_BASE_URL:-}"; then
            STAFF_PORTAL_BASE_URL="${origin}${mount}/backend/"
        fi
        if url_needs_resolve "${STAFF_PORTAL_HEALTH_URL:-}"; then
            STAFF_PORTAL_HEALTH_URL="http://127.0.0.1${mount}/backend/up"
        fi
        if url_needs_resolve "${APM_BASE_URL:-}"; then
            APM_BASE_URL="${origin}${mount}/apm"
        fi
        VITE_STAFF_PORTAL_API_BASE_URL="${mount}/backend"
        VITE_STAFF_PORTAL_BASE_PATH="${mount}/"
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
