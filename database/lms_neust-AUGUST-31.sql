-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 31, 2025 at 05:14 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lms_neust`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year`, `is_active`, `created_at`) VALUES
(1, '2024-2025', 1, '2025-06-24 23:16:43'),
(2, '2023-2024', 0, '2025-06-24 23:16:43'),
(3, '2025-2026', 0, '2025-07-15 03:20:26');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `author_id`, `course_id`, `section_id`, `created_at`) VALUES
(2, 'Python Course Starting', 'The Introduction to Programming course will begin next week. Please prepare by installing Python on your computers.', 2, 1, NULL, '2025-06-24 23:16:43'),
(3, 'dumilim ang paligid', 'vic sotto', 4, NULL, NULL, '2025-06-25 05:58:21'),
(4, 'pompom', 'pompom', 4, NULL, NULL, '2025-07-08 13:32:07'),
(5, 'pompom', 'pompom', 4, NULL, NULL, '2025-07-08 13:32:12'),
(6, 'pompom', 'pompom', 4, NULL, NULL, '2025-07-08 13:34:50'),
(7, 'Attention!!!', 'babalik na si john lloyd poge', 4, NULL, NULL, '2025-07-15 09:46:34'),
(8, 'Hey', 'What&#039;s up', 4, 13, NULL, '2025-08-23 06:45:54'),
(9, 'Announcement', 'oy oy oy', 4, NULL, NULL, '2025-08-25 04:18:32'),
(48, 'test 1 teacher', 'teacher announcement', 17, 10, NULL, '2025-08-27 11:00:31'),
(49, 'test 2 teacher', 'teacher 2', 17, 16, NULL, '2025-08-27 11:01:43'),
(50, 'test 1 teacher', 'teacher announcement', 17, 10, NULL, '2025-08-27 11:14:08'),
(57, 'test 5', 'testing', 17, NULL, NULL, '2025-08-28 06:28:54');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_reads`
--

INSERT INTO `announcement_reads` (`id`, `user_id`, `announcement_id`, `read_at`) VALUES
(1, 4, 3, '2025-07-08 13:28:41'),
(3, 4, 2, '2025-07-08 13:28:45'),
(38, 4, 4, '2025-07-08 13:32:16'),
(39, 4, 5, '2025-07-08 13:34:54'),
(40, 4, 6, '2025-07-08 13:35:26'),
(41, 6, 2, '2025-07-09 01:23:49'),
(42, 16, 6, '2025-07-15 05:19:46'),
(43, 16, 5, '2025-07-15 05:19:59'),
(44, 17, 6, '2025-07-15 06:31:06'),
(45, 17, 5, '2025-07-15 09:45:27'),
(46, 17, 7, '2025-07-15 09:48:32'),
(47, 17, 4, '2025-07-15 09:48:46'),
(48, 17, 3, '2025-07-15 09:50:02'),
(49, 17, 2, '2025-07-16 01:52:35'),
(50, 4, 7, '2025-07-16 02:59:19'),
(51, 20, 7, '2025-07-16 08:53:14'),
(52, 20, 6, '2025-07-16 08:53:29'),
(53, 20, 5, '2025-07-17 08:28:19'),
(54, 20, 4, '2025-07-18 09:22:26'),
(55, 20, 3, '2025-07-31 15:02:41'),
(56, 20, 2, '2025-08-07 02:08:46'),
(57, 4, 8, '2025-08-23 06:49:57'),
(60, 17, 9, '2025-08-25 04:26:53'),
(61, 17, 8, '2025-08-25 04:26:55'),
(64, 20, 9, '2025-08-25 04:27:22'),
(65, 20, 8, '2025-08-25 04:27:23'),
(128, 4, 9, '2025-08-27 10:53:22'),
(129, 20, 49, '2025-08-27 11:01:54'),
(130, 20, 48, '2025-08-27 11:01:55'),
(131, 17, 50, '2025-08-27 11:24:03'),
(132, 17, 49, '2025-08-27 11:24:03'),
(133, 17, 48, '2025-08-27 11:24:03'),
(134, 20, 50, '2025-08-27 11:29:52'),
(135, 4, 50, '2025-08-27 11:30:16'),
(136, 4, 49, '2025-08-27 11:30:16'),
(137, 4, 48, '2025-08-27 11:30:16'),
(154, 17, 57, '2025-08-28 06:30:06'),
(155, 20, 57, '2025-08-28 07:43:17'),
(156, 4, 57, '2025-08-30 10:02:32');

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `assessment_title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `num_questions` int(11) NOT NULL DEFAULT 10,
  `passing_rate` decimal(5,2) DEFAULT 70.00,
  `attempt_limit` int(11) DEFAULT 3 COMMENT 'Maximum number of attempts allowed for this assessment (0 = unlimited)',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether the assessment is locked (1) or unlocked (0)',
  `lock_type` enum('manual','prerequisite_score','prerequisite_videos','date_based') DEFAULT 'manual' COMMENT 'Type of lock mechanism',
  `prerequisite_assessment_id` int(11) DEFAULT NULL COMMENT 'Assessment ID that must be passed before this one can be taken',
  `prerequisite_score` decimal(5,2) DEFAULT NULL COMMENT 'Minimum score required on prerequisite assessment (0-100)',
  `prerequisite_video_count` int(11) DEFAULT NULL COMMENT 'Minimum number of videos that must be watched before taking this assessment',
  `unlock_date` datetime DEFAULT NULL COMMENT 'Date when assessment becomes available (for date-based locking)',
  `lock_message` text DEFAULT NULL COMMENT 'Custom message shown to students when assessment is locked',
  `lock_updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'When the lock settings were last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `module_id`, `assessment_title`, `description`, `time_limit`, `difficulty`, `is_active`, `created_at`, `num_questions`, `passing_rate`, `attempt_limit`, `is_locked`, `lock_type`, `prerequisite_assessment_id`, `prerequisite_score`, `prerequisite_video_count`, `unlock_date`, `lock_message`, `lock_updated_at`) VALUES
(1, 1, 'Python Basics Quiz', 'Test your knowledge of Python fundamentals', 30, 'easy', 1, '2025-06-24 23:16:43', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 'Data Types Assessment', 'Assessment on variables and data types', 45, 'medium', 1, '2025-06-24 23:16:43', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 'Control Structures Test', 'Advanced test on loops and conditions', 60, 'hard', 1, '2025-06-24 23:16:43', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 4, 'HTML Fundamentals', 'Basic HTML markup assessment', 30, 'easy', 1, '2025-06-24 23:16:43', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 7, 'Assessment 1', 'a', 10, 'easy', 1, '2025-06-30 08:51:24', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 7, 'quiz 3', 'multiple choices', 1, 'easy', 1, '2025-07-08 03:49:05', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 7, 'quiz 3', 'multiple choices', 1, 'easy', 1, '2025-07-08 03:52:29', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(9, 10, 'Sample 1', 'number 1', 1, 'easy', 1, '2025-07-15 11:37:14', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(10, 11, 'Quiz 1', '', 10, 'easy', 1, '2025-07-16 01:48:25', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 12, 'basic 1', 'nakapa basic', 2, 'easy', 1, '2025-07-17 05:53:16', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 10, 'New assessment', 'bago', 1, 'easy', 1, '2025-07-19 07:42:05', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(16, 10, 'Mas Bago', 'sssasss', 1, 'easy', 1, '2025-07-19 08:21:30', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(17, 10, 'mapapaomzimm', '', 1, 'easy', 1, '2025-07-20 08:47:33', 10, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(18, 10, 'eto na ngaa', 'asd', 3, 'easy', 1, '2025-08-09 09:44:09', 10, 70.00, 5, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(19, 12, 'basic 2', '', 20, 'medium', 0, '2025-08-13 23:33:54', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '2025-08-30 12:19:03'),
(20, 10, 'Test for attemp', 'test for attemp', 5, 'medium', 1, '2025-08-27 11:41:41', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL),
(21, 27, 'Test 1', 'week 1 test', 5, 'easy', 1, '2025-08-30 10:19:04', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_attempts`
--

CREATE TABLE `assessment_attempts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT NULL,
  `status` enum('in_progress','completed','abandoned') DEFAULT 'in_progress',
  `has_passed` tinyint(1) DEFAULT 0 COMMENT 'Whether this attempt achieved a passing score',
  `has_ever_passed` tinyint(1) DEFAULT 0 COMMENT 'Whether student has ever passed this assessment (updated on first pass)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_attempts`
--

INSERT INTO `assessment_attempts` (`id`, `student_id`, `assessment_id`, `started_at`, `completed_at`, `score`, `max_score`, `status`, `has_passed`, `has_ever_passed`) VALUES
(1, 5, 6, '2025-07-08 03:12:21', NULL, 0.00, 0.00, 'completed', 0, 0),
(2, 5, 6, '2025-07-08 03:17:19', NULL, 0.00, 0.00, 'completed', 0, 0),
(3, 5, 6, '2025-07-08 03:21:46', NULL, 0.00, 0.00, 'completed', 0, 0),
(4, 5, 6, '2025-07-08 03:23:39', NULL, 0.00, 0.00, 'completed', 0, 0),
(5, 5, 6, '2025-07-08 03:34:47', NULL, 0.00, 0.00, 'completed', 0, 0),
(6, 5, 6, '2025-07-08 03:47:18', NULL, 0.00, 0.00, 'completed', 0, 0),
(7, 5, 8, '2025-07-08 03:56:17', '2025-07-08 03:56:17', 0.00, 1.00, 'completed', 0, 1),
(8, 5, 8, '2025-07-08 03:56:36', '2025-07-08 03:56:36', 100.00, 1.00, 'completed', 1, 1),
(9, 5, 8, '2025-07-08 04:06:43', '2025-07-08 04:06:43', 100.00, 1.00, 'completed', 1, 1),
(10, 5, 8, '2025-07-08 04:12:01', '2025-07-08 04:12:01', 100.00, 1.00, 'completed', 1, 1),
(11, 5, 8, '2025-07-08 04:17:35', '2025-07-08 04:17:35', 100.00, 1.00, 'completed', 1, 1),
(12, 5, 8, '2025-07-08 04:22:21', '2025-07-08 04:22:21', 0.00, 1.00, 'completed', 0, 1),
(13, 20, 9, '2025-07-16 09:07:13', '2025-07-16 09:07:13', 100.00, 2.00, 'completed', 1, 1),
(14, 20, 9, '2025-07-16 09:11:02', '2025-07-16 09:11:02', 0.00, 2.00, 'completed', 0, 1),
(15, 20, 9, '2025-07-16 09:13:25', '2025-07-16 09:13:25', 50.00, 2.00, 'completed', 0, 1),
(16, 20, 9, '2025-07-16 09:17:02', '2025-07-16 09:17:02', 50.00, 2.00, 'completed', 0, 1),
(17, 20, 9, '2025-07-16 09:17:27', '2025-07-16 09:17:27', 0.00, 2.00, 'completed', 0, 1),
(18, 20, 9, '2025-07-16 09:17:59', '2025-07-16 09:17:59', 50.00, 2.00, 'completed', 0, 1),
(19, 20, 9, '2025-07-16 09:25:15', '2025-07-16 09:25:15', 0.00, 2.00, 'completed', 0, 1),
(20, 20, 9, '2025-07-16 09:25:46', '2025-07-16 09:25:46', 100.00, 2.00, 'completed', 1, 1),
(21, 20, 9, '2025-07-16 09:26:11', '2025-07-16 09:26:11', 50.00, 2.00, 'completed', 0, 1),
(22, 20, 9, '2025-07-16 09:31:07', '2025-07-16 09:31:07', 50.00, 2.00, 'completed', 0, 1),
(23, 20, 9, '2025-07-16 09:37:48', '2025-07-16 09:37:48', 50.00, 2.00, 'completed', 0, 1),
(24, 20, 9, '2025-07-16 09:38:48', '2025-07-16 09:38:48', 0.00, 2.00, 'completed', 0, 1),
(25, 20, 9, '2025-07-16 09:42:47', '2025-07-16 09:42:47', 50.00, 2.00, 'completed', 0, 1),
(26, 20, 9, '2025-07-16 09:49:11', '2025-07-16 09:49:11', 50.00, 2.00, 'completed', 0, 1),
(27, 20, 9, '2025-07-16 10:14:45', '2025-07-16 10:14:45', 100.00, 2.00, 'completed', 1, 1),
(28, 20, 9, '2025-07-16 10:16:47', '2025-07-16 10:16:47', 100.00, 3.00, 'completed', 1, 1),
(29, 20, 9, '2025-07-16 10:17:07', '2025-07-16 10:17:07', 67.00, 3.00, 'completed', 0, 1),
(30, 20, 9, '2025-07-16 10:17:28', '2025-07-16 10:17:28', 33.00, 3.00, 'completed', 0, 1),
(31, 20, 9, '2025-07-16 10:28:11', '2025-07-16 10:28:11', 33.00, 3.00, 'completed', 0, 1),
(32, 20, 9, '2025-07-16 10:30:44', '2025-07-16 10:30:44', 33.00, 3.00, 'completed', 0, 1),
(33, 20, 9, '2025-07-16 10:36:40', '2025-07-16 10:36:40', 67.00, 3.00, 'completed', 0, 1),
(34, 20, 9, '2025-07-16 10:40:34', '2025-07-16 10:40:34', 67.00, 3.00, 'completed', 0, 1),
(35, 20, 9, '2025-07-16 10:43:34', '2025-07-16 10:43:34', 67.00, 3.00, 'completed', 0, 1),
(36, 20, 9, '2025-07-16 10:47:31', '2025-07-16 10:47:31', 100.00, 3.00, 'completed', 1, 1),
(37, 20, 9, '2025-07-16 10:50:01', '2025-07-16 10:50:01', 67.00, 3.00, 'completed', 0, 1),
(38, 20, 9, '2025-07-16 10:53:27', '2025-07-16 10:53:27', 67.00, 3.00, 'completed', 0, 1),
(39, 20, 9, '2025-07-16 11:03:40', '2025-07-16 11:03:40', 100.00, 3.00, 'completed', 1, 1),
(40, 20, 9, '2025-07-16 11:07:59', '2025-07-16 11:07:59', 67.00, 3.00, 'completed', 0, 1),
(41, 20, 9, '2025-07-16 11:17:04', '2025-07-16 11:17:04', 67.00, 3.00, 'completed', 0, 1),
(42, 20, 9, '2025-07-16 11:20:53', '2025-07-16 11:20:53', 100.00, 3.00, 'completed', 1, 1),
(43, 20, 9, '2025-07-16 11:38:06', '2025-07-16 11:38:06', 33.00, 3.00, 'completed', 0, 1),
(44, 20, 9, '2025-07-16 11:43:29', '2025-07-16 11:43:29', 100.00, 3.00, 'completed', 1, 1),
(45, 20, 9, '2025-07-16 11:46:53', '2025-07-16 11:46:53', 75.00, 4.00, 'completed', 1, 1),
(46, 20, 9, '2025-07-16 11:50:55', '2025-07-16 11:51:10', 100.00, 4.00, 'completed', 1, 1),
(47, 20, 9, '2025-07-17 05:47:43', '2025-07-17 05:48:01', 100.00, 4.00, 'completed', 1, 1),
(50, 20, 9, '2025-07-17 08:18:45', '2025-07-17 08:19:00', 25.00, 4.00, 'completed', 0, 1),
(51, 20, 9, '2025-07-17 08:20:09', '2025-07-17 08:20:28', 60.00, 5.00, 'completed', 0, 1),
(52, 20, 9, '2025-07-17 08:27:30', '2025-07-17 08:27:48', 40.00, 5.00, 'completed', 0, 1),
(54, 20, 9, '2025-07-17 08:48:36', '2025-07-17 08:48:45', 40.00, 5.00, 'completed', 0, 1),
(67, 20, 9, '2025-07-19 07:23:22', '2025-07-19 07:23:40', 100.00, 3.00, 'completed', 1, 1),
(68, 22, 11, '2025-07-19 09:36:17', '2025-07-19 09:36:28', 33.00, 3.00, 'completed', 0, 0),
(69, 20, 16, '2025-07-20 07:49:08', '2025-07-20 07:49:29', 0.00, 10.00, 'completed', 0, 1),
(70, 20, 17, '2025-07-20 08:49:54', '2025-07-20 08:50:12', 0.00, 10.00, 'completed', 0, 1),
(71, 20, 17, '2025-07-20 08:50:25', '2025-07-20 08:50:39', 0.00, 10.00, 'completed', 0, 1),
(72, 20, 17, '2025-07-20 09:03:56', '2025-07-20 09:04:11', 0.00, 10.00, 'completed', 0, 1),
(73, 20, 17, '2025-07-20 09:04:36', '2025-07-20 09:04:50', 0.00, 10.00, 'completed', 0, 1),
(74, 20, 17, '2025-07-24 02:05:52', '2025-07-24 02:06:31', 10.00, 10.00, 'completed', 0, 1),
(75, 20, 17, '2025-07-24 02:08:02', '2025-07-24 02:08:28', 10.00, 10.00, 'completed', 0, 1),
(76, 20, 17, '2025-07-24 02:09:03', '2025-07-24 02:09:27', 10.00, 10.00, 'completed', 0, 1),
(77, 5, 17, '2025-07-24 04:38:51', '2025-07-24 04:39:04', 0.00, 10.00, 'completed', 0, 0),
(78, 20, 17, '2025-07-31 16:05:37', '2025-07-31 16:06:13', 10.00, 10.00, 'completed', 0, 1),
(79, 20, 17, '2025-07-31 16:14:25', '2025-07-31 16:15:25', 0.00, 10.00, 'completed', 0, 1),
(80, 20, 17, '2025-08-07 04:18:22', '2025-08-07 04:19:07', 10.00, 10.00, 'completed', 0, 1),
(81, 20, 16, '2025-08-07 06:54:34', '2025-08-07 06:55:12', 0.00, 10.00, 'completed', 0, 1),
(82, 20, 17, '2025-08-07 07:00:18', '2025-08-07 07:00:49', 60.00, 10.00, 'completed', 0, 1),
(83, 20, 17, '2025-08-09 09:31:15', '2025-08-09 09:31:54', 80.00, 10.00, 'completed', 1, 1),
(84, 20, 17, '2025-08-09 09:35:41', '2025-08-09 09:36:07', 80.00, 10.00, 'completed', 1, 1),
(85, 20, 17, '2025-08-09 09:41:03', '2025-08-09 09:42:03', 0.00, 10.00, 'completed', 0, 1),
(86, 20, 18, '2025-08-09 09:47:15', '2025-08-09 09:47:51', 100.00, 10.00, 'completed', 1, 1),
(87, 20, 18, '2025-08-09 09:55:08', '2025-08-09 09:55:29', 100.00, 10.00, 'completed', 1, 1),
(88, 20, 18, '2025-08-09 10:01:44', '2025-08-09 10:02:07', 100.00, 10.00, 'completed', 1, 1),
(89, 20, 17, '2025-08-21 07:54:37', '2025-08-21 07:55:18', 100.00, 10.00, 'completed', 1, 1),
(90, 20, 20, '2025-08-27 11:46:59', '2025-08-27 11:47:15', 0.00, 4.00, 'completed', 0, 0),
(91, 20, 20, '2025-08-27 11:47:37', '2025-08-27 11:47:49', 0.00, 4.00, 'completed', 0, 0),
(92, 20, 20, '2025-08-27 12:00:05', '2025-08-27 12:00:11', 0.00, 4.00, 'completed', 0, 0),
(93, 20, 19, '2025-08-28 02:19:50', '2025-08-28 02:19:52', 0.00, 0.00, 'completed', 0, 0),
(94, 20, 19, '2025-08-28 02:19:55', '2025-08-28 02:20:02', 0.00, 0.00, 'completed', 0, 0),
(95, 20, 18, '2025-08-30 10:03:55', '2025-08-30 10:04:11', 80.00, 10.00, 'completed', 1, 1),
(96, 20, 16, '2025-08-30 11:17:27', '2025-08-30 11:17:42', 70.00, 10.00, 'completed', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` varchar(32) NOT NULL DEFAULT 'multiple_choice',
  `question_order` int(11) NOT NULL,
  `points` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_questions`
--

INSERT INTO `assessment_questions` (`id`, `assessment_id`, `question_text`, `question_type`, `question_order`, `points`, `created_at`) VALUES
(1, 1, 'What is the correct way to print \"Hello World\" in Python?', 'multiple_choice', 1, 1, '2025-06-24 23:16:43'),
(2, 1, 'Python is a compiled language.', 'true_false', 2, 1, '2025-06-24 23:16:43'),
(4, 2, 'Which data type is used to store whole numbers in Python?', 'multiple_choice', 1, 1, '2025-06-24 23:16:43'),
(5, 2, 'Strings are immutable in Python.', 'true_false', 2, 1, '2025-06-24 23:16:43'),
(6, 8, 'am i handsome', 'multiple_choice', 1, 10, '2025-07-08 03:53:52'),
(82, 11, 'd', 'multiple_choice', 1, 1, '2025-07-18 08:50:44'),
(83, 11, 'what is lizard', 'identification', 2, 1, '2025-07-18 08:51:13'),
(85, 11, 'd', 'true_false', 3, 1, '2025-07-18 08:58:43'),
(86, 9, 'bigay mo sakin____________', 'identification', 1, 1, '2025-07-19 07:21:38'),
(87, 9, 'papatayin ko si wise_______', 'identification', 2, 1, '2025-07-19 07:22:43'),
(88, 9, 'sabi nila jungle saber para________', 'identification', 3, 1, '2025-07-19 07:22:43'),
(89, 12, 'ss', 'identification', 1, 1, '2025-07-19 07:43:54'),
(90, 12, 'aa', 'identification', 2, 1, '2025-07-19 07:43:54'),
(91, 12, 'dd', 'identification', 3, 1, '2025-07-19 07:43:54'),
(92, 12, 'ff', 'identification', 4, 1, '2025-07-19 07:43:54'),
(93, 12, 'gg', 'identification', 5, 1, '2025-07-19 07:43:54'),
(94, 12, 'ww', 'identification', 6, 1, '2025-07-19 07:43:54'),
(95, 12, 'ee', 'identification', 7, 1, '2025-07-19 07:43:54'),
(96, 12, 'rr', 'identification', 8, 1, '2025-07-19 07:43:54'),
(97, 12, 'tt', 'identification', 9, 1, '2025-07-19 07:43:54'),
(98, 12, 'qq', 'identification', 10, 1, '2025-07-19 07:43:54'),
(99, 12, 'uu', 'identification', 11, 1, '2025-07-19 07:43:54'),
(140, 16, 'asd', 'multiple_choice', 1, 1, '2025-07-19 08:22:50'),
(141, 16, 'asd', 'multiple_choice', 2, 1, '2025-07-19 08:22:50'),
(142, 16, 'ff', 'multiple_choice', 3, 1, '2025-07-19 08:22:50'),
(143, 16, 's', 'multiple_choice', 4, 1, '2025-07-19 08:22:50'),
(145, 16, 's', 'multiple_choice', 5, 1, '2025-07-19 08:22:50'),
(146, 16, 'ds', 'multiple_choice', 6, 1, '2025-07-19 08:22:50'),
(147, 16, 's', 'multiple_choice', 7, 1, '2025-07-19 08:22:50'),
(148, 16, 'd', 'multiple_choice', 8, 1, '2025-07-19 08:22:50'),
(150, 16, 'asdf', 'identification', 9, 1, '2025-07-20 07:50:54'),
(151, 16, 'sasasaa', 'true_false', 10, 1, '2025-07-20 07:51:34'),
(152, 17, 'The brain of the computer.', 'identification', 1, 1, '2025-07-20 08:48:49'),
(153, 17, 'Temporary memory of a computer.', 'identification', 2, 1, '2025-07-20 08:48:49'),
(154, 17, 'Device used to print on paper.', 'identification', 3, 1, '2025-07-20 08:48:49'),
(155, 17, 'Which is used to store data permanently?', 'multiple_choice', 4, 1, '2025-07-20 08:48:49'),
(156, 17, 'Which device shows pictures and videos?', 'multiple_choice', 5, 1, '2025-07-20 08:48:49'),
(157, 17, 'What does “USB” stand for?', 'multiple_choice', 6, 1, '2025-07-20 08:48:49'),
(158, 17, 'Which device is used to hear sound from a computer?', 'multiple_choice', 7, 1, '2025-07-20 08:48:49'),
(159, 17, 'A mouse is used to move the pointer on the screen.', 'true_false', 8, 1, '2025-07-20 08:48:49'),
(160, 17, 'A keyboard is used for typing.', 'true_false', 9, 1, '2025-07-20 08:48:49'),
(162, 17, 'A printer is used to display images on a screen.', 'true_false', 10, 1, '2025-07-24 02:04:27'),
(163, 18, 'A', 'true_false', 1, 1, '2025-08-09 09:45:53'),
(164, 18, 'B', 'true_false', 2, 1, '2025-08-09 09:45:53'),
(165, 18, 'C', 'true_false', 3, 1, '2025-08-09 09:45:53'),
(166, 18, 'D', 'true_false', 4, 1, '2025-08-09 09:45:53'),
(167, 18, 'E', 'true_false', 5, 1, '2025-08-09 09:45:53'),
(168, 18, 'F', 'true_false', 6, 1, '2025-08-09 09:45:53'),
(169, 18, 'G', 'true_false', 7, 1, '2025-08-09 09:45:53'),
(170, 18, 'H', 'true_false', 8, 1, '2025-08-09 09:45:53'),
(171, 18, 'I', 'true_false', 9, 1, '2025-08-09 09:45:53'),
(172, 18, 'J', 'identification', 10, 1, '2025-08-09 09:45:53'),
(173, 20, 'What does the acronym CPU stand for in computing?', 'multiple_choice', 1, 1, '2025-08-27 11:45:43'),
(174, 20, 'Which protocol is primarily used for secure data transmission over the internet?', 'multiple_choice', 2, 1, '2025-08-27 11:45:43'),
(175, 20, 'A byte consists of 8 bits.', 'true_false', 3, 1, '2025-08-27 11:45:43'),
(176, 20, 'Cloud computing always requires an internet connection to access data.', 'true_false', 4, 1, '2025-08-27 11:45:43');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_question_answers`
--

CREATE TABLE `assessment_question_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_earned` decimal(5,2) DEFAULT 0.00,
  `feedback` text DEFAULT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `badge_name` varchar(50) NOT NULL,
  `badge_description` text DEFAULT NULL,
  `badge_icon` varchar(255) NOT NULL,
  `badge_type` enum('course_completion','high_score','participation') DEFAULT 'course_completion',
  `criteria` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `badge_name`, `badge_description`, `badge_icon`, `badge_type`, `criteria`, `created_at`) VALUES
(1, 'First Course Complete', 'Completed your first course', 'first_course.png', 'course_completion', '{\"courses_completed\": 1}', '2025-06-24 23:16:43'),
(2, 'High Achiever', 'Scored 90% or higher on an assessment', 'high_achiever.png', 'high_score', '{\"min_score\": 90}', '2025-06-24 23:16:43'),
(3, 'Perfect Score', 'Achieved 100% on an assessment', 'perfect_score.png', 'high_score', '{\"min_score\": 100}', '2025-06-24 23:16:43'),
(4, 'Course Master', 'Completed 5 courses', 'course_master.png', 'course_completion', '{\"courses_completed\": 5}', '2025-06-24 23:16:43'),
(5, 'First Assessment', 'Completed your first assessment', 'first_assessment.png', 'participation', '{\"assessments_taken\": 1}', '2025-07-08 04:43:46'),
(6, 'Assessment Taker', 'Completed 3 assessments', 'assessment_taker.png', 'participation', '{\"assessments_taken\": 3}', '2025-07-08 04:43:46'),
(7, 'Assessment Master', 'Completed 10 assessments', 'assessment_master.png', 'participation', '{\"assessments_taken\": 10}', '2025-07-08 04:43:46'),
(8, 'First Course Complete', 'Completed your first course successfully', 'first_course.png', 'course_completion', '{\"courses_completed\": 1}', '2025-07-08 04:43:46'),
(9, 'Course Master', 'Completed 5 courses', 'course_master.png', 'course_completion', '{\"courses_completed\": 5}', '2025-07-08 04:43:46'),
(10, 'Module Explorer', 'Completed 10 modules', 'module_explorer.png', 'course_completion', '{\"modules_completed\": 10}', '2025-07-08 04:43:46'),
(11, 'Perfect Score', 'Achieved a perfect score on an assessment', 'perfect_score.png', 'high_score', '{\"perfect_scores\": 1}', '2025-07-08 04:43:46'),
(12, 'High Achiever', 'Maintained an average score of 90% or higher', 'high_achiever.png', 'high_score', '{\"average_score\": 90}', '2025-07-08 04:43:46'),
(13, 'Video Watcher', 'Watched 20 video lessons', 'video_watcher.png', 'participation', '{\"videos_watched\": 20}', '2025-07-08 04:43:46'),
(14, 'Consistent Learner', 'Maintained consistent learning for 7 consecutive days', 'consistent_learner.png', 'participation', '{\"consecutive_days\": 7}', '2025-07-08 04:43:46'),
(15, 'First Assessment', 'Completed your first assessment', 'first_assessment.png', 'participation', '{\"assessments_taken\": 1}', '2025-07-08 04:44:03'),
(16, 'Assessment Taker', 'Completed 3 assessments', 'assessment_taker.png', 'participation', '{\"assessments_taken\": 3}', '2025-07-08 04:44:03'),
(17, 'Assessment Master', 'Completed 10 assessments', 'assessment_master.png', 'participation', '{\"assessments_taken\": 10}', '2025-07-08 04:44:03'),
(18, 'First Course Complete', 'Completed your first course successfully', 'first_course.png', 'course_completion', '{\"courses_completed\": 1}', '2025-07-08 04:44:03'),
(19, 'Course Master', 'Completed 5 courses', 'course_master.png', 'course_completion', '{\"courses_completed\": 5}', '2025-07-08 04:44:03'),
(20, 'Module Explorer', 'Completed 10 modules', 'module_explorer.png', 'course_completion', '{\"modules_completed\": 10}', '2025-07-08 04:44:03'),
(21, 'Perfect Score', 'Achieved a perfect score on an assessment', 'perfect_score.png', 'high_score', '{\"perfect_scores\": 1}', '2025-07-08 04:44:03'),
(22, 'High Achiever', 'Maintained an average score of 90% or higher', 'high_achiever.png', 'high_score', '{\"average_score\": 90}', '2025-07-08 04:44:03'),
(23, 'Video Watcher', 'Watched 20 video lessons', 'video_watcher.png', 'participation', '{\"videos_watched\": 20}', '2025-07-08 04:44:03'),
(24, 'Consistent Learner', 'Maintained consistent learning for 7 consecutive days', 'consistent_learner.png', 'participation', '{\"consecutive_days\": 7}', '2025-07-08 04:44:03'),
(25, 'First Assessment', 'Completed your first assessment', 'first_assessment.png', 'participation', '{\"assessments_taken\": 1}', '2025-07-08 04:44:09'),
(26, 'Assessment Taker', 'Completed 3 assessments', 'assessment_taker.png', 'participation', '{\"assessments_taken\": 3}', '2025-07-08 04:44:09'),
(27, 'Assessment Master', 'Completed 10 assessments', 'assessment_master.png', 'participation', '{\"assessments_taken\": 10}', '2025-07-08 04:44:09'),
(28, 'First Course Complete', 'Completed your first course successfully', 'first_course.png', 'course_completion', '{\"courses_completed\": 1}', '2025-07-08 04:44:09'),
(29, 'Course Master', 'Completed 5 courses', 'course_master.png', 'course_completion', '{\"courses_completed\": 5}', '2025-07-08 04:44:09'),
(30, 'Module Explorer', 'Completed 10 modules', 'module_explorer.png', 'course_completion', '{\"modules_completed\": 10}', '2025-07-08 04:44:09'),
(31, 'Perfect Score', 'Achieved a perfect score on an assessment', 'perfect_score.png', 'high_score', '{\"perfect_scores\": 1}', '2025-07-08 04:44:09'),
(32, 'High Achiever', 'Maintained an average score of 90% or higher', 'high_achiever.png', 'high_score', '{\"average_score\": 90}', '2025-07-08 04:44:09'),
(33, 'Video Watcher', 'Watched 20 video lessons', 'video_watcher.png', 'participation', '{\"videos_watched\": 20}', '2025-07-08 04:44:09'),
(34, 'Consistent Learner', 'Maintained consistent learning for 7 consecutive days', 'consistent_learner.png', 'participation', '{\"consecutive_days\": 7}', '2025-07-08 04:44:09'),
(35, 'Course Explorer', 'Complete your first course.', 'course_explorer.png', 'course_completion', '{\"courses_completed\": 1}', '2025-07-24 05:15:57'),
(36, 'Course Finisher', 'Complete 5 different courses.', 'course_finisher.png', 'course_completion', '{\"courses_completed\": 5}', '2025-07-24 05:15:57'),
(37, 'Module Master', 'Complete 10 modules.', 'module_master.png', 'course_completion', '{\"modules_completed\": 10}', '2025-07-24 05:15:57'),
(38, 'Quiz Veteran', 'Take 20 assessments.', 'quiz_veteran.png', 'participation', '{\"assessments_taken\": 20}', '2025-07-24 05:15:57'),
(39, 'Video Binger', 'Watch 50 different videos.', 'video_binger.png', 'participation', '{\"videos_watched\": 50}', '2025-07-24 05:15:57'),
(40, 'Top 10 Leaderboard', 'Reach the top 10 in the leaderboard.', 'top10_leaderboard.png', 'participation', '{\"leaderboard_rank\": 10}', '2025-07-24 05:15:57'),
(41, 'Progress Tracker', 'Check your progress page 10 times.', 'progress_tracker.png', 'participation', '{\"progress_checked\": 10}', '2025-07-24 05:15:57'),
(42, 'Profile Complete', 'Fill out all profile fields and upload a profile picture.', 'profile_complete.png', 'participation', '{\"profile_completed\": 1}', '2025-07-24 05:15:57'),
(43, 'Badge Collector', 'Earn 5 different badges.', 'badge_collector.png', 'participation', '{\"badges_earned\": 5}', '2025-07-24 05:15:57'),
(44, 'Comeback Kid', 'Improve your score by at least 30% on a retake.', 'comeback_kid.png', 'participation', '{\"improved_score\": 1}', '2025-07-24 05:15:57'),
(45, 'Night Owl', 'Complete an activity between 12am and 5am.', 'night_owl.png', 'participation', '{\"late_night_activity\": 1}', '2025-07-24 05:15:57');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `course_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `academic_year_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `year_level` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `title`, `course_code`, `description`, `teacher_id`, `status`, `academic_year_id`, `semester_id`, `is_archived`, `created_at`, `updated_at`, `year_level`) VALUES
(1, 'Introduction to Programming', 'Introduction to Programming', 'IT101', 'Basic programming concepts using Python', 2, 'active', 1, 1, 0, '2025-06-24 23:16:43', '2025-07-08 12:31:32', NULL),
(2, 'Web Development Fundamentals', 'Web Development Fundamentals', 'IT102', 'HTML, CSS, and JavaScript basics', 2, 'active', 1, 1, 0, '2025-06-24 23:16:43', '2025-07-08 12:31:32', NULL),
(3, 'Database Management Systems', 'Database Management Systems', 'IT103', 'SQL and database design principles', 2, 'active', 1, 1, 0, '2025-06-24 23:16:43', '2025-07-08 12:31:32', NULL),
(4, 'programming', NULL, 'PRO1', 'basic programming language', 6, 'active', 1, 1, 0, '2025-06-25 05:23:46', '2025-07-08 12:31:32', NULL),
(5, 'Security 101', NULL, 'SEC69', 'a', 6, 'active', 1, 1, 0, '2025-07-08 09:13:03', '2025-07-08 12:31:32', NULL),
(6, 'Ube', NULL, 'Halaya', 'malamlam', 6, 'active', 1, 1, 0, '2025-07-08 09:17:25', '2025-07-08 12:31:32', NULL),
(7, 'Entrepreneurship', NULL, 'ENTREP', 'business man', 6, 'active', 1, 1, 0, '2025-07-08 12:03:24', '2025-07-08 12:31:32', NULL),
(8, 'Filipinos', NULL, 'uhus', 'suhs', 11, 'active', 1, 2, 0, '2025-07-08 12:31:58', '2025-07-08 12:38:58', NULL),
(9, 'Rizal life', NULL, 'ITR', 'Buhay ni Rizal', 6, 'active', 1, 1, 0, '2025-07-15 03:27:34', '2025-07-15 03:27:34', NULL),
(10, 'History', NULL, 'HS27', 'a', 17, 'active', 1, 1, 0, '2025-07-15 06:33:42', '2025-07-15 06:33:42', NULL),
(11, 'Ethics', NULL, 'ITE01', '', 17, 'active', 1, 2, 0, '2025-07-15 07:14:19', '2025-07-15 07:14:19', '1'),
(12, 'Gen Math', NULL, 'ITGE01', '', 17, 'active', 1, 1, 0, '2025-07-15 07:17:26', '2025-07-15 07:17:26', '3'),
(13, 'Basic Computing', NULL, 'BC1', '', 17, 'active', 1, 1, 0, '2025-07-15 07:50:15', '2025-07-15 09:27:47', '2'),
(14, 'Arts Appreciation', NULL, 'ITAP01', '', 17, 'active', 1, 1, 0, '2025-07-19 10:01:33', '2025-07-19 10:01:33', '2'),
(15, 'Quantitative Methods', NULL, 'ITMS02', '', 17, 'active', 1, 1, 0, '2025-07-19 10:35:10', '2025-07-19 10:35:10', '3'),
(16, 'Web Digital Media', NULL, 'IT-WS06', '', 17, 'active', 1, 1, 0, '2025-08-21 07:50:35', '2025-08-21 07:50:35', '2');

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','completed','dropped') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_enrollments`
--

INSERT INTO `course_enrollments` (`id`, `student_id`, `course_id`, `enrolled_at`, `status`) VALUES
(1, 5, 2, '2025-06-25 02:12:08', 'active'),
(3, 7, 4, '2025-06-25 05:34:43', 'active'),
(4, 9, 4, '2025-06-25 05:41:51', 'active'),
(5, 5, 4, '2025-07-08 02:22:03', 'active'),
(6, 9, 14, '2025-08-28 04:38:28', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `course_modules`
--

CREATE TABLE `course_modules` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `module_title` varchar(100) NOT NULL,
  `module_description` text DEFAULT NULL,
  `module_order` int(11) NOT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `unlock_score` int(11) DEFAULT 70,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_modules`
--

INSERT INTO `course_modules` (`id`, `course_id`, `module_title`, `module_description`, `module_order`, `is_locked`, `unlock_score`, `created_at`) VALUES
(1, 1, 'Getting Started with Python', 'Introduction to Python programming language', 1, 0, 70, '2025-06-24 23:16:43'),
(2, 1, 'Variables and Data Types', 'Understanding variables and different data types', 2, 0, 70, '2025-06-24 23:16:43'),
(3, 1, 'Control Structures', 'Loops and conditional statements', 3, 1, 70, '2025-06-24 23:16:43'),
(4, 2, 'HTML Basics', 'Introduction to HTML markup', 1, 0, 70, '2025-06-24 23:16:43'),
(5, 2, 'CSS Styling', 'Cascading Style Sheets fundamentals', 2, 0, 70, '2025-06-24 23:16:43'),
(6, 3, 'Database Concepts', 'Introduction to database systems', 1, 0, 70, '2025-06-24 23:16:43'),
(7, 4, 'Module 1', '', 1, 0, 70, '2025-06-30 08:48:01'),
(8, 4, 'Module 2', '', 2, 0, 70, '2025-07-02 01:15:02'),
(10, 13, 'Practice 1', '', 1, 0, 70, '2025-07-15 11:09:11'),
(11, 13, 'Practice 2', '', 2, 0, 70, '2025-07-16 01:42:53'),
(12, 11, 'unaa 1', 'first', 1, 0, 70, '2025-07-17 05:52:32'),
(22, 13, 'pr1', '', 4, 0, 70, '2025-07-25 00:52:04'),
(25, 13, 'LMS  Sample Module', 'sample', 5, 0, 70, '2025-07-31 03:13:00'),
(26, 9, 'Module 1', 'd', 1, 0, 70, '2025-08-12 23:45:04'),
(27, 14, 'Module 1', '', 1, 0, 70, '2025-08-30 10:18:11');

-- --------------------------------------------------------

--
-- Table structure for table `course_sections`
--

CREATE TABLE `course_sections` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_sections`
--

INSERT INTO `course_sections` (`id`, `course_id`, `section_id`) VALUES
(4, 3, 5),
(1, 3, 6),
(21, 5, 2),
(5, 7, 5),
(2, 7, 6),
(24, 10, 14),
(6, 11, 5),
(3, 11, 6),
(23, 11, 14),
(22, 13, 14),
(19, 14, 10),
(20, 15, 2),
(25, 16, 14);

-- --------------------------------------------------------

--
-- Table structure for table `course_videos`
--

CREATE TABLE `course_videos` (
  `id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `video_title` varchar(100) NOT NULL,
  `video_description` text DEFAULT NULL,
  `video_file` varchar(255) NOT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `video_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_videos`
--

INSERT INTO `course_videos` (`id`, `module_id`, `video_title`, `video_description`, `video_file`, `video_url`, `video_order`, `created_at`) VALUES
(1, 7, 'Videos 1', 'a', '686492c8bc71a_WIN_20241203_11_51_35_Pro.mp4', NULL, 1, '2025-07-02 02:00:40'),
(2, 8, 'Videos 2', 'q', '686494a30c3a1_WIN_20241112_20_57_13_Pro.mp4', NULL, 1, '2025-07-02 02:08:35'),
(3, 10, 'Video 1', 'Link for team itik', '', 'https://www.youtube.com/watch?v=roDJnRCouH4', 1, '2025-07-15 11:24:26'),
(4, 10, 'The 1975 - About You', 'kanta', '', 'https://www.youtube.com/watch?v=tGv7CUutzqU&list=RDtGv7CUutzqU&start_radio=1', 2, '2025-07-17 07:36:48'),
(5, 10, 'Sample Video LMS', 'sample', '', 'https://drive.google.com/file/d/1DgcVmkkg5aBHUD5VFMPPwGq95OwKK5Px/view?usp=drive_link', 3, '2025-07-31 03:10:41');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','completed','dropped') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `enrolled_at`, `status`) VALUES
(1, 3, 1, '2025-06-24 23:16:43', 'active'),
(2, 3, 2, '2025-06-24 23:16:43', 'active'),
(3, 5, 4, '2025-07-08 03:03:01', 'active'),
(4, 20, 13, '2025-07-16 08:40:55', 'active'),
(6, 20, 10, '2025-07-17 08:18:02', 'active'),
(7, 22, 11, '2025-07-19 09:34:12', 'active'),
(8, 22, 12, '2025-07-19 10:02:21', 'active'),
(9, 22, 13, '2025-07-19 10:07:01', 'active'),
(10, 22, 14, '2025-07-19 10:24:24', 'active'),
(11, 20, 12, '2025-07-19 10:27:47', 'active'),
(12, 22, 15, '2025-07-19 10:39:50', 'active'),
(13, 5, 13, '2025-07-24 03:58:19', 'active'),
(14, 20, 11, '2025-07-31 15:09:49', 'active'),
(16, 20, 16, '2025-08-21 07:53:12', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_requests`
--

CREATE TABLE `enrollment_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollment_requests`
--

INSERT INTO `enrollment_requests` (`id`, `student_id`, `course_id`, `requested_at`, `status`, `approved_at`, `approved_by`, `rejection_reason`) VALUES
(4, 20, 9, '2025-07-19 09:08:23', 'pending', NULL, NULL, NULL),
(5, 20, 12, '2025-07-19 09:09:52', 'approved', '2025-07-19 10:27:47', 17, NULL),
(6, 21, 11, '2025-07-19 09:24:48', 'rejected', '2025-07-19 10:19:48', 17, 'fggggggg'),
(7, 22, 12, '2025-07-19 09:55:38', 'approved', '2025-07-19 10:02:21', 17, NULL),
(8, 22, 14, '2025-07-19 10:24:01', 'approved', '2025-07-19 10:24:24', 17, 'yes wrong request'),
(9, 22, 15, '2025-07-19 10:39:27', 'approved', '2025-07-19 10:39:50', 17, NULL),
(10, 20, 15, '2025-08-28 03:45:18', 'rejected', '2025-08-28 03:45:39', 17, ''),
(11, 22, 7, '2025-07-24 01:59:25', 'pending', NULL, NULL, NULL),
(12, 22, 5, '2025-07-24 11:56:03', 'pending', NULL, NULL, NULL),
(13, 20, 11, '2025-07-31 07:03:27', 'approved', '2025-07-31 15:09:55', 17, NULL),
(14, 20, 5, '2025-07-31 15:08:56', 'pending', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(100) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `email`, `attempt_time`, `success`, `user_agent`) VALUES
(4, '::1', 'salvador@gmail.com', '2025-08-29 10:07:38', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(5, '::1', 'kurumi@gmail.com', '2025-08-29 10:20:34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(6, '::1', 'kurumi@gmail.com', '2025-08-29 10:20:46', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(10, '::1', 'kurumi@gmail.com', '2025-08-29 10:24:06', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(11, '::1', 'kurumi@gmail.com', '2025-08-29 10:35:40', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(15, '::1', 'kurumi@gmail.com', '2025-08-29 10:37:23', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(16, '::1', 'chama@gmail.com', '2025-08-29 12:07:23', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(17, '::1', 'kurumi@gmail.com', '2025-08-29 12:34:40', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(18, '::1', 'kurumi@gmail.com', '2025-08-30 10:00:14', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(19, '::1', 'chama@gmail.com', '2025-08-30 10:00:22', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(20, '::1', 'salvador@gmail.com', '2025-08-30 10:02:30', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `order_number` int(11) NOT NULL DEFAULT 1,
  `is_locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `course_id`, `title`, `description`, `order_number`, `is_locked`, `created_at`, `updated_at`) VALUES
(1, 1, 'Getting Started with Python', 'Introduction to Python programming language', 1, 0, '2025-06-24 23:16:43', '2025-06-25 01:30:48'),
(2, 1, 'Variables and Data Types', 'Understanding variables and different data types', 2, 0, '2025-06-24 23:16:43', '2025-06-25 01:30:48'),
(3, 1, 'Control Structures', 'Loops and conditional statements', 3, 1, '2025-06-24 23:16:43', '2025-06-25 01:30:48'),
(4, 2, 'HTML Basics', 'Introduction to HTML markup', 1, 0, '2025-06-24 23:16:43', '2025-06-25 01:30:48'),
(5, 2, 'CSS Styling', 'Cascading Style Sheets fundamentals', 2, 0, '2025-06-24 23:16:43', '2025-06-25 01:30:48'),
(6, 3, 'Database Concepts', 'Introduction to database systems', 1, 0, '2025-06-24 23:16:43', '2025-06-25 01:30:48');

-- --------------------------------------------------------

--
-- Table structure for table `module_files`
--

CREATE TABLE `module_files` (
  `id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module_files`
--

INSERT INTO `module_files` (`id`, `module_id`, `file_name`, `file_path`, `uploaded_at`) VALUES
(10, 22, 'SPES-2024-ACCOMPLISHMENT-REPORT.docx', '../uploads/modules/1753404724_SPES-2024-ACCOMPLISHMENT-REPORT.docx', '2025-07-25 08:52:04'),
(13, 25, 'Learning Management System of NUEST - MGT BSIT Program.pptx', '../uploads/modules/1753931580_Learning Management System of NUEST - MGT BSIT Program.pptx', '2025-07-31 11:13:00'),
(14, 26, 'midterm-pratical.docx', '../uploads/modules/1755042304_midterm-pratical.docx', '2025-08-13 07:45:04');

-- --------------------------------------------------------

--
-- Table structure for table `module_progress`
--

CREATE TABLE `module_progress` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `last_accessed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `module_progress`
--

INSERT INTO `module_progress` (`id`, `student_id`, `module_id`, `is_completed`, `progress_percentage`, `started_at`, `completed_at`, `last_accessed`) VALUES
(1, 5, 7, 1, 0.00, '2025-07-08 04:23:22', '2025-07-08 04:23:22', '2025-07-08 04:23:22'),
(2, 5, 8, 1, 0.00, '2025-07-08 04:23:27', '2025-07-08 04:23:27', '2025-07-08 04:23:27'),
(4, 20, 10, 1, 0.00, '2025-07-19 07:36:14', '2025-07-19 07:36:14', '2025-07-19 07:36:14');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('enrollment_rejected','enrollment_approved','general') NOT NULL DEFAULT 'general',
  `related_id` int(11) DEFAULT NULL COMMENT 'Related ID (e.g., enrollment_request_id)',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_id`, `is_read`, `created_at`) VALUES
(1, 21, 'Enrollment Request Rejected', 'Your enrollment request for course \'Ethics\' has been rejected. Reason: fggggggg You can request enrollment again if needed.', 'enrollment_rejected', 6, 1, '2025-07-19 10:19:48'),
(2, 22, 'Enrollment Request Approved', 'Your enrollment request for course \'Arts Appreciation\' has been approved! You can now access the course.', 'enrollment_approved', 8, 1, '2025-07-19 10:24:24'),
(3, 20, 'Enrollment Request Approved', 'Your enrollment request for course \'Gen Math\' has been approved! You can now access the course.', 'enrollment_approved', 5, 0, '2025-07-19 10:27:47'),
(4, 22, 'Enrollment Request Approved', 'Your enrollment request for course \'Quantitative Methods\' has been approved! You can now access the course.', 'enrollment_approved', 9, 0, '2025-07-19 10:39:50'),
(5, 20, 'Enrollment Request Approved', 'Your enrollment request for course \'Ethics\' has been approved! You can now access the course.', 'enrollment_approved', 13, 0, '2025-07-31 15:09:49'),
(6, 20, 'Enrollment Request Rejected', 'Your enrollment request for course \'Quantitative Methods\' has been rejected. Reason: jsjhdhshjhs You can request enrollment again if needed.', 'enrollment_rejected', 10, 0, '2025-07-31 15:10:23'),
(7, 20, 'Enrollment Request Rejected', 'Your enrollment request for course \'Quantitative Methods\' has been rejected. Reason: jsjhdhshjhs You can request enrollment again if needed.', 'enrollment_rejected', 10, 0, '2025-07-31 15:10:27'),
(8, 20, 'Enrollment Request Rejected', 'Your enrollment request for course \'Quantitative Methods\' has been rejected. Reason: jsjhdhshjhs You can request enrollment again if needed.', 'enrollment_rejected', 10, 0, '2025-07-31 15:10:32'),
(9, 20, 'Enrollment Request Rejected', 'Your enrollment request for course \'Quantitative Methods\' has been rejected. You can request enrollment again if needed.', 'enrollment_rejected', 10, 0, '2025-08-28 03:45:39');

-- --------------------------------------------------------

--
-- Table structure for table `question_options`
--

CREATE TABLE `question_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `question_options`
--

INSERT INTO `question_options` (`id`, `question_id`, `option_text`, `is_correct`, `option_order`) VALUES
(1, 1, 'print(\"Hello World\")', 1, 1),
(2, 1, 'echo \"Hello World\"', 0, 2),
(3, 1, 'console.log(\"Hello World\")', 0, 3),
(4, 1, 'printf(\"Hello World\")', 0, 4),
(5, 2, 'True', 0, 1),
(6, 2, 'False', 1, 2),
(7, 4, 'int', 1, 1),
(8, 4, 'float', 0, 2),
(9, 4, 'string', 0, 3),
(10, 4, 'boolean', 0, 4),
(11, 5, 'True', 1, 1),
(12, 5, 'False', 0, 2),
(13, 6, 'no', 0, 1),
(14, 6, 'yes', 0, 2),
(15, 6, 'maybe', 0, 3),
(16, 6, 'definitely', 1, 4),
(162, 82, 'g', 0, 1),
(163, 82, 'h', 1, 2),
(164, 82, 'u', 0, 3),
(165, 82, 'k', 0, 4),
(168, 83, 'BUTIKII', 1, 1),
(169, 85, 'True', 0, 1),
(170, 85, 'False', 1, 2),
(171, 86, 'SABER', 1, 1),
(172, 87, 'LEVEL 4', 1, 1),
(173, 88, 'MAS MASAKET', 1, 1),
(174, 89, 'SS', 1, 1),
(175, 90, 'AA', 1, 1),
(176, 91, 'DD', 1, 1),
(177, 92, 'FF', 1, 1),
(178, 93, 'GG', 1, 1),
(179, 94, 'WW', 1, 1),
(180, 95, 'EE', 1, 1),
(181, 96, 'RR', 1, 1),
(182, 97, 'TT', 1, 1),
(183, 98, 'QQ', 1, 1),
(184, 99, 'UU', 1, 1),
(244, 140, 'a', 1, 1),
(245, 140, 'a', 0, 2),
(246, 141, 'ss', 1, 1),
(247, 141, 'dd', 0, 2),
(248, 142, 's', 1, 1),
(249, 142, 'd', 0, 2),
(250, 143, 's', 1, 1),
(251, 143, 'd', 0, 2),
(254, 145, 'd', 1, 1),
(255, 145, 's', 0, 2),
(256, 146, 'd', 1, 1),
(257, 146, 's', 0, 2),
(258, 147, 'd', 1, 1),
(259, 147, 'd', 0, 2),
(260, 148, 'd', 1, 1),
(261, 148, 'sd', 0, 2),
(264, 150, 'AS', 1, 1),
(271, 151, 'True', 0, 1),
(272, 151, 'False', 1, 2),
(296, 163, 'True', 1, 1),
(297, 163, 'False', 0, 2),
(298, 164, 'True', 1, 1),
(299, 164, 'False', 0, 2),
(300, 165, 'True', 1, 1),
(301, 165, 'False', 0, 2),
(302, 166, 'True', 1, 1),
(303, 166, 'False', 0, 2),
(304, 167, 'True', 1, 1),
(305, 167, 'False', 0, 2),
(306, 168, 'True', 1, 1),
(307, 168, 'False', 0, 2),
(308, 169, 'True', 1, 1),
(309, 169, 'False', 0, 2),
(310, 170, 'True', 1, 1),
(311, 170, 'False', 0, 2),
(312, 171, 'True', 1, 1),
(313, 171, 'False', 0, 2),
(314, 172, 'J', 1, 1),
(315, 152, 'CPU', 1, 1),
(316, 153, 'RAM', 1, 1),
(317, 154, 'PRINTER', 1, 1),
(322, 155, 'RAM', 0, 1),
(323, 155, 'Hard Drive', 1, 2),
(324, 155, 'Mouse', 0, 3),
(325, 155, 'Monitor', 0, 4),
(326, 156, 'Monitor', 1, 1),
(327, 156, 'Keyboard', 0, 2),
(328, 156, 'Printer', 0, 3),
(329, 156, 'CPU', 0, 4),
(330, 157, 'Unlimited Storage Box', 1, 1),
(331, 157, 'United Software Base', 0, 2),
(332, 157, 'Universal System Board', 0, 3),
(333, 157, 'Universal Serial Bus', 0, 4),
(338, 158, 'Keyboard', 0, 1),
(339, 158, 'Mouse', 0, 2),
(340, 158, 'Speaker', 1, 3),
(341, 158, 'Monitor', 0, 4),
(342, 159, 'True', 1, 1),
(343, 159, 'False', 0, 2),
(344, 160, 'True', 1, 1),
(345, 160, 'False', 0, 2),
(346, 162, 'True', 0, 1),
(347, 162, 'False', 1, 2),
(348, 173, 'Central Processing Unit', 1, 1),
(349, 173, ' Computer Power Unit', 0, 2),
(350, 173, 'Control Processing Unit', 0, 3),
(351, 173, 'Core Programming Unit', 0, 4),
(352, 174, 'FTP', 0, 1),
(353, 174, 'HTTP', 0, 2),
(354, 174, 'HTTPS', 1, 3),
(355, 175, 'True', 1, 1),
(356, 175, 'False', 0, 2),
(357, 176, 'True', 0, 1),
(358, 176, 'False', 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `registration_tokens`
--

CREATE TABLE `registration_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `role` enum('admin') NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `year`, `name`, `description`, `is_active`) VALUES
(2, 3, 'E', 'EE dolo', 1),
(3, 2, 'A', 'Section A from 2nd year', 1),
(4, 3, 'E', 'Section E from 3rd year', 1),
(5, 1, 'A', '', 1),
(6, 4, 'E', '', 1),
(10, 2, 'E', '', 1),
(12, 1, 'B', '', 1),
(13, 1, 'C', '', 1),
(14, 2, 'Z', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `section_students`
--

CREATE TABLE `section_students` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section_students`
--

INSERT INTO `section_students` (`id`, `section_id`, `student_id`) VALUES
(5, 3, 3),
(6, 3, 7),
(32, 12, 10),
(33, 10, 10),
(34, 13, 10),
(40, 6, 9),
(41, 6, 7),
(63, 5, 23),
(64, 10, 24),
(65, 14, 22),
(66, 14, 10),
(67, 14, 5),
(68, 14, 21),
(69, 14, 20),
(70, 14, 16);

-- --------------------------------------------------------

--
-- Table structure for table `section_teachers`
--

CREATE TABLE `section_teachers` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section_teachers`
--

INSERT INTO `section_teachers` (`id`, `section_id`, `teacher_id`) VALUES
(3, 3, 6),
(14, 12, 17),
(16, 10, 17),
(17, 13, 17),
(18, 5, 11),
(19, 5, 17),
(23, 6, 17),
(28, 14, 17);

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`id`, `academic_year_id`, `name`, `is_active`, `created_at`) VALUES
(1, 1, '2nd Semester', 1, '2025-07-08 10:56:39'),
(2, 1, '1st Semester', 0, '2025-07-08 11:04:20'),
(3, 2, '1st Semester', 0, '2025-07-08 11:10:11'),
(4, 2, '2nd Semester', 0, '2025-07-08 11:10:20'),
(5, 3, '1st Semester', 0, '2025-07-15 03:24:06');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `essay_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_earned` decimal(5,2) DEFAULT 0.00,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `student_answer` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_answers`
--

INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_option_id`, `essay_answer`, `is_correct`, `points_earned`, `answered_at`, `student_answer`) VALUES
(2, 7, 6, 13, NULL, NULL, 0.00, '2025-07-08 03:56:17', NULL),
(3, 8, 6, 16, NULL, NULL, 0.00, '2025-07-08 03:56:36', NULL),
(4, 9, 6, 16, NULL, NULL, 0.00, '2025-07-08 04:06:43', NULL),
(5, 10, 6, 16, NULL, NULL, 0.00, '2025-07-08 04:12:01', NULL),
(6, 11, 6, 16, NULL, NULL, 0.00, '2025-07-08 04:17:35', NULL),
(7, 12, 6, NULL, NULL, NULL, 0.00, '2025-07-08 04:22:21', NULL),
(180, 67, 86, NULL, NULL, NULL, 0.00, '2025-07-19 07:23:22', 'saber'),
(181, 67, 87, NULL, NULL, NULL, 0.00, '2025-07-19 07:23:22', 'level 4'),
(182, 67, 88, NULL, NULL, NULL, 0.00, '2025-07-19 07:23:22', 'mas masaket'),
(183, 68, 82, 163, NULL, NULL, 0.00, '2025-07-19 09:36:17', '2'),
(184, 68, 83, NULL, NULL, NULL, 0.00, '2025-07-19 09:36:17', 'butikii'),
(185, 68, 85, 170, NULL, NULL, 0.00, '2025-07-19 09:36:17', '2'),
(186, 69, 140, 244, NULL, NULL, 0.00, '2025-07-20 07:49:08', '1'),
(187, 69, 141, 246, NULL, NULL, 0.00, '2025-07-20 07:49:08', '1'),
(188, 69, 142, 248, NULL, NULL, 0.00, '2025-07-20 07:49:08', '1'),
(189, 69, 143, 250, NULL, NULL, 0.00, '2025-07-20 07:49:08', '1'),
(191, 69, 145, 254, NULL, NULL, 0.00, '2025-07-20 07:49:08', '1'),
(192, 69, 146, 256, NULL, NULL, 0.00, '2025-07-20 07:49:08', '1'),
(193, 69, 147, 258, NULL, NULL, 0.00, '2025-07-20 07:49:08', '1'),
(194, 69, 148, 260, NULL, NULL, 0.00, '2025-07-20 07:49:08', '1'),
(196, 70, 152, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(197, 70, 153, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(198, 70, 154, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(199, 70, 155, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(200, 70, 156, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(201, 70, 157, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(202, 70, 158, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(203, 70, 159, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(204, 70, 160, NULL, NULL, NULL, 0.00, '2025-07-20 08:49:54', '1'),
(206, 71, 152, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(207, 71, 153, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(208, 71, 154, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(209, 71, 155, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(210, 71, 156, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(211, 71, 157, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(212, 71, 158, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(213, 71, 159, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(214, 71, 160, NULL, NULL, NULL, 0.00, '2025-07-20 08:50:25', '2'),
(216, 72, 152, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(217, 72, 153, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(218, 72, 154, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(219, 72, 155, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(220, 72, 156, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(221, 72, 157, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(222, 72, 158, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(223, 72, 159, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(224, 72, 160, NULL, NULL, NULL, 0.00, '2025-07-20 09:03:56', '2'),
(226, 73, 152, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(227, 73, 153, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(228, 73, 154, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(229, 73, 155, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(230, 73, 156, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(231, 73, 157, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(232, 73, 158, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(233, 73, 159, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(234, 73, 160, NULL, NULL, NULL, 0.00, '2025-07-20 09:04:36', '1'),
(236, 74, 152, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:52', '1'),
(237, 74, 153, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:52', '1'),
(238, 74, 154, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:52', '1'),
(239, 74, 155, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:52', '2'),
(240, 74, 156, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:52', '2'),
(241, 74, 157, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:52', '2'),
(242, 74, 158, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:52', '2'),
(243, 74, 159, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:52', '2'),
(244, 74, 160, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:53', '1'),
(245, 74, 162, NULL, NULL, NULL, 0.00, '2025-07-24 02:05:53', 'oo ngaa'),
(246, 75, 152, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '1'),
(247, 75, 153, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '1'),
(248, 75, 154, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '1'),
(249, 75, 155, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '1'),
(250, 75, 156, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '1'),
(251, 75, 157, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '1'),
(252, 75, 158, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '1'),
(253, 75, 159, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '1'),
(254, 75, 160, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', '2'),
(255, 75, 162, NULL, NULL, NULL, 0.00, '2025-07-24 02:08:02', 'oo ngaa'),
(256, 76, 152, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(257, 76, 153, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(258, 76, 154, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(259, 76, 155, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(260, 76, 156, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(261, 76, 157, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(262, 76, 158, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(263, 76, 159, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(264, 76, 160, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', '1'),
(265, 76, 162, NULL, NULL, NULL, 0.00, '2025-07-24 02:09:03', 'oo ngaa'),
(266, 77, 152, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(267, 77, 153, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(268, 77, 154, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(269, 77, 155, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(270, 77, 156, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(271, 77, 157, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(272, 77, 158, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(273, 77, 159, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(274, 77, 160, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(275, 77, 162, NULL, NULL, NULL, 0.00, '2025-07-24 04:38:51', ''),
(276, 78, 152, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(277, 78, 153, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(278, 78, 154, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(279, 78, 155, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(280, 78, 156, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(281, 78, 157, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(282, 78, 158, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(283, 78, 159, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(284, 78, 160, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', '1'),
(285, 78, 162, NULL, NULL, NULL, 0.00, '2025-07-31 16:05:37', 'oo ngaa'),
(286, 79, 152, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', '1'),
(287, 79, 153, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', '1'),
(288, 79, 154, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', '1'),
(289, 79, 155, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', '1'),
(290, 79, 156, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', '1'),
(291, 79, 157, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', ''),
(292, 79, 158, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', ''),
(293, 79, 159, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', ''),
(294, 79, 160, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', ''),
(295, 79, 162, NULL, NULL, NULL, 0.00, '2025-07-31 16:14:25', ''),
(296, 80, 152, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(297, 80, 153, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(298, 80, 154, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(299, 80, 155, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(300, 80, 156, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(301, 80, 157, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(302, 80, 158, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(303, 80, 159, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(304, 80, 160, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', '1'),
(305, 80, 162, NULL, NULL, NULL, 0.00, '2025-08-07 04:18:22', 'oo ngaa'),
(306, 81, 140, 244, NULL, NULL, 0.00, '2025-08-07 06:54:34', '1'),
(307, 81, 141, 246, NULL, NULL, 0.00, '2025-08-07 06:54:34', '1'),
(308, 81, 142, 248, NULL, NULL, 0.00, '2025-08-07 06:54:34', '1'),
(309, 81, 143, 251, NULL, NULL, 0.00, '2025-08-07 06:54:34', '2'),
(310, 81, 145, 254, NULL, NULL, 0.00, '2025-08-07 06:54:34', '1'),
(311, 81, 146, 257, NULL, NULL, 0.00, '2025-08-07 06:54:34', '2'),
(312, 81, 147, 259, NULL, NULL, 0.00, '2025-08-07 06:54:34', '2'),
(313, 81, 148, 260, NULL, NULL, 0.00, '2025-08-07 06:54:34', '1'),
(314, 81, 150, NULL, NULL, NULL, 0.00, '2025-08-07 06:54:34', 'sks'),
(315, 81, 151, 271, NULL, NULL, 0.00, '2025-08-07 06:54:34', '1'),
(316, 82, 152, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '1'),
(317, 82, 153, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '2'),
(318, 82, 154, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '1'),
(319, 82, 155, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '2'),
(320, 82, 156, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '1'),
(321, 82, 157, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '1'),
(322, 82, 158, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '1'),
(323, 82, 159, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '1'),
(324, 82, 160, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', '1'),
(325, 82, 162, NULL, NULL, NULL, 0.00, '2025-08-07 07:00:18', 'ljlhjlh'),
(326, 83, 152, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '1'),
(327, 83, 153, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '1'),
(328, 83, 154, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '1'),
(329, 83, 155, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '2'),
(330, 83, 156, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '1'),
(331, 83, 157, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '1'),
(332, 83, 158, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '1'),
(333, 83, 159, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '1'),
(334, 83, 160, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', '1'),
(335, 83, 162, NULL, NULL, NULL, 0.00, '2025-08-09 09:31:15', 'oo ngaa'),
(336, 84, 157, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', '1'),
(337, 84, 153, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', '1'),
(338, 84, 162, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', 'oo ngaa'),
(339, 84, 159, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', '1'),
(340, 84, 155, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', '1'),
(341, 84, 158, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', '1'),
(342, 84, 156, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', '1'),
(343, 84, 154, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', ''),
(344, 84, 160, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', '1'),
(345, 84, 152, NULL, NULL, NULL, 0.00, '2025-08-09 09:35:41', '1'),
(346, 85, 154, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(347, 85, 162, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(348, 85, 160, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(349, 85, 156, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(350, 85, 153, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(351, 85, 157, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(352, 85, 155, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(353, 85, 158, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(354, 85, 159, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(355, 85, 152, NULL, NULL, NULL, 0.00, '2025-08-09 09:41:03', ''),
(356, 86, 168, 306, NULL, NULL, 0.00, '2025-08-09 09:47:15', '1'),
(357, 86, 165, 300, NULL, NULL, 0.00, '2025-08-09 09:47:15', '1'),
(358, 86, 164, 298, NULL, NULL, 0.00, '2025-08-09 09:47:15', '1'),
(359, 86, 169, 308, NULL, NULL, 0.00, '2025-08-09 09:47:15', '1'),
(360, 86, 170, 310, NULL, NULL, 0.00, '2025-08-09 09:47:15', '1'),
(361, 86, 171, 312, NULL, NULL, 0.00, '2025-08-09 09:47:15', '1'),
(362, 86, 163, 296, NULL, NULL, 0.00, '2025-08-09 09:47:16', '1'),
(363, 86, 167, 304, NULL, NULL, 0.00, '2025-08-09 09:47:16', '1'),
(364, 86, 166, 302, NULL, NULL, 0.00, '2025-08-09 09:47:16', '1'),
(365, 86, 172, NULL, NULL, NULL, 0.00, '2025-08-09 09:47:16', 'j'),
(366, 87, 164, 298, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(367, 87, 170, 310, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(368, 87, 171, 312, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(369, 87, 168, 306, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(370, 87, 169, 308, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(371, 87, 167, 304, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(372, 87, 166, 302, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(373, 87, 165, 300, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(374, 87, 163, 296, NULL, NULL, 0.00, '2025-08-09 09:55:08', '1'),
(375, 87, 172, NULL, NULL, NULL, 0.00, '2025-08-09 09:55:08', 'J'),
(376, 88, 168, 306, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(377, 88, 171, 312, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(378, 88, 170, 310, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(379, 88, 163, 296, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(380, 88, 169, 308, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(381, 88, 165, 300, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(382, 88, 172, NULL, NULL, NULL, 0.00, '2025-08-09 10:01:44', 'J'),
(383, 88, 164, 298, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(384, 88, 166, 302, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(385, 88, 167, 304, NULL, NULL, 0.00, '2025-08-09 10:01:44', '1'),
(386, 89, 157, 330, NULL, NULL, 0.00, '2025-08-21 07:54:37', '1'),
(387, 89, 160, 344, NULL, NULL, 0.00, '2025-08-21 07:54:37', '1'),
(388, 89, 159, 342, NULL, NULL, 0.00, '2025-08-21 07:54:37', '1'),
(389, 89, 152, NULL, NULL, NULL, 0.00, '2025-08-21 07:54:37', 'CPU'),
(390, 89, 158, 340, NULL, NULL, 0.00, '2025-08-21 07:54:37', '3'),
(391, 89, 154, NULL, NULL, NULL, 0.00, '2025-08-21 07:54:37', 'printer'),
(392, 89, 155, 323, NULL, NULL, 0.00, '2025-08-21 07:54:37', '2'),
(393, 89, 156, 326, NULL, NULL, 0.00, '2025-08-21 07:54:37', '1'),
(394, 89, 162, 347, NULL, NULL, 0.00, '2025-08-21 07:54:37', '2'),
(395, 89, 153, NULL, NULL, NULL, 0.00, '2025-08-21 07:54:37', 'Ram'),
(396, 90, 174, 353, NULL, NULL, 0.00, '2025-08-27 11:46:59', '2'),
(397, 90, 175, 356, NULL, NULL, 0.00, '2025-08-27 11:46:59', '2'),
(398, 90, 176, 357, NULL, NULL, 0.00, '2025-08-27 11:46:59', '1'),
(399, 90, 173, 349, NULL, NULL, 0.00, '2025-08-27 11:46:59', '2'),
(400, 91, 175, 356, NULL, NULL, 0.00, '2025-08-27 11:47:37', '2'),
(401, 91, 173, 349, NULL, NULL, 0.00, '2025-08-27 11:47:37', '2'),
(402, 91, 176, 357, NULL, NULL, 0.00, '2025-08-27 11:47:37', '1'),
(403, 91, 174, 352, NULL, NULL, 0.00, '2025-08-27 11:47:37', '1'),
(404, 92, 174, NULL, NULL, NULL, 0.00, '2025-08-27 12:00:05', ''),
(405, 92, 173, NULL, NULL, NULL, 0.00, '2025-08-27 12:00:05', ''),
(406, 92, 176, NULL, NULL, NULL, 0.00, '2025-08-27 12:00:05', ''),
(407, 92, 175, NULL, NULL, NULL, 0.00, '2025-08-27 12:00:05', ''),
(408, 95, 167, 304, NULL, NULL, 0.00, '2025-08-30 10:03:55', '1'),
(409, 95, 163, 296, NULL, NULL, 0.00, '2025-08-30 10:03:55', '1'),
(410, 95, 170, 310, NULL, NULL, 0.00, '2025-08-30 10:03:55', '1'),
(411, 95, 165, 300, NULL, NULL, 0.00, '2025-08-30 10:03:55', '1'),
(412, 95, 171, 312, NULL, NULL, 0.00, '2025-08-30 10:03:55', '1'),
(413, 95, 166, 302, NULL, NULL, 0.00, '2025-08-30 10:03:55', '1'),
(414, 95, 172, NULL, NULL, NULL, 0.00, '2025-08-30 10:03:55', 'q'),
(415, 95, 169, 308, NULL, NULL, 0.00, '2025-08-30 10:03:55', '1'),
(416, 95, 168, 306, NULL, NULL, 0.00, '2025-08-30 10:03:55', '1'),
(417, 95, 164, 299, NULL, NULL, 0.00, '2025-08-30 10:03:55', '2'),
(418, 96, 142, 248, NULL, NULL, 0.00, '2025-08-30 11:17:27', '1'),
(419, 96, 140, 244, NULL, NULL, 0.00, '2025-08-30 11:17:27', '1'),
(420, 96, 143, 251, NULL, NULL, 0.00, '2025-08-30 11:17:27', '2'),
(421, 96, 146, 256, NULL, NULL, 0.00, '2025-08-30 11:17:27', '1'),
(422, 96, 141, 246, NULL, NULL, 0.00, '2025-08-30 11:17:27', '1'),
(423, 96, 150, NULL, NULL, NULL, 0.00, '2025-08-30 11:17:27', 'a'),
(424, 96, 151, 271, NULL, NULL, 0.00, '2025-08-30 11:17:27', '1'),
(425, 96, 147, 258, NULL, NULL, 0.00, '2025-08-30 11:17:27', '1'),
(426, 96, 148, 260, NULL, NULL, 0.00, '2025-08-30 11:17:27', '1'),
(427, 96, 145, 254, NULL, NULL, 0.00, '2025-08-30 11:17:27', '1');

-- --------------------------------------------------------

--
-- Table structure for table `student_badges`
--

CREATE TABLE `student_badges` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_badges`
--

INSERT INTO `student_badges` (`id`, `student_id`, `badge_id`, `awarded_at`) VALUES
(1, 5, 1, '2025-07-08 04:43:47'),
(2, 5, 5, '2025-07-08 04:43:47'),
(3, 5, 6, '2025-07-08 04:43:47'),
(4, 5, 7, '2025-07-08 04:43:47'),
(5, 5, 8, '2025-07-08 04:43:47'),
(6, 5, 11, '2025-07-08 04:43:47'),
(7, 5, 15, '2025-07-08 04:44:03'),
(8, 5, 16, '2025-07-08 04:44:03'),
(9, 5, 17, '2025-07-08 04:44:03'),
(10, 5, 18, '2025-07-08 04:44:03'),
(11, 5, 21, '2025-07-08 04:44:03'),
(12, 5, 25, '2025-07-08 04:44:09'),
(13, 5, 26, '2025-07-08 04:44:09'),
(14, 5, 27, '2025-07-08 04:44:09'),
(15, 5, 28, '2025-07-08 04:44:09'),
(16, 5, 31, '2025-07-08 04:44:09'),
(17, 20, 5, '2025-07-16 09:07:13'),
(18, 20, 11, '2025-07-16 09:07:13'),
(19, 20, 12, '2025-07-16 09:07:13'),
(20, 20, 15, '2025-07-16 09:07:13'),
(21, 20, 21, '2025-07-16 09:07:13'),
(22, 20, 22, '2025-07-16 09:07:13'),
(23, 20, 25, '2025-07-16 09:07:13'),
(24, 20, 31, '2025-07-16 09:07:13'),
(25, 20, 32, '2025-07-16 09:07:13'),
(26, 20, 6, '2025-07-16 09:13:25'),
(27, 20, 16, '2025-07-16 09:13:25'),
(28, 20, 26, '2025-07-16 09:13:25'),
(29, 20, 7, '2025-07-16 09:31:07'),
(30, 20, 17, '2025-07-16 09:31:07'),
(31, 20, 27, '2025-07-16 09:31:07'),
(32, 22, 5, '2025-07-19 09:36:17'),
(33, 22, 15, '2025-07-19 09:36:17'),
(34, 22, 25, '2025-07-19 09:36:17'),
(35, 20, 14, '2025-07-24 02:05:53'),
(36, 20, 24, '2025-07-24 02:05:53'),
(37, 20, 34, '2025-07-24 02:05:53'),
(38, 20, 38, '2025-07-31 16:05:37');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_irregular` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `name`, `student_id`, `role`, `status`, `profile_picture`, `created_at`, `updated_at`, `is_irregular`) VALUES
(2, 'teacher1', 'teacher@neust.edu.ph', '$2y$10$yv6ry0Kl8ZfJFldGwFsD0OpAXoxIkJMKBYvzZYg.Pujtaeu1JVqHO', 'John', 'Doe', 'John Doe', 'NEUST-MGT(TCH)-00001', 'teacher', 'active', NULL, '2025-06-24 23:16:43', '2025-08-10 09:01:28', 0),
(3, 'student1', 'student@neust.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', 'Jane Smith', 'NEUST-MGT(STD)-00001', 'student', 'active', NULL, '2025-06-24 23:16:43', '2025-08-10 09:01:28', 0),
(4, 'monin', 'salvador@gmail.com', '$2y$10$XUyt6RvHlG1cvtXTf8d0UOauBUhXjb0SgbxqSHpU9RNURA0hvf0Yi', 'Raymond', 'Salvador', 'Raymond Salvador', 'NEUST-MGT(ADM)-00001', 'admin', 'active', '1753346832_d15178edadae7314.png', '2025-06-24 23:29:05', '2025-08-10 09:01:28', 0),
(5, 'jj', 'espiritu@gmail.com', '$2y$10$7I517Dvps0XdCDaWcIM0fOFpAoJtgs5jdcILJ0DJ0h1dKGR9..f5u', 'Joseph', 'espiritu', 'Joseph espiritu', 'NEUST-MGT(STD)-00002', 'student', 'active', '1754543343_c137d0015eacd300.jpg', '2025-06-24 23:39:45', '2025-08-10 09:01:28', 0),
(6, 'ido', 'puesca@gmail.com', '$2y$10$Npjb5x9vt8CrMHL32MCx9uFB3A0oILHGy/Ws5gIIURf/Op04v6bXi', 'Lawrence', 'Puesca', 'Lawrence Puesca', 'NEUST-MGT(TCH)-00002', 'teacher', 'active', NULL, '2025-06-25 00:10:54', '2025-08-10 09:01:28', 0),
(7, 'Loyd', 'eusebio@gmail.com', '$2y$10$4uAgDa3yPZTODdlAEcPIcOK0yO/Gvvv.QVGgAhjnEHwRTiRCBMwlG', 'John', 'Lloyd', 'John Lloyd', 'NEUST-MGT(STD)-00003', 'student', 'active', NULL, '2025-06-25 00:47:51', '2025-08-10 09:01:28', 0),
(9, 'agaga', 'aga@gmail.com', '$2y$10$Km8AJwoFsfYYPRz.M9JDcueriu5QLwmmuRdxMJzBBxc9LuESlGZi6', 'aga', 'gago', NULL, 'NEUST-MGT(STD)-00004', 'student', 'active', NULL, '2025-06-25 05:40:50', '2025-08-10 09:01:28', 0),
(10, 'jd', 'jane@gmail.com', '$2y$10$IKJFtwvEgGLOF60sJhRsCOY6xEmAiotv.PcfuqdAZIz.En7aSewNW', 'Jane', 'Doe', NULL, 'NEUST-MGT(STD)-00005', 'student', 'active', NULL, '2025-07-08 11:27:31', '2025-08-10 09:01:28', 1),
(11, 'make', 'inovero@gmail.com', '$2y$10$.gENF6tE.tHTQvvY3RrN2uQBSMbW2nFm0zcRJIXEiBW9czVBSTY2u', 'Make', 'Inovero', NULL, 'NEUST-MGT(TCH)-00003', 'teacher', 'active', NULL, '2025-07-08 11:41:39', '2025-08-10 09:01:28', 0),
(12, 'emhey', 'mj@gmail.com', '$2y$10$oB0i0JG3b8fyxnLY07Zfo.o8mUCC31HqsIB9P8Me7Vo7a8xAdnr/e', 'Mark James', 'Dela Cruz', NULL, 'NEUST-MGT(TCH)-00004', 'teacher', 'active', NULL, '2025-07-15 03:13:06', '2025-08-10 09:01:28', 0),
(14, 'gogo', 'mango@gmail.com', '$2y$10$/L23NjsZS./40IBFqIl/SeWtlkBrxJUSaTetapmK10Z5ZGraMk6fC', 'mango', 'go', NULL, 'NEUST-MGT(STD)-00006', 'student', 'active', NULL, '2025-07-15 03:58:05', '2025-08-10 09:01:28', 0),
(15, 'apple', 'apple@gmail.com', '$2y$10$n9K2Iga3DiAlnXs/5xEwCuLRow7tTmnlB/PdGR19xjwUc9wA1tmGK', 'apple', 'green', NULL, 'NEUST-MGT(STD)-00007', 'student', 'active', NULL, '2025-07-15 04:01:32', '2025-08-10 09:01:28', 0),
(16, 'falin', 'falin@gmail.com', '$2y$10$/0iRwfB/MZiJszv/NRnAWumyW9fTcUCBPk5PjGdcTAzMnWiFH8l3q', 'Falin', 'Touden', NULL, 'NEUST-MGT(STD)-00008', 'student', 'active', NULL, '2025-07-15 04:14:32', '2025-08-10 09:01:28', 0),
(17, 'kurumi', 'kurumi@gmail.com', '$2y$10$d40Ga09tHUs1oKLh0ngTIezQnSmmCbrEgOaMZrOBRU0wxuPr4CLK.', 'Kurumii', 'Tokisaki', NULL, 'NEUST-MGT(TCH)-00005', 'teacher', 'active', NULL, '2025-07-15 06:04:29', '2025-08-10 09:01:28', 0),
(18, 'zhi', 'zhie@gmail.com', '$2y$10$P9djqDPDpN8kWGm7tEJFmu0/4k24koxEEHOnYOiRoProN7YsXLQCO', 'Shizuro', 'Toma', NULL, 'NEUST-MGT(STD)-00009', 'student', 'active', NULL, '2025-07-15 06:07:39', '2025-08-10 09:01:28', 0),
(19, 'ancis', 'orlanda@gmail.com', '$2y$10$1PU6xZLuXKYXQWPLYl5lpeeRj./qsyWjdLakgZDDfwL5dDOCELjPC', 'John Francis', 'Orlanda', NULL, 'NEUST-MGT(STD)-00010', 'student', 'active', NULL, '2025-07-16 05:17:13', '2025-08-10 09:01:28', 0),
(20, 'chamamama', 'chama@gmail.com', '$2y$10$Wi3mFM1EqwdJkgIqUwkCx.hCHB7Q3dmW5T.McJ68VD0zwUzNmx4Qe', 'chama', 'mama', NULL, 'NEUST-MGT(STD)-00011', 'student', 'active', '1752736313_79758873b8c2e44f.jpg', '2025-07-16 08:18:03', '2025-08-10 09:01:28', 0),
(21, 'Lebron James', 'lebron@gmail.com', '$2y$10$Orq5/8oUZWU99eJXqx6zVuvNIfr9SbhK5WgldJeY.W8lTdFJZGlLO', 'Lebron', 'James', NULL, 'NEUST-MGT(STD)-00012', 'student', 'active', NULL, '2025-07-19 09:20:16', '2025-08-10 09:01:28', 1),
(22, 'Steph Curry', 'curry@gmail.com', '$2y$10$BgZXOvoTj0tEJ4nL7tfqo.7XskQ3zf1BQBa0YPjwQbVBnRzVrxbJC', 'Steph', 'Curry', NULL, 'NEUST-MGT(STD)-00013', 'student', 'active', NULL, '2025-07-19 09:29:13', '2025-08-10 09:01:28', 1),
(23, 'gAMOT', 'R@gmail.com', '$2y$10$qXWrVLVdRmJ5I7.C1t7tz.58j3dIaaYBZ252SOeEj.dlje2id3.iO', 'Lagundi', 'Flemex', NULL, 'NEUST-MGT(STD)-00014', 'student', 'active', NULL, '2025-07-24 02:35:10', '2025-08-10 09:01:28', 0),
(24, 'mason', 'tore@gmail.com', '$2y$10$XZ6mfZ7EwtuTr3mBYyBiVOQLuY9.0lIfJbam49nACBfAq91WXdiym', 'General', 'Tore', NULL, 'NEUST-MGT(STD)-00015', 'student', 'active', NULL, '2025-07-24 02:39:19', '2025-08-10 09:01:28', 0),
(25, 'Leidenschaftlich', 'vi@gmail.com', '$2y$10$YyxZixUANmx0O.FfN5lfb..KRPFoMODyiUmeiZT/El9fz0Lq/euwS', 'Violet', 'Evergarden', NULL, 'NEUST-MGT(TCH)-00006', 'teacher', 'active', NULL, '2025-08-12 23:30:32', '2025-08-12 23:30:32', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `duration` int(11) DEFAULT 0,
  `order_number` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_stats`
--

CREATE TABLE `video_stats` (
  `id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `duration_watched` int(11) DEFAULT 0 COMMENT 'Duration watched in seconds',
  `completed` tinyint(1) DEFAULT 0 COMMENT 'Whether video was fully watched'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_views`
--

CREATE TABLE `video_views` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `watch_duration` int(11) DEFAULT 0,
  `completion_percentage` decimal(5,2) DEFAULT 0.00,
  `watched_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `video_views`
--

INSERT INTO `video_views` (`id`, `student_id`, `video_id`, `watch_duration`, `completion_percentage`, `watched_at`) VALUES
(1, 20, 3, 0, 0.00, '2025-07-22 08:58:04'),
(2, 20, 4, 0, 0.00, '2025-07-22 09:49:17'),
(3, 5, 3, 0, 0.00, '2025-07-24 04:04:39'),
(4, 5, 4, 0, 0.00, '2025-07-24 04:04:59'),
(5, 22, 3, 0, 0.00, '2025-07-24 10:43:56'),
(6, 20, 5, 0, 0.00, '2025-07-31 05:15:15'),
(7, 20, 4, 0, 0.00, '2025-08-21 07:59:27'),
(8, 20, 3, 0, 0.00, '2025-08-30 11:50:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year` (`year`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_author_id` (`author_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `fk_announcements_section` (`section_id`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`announcement_id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_module_id` (`module_id`),
  ADD KEY `idx_difficulty` (`difficulty`),
  ADD KEY `idx_is_locked` (`is_locked`),
  ADD KEY `idx_lock_type` (`lock_type`),
  ADD KEY `idx_prerequisite_assessment` (`prerequisite_assessment_id`),
  ADD KEY `idx_unlock_date` (`unlock_date`);

--
-- Indexes for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_assessment_id` (`assessment_id`),
  ADD KEY `idx_assessment_pass_status` (`student_id`,`assessment_id`,`has_ever_passed`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assessment_id` (`assessment_id`);

--
-- Indexes for table `assessment_question_answers`
--
ALTER TABLE `assessment_question_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempt_id` (`attempt_id`),
  ADD KEY `idx_question_id` (`question_id`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `academic_year_id` (`academic_year_id`),
  ADD KEY `idx_course_name` (`course_name`),
  ADD KEY `idx_teacher_id` (`teacher_id`),
  ADD KEY `idx_archived` (`is_archived`),
  ADD KEY `fk_courses_semester` (`semester_id`);

--
-- Indexes for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_course_id` (`course_id`);

--
-- Indexes for table `course_modules`
--
ALTER TABLE `course_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_module_order` (`module_order`);

--
-- Indexes for table `course_sections`
--
ALTER TABLE `course_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_course_section` (`course_id`,`section_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `course_videos`
--
ALTER TABLE `course_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_module_id` (`module_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_course_id` (`course_id`);

--
-- Indexes for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`student_id`,`course_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `status` (`status`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_email_time` (`ip_address`,`email`,`attempt_time`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_order_number` (`order_number`);

--
-- Indexes for table `module_files`
--
ALTER TABLE `module_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `module_id` (`module_id`);

--
-- Indexes for table `module_progress`
--
ALTER TABLE `module_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_module` (`student_id`,`module_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_module_id` (`module_id`),
  ADD KEY `idx_completed` (`is_completed`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `type` (`type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_id` (`question_id`);

--
-- Indexes for table `registration_tokens`
--
ALTER TABLE `registration_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `section_students`
--
ALTER TABLE `section_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `section_teachers`
--
ALTER TABLE `section_teachers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `academic_year_id` (`academic_year_id`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `selected_option_id` (`selected_option_id`),
  ADD KEY `idx_attempt_id` (`attempt_id`),
  ADD KEY `idx_question_id` (`question_id`);

--
-- Indexes for table `student_badges`
--
ALTER TABLE `student_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_badge` (`student_id`,`badge_id`),
  ADD KEY `badge_id` (`badge_id`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_badge_id` (`badge_id`);

--
-- Indexes for table `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_module_id` (`module_id`),
  ADD KEY `idx_order_number` (`order_number`);

--
-- Indexes for table `video_stats`
--
ALTER TABLE `video_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `video_id` (`video_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `viewed_at` (`viewed_at`);

--
-- Indexes for table `video_views`
--
ALTER TABLE `video_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_video_id` (`video_id`),
  ADD KEY `idx_watched_at` (`watched_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=177;

--
-- AUTO_INCREMENT for table `assessment_question_answers`
--
ALTER TABLE `assessment_question_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `course_modules`
--
ALTER TABLE `course_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `course_sections`
--
ALTER TABLE `course_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `course_videos`
--
ALTER TABLE `course_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `module_files`
--
ALTER TABLE `module_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `module_progress`
--
ALTER TABLE `module_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `question_options`
--
ALTER TABLE `question_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=359;

--
-- AUTO_INCREMENT for table `registration_tokens`
--
ALTER TABLE `registration_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `section_students`
--
ALTER TABLE `section_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `section_teachers`
--
ALTER TABLE `section_teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=428;

--
-- AUTO_INCREMENT for table `student_badges`
--
ALTER TABLE `student_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_stats`
--
ALTER TABLE `video_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `video_views`
--
ALTER TABLE `video_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcements_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `announcement_reads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_reads_ibfk_2` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `course_modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prerequisite_assessment` FOREIGN KEY (`prerequisite_assessment_id`) REFERENCES `assessments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD CONSTRAINT `assessment_attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_attempts_ibfk_2` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_question_answers`
--
ALTER TABLE `assessment_question_answers`
  ADD CONSTRAINT `assessment_question_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_question_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_courses_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_modules`
--
ALTER TABLE `course_modules`
  ADD CONSTRAINT `course_modules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_sections`
--
ALTER TABLE `course_sections`
  ADD CONSTRAINT `course_sections_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_sections_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_videos`
--
ALTER TABLE `course_videos`
  ADD CONSTRAINT `course_videos_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `course_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  ADD CONSTRAINT `enrollment_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollment_requests_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollment_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `module_files`
--
ALTER TABLE `module_files`
  ADD CONSTRAINT `module_files_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `course_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `module_progress`
--
ALTER TABLE `module_progress`
  ADD CONSTRAINT `module_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_progress_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `course_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `question_options`
--
ALTER TABLE `question_options`
  ADD CONSTRAINT `question_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `section_students`
--
ALTER TABLE `section_students`
  ADD CONSTRAINT `section_students_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `section_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `section_teachers`
--
ALTER TABLE `section_teachers`
  ADD CONSTRAINT `section_teachers_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `section_teachers_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `semesters`
--
ALTER TABLE `semesters`
  ADD CONSTRAINT `semesters_ibfk_1` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `question_options` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_badges`
--
ALTER TABLE `student_badges`
  ADD CONSTRAINT `student_badges_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `videos`
--
ALTER TABLE `videos`
  ADD CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `video_stats`
--
ALTER TABLE `video_stats`
  ADD CONSTRAINT `video_stats_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `course_videos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_stats_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `video_views`
--
ALTER TABLE `video_views`
  ADD CONSTRAINT `video_views_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_views_ibfk_2` FOREIGN KEY (`video_id`) REFERENCES `course_videos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
