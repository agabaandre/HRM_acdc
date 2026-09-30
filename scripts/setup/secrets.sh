#!/usr/bin/env bash
# shellcheck shell=bash
# Generate / ensure shared secrets for CBP setup (no hardcoded credentials).
#
# Requires env_get / env_set from scripts/setup/env-upsert.sh when using
# setup_ensure_secret_in_file. Module configure scripts may use the dotenv_*
# variants after sourcing their own dotenv.sh.

setup_rand_hex() {
  local bytes="${1:-32}"
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -hex "$bytes" 2>/dev/null && return 0
  fi
  # Portable fallback (no openssl)
  head -c "$bytes" /dev/urandom 2>/dev/null | od -An -tx1 | tr -d ' \n'
}

setup_secret_is_placeholder() {
  local v="$1"
  [[ -z "$v" ]] && return 0
  case "$v" in
    change-me*|CHANGE_ME*|REPLACE*|your-*|YOUR_*|xxx|TODO*) return 0 ;;
  esac
  return 1
}

# Ensure KEY exists in dotenv FILE; generate hex secret if missing/placeholder.
# Echoes the resolved value. Optional 3rd arg = hex byte length (default 32).
setup_ensure_secret_in_file() {
  local file="$1" key="$2" bytes="${3:-32}"
  local cur val
  cur="$(env_get "$file" "$key")"
  if ! setup_secret_is_placeholder "$cur"; then
    printf '%s' "$cur"
    return 0
  fi
  val="$(setup_rand_hex "$bytes")"
  if [[ -z "$val" ]]; then
    echo "error: could not generate $key (openssl/urandom unavailable)" >&2
    return 1
  fi
  env_set "$file" "$key" "$val" || return 1
  echo "    generated $key → $file" >&2
  printf '%s' "$val"
}

# Same behaviour using a module's dotenv_get / dotenv_set helpers.
dotenv_ensure_secret() {
  local file="$1" key="$2" bytes="${3:-32}"
  local cur val
  cur="$(dotenv_get "$file" "$key" 2>/dev/null || true)"
  if ! setup_secret_is_placeholder "$cur"; then
    printf '%s' "$cur"
    return 0
  fi
  val="$(setup_rand_hex "$bytes")"
  if [[ -z "$val" ]]; then
    echo "error: could not generate $key (openssl/urandom unavailable)" >&2
    return 1
  fi
  dotenv_set "$file" "$key" "$val" || return 1
  echo "    generated $key → $file" >&2
  printf '%s' "$val"
}

# Propagate shared secret using dotenv_get/dotenv_set (module configure scripts).
# Writes to staff root .env (+ setup.env). Module app .env files do NOT store
# JWT_SECRET / SESSION_SECRET — they inherit at runtime via shared/load-staff-root-env.php.
dotenv_ensure_shared_secret() {
  local staff_env="$1" setup_env="$2" app_env="$3" key="${4:-JWT_SECRET}"
  local val=""

  if [[ -f "$staff_env" ]]; then
    val="$(dotenv_get "$staff_env" "$key" 2>/dev/null || true)"
  fi
  if setup_secret_is_placeholder "$val" && [[ -f "$setup_env" ]]; then
    val="$(dotenv_get "$setup_env" "$key" 2>/dev/null || true)"
  fi
  # Read-only fallback from a leftover module copy (do not write back there).
  if setup_secret_is_placeholder "$val" && [[ -f "$app_env" ]]; then
    val="$(dotenv_get "$app_env" "$key" 2>/dev/null || true)"
  fi
  if setup_secret_is_placeholder "$val"; then
    val="$(setup_rand_hex 32)"
    echo "    generated shared $key" >&2
  fi
  [[ -n "$val" ]] || return 1

  if [[ -n "$staff_env" ]]; then
    mkdir -p "$(dirname "$staff_env")" 2>/dev/null || true
    touch "$staff_env" 2>/dev/null || true
    [[ -w "$staff_env" ]] && dotenv_set "$staff_env" "$key" "$val"
  fi
  [[ -n "$setup_env" && -f "$setup_env" && -w "$setup_env" ]] && dotenv_set "$setup_env" "$key" "$val"
  # Intentionally not writing $key into $app_env (centralized in staff root .env).
  printf '%s' "$val"
}
