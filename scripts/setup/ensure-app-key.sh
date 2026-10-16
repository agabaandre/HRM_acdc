#!/usr/bin/env bash
# shellcheck shell=bash
# Ensure Laravel .env has a real APP_KEY.
# artisan key:generate fails with "No APP_KEY variable was found" when the line is absent.
#
# Usage:
#   source scripts/setup/ensure-app-key.sh
#   ensure_app_key_in_env /path/to/app          # uses /path/to/app/.env
#   ensure_app_key_in_env /path/to/app /path/to/.env

ensure_env_has_app_key_line() {
  local env_file="$1"
  [[ -f "$env_file" ]] || return 1
  if grep -qE '^APP_KEY=' "$env_file" 2>/dev/null; then
    return 0
  fi
  # Append placeholder so artisan key:generate (or our writer) can replace it.
  printf '\nAPP_KEY=\n' >>"$env_file" || return 1
  return 0
}

ensure_app_key_in_env() {
  local dir="$1"
  local env_file="${2:-$dir/.env}"
  local key="" php_bin="${PHP_BIN:-php}"

  [[ -f "$env_file" ]] || return 0
  ensure_env_has_app_key_line "$env_file" || return 1

  if command -v grep >/dev/null 2>&1; then
    key="$(grep -E '^APP_KEY=' "$env_file" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '[:space:]"')"
  fi
  if [[ -n "$key" ]]; then
    return 0
  fi

  echo "    generating APP_KEY → $dir"
  rm -f "$dir/bootstrap/cache/config.php" 2>/dev/null || true

  if [[ -f "$dir/artisan" && -f "$dir/vendor/autoload.php" ]] \
    && command -v "$php_bin" >/dev/null 2>&1; then
    if (cd "$dir" && "$php_bin" artisan key:generate --no-interaction --force) >/dev/null 2>&1; then
      return 0
    fi
  fi

  # Fallback: write key without booting Laravel.
  if command -v php >/dev/null 2>&1; then
    php -r '
      $env = $argv[1];
      $key = "base64:" . base64_encode(random_bytes(32));
      $raw = file_get_contents($env);
      if ($raw === false) { exit(1); }
      if (preg_match("/^APP_KEY=.*/m", $raw)) {
        $raw = preg_replace("/^APP_KEY=.*/m", "APP_KEY=" . $key, $raw, 1);
      } else {
        $raw .= (str_ends_with($raw, "\n") ? "" : "\n") . "APP_KEY=" . $key . "\n";
      }
      exit(file_put_contents($env, $raw) === false ? 1 : 0);
    ' "$env_file" && return 0
  fi

  echo "warning: could not set APP_KEY in $env_file" >&2
  return 1
}
