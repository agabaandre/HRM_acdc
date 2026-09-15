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
