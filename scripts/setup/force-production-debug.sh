#!/usr/bin/env bash
# shellcheck shell=bash
# When site role is Production, every Laravel .env must use APP_ENV=production
# and APP_DEBUG=false (Ignition / stack traces must stay off).

setup_is_production_site() {
  [[ "${SITE_KIND:-}" == "production" ]] \
    || [[ "${APP_ENV:-}" == "production" ]] \
    || [[ "${STAFF_PORTAL_PRODUCTION_SETUP:-0}" == "1" ]] \
    || [[ "${HELPDESK_PRODUCTION_SETUP:-0}" == "1" ]] \
    || [[ "${FINANCE_PRODUCTION_SETUP:-0}" == "1" ]] \
    || [[ "${RISK_REGISTER_PRODUCTION_SETUP:-0}" == "1" ]] \
    || [[ "${IT_PRODUCTION_SETUP:-0}" == "1" ]] \
    || [[ "${REGISTRY_PRODUCTION_SETUP:-0}" == "1" ]]
}

# Force shell + optional dotenv files (uses env_set from env-upsert.sh when sourced from setup.sh).
setup_force_production_app_runtime() {
  if ! setup_is_production_site; then
    return 0
  fi
  APP_ENV=production
  APP_DEBUG=false
  export APP_ENV APP_DEBUG

  local file
  for file in "$@"; do
    [[ -n "$file" && -f "$file" ]] || continue
    if declare -F env_set >/dev/null 2>&1; then
      env_set "$file" APP_ENV "production" || true
      env_set "$file" APP_DEBUG "false" || true
    elif declare -F dotenv_set >/dev/null 2>&1; then
      dotenv_set "$file" APP_ENV "production" || true
      dotenv_set "$file" APP_DEBUG "false" || true
    fi
  done
}
