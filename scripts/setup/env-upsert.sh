#!/usr/bin/env bash
# shellcheck shell=bash
# Get/set KEY=value in dotenv files without wiping other keys.
# Accepts optional spaces around '=' (e.g. DB_HOST = 127.0.0.1).

env_get() {
  local file="$1" key="$2"
  [[ -f "$file" ]] || return 0
  local line val
  line="$(grep -E "^[[:space:]]*${key}[[:space:]]*=" "$file" 2>/dev/null | tail -n 1 || true)"
  [[ -n "$line" ]] || return 0
  val="${line#*=}"
  val="${val%$'\r'}"
  val="${val#"${val%%[![:space:]]*}"}"
  val="${val%"${val##*[![:space:]]}"}"
  if [[ "$val" =~ ^\".*\"$ ]]; then val="${val:1:${#val}-2}"; fi
  if [[ "$val" =~ ^\'.*\'$ ]]; then val="${val:1:${#val}-2}"; fi
  printf '%s' "$val"
}

env_set() {
  local file="$1" key="$2" value="$3"
  local tmp
  mkdir -p "$(dirname "$file")"
  touch "$file"
  tmp="${file}.tmp.$$"
  grep -v -E "^[[:space:]]*${key}[[:space:]]*=" "$file" >"$tmp" 2>/dev/null || : >"$tmp"
  if [[ "$value" =~ [[:space:]#\$] || "$value" == *\"* ]]; then
    printf '%s="%s"\n' "$key" "${value//\"/\\\"}" >>"$tmp"
  else
    printf '%s=%s\n' "$key" "$value" >>"$tmp"
  fi
  mv "$tmp" "$file"
}

env_ensure_file() {
  local file="$1" template="${2:-}"
  if [[ -f "$file" ]]; then
    return 0
  fi
  mkdir -p "$(dirname "$file")"
  if [[ -n "$template" && -f "$template" ]]; then
    cp "$template" "$file"
  else
    : >"$file"
  fi
}
