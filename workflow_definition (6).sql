-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 02, 2025 at 03:54 PM
-- Server version: 9.2.0
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bms_new`
--

-- --------------------------------------------------------

--
-- Table structure for table `workflow_definition`
--

CREATE TABLE `workflow_definition` (
  `id` int NOT NULL,
  `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workflow_id` int NOT NULL,
  `approval_order` int NOT NULL,
  `is_enabled` int NOT NULL DEFAULT '1',
  `memo_print_section` enum('from','to','through','others') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'through',
  `print_order` int DEFAULT NULL COMMENT 'Order in which this workflow step should appear in memo printing within its section',
  `is_division_specific` tinyint(1) NOT NULL DEFAULT '0',
  `category` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fund_type` int DEFAULT NULL,
  `division_reference_column` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `triggers_category_check` tinyint(1) NOT NULL DEFAULT '0',
  `allowed_funders` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `workflow_definition`
--

INSERT INTO `workflow_definition` (`id`, `role`, `workflow_id`, `approval_order`, `is_enabled`, `memo_print_section`, `print_order`, `is_division_specific`, `category`, `fund_type`, `division_reference_column`, `triggers_category_check`, `allowed_funders`) VALUES
(1, 'Head of Division', 1, 1, 1, 'from', 5, 1, NULL, NULL, 'division_head', 0, NULL),
(2, 'Grants Officer', 1, 3, 1, 'others', NULL, 0, NULL, 2, NULL, 0, '[2]'),
(3, 'PIU Officer', 1, 4, 1, 'others', NULL, 0, NULL, 1, NULL, 0, '[1]'),
(4, 'Finance Officer', 1, 5, 1, 'others', NULL, 1, NULL, 1, 'finance_officer', 0, '[1]'),
(5, 'Director Finance', 1, 6, 1, 'others', NULL, 0, NULL, NULL, NULL, 1, NULL),
(6, 'Head of Operations', 1, 7, 1, 'through', 4, 0, 'Operations', NULL, NULL, 0, NULL),
(7, 'Head of Programs', 1, 7, 1, 'through', 4, 0, 'Programs', NULL, NULL, 0, NULL),
(8, 'Deputy Director General', 1, 8, 1, 'through', 3, 0, NULL, NULL, NULL, 0, NULL),
(9, 'Chief of Staff and Head of Executive Office', 1, 9, 1, 'through', 2, 0, NULL, NULL, NULL, 0, NULL),
(10, 'Director General', 1, 10, 1, 'to', 1, 0, NULL, NULL, NULL, 0, NULL),
(12, 'Director', 1, 2, 1, 'others', NULL, 1, NULL, NULL, 'director_id', 1, NULL),
(13, 'Grants Officer', 2, 1, 1, 'others', NULL, 0, NULL, 2, NULL, 0, '[2]'),
(14, 'Finance Officer', 3, 1, 1, 'others', NULL, 1, NULL, NULL, 'finance_officer', 0, NULL),
(16, 'Director Administration', 3, 2, 1, 'to', NULL, 0, NULL, NULL, NULL, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `workflow_definition`
--
ALTER TABLE `workflow_definition`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_workflow_definition_workflow_enabled` (`workflow_id`,`is_enabled`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `workflow_definition`
--
ALTER TABLE `workflow_definition`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
