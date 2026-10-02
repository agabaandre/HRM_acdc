#!/usr/bin/env bash
# shellcheck shell=bash
# Empty MySQL schema detection for ./setup.sh installers.

setup_db_mysql_bin() {
  if command -v mysql >/dev/null 2>&1; then
    command -v mysql
  elif command -v mariadb >/dev/null 2>&1; then
    command -v mariadb
  else
    return 1
  fi
}

# Prints table count for schema. Exit 1 if client missing or query fails.
setup_db_table_count() {
  local host="$1" port="$2" user="$3" pass="$4" database="$5"
  local bin sql out
  bin="$(setup_db_mysql_bin)" || return 1
  [[ -n "$database" ]] || return 1
  sql="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();"
  out="$(
    MYSQL_PWD="$pass" "$bin" -h "$host" -P "$port" -u "$user" -N -B "$database" \
      -e "$sql" 2>/dev/null
  )" || return 1
  out="$(printf '%s' "$out" | tr -d '[:space:]')"
  [[ "$out" =~ ^[0-9]+$ ]] || return 1
  printf '%s\n' "$out"
}

# Exit 0 = treat as new (empty or unreachable). Exit 1 = has tables.
setup_db_is_empty() {
  local host="$1" port="$2" user="$3" pass="$4" database="$5"
  local count
  if ! count="$(setup_db_table_count "$host" "$port" "$user" "$pass" "$database")"; then
    echo "warn: could not probe MySQL ${database}@${host}:${port} — treating as new (will seed)" >&2
    return 0
  fi
  if [[ "$count" -eq 0 ]]; then
    echo "    ${database}: 0 tables (new)" >&2
    return 0
  fi
  echo "    ${database}: ${count} tables (existing — skip seed)" >&2
  return 1
}
