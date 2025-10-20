-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 20, 2025 at 01:46 PM
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
-- Database: `approval_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `approval_trails`
--

CREATE TABLE `approval_trails` (
  `id` bigint UNSIGNED NOT NULL,
  `matrix_id` bigint UNSIGNED DEFAULT NULL,
  `model_id` bigint UNSIGNED DEFAULT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `staff_id` bigint UNSIGNED NOT NULL,
  `approval_order` int NOT NULL DEFAULT '1',
  `oic_staff_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `forward_workflow_id` bigint UNSIGNED DEFAULT NULL COMMENT 'Reference to the forward workflow definition'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `approval_trails`
--

INSERT INTO `approval_trails` (`id`, `matrix_id`, `model_id`, `model_type`, `staff_id`, `approval_order`, `oic_staff_id`, `action`, `is_archived`, `remarks`, `created_at`, `updated_at`, `forward_workflow_id`) VALUES
(1, 2, 2, 'App\\Models\\Matrix', 322, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-12 11:26:59', '2025-09-25 09:01:50', NULL),
(2, NULL, 2, 'App\\Models\\NonTravelMemo', 258, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-15 19:10:09', '2025-09-15 19:10:09', 1),
(3, NULL, 1, 'App\\Models\\NonTravelMemo', 258, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-15 19:10:27', '2025-09-15 19:10:27', 1),
(5, 5, 5, 'App\\Models\\Matrix', 221, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-16 10:53:39', '2025-09-16 10:53:39', NULL),
(6, 2, 2, 'App\\Models\\Matrix', 25, 1, NULL, 'approved', 1, 'approved', '2025-09-16 14:35:16', '2025-09-25 09:01:50', 1),
(8, 2, 2, 'App\\Models\\Matrix', 522, 4, NULL, 'returned', 1, 'Dear Edouard, \r\nAs discussed, as there is not yet a medium to provide comment on each activity or review and revise together, I would have to return this Q4 matrix back to you for further revision. As it is, only one of the activities is passed from the PIU end. \r\n1. Validation workshop for ERF: The approved budget is $42,839 while the budget proposed is $70,474. \r\n2. Development of AVOHC Framework: Budget approved is $36,000 while requested budget is $46,844. \r\n3. Risk ranking grand round: Activity can not be confirmed as activity code and funding source not provided. Kindly include the activity code and funding source and ensure that the activity aligns with the approved AWPB. \r\n\r\nGenerally, and for other activities, as discussed, please review the DSA provision as appropriate. \r\n\r\nThank you very much. \r\n\r\nBest regards, \r\nMichael', '2025-09-18 10:25:40', '2025-09-25 09:01:50', 1),
(9, 9, 9, 'App\\Models\\Matrix', 192, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-18 12:18:28', '2025-09-18 12:18:28', NULL),
(10, 2, 2, 'App\\Models\\Matrix', 25, 1, NULL, 'returned', 1, 'To address the comments from PIU', '2025-09-18 13:32:39', '2025-09-25 09:01:50', 1),
(11, 4, 4, 'App\\Models\\Matrix', 86, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-18 13:58:46', '2025-09-18 13:58:46', NULL),
(12, NULL, 125, 'App\\Models\\Activity', 627, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-18 14:24:09', '2025-09-18 14:24:09', 1),
(13, NULL, 3, 'App\\Models\\NonTravelMemo', 105, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-18 15:20:44', '2025-09-18 15:20:44', 1),
(14, 4, 4, 'App\\Models\\Matrix', 24, 1, NULL, 'returned', 0, 'Abebaw to submit one activity, Zi to change single memo submission and submit the WAOH conference in the matrix', '2025-09-18 17:59:01', '2025-09-18 17:59:01', 1),
(15, NULL, 125, 'App\\Models\\Activity', 24, 1, NULL, 'returned', 0, '', '2025-09-18 17:59:58', '2025-09-18 17:59:58', 1),
(16, 7, 7, 'App\\Models\\Matrix', 30, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-19 09:45:14', '2025-09-19 09:45:14', NULL),
(17, 4, 4, 'App\\Models\\Matrix', 86, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-19 11:10:25', '2025-09-19 11:10:25', NULL),
(19, 4, 4, 'App\\Models\\Matrix', 24, 1, NULL, 'approved', 0, NULL, '2025-09-19 11:46:16', '2025-09-19 11:46:16', 1),
(20, NULL, 3, 'App\\Models\\NonTravelMemo', 24, 1, NULL, 'approved', 0, '', '2025-09-19 11:52:30', '2025-09-19 11:52:30', 1),
(21, NULL, 3, 'App\\Models\\NonTravelMemo', 327, 3, NULL, 'approved', 0, '', '2025-09-19 15:20:32', '2025-09-19 15:20:32', 1),
(22, 4, 4, 'App\\Models\\Matrix', 327, 3, NULL, 'approved', 0, NULL, '2025-09-19 15:22:52', '2025-09-19 15:22:52', 1),
(23, 2, 2, 'App\\Models\\Matrix', 322, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-19 18:43:12', '2025-09-25 09:01:50', NULL),
(24, 4, 4, 'App\\Models\\Matrix', 522, 4, NULL, 'returned', 0, 'Dear Dr Yenew, \r\n\r\nAs discussed, returning this matrix to revise few activities not aligned with the approved AWPB. \r\nThank you very much. \r\n\r\nBest regards, \r\nMichael', '2025-09-20 14:32:27', '2025-09-20 14:32:27', 1),
(25, 4, 4, 'App\\Models\\Matrix', 24, 1, NULL, 'returned', 0, 'please address comments from Michael, let\'s remove Donewell\'s activity under CEPI budget and resubmit', '2025-09-20 15:37:46', '2025-09-20 15:37:46', 1),
(26, 7, 7, 'App\\Models\\Matrix', 23, 1, NULL, 'returned', 0, 'URGENT!!!\r\n\r\nDear Colleagues,\r\n \r\nI tried to review the Q4 activity submission; there are a lot of missing sections and variables, and I wonder if the Unit Leads had reviewed it at all:\r\n \r\n- Please give background and meeting/training objectives, and expected outcomes\r\n- Budget breakdown and/or if an external indicated source of funds\r\n- Budget calculations – some missing variable- the internal participant list is missing\r\n- CPHIA participation- please don’t double-count people in different CPHIA events; rather add others to participate\r\n- CPHIA- event participation should be linked with some activity, including abstract presentation, launch of …\r\n \r\n \r\nUnit Leads, \r\n- review all the submissions with the budget balance you have- and submit by midday Monday 22 September \r\n \r\nMallion- please return all back', '2025-09-21 22:14:27', '2025-09-21 22:14:27', 1),
(27, NULL, 2, 'App\\Models\\NonTravelMemo', 23, 1, NULL, 'returned', 0, 'Who are the participants? What was their performance, and how was this course chosen?', '2025-09-21 22:19:17', '2025-09-21 22:19:17', 1),
(28, NULL, 1, 'App\\Models\\NonTravelMemo', 23, 1, NULL, 'returned', 0, 'Same as the previous? who are the participant...', '2025-09-21 22:22:45', '2025-09-21 22:22:45', 1),
(29, 5, 5, 'App\\Models\\Matrix', 492, 1, NULL, 'returned', 0, 'needs to be revised - AMR', '2025-09-22 09:55:13', '2025-09-22 09:55:13', 1),
(30, 4, 4, 'App\\Models\\Matrix', 86, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-22 12:23:33', '2025-09-22 12:23:33', NULL),
(31, 4, 4, 'App\\Models\\Matrix', 24, 1, NULL, 'approved', 0, NULL, '2025-09-22 12:33:53', '2025-09-22 12:33:53', 1),
(32, 2, 2, 'App\\Models\\Matrix', 25, 1, NULL, 'returned', 1, 'Kindly add the missing activity', '2025-09-22 14:20:02', '2025-09-25 09:01:50', 1),
(33, 9, 9, 'App\\Models\\Matrix', 25, 1, NULL, 'returned', 0, 'For adding the missing activities', '2025-09-22 15:14:22', '2025-09-22 15:14:22', 1),
(34, 9, 9, 'App\\Models\\Matrix', 25, 0, NULL, 'returned', 0, 'For adding the missing activities', '2025-09-22 15:14:23', '2025-09-22 15:14:23', NULL),
(35, 9, 9, 'App\\Models\\Matrix', 25, 1, NULL, 'returned', 0, 'To complete missing items', '2025-09-22 15:17:58', '2025-09-22 15:17:58', 1),
(36, 9, 9, 'App\\Models\\Matrix', 192, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-22 15:32:14', '2025-09-22 15:32:14', NULL),
(37, 2, 2, 'App\\Models\\Matrix', 322, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-23 02:57:36', '2025-09-25 09:01:50', NULL),
(38, 4, 4, 'App\\Models\\Matrix', 522, 4, NULL, 'approved', 0, NULL, '2025-09-23 11:25:15', '2025-09-23 11:25:15', 1),
(39, 7, 7, 'App\\Models\\Matrix', 30, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-23 11:29:40', '2025-09-23 11:29:40', NULL),
(40, 8, 8, 'App\\Models\\Matrix', 230, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-23 18:38:28', '2025-09-24 08:56:56', NULL),
(44, 7, 7, 'App\\Models\\Matrix', 23, 1, NULL, 'approved', 0, NULL, '2025-09-23 19:05:10', '2025-09-23 19:05:10', 1),
(45, 7, 7, 'App\\Models\\Matrix', 327, 3, NULL, 'approved', 0, NULL, '2025-09-24 04:17:34', '2025-09-24 04:17:34', 1),
(46, 5, 5, 'App\\Models\\Matrix', 221, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-24 05:53:18', '2025-09-24 05:53:18', NULL),
(47, 8, 8, 'App\\Models\\Matrix', 491, 1, NULL, 'returned', 1, 'Intégrer les deux ministres pour le RESCO vu que le MELP n\'a pas prévu de ressources à cet effet', '2025-09-24 08:56:56', '2025-09-24 08:56:56', 1),
(49, 5, 5, 'App\\Models\\Matrix', 492, 1, NULL, 'approved', 0, NULL, '2025-09-24 11:27:38', '2025-09-24 11:27:38', 1),
(50, 8, 8, 'App\\Models\\Matrix', 230, 0, NULL, 'submitted', 1, 'Observations prises en compte', '2025-09-24 14:28:25', '2025-09-26 20:42:19', NULL),
(51, 8, 8, 'App\\Models\\Matrix', 230, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-24 14:28:25', '2025-09-26 20:42:19', NULL),
(52, 5, 5, 'App\\Models\\Matrix', 522, 4, NULL, 'approved', 0, NULL, '2025-09-24 16:04:55', '2025-09-24 16:04:55', 1),
(53, 4, 4, 'App\\Models\\Matrix', 484, 5, NULL, 'approved', 0, NULL, '2025-09-25 04:53:08', '2025-09-25 04:53:08', 1),
(54, 5, 5, 'App\\Models\\Matrix', 484, 5, NULL, 'approved', 0, NULL, '2025-09-25 05:07:28', '2025-09-25 05:07:28', 1),
(55, 5, 5, 'App\\Models\\Matrix', 9, 6, NULL, 'approved', 0, NULL, '2025-09-25 05:36:28', '2025-09-25 05:36:28', 1),
(56, 4, 4, 'App\\Models\\Matrix', 9, 6, NULL, 'approved', 0, NULL, '2025-09-25 08:38:53', '2025-09-25 08:38:53', 1),
(57, 2, 2, 'App\\Models\\Matrix', 25, 1, NULL, 'returned', 1, 'Add missing items as per our discussions', '2025-09-25 09:01:50', '2025-09-25 09:01:50', 1),
(58, 9, 9, 'App\\Models\\Matrix', 25, 1, NULL, 'approved', 0, NULL, '2025-09-25 09:23:04', '2025-09-25 09:23:04', 1),
(59, 2, 2, 'App\\Models\\Matrix', 322, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-25 10:04:42', '2025-09-25 10:04:42', NULL),
(60, NULL, 104, 'App\\Models\\Activity', 192, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-25 10:15:19', '2025-09-25 10:15:19', 1),
(61, 2, 2, 'App\\Models\\Matrix', 25, 1, NULL, 'approved', 0, NULL, '2025-09-25 10:29:41', '2025-09-25 10:29:41', 1),
(62, 6, 6, 'App\\Models\\Matrix', 302, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-25 11:35:03', '2025-09-29 11:47:29', NULL),
(63, 7, 7, 'App\\Models\\Matrix', 522, 4, NULL, 'approved', 0, NULL, '2025-09-25 14:37:10', '2025-09-25 14:37:10', 1),
(65, 4, 4, 'App\\Models\\Matrix', 306, 8, NULL, 'approved', 0, NULL, '2025-09-25 15:31:16', '2025-09-25 15:31:16', 1),
(66, 9, 9, 'App\\Models\\Matrix', 522, 4, NULL, 'approved', 0, NULL, '2025-09-25 17:21:56', '2025-09-25 17:21:56', 1),
(67, NULL, 60, 'App\\Models\\Activity', 299, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-26 06:47:23', '2025-09-26 06:47:23', 1),
(68, NULL, 1, 'App\\Models\\SpecialMemo', 212, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-26 06:53:15', '2025-10-03 11:37:55', 1),
(69, NULL, 2, 'App\\Models\\SpecialMemo', 212, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-26 07:18:20', '2025-10-03 11:52:21', 1),
(70, NULL, 89, 'App\\Models\\Activity', 40, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-26 14:19:32', '2025-09-26 14:19:32', 1),
(71, NULL, 61, 'App\\Models\\Activity', 40, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-26 14:26:04', '2025-09-26 14:26:04', 1),
(72, 2, 2, 'App\\Models\\Matrix', 327, 3, NULL, 'returned', 0, 'Kindly align on the available budget', '2025-09-26 14:48:40', '2025-09-26 14:48:40', 1),
(73, NULL, 104, 'App\\Models\\Activity', 25, 1, NULL, 'approved', 0, '', '2025-09-26 19:20:07', '2025-09-26 19:20:07', 1),
(74, 2, 2, 'App\\Models\\Matrix', 25, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-26 19:23:56', '2025-09-26 19:23:56', 1),
(75, 8, 8, 'App\\Models\\Matrix', 491, 1, NULL, 'returned', 1, 'We received notification from WB today and we need to adjust our plan.', '2025-09-26 20:42:19', '2025-09-26 20:42:19', 1),
(76, 8, 8, 'App\\Models\\Matrix', 230, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-28 09:27:15', '2025-09-28 09:27:15', NULL),
(77, 2, 2, 'App\\Models\\Matrix', 327, 3, NULL, 'approved', 0, 'ok', '2025-09-29 09:10:29', '2025-09-29 09:10:29', 1),
(79, 12, 12, 'App\\Models\\Matrix', 188, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-29 10:29:21', '2025-10-01 09:37:56', NULL),
(80, 12, 12, 'App\\Models\\Matrix', 188, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-29 10:29:58', '2025-10-01 09:37:56', 1),
(81, NULL, 104, 'App\\Models\\Activity', 522, 4, NULL, 'approved', 0, '', '2025-09-29 11:13:39', '2025-09-29 11:13:39', 1),
(82, 4, 4, 'App\\Models\\Matrix', 6, 9, NULL, 'approved', 0, NULL, '2025-09-29 11:21:35', '2025-09-29 11:21:35', 1),
(84, 6, 6, 'App\\Models\\Matrix', 6, 1, NULL, 'returned', 1, 'Please combine the AES field work activities, AES host site engagement activities, and the Kofi Annan Scholar program activities as discussed with Senga.', '2025-09-29 11:47:29', '2025-09-29 11:47:29', 1),
(89, NULL, 8, 'App\\Models\\NonTravelMemo', 527, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-09-29 14:14:50', '2025-09-29 17:22:36', 1),
(90, NULL, 7, 'App\\Models\\NonTravelMemo', 534, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-29 14:20:50', '2025-09-29 14:20:50', 1),
(91, 2, 2, 'App\\Models\\Matrix', 522, 4, NULL, 'approved', 0, NULL, '2025-09-29 16:47:54', '2025-09-29 16:47:54', 1),
(92, NULL, 7, 'App\\Models\\NonTravelMemo', 522, 1, NULL, 'approved', 0, '', '2025-09-29 17:17:57', '2025-09-29 17:17:57', 1),
(93, NULL, 8, 'App\\Models\\NonTravelMemo', 522, 1, NULL, 'returned', 1, '', '2025-09-29 17:22:36', '2025-09-29 17:22:36', 1),
(94, NULL, 104, 'App\\Models\\Activity', 522, 4, NULL, 'approved', 0, '', '2025-09-29 17:24:40', '2025-09-29 17:24:40', 1),
(95, 4, 4, 'App\\Models\\Matrix', 449, 10, NULL, 'approved', 0, NULL, '2025-09-30 11:28:58', '2025-09-30 11:28:58', 1),
(96, NULL, 11, 'App\\Models\\NonTravelMemo', 534, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-30 13:00:24', '2025-09-30 13:00:24', 1),
(97, NULL, 10, 'App\\Models\\NonTravelMemo', 534, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-30 13:00:48', '2025-09-30 13:00:48', 1),
(98, NULL, 9, 'App\\Models\\NonTravelMemo', 534, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-30 13:01:10', '2025-09-30 13:01:10', 1),
(99, NULL, 151, 'App\\Models\\Activity', 322, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-30 14:25:50', '2025-09-30 14:25:50', 1),
(100, NULL, 150, 'App\\Models\\Activity', 322, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-30 14:26:24', '2025-09-30 14:26:24', 1),
(101, 10, 10, 'App\\Models\\Matrix', 275, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-09-30 14:43:59', '2025-09-30 14:43:59', NULL),
(102, NULL, 1, 'App\\Models\\SpecialMemo', 71, 1, NULL, 'approved', 1, '', '2025-09-30 17:10:09', '2025-10-03 11:37:55', 1),
(103, NULL, 2, 'App\\Models\\SpecialMemo', 71, 1, NULL, 'approved', 1, '', '2025-09-30 17:10:42', '2025-10-03 11:52:21', 1),
(104, 2, 2, 'App\\Models\\Matrix', 111, 5, NULL, 'approved', 0, NULL, '2025-10-01 09:21:10', '2025-10-01 09:21:10', 1),
(105, 12, 12, 'App\\Models\\Matrix', 8, 1, NULL, 'returned', 1, 'we discussed', '2025-10-01 09:37:56', '2025-10-01 09:37:56', 1),
(106, NULL, 118, 'App\\Models\\Activity', 192, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-01 12:00:24', '2025-10-01 12:00:24', 1),
(107, NULL, 9, 'App\\Models\\NonTravelMemo', 522, 1, NULL, 'approved', 0, '', '2025-10-01 18:39:17', '2025-10-01 18:39:17', 1),
(108, NULL, 11, 'App\\Models\\NonTravelMemo', 522, 1, NULL, 'approved', 0, '', '2025-10-01 18:40:01', '2025-10-01 18:40:01', 1),
(109, NULL, 10, 'App\\Models\\NonTravelMemo', 522, 1, NULL, 'approved', 0, '', '2025-10-01 18:40:40', '2025-10-01 18:40:40', 1),
(110, 7, 7, 'App\\Models\\Matrix', 484, 5, NULL, 'approved', 0, NULL, '2025-10-02 09:17:47', '2025-10-02 09:17:47', 1),
(111, NULL, 7, 'App\\Models\\NonTravelMemo', 522, 4, NULL, 'approved', 0, '', '2025-10-02 09:45:59', '2025-10-02 09:45:59', 1),
(112, NULL, 11, 'App\\Models\\NonTravelMemo', 522, 4, NULL, 'approved', 0, '', '2025-10-02 09:46:47', '2025-10-02 09:46:47', 1),
(113, NULL, 7, 'App\\Models\\NonTravelMemo', 520, 5, NULL, 'approved', 0, '', '2025-10-02 10:14:52', '2025-10-02 10:14:52', 1),
(114, NULL, 11, 'App\\Models\\NonTravelMemo', 520, 5, NULL, 'approved', 0, '', '2025-10-02 10:16:07', '2025-10-02 10:16:07', 1),
(115, 12, 12, 'App\\Models\\Matrix', 188, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-02 11:59:26', '2025-10-02 11:59:26', NULL),
(116, 6, 6, 'App\\Models\\Matrix', 302, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-02 13:49:41', '2025-10-02 13:49:41', NULL),
(117, 4, 4, 'App\\Models\\Matrix', 305, 11, NULL, 'approved', 0, NULL, '2025-10-02 14:11:51', '2025-10-02 14:11:51', 1),
(118, NULL, 1, 'App\\Models\\RequestARF', 82, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-02 18:00:56', '2025-10-02 18:00:56', 2),
(119, NULL, 2, 'App\\Models\\RequestARF', 82, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-02 18:05:48', '2025-10-02 18:05:48', 2),
(120, NULL, 69, 'App\\Models\\Activity', 258, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-02 19:52:33', '2025-10-02 19:52:33', 1),
(121, 8, 8, 'App\\Models\\Matrix', 491, 1, NULL, 'approved', 0, NULL, '2025-10-03 07:37:21', '2025-10-03 07:37:21', 1),
(122, 8, 8, 'App\\Models\\Matrix', 491, 2, NULL, 'approved', 0, NULL, '2025-10-03 07:37:23', '2025-10-03 07:37:23', 1),
(123, NULL, 118, 'App\\Models\\Activity', 25, 1, NULL, 'approved', 0, '', '2025-10-03 11:22:10', '2025-10-03 11:22:10', 1),
(124, NULL, 1, 'App\\Models\\SpecialMemo', 522, 4, NULL, 'returned', 1, '', '2025-10-03 11:37:55', '2025-10-03 11:37:55', 1),
(125, NULL, 2, 'App\\Models\\SpecialMemo', 522, 4, NULL, 'returned', 1, '', '2025-10-03 11:52:21', '2025-10-03 11:52:21', 1),
(126, NULL, 10, 'App\\Models\\NonTravelMemo', 522, 4, NULL, 'approved', 0, '', '2025-10-03 12:11:26', '2025-10-03 12:11:26', 1),
(127, NULL, 9, 'App\\Models\\NonTravelMemo', 522, 4, NULL, 'approved', 0, '', '2025-10-03 12:13:28', '2025-10-03 12:13:28', 1),
(128, NULL, 2, 'App\\Models\\SpecialMemo', 212, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-03 12:34:46', '2025-10-03 12:34:46', 1),
(129, NULL, 3, 'App\\Models\\RequestARF', 627, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-03 15:40:21', '2025-10-03 15:40:21', 2),
(130, NULL, 4, 'App\\Models\\RequestARF', 627, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-03 15:41:52', '2025-10-03 15:41:52', 2),
(131, NULL, 5, 'App\\Models\\RequestARF', 627, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-03 15:42:36', '2025-10-03 15:42:36', 2),
(132, NULL, 6, 'App\\Models\\RequestARF', 627, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-03 15:43:58', '2025-10-03 15:43:58', 2),
(133, NULL, 7, 'App\\Models\\RequestARF', 83, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-03 15:45:43', '2025-10-03 15:45:43', 2),
(134, NULL, 8, 'App\\Models\\RequestARF', 186, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-03 15:49:38', '2025-10-03 15:49:38', 2),
(135, NULL, 9, 'App\\Models\\RequestARF', 186, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-03 15:51:37', '2025-10-03 15:51:37', 2),
(136, NULL, 10, 'App\\Models\\RequestARF', 89, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-04 10:50:53', '2025-10-04 10:50:53', 2),
(137, NULL, 11, 'App\\Models\\RequestARF', 89, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-04 10:52:25', '2025-10-04 10:52:25', 2),
(138, NULL, 12, 'App\\Models\\RequestARF', 89, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-04 10:53:02', '2025-10-04 10:53:02', 2),
(139, NULL, 13, 'App\\Models\\RequestARF', 90, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-05 09:48:42', '2025-10-05 09:48:42', 2),
(140, NULL, 14, 'App\\Models\\RequestARF', 90, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-05 10:09:15', '2025-10-05 10:09:15', 2),
(141, 9, 9, 'App\\Models\\Matrix', 484, 5, NULL, 'approved', 0, NULL, '2025-10-06 06:11:56', '2025-10-06 06:11:56', 1),
(142, NULL, 46, 'App\\Models\\Activity', 452, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-06 07:38:02', '2025-10-06 07:38:02', 1),
(143, NULL, 206, 'App\\Models\\Activity', 118, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-06 09:04:21', '2025-10-06 09:04:21', 1),
(144, NULL, 1, 'App\\Models\\ServiceRequest', 83, 0, NULL, 'submitted', 0, 'Service request created and submitted for approval', '2025-10-06 09:06:18', '2025-10-06 09:06:18', 2),
(145, 12, 12, 'App\\Models\\Matrix', 8, 1, NULL, 'approved', 0, NULL, '2025-10-06 09:15:54', '2025-10-06 09:15:54', 1),
(146, NULL, 203, 'App\\Models\\Activity', 118, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-06 09:27:06', '2025-10-06 09:27:06', 1),
(147, NULL, 14, 'App\\Models\\RequestARF', 197, 1, NULL, 'approved', 0, 'test', '2025-10-06 11:10:54', '2025-10-06 11:10:54', 2),
(148, NULL, 4, 'App\\Models\\SpecialMemo', 558, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-06 13:49:34', '2025-10-06 13:49:34', 1),
(149, NULL, 212, 'App\\Models\\Activity', 558, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-10-06 14:02:45', '2025-10-09 17:01:00', 1),
(150, NULL, 212, 'App\\Models\\Activity', 24, 1, NULL, 'approved', 1, '', '2025-10-06 14:08:07', '2025-10-09 17:01:00', 1),
(151, NULL, 151, 'App\\Models\\Activity', 25, 1, NULL, 'approved', 0, '', '2025-10-06 21:10:14', '2025-10-06 21:10:14', 1),
(152, NULL, 150, 'App\\Models\\Activity', 25, 1, NULL, 'approved', 0, '', '2025-10-06 21:57:17', '2025-10-06 21:57:17', 1),
(153, NULL, 212, 'App\\Models\\Activity', 558, 4, NULL, 'approved', 1, '', '2025-10-06 22:25:52', '2025-10-09 17:01:00', 1),
(154, NULL, 212, 'App\\Models\\Activity', 484, 5, NULL, 'approved', 1, '', '2025-10-06 22:36:05', '2025-10-09 17:01:00', 1),
(156, NULL, 104, 'App\\Models\\Activity', 484, 5, NULL, 'approved', 0, '', '2025-10-07 08:12:14', '2025-10-07 08:12:14', 1),
(157, NULL, 104, 'App\\Models\\Activity', 558, 6, NULL, 'approved', 0, '', '2025-10-07 08:13:30', '2025-10-07 08:13:30', 1),
(158, NULL, 104, 'App\\Models\\Activity', 558, 9, NULL, 'approved', 0, '', '2025-10-07 09:29:24', '2025-10-07 09:29:24', 1),
(159, NULL, 104, 'App\\Models\\Activity', 558, 9, NULL, 'approved', 0, '', '2025-10-07 10:19:20', '2025-10-07 10:19:20', 1),
(160, 17, 17, 'App\\Models\\Matrix', 558, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-10-08 14:08:38', '2025-10-08 14:15:34', NULL),
(188, 1, 1, 'App\\Models\\Matrix', 74, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-10-09 15:21:04', '2025-10-09 16:54:45', NULL),
(189, 1, 1, 'App\\Models\\Matrix', 558, 1, NULL, 'approved', 1, NULL, '2025-10-09 15:44:12', '2025-10-09 16:54:45', 1),
(190, 1, 1, 'App\\Models\\Matrix', 558, 3, NULL, 'approved', 1, NULL, '2025-10-09 16:08:53', '2025-10-09 16:54:45', 1),
(191, 1, 1, 'App\\Models\\Matrix', 558, 4, NULL, 'approved', 1, NULL, '2025-10-09 16:09:09', '2025-10-09 16:54:45', 1),
(192, 1, 1, 'App\\Models\\Matrix', 558, 5, NULL, 'approved', 1, NULL, '2025-10-09 16:09:44', '2025-10-09 16:54:45', 1),
(193, 1, 1, 'App\\Models\\Matrix', 558, 6, NULL, 'approved', 1, NULL, '2025-10-09 16:10:29', '2025-10-09 16:54:45', 1),
(196, 1, 1, 'App\\Models\\Matrix', 558, 7, NULL, 'approved', 1, NULL, '2025-10-09 16:48:32', '2025-10-09 16:54:45', 1),
(197, 1, 1, 'App\\Models\\Matrix', 558, 9, NULL, 'approved', 1, NULL, '2025-10-09 16:48:53', '2025-10-09 16:54:45', 1),
(198, 1, 1, 'App\\Models\\Matrix', 558, 10, NULL, 'returned', 1, 'retest', '2025-10-09 16:54:35', '2025-10-09 16:54:45', 1),
(199, 1, 1, 'App\\Models\\Matrix', 558, 1, NULL, 'returned', 1, 'retest', '2025-10-09 16:54:45', '2025-10-09 16:54:45', 1),
(200, 1, 1, 'App\\Models\\Matrix', 74, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-09 16:55:36', '2025-10-09 16:55:36', NULL),
(201, 1, 1, 'App\\Models\\Matrix', 558, 1, NULL, 'approved', 0, NULL, '2025-10-09 16:57:22', '2025-10-09 16:57:22', 1),
(202, 1, 1, 'App\\Models\\Matrix', 558, 3, NULL, 'approved', 0, NULL, '2025-10-09 16:57:36', '2025-10-09 16:57:36', 1),
(203, 1, 1, 'App\\Models\\Matrix', 558, 4, NULL, 'approved', 0, NULL, '2025-10-09 16:57:51', '2025-10-09 16:57:51', 1),
(204, 1, 1, 'App\\Models\\Matrix', 558, 5, NULL, 'approved', 0, NULL, '2025-10-09 16:58:21', '2025-10-09 16:58:21', 1),
(205, 1, 1, 'App\\Models\\Matrix', 558, 6, NULL, 'approved', 0, NULL, '2025-10-09 16:58:37', '2025-10-09 16:58:37', 1),
(206, 1, 1, 'App\\Models\\Matrix', 558, 7, NULL, 'approved', 0, NULL, '2025-10-09 16:58:55', '2025-10-09 16:58:55', 1),
(207, 1, 1, 'App\\Models\\Matrix', 558, 9, NULL, 'approved', 0, NULL, '2025-10-09 16:59:10', '2025-10-09 16:59:10', 1),
(208, 1, 1, 'App\\Models\\Matrix', 558, 10, NULL, 'approved', 0, NULL, '2025-10-09 16:59:26', '2025-10-09 16:59:26', 1),
(209, 1, 1, 'App\\Models\\Matrix', 558, 11, NULL, 'approved', 0, NULL, '2025-10-09 16:59:44', '2025-10-09 16:59:44', 1),
(210, NULL, 212, 'App\\Models\\Activity', 558, 6, NULL, 'returned', 1, '', '2025-10-09 17:01:00', '2025-10-09 17:01:00', 1),
(211, NULL, 212, 'App\\Models\\Activity', 558, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-10-09 17:28:01', '2025-10-09 17:30:48', 1),
(212, NULL, 212, 'App\\Models\\Activity', 558, 1, NULL, 'approved', 1, '', '2025-10-09 17:30:06', '2025-10-09 17:30:48', 1),
(213, NULL, 212, 'App\\Models\\Activity', 558, 4, NULL, 'approved', 1, '', '2025-10-09 17:30:18', '2025-10-09 17:30:48', 1),
(214, NULL, 212, 'App\\Models\\Activity', 558, 5, NULL, 'approved', 1, '', '2025-10-09 17:30:37', '2025-10-09 17:30:48', 1),
(215, NULL, 212, 'App\\Models\\Activity', 558, 6, NULL, 'returned', 1, '', '2025-10-09 17:30:48', '2025-10-09 17:30:48', 1),
(216, NULL, 212, 'App\\Models\\Activity', 558, 1, NULL, 'returned', 1, '', '2025-10-09 17:31:38', '2025-10-09 17:31:38', NULL),
(217, NULL, 212, 'App\\Models\\Activity', 558, 0, NULL, 'submitted', 1, 'Submitted for approval', '2025-10-09 17:31:49', '2025-10-09 17:33:07', 1),
(218, NULL, 212, 'App\\Models\\Activity', 558, 1, NULL, 'approved', 1, '', '2025-10-09 17:31:58', '2025-10-09 17:33:07', 1),
(219, NULL, 212, 'App\\Models\\Activity', 558, 4, NULL, 'approved', 1, '', '2025-10-09 17:32:07', '2025-10-09 17:33:07', 1),
(220, NULL, 212, 'App\\Models\\Activity', 558, 5, NULL, 'approved', 1, '', '2025-10-09 17:32:20', '2025-10-09 17:33:07', 1),
(221, NULL, 212, 'App\\Models\\Activity', 558, 6, NULL, 'approved', 1, '', '2025-10-09 17:32:48', '2025-10-09 17:33:07', 1),
(222, NULL, 212, 'App\\Models\\Activity', 558, 8, NULL, 'approved', 1, '', '2025-10-09 17:32:57', '2025-10-09 17:33:07', 1),
(223, NULL, 212, 'App\\Models\\Activity', 558, 9, NULL, 'returned', 1, '', '2025-10-09 17:33:07', '2025-10-09 17:33:07', 1),
(224, NULL, 212, 'App\\Models\\Activity', 558, 1, NULL, 'returned', 1, '', '2025-10-09 17:33:33', '2025-10-09 17:33:33', NULL),
(225, NULL, 212, 'App\\Models\\Activity', 558, 0, NULL, 'submitted', 0, 'Submitted for approval', '2025-10-09 17:33:38', '2025-10-09 17:33:38', 1),
(226, NULL, 212, 'App\\Models\\Activity', 558, 1, NULL, 'approved', 0, '', '2025-10-09 17:33:46', '2025-10-09 17:33:46', 1),
(227, NULL, 212, 'App\\Models\\Activity', 558, 4, NULL, 'approved', 0, '', '2025-10-09 17:34:02', '2025-10-09 17:34:02', 1),
(228, NULL, 212, 'App\\Models\\Activity', 558, 5, NULL, 'approved', 0, '', '2025-10-09 17:34:51', '2025-10-09 17:34:51', 1),
(229, NULL, 212, 'App\\Models\\Activity', 558, 6, NULL, 'approved', 0, '', '2025-10-09 17:35:02', '2025-10-09 17:35:02', 1),
(230, NULL, 212, 'App\\Models\\Activity', 558, 9, NULL, 'approved', 0, '', '2025-10-09 17:35:17', '2025-10-09 17:35:17', 1),
(231, NULL, 212, 'App\\Models\\Activity', 558, 10, NULL, 'approved', 0, '', '2025-10-09 17:35:28', '2025-10-09 17:35:28', 1),
(232, NULL, 212, 'App\\Models\\Activity', 558, 11, NULL, 'approved', 0, '', '2025-10-09 17:35:35', '2025-10-09 17:35:35', 1),
(233, NULL, 11, 'App\\Models\\NonTravelMemo', 558, 6, NULL, 'approved', 0, '', '2025-10-09 17:40:42', '2025-10-09 17:40:42', 1),
(234, NULL, 11, 'App\\Models\\NonTravelMemo', 558, 9, NULL, 'approved', 0, '', '2025-10-09 17:40:49', '2025-10-09 17:40:49', 1),
(235, NULL, 11, 'App\\Models\\NonTravelMemo', 558, 10, NULL, 'approved', 0, '', '2025-10-09 17:54:19', '2025-10-09 17:54:19', 1),
(236, NULL, 15, 'App\\Models\\RequestARF', 558, 0, NULL, 'submitted', 0, 'ARF request created and submitted for approval', '2025-10-09 21:13:44', '2025-10-09 21:13:44', 2),
(237, NULL, 15, 'App\\Models\\RequestARF', 197, 1, NULL, 'approved', 0, '', '2025-10-09 21:16:53', '2025-10-09 21:16:53', 2),
(238, NULL, 2, 'App\\Models\\ServiceRequest', 558, 0, NULL, 'submitted', 0, 'Service request created and submitted for approval', '2025-10-09 23:01:42', '2025-10-09 23:01:42', 3),
(240, NULL, 1, 'App\\Models\\ServiceRequest', 558, 0, NULL, 'submitted', 0, 'Service request created and submitted for approval', '2025-10-09 23:20:22', '2025-10-09 23:20:22', 3),
(241, NULL, 1, 'App\\Models\\ServiceRequest', 9, 31, NULL, 'approved', 0, '', '2025-10-09 23:24:45', '2025-10-09 23:24:45', 3),
(242, NULL, 2, 'App\\Models\\ServiceRequest', 558, 31, NULL, 'submitted', 0, 'Service request created and submitted for approval', '2025-10-10 00:02:32', '2025-10-10 00:02:32', 3),
(243, NULL, 2, 'App\\Models\\ServiceRequest', 9, 31, NULL, 'approved', 0, '', '2025-10-10 00:07:35', '2025-10-10 00:07:35', 3),
(244, NULL, 2, 'App\\Models\\ServiceRequest', 446, 32, NULL, 'approved', 0, '', '2025-10-10 00:13:36', '2025-10-10 00:13:36', 3),
(245, 17, 17, 'App\\Models\\Matrix', 558, 1, NULL, 'approved', 0, NULL, '2025-10-10 14:39:57', '2025-10-10 14:39:57', 1),
(246, 17, 17, 'App\\Models\\Matrix', 558, 3, NULL, 'approved', 0, NULL, '2025-10-10 14:46:28', '2025-10-10 14:46:28', 1),
(247, 17, 17, 'App\\Models\\Matrix', 558, 4, NULL, 'approved', 0, NULL, '2025-10-10 14:47:21', '2025-10-10 14:47:21', 1),
(248, 17, 17, 'App\\Models\\Matrix', 558, 5, NULL, 'approved', 0, NULL, '2025-10-10 14:48:55', '2025-10-10 14:48:55', 1),
(249, 17, 17, 'App\\Models\\Matrix', 558, 6, NULL, 'approved', 0, NULL, '2025-10-10 14:50:41', '2025-10-10 14:50:41', 1),
(250, NULL, 4, 'App\\Models\\SpecialMemo', 558, 1, NULL, 'approved', 0, '', '2025-10-10 15:01:24', '2025-10-10 15:01:24', 1),
(251, NULL, 11, 'App\\Models\\NonTravelMemo', 558, 11, NULL, 'approved', 0, '', '2025-10-10 15:05:51', '2025-10-10 15:05:51', 1),
(252, 2, 2, 'App\\Models\\Matrix', 558, 6, NULL, 'approved', 0, NULL, '2025-10-12 07:48:12', '2025-10-12 07:48:12', 1),
(253, 2, 2, 'App\\Models\\Matrix', 558, 8, NULL, 'returned', 0, 'ty', '2025-10-12 07:49:10', '2025-10-12 07:49:10', 1),
(254, NULL, 4, 'App\\Models\\SpecialMemo', 558, 4, NULL, 'approved', 0, '', '2025-10-16 21:26:26', '2025-10-16 21:26:26', 1),
(255, NULL, 4, 'App\\Models\\SpecialMemo', 558, 5, NULL, 'approved', 0, '', '2025-10-16 21:26:41', '2025-10-16 21:26:41', 1),
(256, NULL, 4, 'App\\Models\\SpecialMemo', 558, 6, NULL, 'approved', 0, '', '2025-10-16 21:26:48', '2025-10-16 21:26:48', 1),
(257, NULL, 4, 'App\\Models\\SpecialMemo', 558, 8, NULL, 'approved', 0, '', '2025-10-16 21:27:02', '2025-10-16 21:27:02', 1),
(258, NULL, 4, 'App\\Models\\SpecialMemo', 558, 9, NULL, 'approved', 0, '', '2025-10-16 21:27:08', '2025-10-16 21:27:08', 1),
(259, NULL, 4, 'App\\Models\\SpecialMemo', 558, 10, NULL, 'approved', 0, '', '2025-10-16 21:27:15', '2025-10-16 21:27:15', 1),
(260, NULL, 4, 'App\\Models\\SpecialMemo', 558, 11, NULL, 'approved', 0, '', '2025-10-16 21:27:24', '2025-10-16 21:27:24', 1),
(261, NULL, 3, 'App\\Models\\ServiceRequest', 558, 31, NULL, 'submitted', 0, 'Service request created and submitted for approval', '2025-10-16 21:29:53', '2025-10-16 21:29:53', 3),
(262, NULL, 4, 'App\\Models\\ServiceRequest', 558, 31, NULL, 'submitted', 0, 'Service request created and submitted for approval', '2025-10-16 22:11:24', '2025-10-16 22:11:24', 3),
(263, NULL, 4, 'App\\Models\\ServiceRequest', 558, 31, NULL, 'approved', 0, '', '2025-10-16 22:47:48', '2025-10-16 22:47:48', 3),
(264, NULL, 4, 'App\\Models\\ServiceRequest', 558, 32, NULL, 'approved', 0, '', '2025-10-16 22:47:57', '2025-10-16 22:47:57', 3),
(265, NULL, 10, 'App\\Models\\NonTravelMemo', 558, 5, NULL, 'approved', 0, '', '2025-10-17 06:22:30', '2025-10-17 06:22:30', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approval_trails`
--
ALTER TABLE `approval_trails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approval_trails_forward_workflow_id_index` (`forward_workflow_id`),
  ADD KEY `approval_trails_model_id_index` (`model_id`),
  ADD KEY `approval_trails_matrix_id_index` (`matrix_id`),
  ADD KEY `approval_trails_staff_id_index` (`staff_id`),
  ADD KEY `approval_trails_oic_staff_id_index` (`oic_staff_id`),
  ADD KEY `approval_trails_model_type_index` (`model_type`),
  ADD KEY `approval_trails_action_index` (`action`),
  ADD KEY `approval_trails_approval_order_index` (`approval_order`),
  ADD KEY `approval_trails_created_at_index` (`created_at`),
  ADD KEY `approval_trails_model_id_model_type_index` (`model_id`,`model_type`),
  ADD KEY `approval_trails_model_id_model_type_action_index` (`model_id`,`model_type`,`action`),
  ADD KEY `approval_trails_staff_id_action_index` (`staff_id`,`action`),
  ADD KEY `approval_trails_approval_order_action_index` (`approval_order`,`action`),
  ADD KEY `approval_trails_matrix_id_approval_order_index` (`matrix_id`,`approval_order`),
  ADD KEY `approval_trails_is_archived_model_id_model_type_index` (`is_archived`,`model_id`,`model_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approval_trails`
--
ALTER TABLE `approval_trails`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=266;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
