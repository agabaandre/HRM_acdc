#!/usr/bin/env bash
# shellcheck shell=bash
# Probe Laravel Staff Share API with STAFF_API_* credentials.
# Usage:
#   ./scripts/setup/probe-staff-share-api.sh [base_url]
# Env: STAFF_API_USERNAME STAFF_API_PASSWORD STAFF_API_TOKEN
#      STAFF_API_INTERNAL_BASE_URL (fallback base)
#
# Exit 0 if JWT or static-token path works; 1 if both fail.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAFF_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

base="${1:-}"
if [[ -z "$base" ]]; then
  base="${STAFF_API_INTERNAL_BASE_URL:-}"
fi
if [[ -z "$base" ]]; then
  base="http://127.0.0.1/staff/backend"
fi
# Normalize: …/staff → …/staff/backend (Laravel Share mount)
base="${base%/}"
if [[ "$base" != */backend ]]; then
  base="${base}/backend"
fi

# Docker Compose service hostname is not resolvable on the host — rewrite for probing.
if [[ "$base" =~ ^https?://web(/|$) ]]; then
  base="$(printf '%s' "$base" | sed -E 's#^(https?://)web#\1127.0.0.1#')"
  echo "    (rewrote Compose host web → 127.0.0.1 for host-side probe)"
fi

user="${STAFF_API_USERNAME:-}"
pass="${STAFF_API_PASSWORD:-}"
token="${STAFF_API_TOKEN:-YWZyY2FjZGNzdGFmZnRyYWNrZXI}"

echo "==> Staff Share API probe"
echo "    base: $base"
echo "    user: ${user:-"(empty)"}"
if [[ -n "$token" ]]; then
  echo "    token: set (${#token} chars)"
else
  echo "    token: (empty)"
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "    FAIL: curl not found" >&2
  return 1 2>/dev/null || exit 1
fi

_auth_mode=""
_http() {
  # $1=method $2=url [$3=extra curl args...]
  local method="$1" url="$2"
  shift 2
  curl -sS -o /tmp/staff-share-probe-body.$$ -w "%{http_code}" \
    --connect-timeout 5 --max-time 45 \
    -X "$method" -H "Accept: application/json" \
    "$@" "$url" 2>/tmp/staff-share-probe-err.$$ || echo "000"
}

jwt_ok=0
static_ok=0
staff_n=""
div_n=""
dir_n=""

# 1) Prefer credentials → POST /share/token
if [[ -n "$user" && -n "$pass" ]]; then
  code="$(_http POST "${base}/share/token" -u "${user}:${pass}")"
  body="$(cat /tmp/staff-share-probe-body.$$ 2>/dev/null || true)"
  if [[ "$code" == "200" ]] && [[ "$body" == *'"access_token"'* ]]; then
    jwt_ok=1
    _auth_mode="JWT (POST /share/token with username/password)"
    echo "    credentials → token: OK (HTTP $code)"
  else
    echo "    credentials → token: FAIL (HTTP ${code:-?})"
    if [[ -n "$body" ]]; then
      echo "      $(printf '%s' "$body" | head -c 160)"
    fi
    err="$(cat /tmp/staff-share-probe-err.$$ 2>/dev/null || true)"
    [[ -n "$err" ]] && echo "      curl: $(printf '%s' "$err" | head -c 120)"
  fi
else
  echo "    credentials → token: SKIP (STAFF_API_USERNAME/PASSWORD empty)"
fi

# 2) Static token path (CI3 / fallback)
if [[ -n "$token" ]]; then
  code="$(_http GET "${base}/share/get_current_staff/${token}?limit=2")"
  body="$(cat /tmp/staff-share-probe-body.$$ 2>/dev/null || true)"
  if [[ "$code" == "200" ]] && [[ "$body" == '['* || "$body" == '{'* ]]; then
    static_ok=1
    [[ -z "$_auth_mode" ]] && _auth_mode="static STAFF_API_TOKEN (path)"
    # Count array elements roughly via php if available
    if command -v php >/dev/null 2>&1; then
      staff_n="$(php -r '$j=json_decode(stream_get_contents(STDIN), true); echo is_array($j)?count($j):0;' <<<"$body" 2>/dev/null || echo "?")"
    fi
    echo "    static token → staff: OK (HTTP $code${staff_n:+, ~$staff_n rows in sample})"
  else
    echo "    static token → staff: FAIL (HTTP ${code:-?})"
    [[ -n "$body" ]] && echo "      $(printf '%s' "$body" | head -c 160)"
  fi

  code="$(_http GET "${base}/share/divisions/${token}")"
  body="$(cat /tmp/staff-share-probe-body.$$ 2>/dev/null || true)"
  if [[ "$code" == "200" ]]; then
    if command -v php >/dev/null 2>&1; then
      div_n="$(php -r '$j=json_decode(stream_get_contents(STDIN), true); echo is_array($j)?count($j):0;' <<<"$body" 2>/dev/null || echo "?")"
    fi
    echo "    static token → divisions: OK (HTTP $code${div_n:+, $div_n})"
  else
    echo "    static token → divisions: FAIL (HTTP ${code:-?})"
  fi

  code="$(_http GET "${base}/share/directorates/${token}")"
  body="$(cat /tmp/staff-share-probe-body.$$ 2>/dev/null || true)"
  if [[ "$code" == "200" ]]; then
    if command -v php >/dev/null 2>&1; then
      dir_n="$(php -r '$j=json_decode(stream_get_contents(STDIN), true); echo is_array($j)?count($j):0;' <<<"$body" 2>/dev/null || echo "?")"
    fi
    echo "    static token → directorates: OK (HTTP $code${dir_n:+, $dir_n})"
  else
    echo "    static token → directorates: FAIL (HTTP ${code:-?})"
  fi
else
  echo "    static token: SKIP (STAFF_API_TOKEN empty)"
fi

rm -f /tmp/staff-share-probe-body.$$ /tmp/staff-share-probe-err.$$ 2>/dev/null || true

if [[ "$jwt_ok" -eq 1 || "$static_ok" -eq 1 ]]; then
  echo "    STATUS: CONNECTED via ${_auth_mode}"
  echo "    docs: ${base}/share/docs"
  return 0 2>/dev/null || exit 0
fi

echo "    STATUS: FAILED — Helpdesk/APM directory sync will not work until this succeeds" >&2
echo "    Fix: set STAFF_API_USERNAME/PASSWORD to a portal login that can call Share," >&2
echo "         or ensure STAFF_API_TOKEN matches share.api_token, and base is …/staff/backend" >&2
return 1 2>/dev/null || exit 1
