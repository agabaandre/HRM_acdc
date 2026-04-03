-- CodeIgniter Staff app: run once on the database that holds the `user` table (same DB as DB_NAME).
-- Enables per-user email+password login (Staff login + APM API). Default: off (0).

ALTER TABLE `user`
    ADD COLUMN `allow_email_login` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = may use email+password; Microsoft SSO unaffected';
