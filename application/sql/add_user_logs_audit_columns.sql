-- Staff portal (CI3): extend user_logs for HTTP method, URI, structured old/new JSON, and revert tracking.
-- Optional next step (ISO/IEC 27001–oriented tamper-evidence): application/sql/add_user_logs_iso_audit_columns.sql + application/config/audit_log.php
-- Run once on the staff database. If a column already exists, remove that line and re-run.

ALTER TABLE `user_logs`
  ADD COLUMN `http_method` VARCHAR(12) NULL DEFAULT NULL COMMENT 'GET, POST, PUT, PATCH, DELETE' AFTER `action`,
  ADD COLUMN `request_uri` VARCHAR(512) NULL DEFAULT NULL AFTER `http_method`,
  ADD COLUMN `event_type` VARCHAR(32) NULL DEFAULT NULL COMMENT 'access, create, update, delete, record_audit' AFTER `request_uri`,
  ADD COLUMN `target_table` VARCHAR(191) NULL DEFAULT NULL AFTER `event_type`,
  ADD COLUMN `target_id` VARCHAR(64) NULL DEFAULT NULL AFTER `target_table`,
  ADD COLUMN `old_values` LONGTEXT NULL COMMENT 'JSON snapshot for revert' AFTER `target_id`,
  ADD COLUMN `new_values` LONGTEXT NULL COMMENT 'JSON snapshot' AFTER `old_values`,
  ADD COLUMN `reverted_at` DATETIME NULL DEFAULT NULL AFTER `new_values`,
  ADD COLUMN `reverted_by_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `reverted_at`;

-- Optional: index for filters (ignore error if duplicate)
-- CREATE INDEX `idx_user_logs_created_event` ON `user_logs` (`created_at`, `event_type`);
-- CREATE INDEX `idx_user_logs_target` ON `user_logs` (`target_table`, `target_id`);
