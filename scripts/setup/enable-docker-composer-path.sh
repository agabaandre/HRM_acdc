#!/usr/bin/env bash
# shellcheck shell=bash
# Source from module setup*.sh so `composer` resolves to Compose when Docker deploy is on.
# Expects STAFF_ROOT (or derives from caller’s module root via $1).

_staff_root="${STAFF_ROOT:-}"
if [[ -z "$_staff_root" && -n "${1:-}" ]]; then
  _staff_root="$(cd "$1/../.." && pwd)"
fi
if [[ -z "$_staff_root" ]]; then
  return 0 2>/dev/null || exit 0
fi

if [[ -z "${DEPLOY_MODE:-}" && -f "$_staff_root/.env" ]]; then
  _dm="$(grep -E '^DEPLOY_MODE=' "$_staff_root/.env" | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  [[ -n "$_dm" ]] && DEPLOY_MODE="$_dm"
fi

if [[ "${DEPLOY_MODE:-}" == "docker" || "${STAFF_COMPOSER_VIA_DOCKER:-0}" == "1" ]]; then
  export STAFF_ROOT="$_staff_root"
  export DEPLOY_MODE="${DEPLOY_MODE:-docker}"
  export STAFF_COMPOSER_VIA_DOCKER=1
  export PATH="${STAFF_ROOT}/scripts/setup/bin:$PATH"
fi
unset _staff_root _dm
