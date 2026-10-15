#!/usr/bin/env bash
# Move Finance fin_* tables (with data) from the staff DB into finance.
# Safe to re-run: skips tables already present in finance.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-${DB_USERNAME:-root}}"
DB_PASS="${DB_PASS:-${DB_PASSWORD:-}}"
SOURCE_DB="${SOURCE_DB:-staff}"
TARGET_DB="${TARGET_DB:-finance}"

# Prefer credentials from setup.env / backend/.env when present.
if [[ -f "$ROOT/setup.env" ]]; then
  # shellcheck disable=SC1090
  set -a; source <(grep -E '^(DB_HOST|DB_PORT|DB_USERNAME|DB_PASSWORD|DB_USER|DB_PASS)=' "$ROOT/setup.env" | sed 's/\r$//'); set +a
fi
DB_USER="${DB_USERNAME:-${DB_USER:-root}}"
DB_PASS="${DB_PASSWORD:-${DB_PASS:-}}"

mysql_cmd=(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER")
if [[ -n "$DB_PASS" ]]; then
  mysql_cmd+=(-p"$DB_PASS")
fi

echo "==> Ensuring database $TARGET_DB exists"
"${mysql_cmd[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$TARGET_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> Renaming fin_* tables from $SOURCE_DB → $TARGET_DB"
tables="$("${mysql_cmd[@]}" -N -e "
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA='$SOURCE_DB'
  AND TABLE_NAME LIKE 'fin\\\\_%'
ORDER BY TABLE_NAME;
")"

if [[ -z "${tables// }" ]]; then
  echo "    No fin_* tables left on $SOURCE_DB (already migrated?)."
else
  while IFS= read -r table; do
    [[ -z "$table" ]] && continue
    exists="$("${mysql_cmd[@]}" -N -e "
      SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA='$TARGET_DB' AND TABLE_NAME='$table';
    ")"
    if [[ "$exists" == "1" ]]; then
      echo "    skip $table (already on $TARGET_DB)"
      continue
    fi
    echo "    move $table"
    "${mysql_cmd[@]}" -e "RENAME TABLE \`$SOURCE_DB\`.\`$table\` TO \`$TARGET_DB\`.\`$table\`;"
  done <<< "$tables"
fi

echo "==> Ensuring Laravel infra tables on $TARGET_DB"
for t in migrations sessions cache cache_locks sp_queue_jobs sp_job_batches sp_failed_jobs \
         jobs job_batches failed_jobs oauth_access_tokens oauth_auth_codes oauth_clients \
         oauth_device_codes oauth_refresh_tokens personal_access_tokens password_reset_tokens users; do
  "${mysql_cmd[@]}" -e "
    CREATE TABLE IF NOT EXISTS \`$TARGET_DB\`.\`$t\` LIKE \`$SOURCE_DB\`.\`$t\`;
  " 2>/dev/null || true
done

"${mysql_cmd[@]}" -e "
INSERT IGNORE INTO \`$TARGET_DB\`.migrations (id, migration, batch)
SELECT id, migration, batch FROM \`$SOURCE_DB\`.migrations
WHERE migration LIKE '%fin_%'
   OR migration LIKE '%create_fin_%'
   OR migration LIKE '%finance%';

DELETE FROM \`$SOURCE_DB\`.migrations
WHERE migration LIKE '%fin_%'
   OR migration LIKE '%create_fin_%';
" 2>/dev/null || true

point_env_at_finance() {
  local file="$1"
  [[ -f "$file" ]] || return 0
  if grep -q '^DB_DATABASE=' "$file"; then
    sed -i.bak "s/^DB_DATABASE=.*/DB_DATABASE=$TARGET_DB/" "$file"
  else
    echo "DB_DATABASE=$TARGET_DB" >> "$file"
  fi
  # Ensure MySQL for the API backend (root .env may still be sqlite for legacy).
  if [[ "$file" == *"/backend/.env" ]]; then
    if grep -q '^DB_CONNECTION=' "$file"; then
      sed -i.bak "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" "$file"
    else
      echo "DB_CONNECTION=mysql" >> "$file"
    fi
  fi
}

echo "==> Point Finance env at $TARGET_DB"
point_env_at_finance "$ROOT/setup.env"
point_env_at_finance "$ROOT/backend/.env"
point_env_at_finance "$ROOT/.env"

echo "==> Done. Finance tables are on $TARGET_DB; staff has no fin_* tables."
echo "    Restart php-fpm / clear config: cd $ROOT/backend && php artisan config:clear"
