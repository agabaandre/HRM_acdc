#!/usr/bin/env bash
# shellcheck shell=bash
# Sets URL globals from PUBLIC_BASE (…/staff, …/cbp, …/demo_cbp, …) and DEPLOY_MODE.

setup_normalize_base() {
  local b="${1%/}"
  # Strip accidental trailing app segments if the user pasted a deep URL.
  case "$b" in
    */backend) b="${b%/backend}" ;;
    */apm) b="${b%/apm}" ;;
    */finance) b="${b%/finance}" ;;
    */helpdesk) b="${b%/helpdesk}" ;;
  esac
  b="${b%/}"
  # Host-only → default web-folder name (basename of repo, else staff).
  if [[ "$b" =~ ^https?://[^/]+$ ]]; then
    local folder="${DEFAULT_WEB_ROOT:-staff}"
    b="${b}/${folder}"
  fi
  printf '%s' "$b"
}

# First path segment of the public base (staff, cbp, demo_cbp, …).
setup_web_root_from_base() {
  local base path seg
  base="$(setup_normalize_base "$1")"
  path="${base#*://*/}"
  path="${path%%\?*}"
  path="${path%%\#*}"
  path="${path#/}"
  seg="${path%%/*}"
  if [[ -z "$seg" || "$seg" == *"("* ]]; then
    seg="${DEFAULT_WEB_ROOT:-staff}"
  fi
  printf '%s' "$seg"
}

setup_map_urls() {
  local base deploy
  base="$(setup_normalize_base "${PUBLIC_BASE:?}")"
  deploy="${DEPLOY_MODE:?}" # host|docker
  PUBLIC_BASE="$base"
  WEB_ROOT="$(setup_web_root_from_base "$base")"
  PUBLIC_PATH="/${WEB_ROOT}"

  BASE_URL="${base}/"
  CI_BASE_URL="${base}/"
  APM_BASE_URL="${base}/apm"
  STAFF_PORTAL_APP_URL="${base}/backend"
  STAFF_PORTAL_SPA_URL="${base}/"
  APM_APP_URL="${base}/apm"
  FINANCE_APP_URL="${base}/finance"
  HELPDESK_APP_URL="${base}/helpdesk/backend"
  HELPDESK_FRONTEND_URL="${base}/helpdesk"
  VITE_STAFF_PORTAL_API_BASE_URL="${PUBLIC_PATH}/backend"
  VITE_STAFF_PORTAL_BASE_PATH="${PUBLIC_PATH}/"
  VITE_FINANCE_BASE_PATH="${PUBLIC_PATH}/finance/"
  FINANCE_SESSION_PATH="${PUBLIC_PATH}/finance"

  if [[ "$deploy" == "docker" ]]; then
    SHARE_INTERNAL_BASE="http://web${PUBLIC_PATH}/backend"
    REDIS_HOST_DEFAULT="redis"
  else
    SHARE_INTERNAL_BASE="http://127.0.0.1${PUBLIC_PATH}/backend"
    REDIS_HOST_DEFAULT="127.0.0.1"
  fi
  STAFF_API_INTERNAL_BASE_URL="$SHARE_INTERNAL_BASE"
  HELPDESK_STAFF_API_INTERNAL_BASE_URL="$SHARE_INTERNAL_BASE"
}

# Derive STAFF_SITE_ID from public base URL (host + path), matching StaffStorage / storage scripts.
setup_derive_site_id() {
  local url="${1:-${BASE_URL:-http://localhost/staff}}"
  url="${url%/}"
  if command -v php >/dev/null 2>&1; then
    local out
    out="$(
      BASE_URL="$url" php -r '
        $u = trim(getenv("BASE_URL") ?: "http://localhost/staff");
        $p = parse_url($u);
        $host = strtolower(preg_replace("/^www\./", "", $p["host"] ?? "localhost"));
        $slug = implode("-", array_filter(explode(".", $host)));
        $port = $p["port"] ?? null;
        if ($port && !in_array((int)$port, [80, 443], true)) {
            $slug .= "-".$port;
        }
        $path = trim($p["path"] ?? "", "/");
        if ($path !== "") {
            $pathSlug = trim(preg_replace("/[^a-z0-9]+/", "-", strtolower($path)), "-");
            if ($pathSlug !== "") {
                $slug .= "-".$pathSlug;
            }
        }
        echo preg_replace("/^-+|-+$/", "", $slug) ?: "localhost-staff";
      ' 2>/dev/null || true
    )"
    if [[ -n "$out" ]]; then
      printf '%s' "$out"
      return 0
    fi
  fi
  # Bash fallback when PHP is unavailable
  local host path slug
  host="$(printf '%s' "$url" | sed -E 's#^https?://([^/:]+).*#\1#' | tr '[:upper:]' '[:lower:]')"
  host="${host#www.}"
  path="$(printf '%s' "$url" | sed -E 's#^https?://[^/]+/#/#; s#^https?://[^/]+$##')"
  path="$(printf '%s' "$path" | sed -E 's#^/##; s#/$##')"
  slug="$(printf '%s' "$host" | tr '.' '-')"
  if [[ -n "$path" ]]; then
    path="$(printf '%s' "$path" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+//; s/-+$//')"
    [[ -n "$path" ]] && slug="${slug}-${path}"
  fi
  printf '%s' "${slug:-localhost-staff}"
}

setup_apply_storage_paths() {
  STAFF_HOST_DATA_ROOT="${STAFF_HOST_DATA_ROOT:-/var/staffdata}"
  STAFF_DATA_ROOT="${STAFF_HOST_DATA_ROOT}/${STAFF_SITE_ID}"
  STAFF_PORTAL_UPLOADS_ROOT="${STAFF_DATA_ROOT}/ci"
  STAFF_APM_FILES_ROOT="${STAFF_DATA_ROOT}/apm"
  STAFF_HELPDESK_FILES_ROOT="${STAFF_DATA_ROOT}/helpdesk"
  STAFF_PORTAL_MODULE_FILES_ROOT="${STAFF_DATA_ROOT}/staff-portal"
}
