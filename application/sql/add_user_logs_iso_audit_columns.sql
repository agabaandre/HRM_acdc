-- Staff portal (CI3): ISO/IEC 27001-style tamper-evidence for user_logs (hash chain).
-- Run after application/sql/add_user_logs_audit_columns.sql. Remove any line that already exists.

ALTER TABLE `user_logs`
  ADD COLUMN `audit_prev_hash` CHAR(64) NOT NULL DEFAULT '0000000000000000000000000000000000000000000000000000000000000000' COMMENT 'SHA-256 hex of previous row chain' AFTER `reverted_by_user_id`,
  ADD COLUMN `audit_row_hash` CHAR(64) NULL DEFAULT NULL COMMENT 'SHA-256 hex chain: sha256(prev + LF + canonical)' AFTER `audit_prev_hash`;
