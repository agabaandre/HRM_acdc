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

# Atomic-enough upsert. Draft is built under /tmp so we still work when the
# target directory is not writable but the .env file itself is (common on
# shared hosting). Avoids set -e abort from grep -v exit status 1.
env_set() {
  local file="$1" key="$2" value="${3-}"
  local dir tmp
  dir="$(dirname "$file")"
  if [[ ! -d "$dir" ]]; then
    mkdir -p "$dir" || {
      echo "error: cannot create directory $dir" >&2
      return 1
    }
  fi
  if [[ ! -f "$file" ]]; then
    if ! : >"$file" 2>/dev/null; then
      echo "error: cannot create $file (check ownership/permissions)" >&2
      return 1
    fi
  elif [[ ! -w "$file" ]]; then
    echo "error: cannot write $file (check ownership/permissions)" >&2
    return 1
  fi

  tmp="$(mktemp "${TMPDIR:-/tmp}/cbp-env.XXXXXX")" || {
    echo "error: mktemp failed" >&2
    return 1
  }
  # grep -v exits 1 when every line is filtered — ignore that.
  grep -v -E "^[[:space:]]*${key}[[:space:]]*=" "$file" >"$tmp" 2>/dev/null || true
  if [[ "$value" =~ [[:space:]#\$] || "$value" == *\"* ]]; then
    printf '%s="%s"\n' "$key" "${value//\"/\\\"}" >>"$tmp"
  else
    printf '%s=%s\n' "$key" "$value" >>"$tmp"
  fi

  # Prefer in-place overwrite (needs write on file only). Fall back to mv.
  if cat "$tmp" >"$file" 2>/dev/null; then
    rm -f "$tmp"
    return 0
  fi
  if mv "$tmp" "$file" 2>/dev/null; then
    return 0
  fi
  rm -f "$tmp"
  echo "error: failed to update $file" >&2
  return 1
}

env_ensure_file() {
  local file="$1" template="${2:-}"
  if [[ -f "$file" ]]; then
    return 0
  fi
  local dir
  dir="$(dirname "$file")"
  mkdir -p "$dir" || {
    echo "error: cannot create directory $dir" >&2
    return 1
  }
  if [[ -n "$template" && -f "$template" ]]; then
    cp "$template" "$file" || {
      echo "error: cannot create $file from template" >&2
      return 1
    }
  else
    : >"$file" || {
      echo "error: cannot create $file" >&2
      return 1
    }
  fi
}
