-- MySQL dump 10.13  Distrib 9.2.0, for macos15.2 (arm64)
--
-- Host: 127.0.0.1    Database: staff
-- ------------------------------------------------------
-- Server version	9.2.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `access_sessions`
--

DROP TABLE IF EXISTS `access_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `access_sessions` (
  `id` varchar(40) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int unsigned NOT NULL DEFAULT '0',
  `data` blob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ci_sessions_timestamp` (`timestamp`),
  KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `au_values`
--

DROP TABLE IF EXISTS `au_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `au_values` (
  `id` int NOT NULL AUTO_INCREMENT,
  `description` text NOT NULL,
  `annotation` text NOT NULL,
  `score_5` text NOT NULL,
  `score_4` text NOT NULL,
  `score_3` text NOT NULL,
  `score_2` text NOT NULL,
  `score_1` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cbp_modules`
--

DROP TABLE IF EXISTS `cbp_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cbp_modules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stable key, e.g. staff_portal, approvals_management',
  `system_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `base_url` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Relative CI path, apm segment, or empty for finance resolver',
  `base_url_development` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Finance app base URL for local/dev hosts',
  `base_url_production` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Finance path or URL in production; empty = same host /finance',
  `icon_class` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-th' COMMENT 'Font Awesome icon without fas prefix, e.g. fa-users',
  `permission_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Permission id as stored in session, e.g. 84',
  `uses_staff_portal_token` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = append Staff portal session token (APM/Finance style)',
  `is_production` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 = visible only to users with role_id 10 (admins), plus permission',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_apm_menu` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = show link in APM primary navigation',
  `alternate_base_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional CI path when user role matches alternate_for_role_id',
  `alternate_for_role_id` int unsigned DEFAULT NULL,
  `target_resolver` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'codeigniter' COMMENT 'codeigniter | staff_app_token | finance_host',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cbp_modules_module_key` (`module_key`),
  KEY `idx_cbp_modules_enabled_sort` (`is_enabled`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contract_types`
--

DROP TABLE IF EXISTS `contract_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_types` (
  `contract_type_id` int NOT NULL AUTO_INCREMENT,
  `contract_type` varchar(50) NOT NULL,
  PRIMARY KEY (`contract_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contracting_institutions`
--

DROP TABLE IF EXISTS `contracting_institutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracting_institutions` (
  `contracting_institution_id` int NOT NULL AUTO_INCREMENT,
  `contracting_institution` varchar(100) NOT NULL,
  PRIMARY KEY (`contracting_institution_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `directorates`
--

DROP TABLE IF EXISTS `directorates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `directorates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `director_id` int DEFAULT NULL COMMENT 'Director staff_id (staff.staff_id)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `directorates_director_id_index` (`director_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `divisions`
--

DROP TABLE IF EXISTS `divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `divisions` (
  `division_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `division_name` varchar(150) NOT NULL,
  `division_short_name` varchar(100) DEFAULT NULL,
  `division_head` int NOT NULL,
  `focal_person` int NOT NULL,
  `admin_assistant` int NOT NULL,
  `finance_officer` int NOT NULL,
  `directorate_id` int DEFAULT NULL,
  `head_oic_id` int DEFAULT NULL,
  `head_oic_start_date` date DEFAULT NULL,
  `head_oic_end_date` date DEFAULT NULL,
  `director_id` int DEFAULT NULL,
  `director_oic_id` int DEFAULT NULL,
  `director_oic_start_date` date DEFAULT NULL,
  `director_oic_end_date` date DEFAULT NULL,
  `category` enum('Programs','Operations','Other','') NOT NULL DEFAULT 'Other',
  `is_active` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`division_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `duty_stations`
--

DROP TABLE IF EXISTS `duty_stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `duty_stations` (
  `duty_station_id` int NOT NULL AUTO_INCREMENT,
  `duty_station_name` varchar(100) NOT NULL,
  `country` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `city` varchar(100) NOT NULL,
  PRIMARY KEY (`duty_station_id`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_notifications`
--

DROP TABLE IF EXISTS `email_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entry_id` varchar(150) NOT NULL,
  `staff_id` int NOT NULL,
  `subject` varchar(100) NOT NULL,
  `email_to` varchar(200) NOT NULL,
  `body` mediumtext NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `end_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `next_dispatch` datetime DEFAULT NULL,
  `trigger` varchar(200) NOT NULL COMMENT 'staff or system',
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_id` (`entry_id`),
  UNIQUE KEY `staff_id` (`staff_id`,`subject`,`end_date`),
  KEY `entry_id_2` (`entry_id`)
) ENGINE=MyISAM AUTO_INCREMENT=41195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `emails`
--

DROP TABLE IF EXISTS `emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `emails` (
  `sapno` int NOT NULL,
  `title` varchar(3) DEFAULT NULL,
  `fname` varchar(14) DEFAULT NULL,
  `lname` varchar(12) DEFAULT NULL,
  `oname` varchar(16) DEFAULT NULL,
  `email` varchar(27) DEFAULT NULL,
  PRIMARY KEY (`sapno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `emailstatus`
--

DROP TABLE IF EXISTS `emailstatus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `emailstatus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `disabled_at` datetime NOT NULL,
  `eanbled_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `funders`
--

DROP TABLE IF EXISTS `funders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `funders` (
  `funder_id` int NOT NULL AUTO_INCREMENT,
  `funder` varchar(100) NOT NULL,
  PRIMARY KEY (`funder_id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grades`
--

DROP TABLE IF EXISTS `grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grades` (
  `grade_id` int NOT NULL AUTO_INCREMENT,
  `grade` varchar(50) NOT NULL,
  PRIMARY KEY (`grade_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_permissions`
--

DROP TABLE IF EXISTS `group_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id` (`id`),
  KEY `group_id` (`group_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1065 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `job_id` int NOT NULL AUTO_INCREMENT,
  `job_name` varchar(200) NOT NULL,
  PRIMARY KEY (`job_id`)
) ENGINE=InnoDB AUTO_INCREMENT=425 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs_acting`
--

DROP TABLE IF EXISTS `jobs_acting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs_acting` (
  `job_acting_id` int NOT NULL AUTO_INCREMENT,
  `job_acting` varchar(300) NOT NULL,
  PRIMARY KEY (`job_acting_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kin_relationship_types`
--

DROP TABLE IF EXISTS `kin_relationship_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kin_relationship_types` (
  `kin_relationship_id` int NOT NULL AUTO_INCREMENT,
  `relationship_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`kin_relationship_id`),
  KEY `idx_kin_rel_active_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_types` (
  `leave_id` int NOT NULL AUTO_INCREMENT,
  `leave_name` varchar(100) NOT NULL,
  `leave_days` int NOT NULL,
  `is_accrued` int NOT NULL DEFAULT '0',
  `accrual_rate` double NOT NULL,
  PRIMARY KEY (`leave_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nationalities`
--

DROP TABLE IF EXISTS `nationalities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nationalities` (
  `nationality_id` int NOT NULL AUTO_INCREMENT,
  `nationality` varchar(50) NOT NULL,
  `nationality_name` varchar(50) DEFAULT NULL,
  `continent` varchar(50) NOT NULL,
  `region_id` int NOT NULL,
  `iso2` varchar(2) DEFAULT NULL,
  `iso3` varchar(3) DEFAULT NULL,
  PRIMARY KEY (`nationality_id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` text,
  `definition` text,
  `module` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ppa_approval_trail`
--

DROP TABLE IF EXISTS `ppa_approval_trail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ppa_approval_trail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entry_id` varchar(100) NOT NULL,
  `staff_id` int NOT NULL,
  `comments` text,
  `action` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=865 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ppa_approval_trail_end_term`
--

DROP TABLE IF EXISTS `ppa_approval_trail_end_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ppa_approval_trail_end_term` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entry_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `staff_id` int NOT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_entry_id` (`entry_id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=599 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ppa_approval_trail_midterm`
--

DROP TABLE IF EXISTS `ppa_approval_trail_midterm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ppa_approval_trail_midterm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entry_id` varchar(100) NOT NULL,
  `staff_id` int NOT NULL,
  `comments` text,
  `action` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(20) NOT NULL DEFAULT 'PPA',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=781 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ppa_configs`
--

DROP TABLE IF EXISTS `ppa_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ppa_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `allow_supervisor_return` int NOT NULL DEFAULT '1',
  `allow_supervisor_comments` int NOT NULL DEFAULT '1',
  `allow_supervisor_ppa_edit` int NOT NULL DEFAULT '1',
  `allow_employee_comments` int NOT NULL DEFAULT '1',
  `ppa_start` date NOT NULL,
  `ppa_deadline` date DEFAULT NULL,
  `mid_term_deadline` date DEFAULT NULL,
  `mid_term_start` date NOT NULL,
  `end_term_start` date NOT NULL,
  `end_term_deadline` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ppa_end_term_review`
--

DROP TABLE IF EXISTS `ppa_end_term_review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ppa_end_term_review` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ppa_id` int NOT NULL,
  `objective_id` varchar(100) NOT NULL,
  `weight` int NOT NULL,
  `self_appraisal` int NOT NULL,
  `appraisers_rating` int NOT NULL,
  `comments_on_achievement` text NOT NULL,
  `comments_on_failed_achievement` text NOT NULL,
  `au_values_rating` text NOT NULL,
  `comments_on_pdp` text NOT NULL,
  `overall_rating` text NOT NULL,
  `sign_off_supervisor` varchar(100) NOT NULL,
  `supervisor_sign_date` datetime NOT NULL,
  `status` int NOT NULL,
  `sign_off_staff` varchar(100) NOT NULL,
  `staff_sign_date` datetime NOT NULL,
  `staff_agree_with_supervisor` varchar(100) NOT NULL,
  `staff_disagree_with_supervisor` varchar(100) NOT NULL,
  `sign_off_supervisor2` int NOT NULL,
  `supervisor2_sign_date` datetime NOT NULL,
  `supervisor2_agree_with_supervisor` varchar(100) NOT NULL,
  `supervisor2_disagree_with_supervisor` varchar(100) NOT NULL,
  `callibarated_rating_application` int NOT NULL DEFAULT '0',
  `calibrated_rating_status` text,
  `calibrated_rating_chair_sign_off` varchar(100) NOT NULL,
  `calibrated_rating_chair_sign_off_date` datetime NOT NULL,
  `calibrated_rating_hr_sign_off` varchar(19) NOT NULL,
  `calibrated_rating_hr_sign_off_date` datetime NOT NULL,
  `calibrated_rating_panel1_sign_off` varchar(10) NOT NULL,
  `calibrated_rating_panel1_sign_off_date` datetime NOT NULL,
  `calibrated_rating_panel2_sign_off` varchar(10) NOT NULL,
  `calibrated_rating_panel2_sign_off_date` datetime NOT NULL,
  `calibrated_rating_panel3_sign_off` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ppa_entries`
--

DROP TABLE IF EXISTS `ppa_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ppa_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `staff_contract_id` int DEFAULT NULL,
  `performance_period` varchar(50) NOT NULL,
  `entry_id` varchar(100) NOT NULL,
  `supervisor_id` int DEFAULT NULL,
  `supervisor2_id` int DEFAULT NULL,
  `objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `training_recommended` enum('Yes','No') DEFAULT 'No',
  `required_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `training_contributions` text,
  `recommended_trainings` text,
  `recommended_trainings_details` text,
  `staff_sign_off` tinyint(1) DEFAULT '0',
  `draft_status` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `endterm_objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `endterm_competency` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `endterm_achievements` text,
  `endterm_non_achievements` text,
  `endterm_comments` text,
  `endterm_training_review` text,
  `endterm_recommended_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `endterm_training_contributions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `endterm_recommended_trainings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `endterm_recommended_trainings_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `endterm_rating_by` int DEFAULT NULL,
  `endterm_sign_off` tinyint(1) DEFAULT '0',
  `endterm_draft_status` tinyint(1) DEFAULT '1',
  `endterm_supervisor_1` int DEFAULT NULL,
  `endterm_supervisor_2` int DEFAULT NULL,
  `endterm_created_at` datetime DEFAULT NULL,
  `endterm_updated_at` datetime DEFAULT NULL,
  `endterm_supervisor1_discussion_confirmed` tinyint(1) DEFAULT '0',
  `endterm_staff_discussion_confirmed` tinyint(1) DEFAULT '0',
  `endterm_staff_rating_acceptance` tinyint(1) DEFAULT NULL,
  `endterm_staff_consent_at` datetime DEFAULT NULL,
  `endterm_supervisor2_agreement` tinyint(1) DEFAULT NULL,
  `overall_end_term_status` varchar(50) DEFAULT NULL,
  `midterm_objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `midterm_competency` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `midterm_achievements` text,
  `midterm_non_achievements` text,
  `midterm_comments` text,
  `midterm_training_review` text,
  `midterm_recommended_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `midterm_training_contributions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `midterm_recommended_trainings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `midterm_recommended_trainings_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `midterm_rating_by` int DEFAULT NULL,
  `midterm_sign_off` tinyint(1) DEFAULT '0',
  `midterm_draft_status` tinyint(1) DEFAULT '1',
  `midterm_created_at` datetime DEFAULT NULL,
  `midterm_updated_at` datetime DEFAULT NULL,
  `midterm_supervisor_1` int DEFAULT NULL,
  `midterm_supervisor_2` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_id` (`entry_id`),
  UNIQUE KEY `staff_id` (`staff_id`,`performance_period`)
) ENGINE=InnoDB AUTO_INCREMENT=379 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ppa_mid_term_review`
--

DROP TABLE IF EXISTS `ppa_mid_term_review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ppa_mid_term_review` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ppa_id` int NOT NULL,
  `objective_id` varchar(100) NOT NULL,
  `weight` int NOT NULL,
  `self_appraisal` int NOT NULL,
  `appraisers_rating` int NOT NULL,
  `comments_on_achievement` text NOT NULL,
  `comments_on_failed_achievement` text NOT NULL,
  `au_values_rating` text NOT NULL,
  `comments_on_pdp` text NOT NULL,
  `any_additional_training` varchar(100) NOT NULL,
  `skill_areas` text NOT NULL,
  `training_areas` text NOT NULL,
  `courses` text NOT NULL,
  `higly_recommended` text NOT NULL,
  `date_time` datetime NOT NULL,
  `status` int NOT NULL,
  `sign_off_staff` varchar(100) NOT NULL,
  `sign_off_supervisor` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `regions`
--

DROP TABLE IF EXISTS `regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `regions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `region_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `activity_id` int NOT NULL,
  `report_date` date NOT NULL,
  `description` text NOT NULL,
  `supervisor_comment` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  UNIQUE KEY `activity_id` (`activity_id`,`report_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rrt`
--

DROP TABLE IF EXISTS `rrt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rrt` (
  `rrt_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `oname` varchar(50) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(11) NOT NULL,
  `nationality_id` varchar(50) NOT NULL,
  `initiation_date` date NOT NULL,
  `tel_1` varchar(30) NOT NULL,
  `tel_2` varchar(30) NOT NULL,
  `whatsapp` varchar(30) NOT NULL,
  `work_email` varchar(50) NOT NULL,
  `private_email` varchar(50) NOT NULL,
  `physical_location` varchar(500) NOT NULL,
  PRIMARY KEY (`rrt_id`)
) ENGINE=InnoDB AUTO_INCREMENT=175 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rrt_contracts`
--

DROP TABLE IF EXISTS `rrt_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rrt_contracts` (
  `rrt_contract_id` int NOT NULL AUTO_INCREMENT,
  `rrt_id` int NOT NULL,
  `job_id` int NOT NULL,
  `grade_id` int NOT NULL,
  `duty_station_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status_id` int NOT NULL DEFAULT '1',
  `file_name` varchar(200) NOT NULL,
  `contracting_institution_id` int NOT NULL,
  `funder_id` int NOT NULL,
  `comments` varchar(200) NOT NULL,
  PRIMARY KEY (`rrt_contract_id`)
) ENGINE=InnoDB AUTO_INCREMENT=175 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `separated`
--

DROP TABLE IF EXISTS `separated`;
/*!50001 DROP VIEW IF EXISTS `separated`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `separated` AS SELECT 
 1 AS `status_id`,
 1 AS `status`,
 1 AS `duty_station_id`,
 1 AS `contract_type_id`,
 1 AS `division_id`,
 1 AS `nationality_id`,
 1 AS `staff_id`,
 1 AS `title`,
 1 AS `fname`,
 1 AS `lname`,
 1 AS `oname`,
 1 AS `grade_id`,
 1 AS `end_date`,
 1 AS `grade`,
 1 AS `date_of_birth`,
 1 AS `gender`,
 1 AS `job_id`,
 1 AS `job_name`,
 1 AS `job_acting_id`,
 1 AS `job_acting`,
 1 AS `contracting_institution`,
 1 AS `contracting_institution_id`,
 1 AS `contract_type`,
 1 AS `nationality`,
 1 AS `division_name`,
 1 AS `first_supervisor`,
 1 AS `second_supervisor`,
 1 AS `duty_station_name`,
 1 AS `initiation_date`,
 1 AS `tel_1`,
 1 AS `tel_2`,
 1 AS `whatsapp`,
 1 AS `work_email`,
 1 AS `private_email`,
 1 AS `physical_location`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `setting`
--

DROP TABLE IF EXISTS `setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `setting` (
  `id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `site_name` varchar(100) NOT NULL,
  `seo_keywords` text NOT NULL,
  `site_description` text NOT NULL,
  `contracts_status_copied_emails` text NOT NULL,
  `staff_multistep` int NOT NULL DEFAULT '0',
  `ppa_multi` int NOT NULL DEFAULT '0',
  `address` text,
  `email` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `language` varchar(100) DEFAULT NULL,
  `timezone` varchar(150) NOT NULL,
  `default_password` varchar(20) DEFAULT 'africacdc.org',
  `values_version` int NOT NULL,
  `mail_host` varchar(100) NOT NULL,
  `mail_username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `mail_smtp_port` varchar(100) NOT NULL,
  `current_period` varchar(4) NOT NULL DEFAULT 'Q1',
  `allow_form_login` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `staff_id` int NOT NULL AUTO_INCREMENT,
  `SAPNO` varchar(20) NOT NULL,
  `photo` varchar(200) NOT NULL,
  `signature` varchar(100) NOT NULL,
  `passport_biodata_page` varchar(255) DEFAULT NULL,
  `residential_address_duty_station` text,
  `number_of_dependants` int DEFAULT NULL,
  `next_of_kin_json` longtext,
  `title` varchar(50) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `oname` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` varchar(11) NOT NULL,
  `nationality_id` int NOT NULL,
  `initiation_date` date NOT NULL,
  `tel_1` varchar(30) NOT NULL,
  `tel_2` varchar(30) NOT NULL,
  `whatsapp` varchar(30) NOT NULL,
  `work_email` varchar(50) DEFAULT NULL,
  `email_status` int DEFAULT '1',
  `email_disabled_at` datetime DEFAULT NULL,
  `email_disabled_by` int DEFAULT NULL,
  `helpdesk_agent_at` datetime DEFAULT NULL COMMENT 'When this staff was designated a Helpdesk agent (NULL = not an agent).',
  `private_email` varchar(50) NOT NULL,
  `physical_location` varchar(500) NOT NULL,
  `flag` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`staff_id`),
  KEY `staff_helpdesk_agent_at_index` (`helpdesk_agent_at`)
) ENGINE=InnoDB AUTO_INCREMENT=673 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_contracts`
--

DROP TABLE IF EXISTS `staff_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_contracts` (
  `staff_contract_id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `job_id` int NOT NULL,
  `job_acting_id` int DEFAULT NULL,
  `grade_id` varchar(10) NOT NULL,
  `contracting_institution_id` int NOT NULL,
  `funder_id` int NOT NULL DEFAULT '3',
  `first_supervisor` int NOT NULL DEFAULT '0',
  `second_supervisor` int DEFAULT NULL,
  `contract_type_id` int NOT NULL,
  `duty_station_id` int NOT NULL,
  `division_id` int NOT NULL,
  `other_associated_divisions` json DEFAULT NULL,
  `unit_id` int NOT NULL DEFAULT '1',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status_id` int DEFAULT '1',
  `file_name` varchar(500) DEFAULT NULL,
  `comments` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`staff_contract_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1815 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_contracts_status_dis`
--

DROP TABLE IF EXISTS `staff_contracts_status_dis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_contracts_status_dis` (
  `staff_contract_id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `job_id` int NOT NULL,
  `job_acting_id` int NOT NULL DEFAULT '20',
  `grade_id` varchar(10) NOT NULL,
  `contracting_institution_id` int NOT NULL,
  `funder_id` int NOT NULL DEFAULT '3',
  `first_supervisor` int NOT NULL DEFAULT '0',
  `second_supervisor` int DEFAULT NULL,
  `contract_type_id` int NOT NULL,
  `duty_station_id` int NOT NULL,
  `division_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status_id` int DEFAULT '1',
  `file_name` varchar(500) DEFAULT NULL,
  `comments` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`staff_contract_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1322 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_import`
--

DROP TABLE IF EXISTS `staff_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_import` (
  `staff_id` int NOT NULL,
  `SAPNO` varchar(20) NOT NULL,
  `photo` varchar(200) NOT NULL,
  `signature` varchar(100) NOT NULL,
  `title` varchar(50) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `oname` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` varchar(11) NOT NULL,
  `nationality_id` int NOT NULL,
  `initiation_date` date NOT NULL,
  `tel_1` varchar(30) NOT NULL,
  `tel_2` varchar(30) NOT NULL,
  `whatsapp` varchar(30) NOT NULL,
  `work_email` varchar(50) DEFAULT NULL,
  `email_status` int DEFAULT '1',
  `email_disabled_at` datetime DEFAULT NULL,
  `email_disabled_by` int DEFAULT NULL,
  `private_email` varchar(50) NOT NULL,
  `physical_location` varchar(500) NOT NULL,
  `flag` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_leave`
--

DROP TABLE IF EXISTS `staff_leave`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_leave` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `start_date` date NOT NULL,
  `leave_id` int NOT NULL,
  `end_date` date NOT NULL,
  `email_leave` varchar(200) NOT NULL,
  `mobile_leave` varchar(200) NOT NULL,
  `supporting_staff` varchar(100) NOT NULL,
  `requested_days` int NOT NULL,
  `leave_balance` int NOT NULL,
  `remarks` text,
  `contract_id` int NOT NULL,
  `supervisor_id` int NOT NULL,
  `supervisor2_id` int NOT NULL,
  `division_head` int NOT NULL,
  `reject_reason` text,
  `supporting_documentation` text,
  `approval_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `approval_status1` varchar(20) NOT NULL DEFAULT 'Pending',
  `approval_status2` varchar(20) NOT NULL DEFAULT 'Pending',
  `approval_status3` varchar(20) NOT NULL DEFAULT 'Pending',
  `overall_status` varchar(10) NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `email_leave` (`email_leave`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `status`
--

DROP TABLE IF EXISTS `status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status` (
  `status_id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(50) NOT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `training_categories`
--

DROP TABLE IF EXISTS `training_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `training_skills`
--

DROP TABLE IF EXISTS `training_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `training_skills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `skill` text NOT NULL,
  `category_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `unit_id` int NOT NULL AUTO_INCREMENT,
  `division_id` int DEFAULT NULL,
  `unit_name` varchar(255) NOT NULL,
  `staff_id` int NOT NULL COMMENT 'Unit head (staff_id)',
  PRIMARY KEY (`unit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `password` varchar(255) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `role` varchar(255) NOT NULL,
  `auth_staff_id` int NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `changed` date DEFAULT NULL,
  `isChanged` int DEFAULT '0',
  `photo` varchar(200) DEFAULT 'author.png',
  `signature` varchar(100) DEFAULT NULL,
  `is_approved` int NOT NULL DEFAULT '0',
  `is_verfied` int NOT NULL DEFAULT '0',
  `allow_email_login` int NOT NULL DEFAULT '0',
  `langauge` varchar(100) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `staff_id` (`auth_staff_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2490 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_groups`
--

DROP TABLE IF EXISTS `user_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_name` text,
  PRIMARY KEY (`id`),
  KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_log`
--

DROP TABLE IF EXISTS `user_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_log` (
  `user_log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `date_loged_in` date NOT NULL,
  `time_loged_in` time NOT NULL,
  `action` text NOT NULL,
  `email` varchar(40) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`user_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_logs`
--

DROP TABLE IF EXISTS `user_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` text NOT NULL,
  `http_method` varchar(12) DEFAULT NULL COMMENT 'GET, POST, PUT, PATCH, DELETE',
  `request_uri` varchar(512) DEFAULT NULL,
  `event_type` varchar(32) DEFAULT NULL COMMENT 'access, create, update, delete, record_audit',
  `target_table` varchar(191) DEFAULT NULL,
  `target_id` varchar(64) DEFAULT NULL,
  `old_values` longtext COMMENT 'JSON snapshot for revert',
  `new_values` longtext COMMENT 'JSON snapshot',
  `reverted_at` datetime DEFAULT NULL,
  `reverted_by_user_id` int unsigned DEFAULT NULL,
  `audit_prev_hash` char(64) NOT NULL DEFAULT '0000000000000000000000000000000000000000000000000000000000000000' COMMENT 'SHA-256 hex of previous row chain',
  `audit_row_hash` char(64) DEFAULT NULL COMMENT 'SHA-256 hex chain: sha256(prev + LF + canonical)',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1131113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id` (`id`),
  KEY `group_id` (`user_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work_plan_weekly_tasks`
--

DROP TABLE IF EXISTS `work_plan_weekly_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_plan_weekly_tasks` (
  `activity_id` int NOT NULL AUTO_INCREMENT,
  `staff_id` text NOT NULL,
  `work_planner_tasks_id` int NOT NULL,
  `activity_name` varchar(255) NOT NULL,
  `week` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `comments` text NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int NOT NULL,
  `updated_by` int NOT NULL,
  PRIMARY KEY (`activity_id`),
  KEY `deliverable_id` (`work_planner_tasks_id`)
) ENGINE=InnoDB AUTO_INCREMENT=203 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work_planner_tasks`
--

DROP TABLE IF EXISTS `work_planner_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_planner_tasks` (
  `activity_id` int NOT NULL AUTO_INCREMENT,
  `created_by` int NOT NULL,
  `workplan_id` int NOT NULL,
  `activity_name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `priority` enum('High','Medium','Low','') NOT NULL,
  `comments` text NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`activity_id`),
  KEY `staff_id` (`created_by`),
  KEY `deliverable_id` (`workplan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `workplan_tasks`
--

DROP TABLE IF EXISTS `workplan_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workplan_tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `division_id` int NOT NULL,
  `intermediate_outcome` text,
  `broad_activity` text,
  `output_indicator` text,
  `cumulative_target` varchar(255) DEFAULT NULL,
  `activity_name` text,
  `year` varchar(10) NOT NULL DEFAULT '2025',
  `has_budget` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `separated`
--

/*!50001 DROP VIEW IF EXISTS `separated`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `separated` AS select 1 AS `status_id`,1 AS `status`,1 AS `duty_station_id`,1 AS `contract_type_id`,1 AS `division_id`,1 AS `nationality_id`,1 AS `staff_id`,1 AS `title`,1 AS `fname`,1 AS `lname`,1 AS `oname`,1 AS `grade_id`,1 AS `end_date`,1 AS `grade`,1 AS `date_of_birth`,1 AS `gender`,1 AS `job_id`,1 AS `job_name`,1 AS `job_acting_id`,1 AS `job_acting`,1 AS `contracting_institution`,1 AS `contracting_institution_id`,1 AS `contract_type`,1 AS `nationality`,1 AS `division_name`,1 AS `first_supervisor`,1 AS `second_supervisor`,1 AS `duty_station_name`,1 AS `initiation_date`,1 AS `tel_1`,1 AS `tel_2`,1 AS `whatsapp`,1 AS `work_email`,1 AS `private_email`,1 AS `physical_location` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-04  1:26:42
