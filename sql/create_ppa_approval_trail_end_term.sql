-- phpMyAdmin SQL Dump
-- Table structure for table `ppa_approval_trail_end_term`
--
-- This table stores the approval trail for end-term PPA reviews
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Table structure for table `ppa_approval_trail_end_term`
--

CREATE TABLE IF NOT EXISTS `ppa_approval_trail_end_term` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `entry_id` VARCHAR(255) NOT NULL,
  `staff_id` INT(11) NOT NULL,
  `comments` TEXT NULL,
  `action` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `type` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_entry_id` (`entry_id`),
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

