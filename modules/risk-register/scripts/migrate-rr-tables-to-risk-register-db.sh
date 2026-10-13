#!/usr/bin/env bash
# Move Risk Register rr_* tables (with data) from the staff DB into risk_register.
# Safe to re-run: skips tables already present in risk_register.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAFF_ROOT="$(cd "$ROOT/../.." && pwd)"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-${DB_USERNAME:-root}}"
DB_PASS="${DB_PASS:-${DB_PASSWORD:-}}"
SOURCE_DB="${SOURCE_DB:-staff}"
TARGET_DB="${TARGET_DB:-risk_register}"

mysql_cmd=(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER")
if [[ -n "$DB_PASS" ]]; then
  mysql_cmd+=(-p"$DB_PASS")
fi

echo "==> Ensuring database $TARGET_DB exists"
"${mysql_cmd[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$TARGET_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> Copying finance-owned settings keys to $SOURCE_DB.fin_settings (if source rr_settings still exists)"
"${mysql_cmd[@]}" <<SQL
CREATE TABLE IF NOT EXISTS \`$SOURCE_DB\`.fin_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  \`key\` VARCHAR(191) NOT NULL,
  \`value\` LONGTEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY fin_settings_key_unique (\`key\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO \`$SOURCE_DB\`.fin_settings (\`key\`, \`value\`, created_at, updated_at)
SELECT s.\`key\`, s.\`value\`, s.created_at, s.updated_at
FROM \`$SOURCE_DB\`.rr_settings s
WHERE EXISTS (
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA='$SOURCE_DB' AND TABLE_NAME='rr_settings'
)
AND (
  s.\`key\` LIKE 'finance_%'
  OR s.\`key\` LIKE 'apm_%'
  OR s.\`key\` IN (
    'staff_api_base_url','staff_api_email','staff_api_password','staff_api_token',
    'email_from_address','email_from_name','mail_mailer','use_exchange_email',
    'critical_alert_emails','critical_alert_enabled','app_log_telemetry_enabled'
  )
)
ON DUPLICATE KEY UPDATE
  \`value\` = VALUES(\`value\`),
  updated_at = VALUES(updated_at);
SQL

echo "==> Renaming rr_* tables from $SOURCE_DB → $TARGET_DB"
tables="$("${mysql_cmd[@]}" -N -e "
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA='$SOURCE_DB'
  AND TABLE_NAME LIKE 'rr\\\\_%'
  AND TABLE_NAME NOT LIKE 'rrt%'
ORDER BY TABLE_NAME;
")"

if [[ -z "${tables// }" ]]; then
  echo "    No rr_* tables left on $SOURCE_DB (already migrated?)."
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
for t in migrations sessions cache cache_locks sp_queue_jobs sp_job_batches sp_failed_jobs; do
  "${mysql_cmd[@]}" -e "
    CREATE TABLE IF NOT EXISTS \`$TARGET_DB\`.\`$t\` LIKE \`$SOURCE_DB\`.\`$t\`;
  " 2>/dev/null || true
done

"${mysql_cmd[@]}" -e "
INSERT IGNORE INTO \`$TARGET_DB\`.migrations (id, migration, batch)
SELECT id, migration, batch FROM \`$SOURCE_DB\`.migrations
WHERE migration LIKE '%rr_%'
   OR migration LIKE '%rating_band%'
   OR migration LIKE '%oio_fields_to_rr_%';

DELETE FROM \`$SOURCE_DB\`.migrations
WHERE migration LIKE '%rr_%'
   OR migration LIKE '%rating_band%'
   OR migration LIKE '%oio_fields_to_rr_%';
" 2>/dev/null || true

echo "==> Point Risk Register env at $TARGET_DB"
if [[ -f "$ROOT/setup.env" ]]; then
  if grep -q '^DB_DATABASE=' "$ROOT/setup.env"; then
    sed -i.bak "s/^DB_DATABASE=.*/DB_DATABASE=$TARGET_DB/" "$ROOT/setup.env"
  else
    echo "DB_DATABASE=$TARGET_DB" >> "$ROOT/setup.env"
  fi
fi
if [[ -f "$ROOT/backend/.env" ]]; then
  if grep -q '^DB_DATABASE=' "$ROOT/backend/.env"; then
    sed -i.bak "s/^DB_DATABASE=.*/DB_DATABASE=$TARGET_DB/" "$ROOT/backend/.env"
  else
    echo "DB_DATABASE=$TARGET_DB" >> "$ROOT/backend/.env"
  fi
fi

echo "==> Done. Risk Register tables are on $TARGET_DB; staff retains rrt* and fin_settings."
echo "    Restart php-fpm / clear config: cd $ROOT/backend && php artisan config:clear"
