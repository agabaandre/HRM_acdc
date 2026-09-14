#!/usr/bin/env bash
# shellcheck shell=bash
# Sets URL globals from PUBLIC_BASE (…/staff) and DEPLOY_MODE (host|docker).

setup_normalize_base() {
  local b="${1%/}"
  case "$b" in
    */staff) ;;
    *) b="${b}/staff" ;;
  esac
  printf '%s' "$b"
}

setup_map_urls() {
  local base deploy
  base="$(setup_normalize_base "${PUBLIC_BASE:?}")"
  deploy="${DEPLOY_MODE:?}" # host|docker
  PUBLIC_BASE="$base"
  BASE_URL="${base}/"
  CI_BASE_URL="${base}/"
  APM_BASE_URL="${base}/apm"
  STAFF_PORTAL_APP_URL="${base}/backend"
  STAFF_PORTAL_SPA_URL="${base}/"
  APM_APP_URL="${base}/apm"
  FINANCE_APP_URL="${base}/finance"
  HELPDESK_APP_URL="${base}/helpdesk/backend"
  HELPDESK_FRONTEND_URL="${base}/helpdesk"
  if [[ "$deploy" == "docker" ]]; then
    SHARE_INTERNAL_BASE="http://web/staff/backend"
    REDIS_HOST_DEFAULT="redis"
  else
    SHARE_INTERNAL_BASE="http://127.0.0.1/staff/backend"
    REDIS_HOST_DEFAULT="127.0.0.1"
  fi
  STAFF_API_INTERNAL_BASE_URL="$SHARE_INTERNAL_BASE"
  HELPDESK_STAFF_API_INTERNAL_BASE_URL="$SHARE_INTERNAL_BASE"
}
