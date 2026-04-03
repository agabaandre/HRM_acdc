-- Central Business Platform modules (Staff portal home + APM header menu).
-- Run once on the Staff (CodeIgniter) database. APM reads the same table via STAFF_DB_* when configured.

CREATE TABLE IF NOT EXISTS `cbp_modules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module_key` varchar(64) NOT NULL COMMENT 'Stable key, e.g. staff_portal, approvals_management',
  `system_name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `base_url` varchar(512) NOT NULL DEFAULT '' COMMENT 'Relative CI path, apm segment, or empty for finance resolver',
  `base_url_development` varchar(512) DEFAULT NULL COMMENT 'Finance app base URL for local/dev hosts',
  `base_url_production` varchar(512) DEFAULT NULL COMMENT 'Finance path or URL in production; empty = same host /finance',
  `icon_class` varchar(128) NOT NULL DEFAULT 'fa-th' COMMENT 'Font Awesome icon without fas prefix, e.g. fa-users',
  `permission_code` varchar(32) NOT NULL COMMENT 'Permission id as stored in session, e.g. 84',
  `uses_staff_portal_token` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = append Staff portal session token (APM/Finance style)',
  `is_production` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = visible only to users with role_id 10 (admins), plus permission',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `show_in_apm_menu` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = show link in APM primary navigation',
  `alternate_base_url` varchar(255) DEFAULT NULL COMMENT 'Optional CI path when user role matches alternate_for_role_id',
  `alternate_for_role_id` int unsigned DEFAULT NULL,
  `target_resolver` varchar(32) NOT NULL DEFAULT 'codeigniter' COMMENT 'codeigniter | staff_app_token | finance_host | external_microservice (external system in UI)',
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cbp_modules_module_key` (`module_key`),
  KEY `idx_cbp_modules_enabled_sort` (`is_enabled`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `cbp_modules` (
  `module_key`, `system_name`, `description`, `base_url`, `base_url_development`, `base_url_production`,
  `icon_class`, `permission_code`, `uses_staff_portal_token`, `is_production`, `is_enabled`, `show_in_apm_menu`,
  `alternate_base_url`, `alternate_for_role_id`, `target_resolver`, `sort_order`
) VALUES
(
  'staff_portal',
  'Staff Portal',
  'Manage staff details, contracts, appraisals and access HR services efficiently.',
  'dashboard',
  NULL,
  NULL,
  'fa-users',
  '84',
  0,
  1,
  1,
  1,
  'auth/profile',
  17,
  'codeigniter',
  10
),
(
  'approvals_management',
  'Approvals Management (APM)',
  'Tracks submissions, reviews, and approvals for travel matrices, single and special memos, change, DSA and ARF requests.',
  'apm',
  NULL,
  NULL,
  'fa-sitemap',
  '85',
  1,
  1,
  1,
  0,
  NULL,
  NULL,
  'staff_app_token',
  20
),
(
  'finance_management',
  'Finance Management',
  'Manage financial reports, invoices, budgets, transactions, and vendor information.',
  '',
  'http://localhost:3002',
  NULL,
  'fa-wallet',
  '92',
  1,
  1,
  1,
  1,
  NULL,
  NULL,
  'finance_host',
  30
);
