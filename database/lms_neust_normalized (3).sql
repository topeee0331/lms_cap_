-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 05, 2025 at 03:04 AM
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
-- Database: `lms_neust_normalized`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_periods`
--

CREATE TABLE `academic_periods` (
  `id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_periods`
--

INSERT INTO `academic_periods` (`id`, `academic_year`, `semester_name`, `is_active`, `start_date`, `end_date`, `created_at`) VALUES
(1, '2024-2025', 'First Semester', 1, '2024-08-01', '2024-12-31', '2025-08-31 15:43:11'),
(2, '2024-2025', 'Second Semester', 0, '2025-01-01', '2025-05-31', '2025-08-31 15:43:11'),
(3, '2025-2026', 'First Semester', 0, '2025-09-01', '2026-02-01', '2025-09-01 02:10:20');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  `target_audience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of target users/courses/sections',
  `is_global` tinyint(1) DEFAULT 0,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `expires_at` timestamp NULL DEFAULT NULL,
  `read_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of users who read this',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `author_id`, `target_audience`, `is_global`, `priority`, `expires_at`, `read_by`, `created_at`) VALUES
(1, 'Test 1', 'Test 1', 1, NULL, 1, 'normal', NULL, '[2,1,4]', '2025-09-01 04:20:29'),
(2, 'test 2 announcement admin', 'admin testing the announcement general announcement type', 1, NULL, 1, 'normal', NULL, '[2,4]', '2025-09-03 02:24:50');

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` varchar(50) NOT NULL COMMENT 'String ID like assess_68b63fb3c750e',
  `course_id` int(11) NOT NULL,
  `assessment_title` varchar(100) NOT NULL,
  `assessment_order` int(11) DEFAULT 1 COMMENT 'Order of assessment within the course (1 = first, 2 = second, etc.)',
  `description` text DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `status` enum('active','inactive') DEFAULT 'active',
  `num_questions` int(11) DEFAULT 10,
  `passing_rate` decimal(5,2) DEFAULT 70.00,
  `attempt_limit` int(11) DEFAULT 3,
  `is_locked` tinyint(1) DEFAULT 0,
  `lock_type` enum('manual','prerequisite_score','prerequisite_videos','date_based') DEFAULT 'manual',
  `prerequisite_assessment_id` varchar(50) DEFAULT NULL,
  `prerequisite_score` decimal(5,2) DEFAULT NULL,
  `prerequisite_video_count` int(11) DEFAULT NULL,
  `unlock_date` datetime DEFAULT NULL,
  `lock_message` text DEFAULT NULL,
  `questions` longtext DEFAULT '[]',
  `lock_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `course_id`, `assessment_title`, `assessment_order`, `description`, `time_limit`, `difficulty`, `status`, `num_questions`, `passing_rate`, `attempt_limit`, `is_locked`, `lock_type`, `prerequisite_assessment_id`, `prerequisite_score`, `prerequisite_video_count`, `unlock_date`, `lock_message`, `questions`, `lock_updated_at`, `created_at`, `updated_at`) VALUES
('assess_68b94fa759f75', 1, 'Assessment 1', 1, 'week 1', 5, 'easy', 'active', 10, 70.00, 10, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-04 02:36:55', '2025-09-04 09:42:16'),
('assess_68b962a4ec142', 1, 'Assessment 2', 2, 'week 2', 5, 'medium', 'active', 10, 80.00, 10, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-04 03:57:56', '2025-09-04 09:57:56'),
('assess_68b966b8035b1', 1, 'Assessment 3', 3, '', 5, 'medium', 'active', 10, 70.00, 10, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-04 04:15:20', '2025-09-04 10:15:20');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_attempts`
--

CREATE TABLE `assessment_attempts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assessment_id` varchar(50) DEFAULT NULL,
  `status` enum('in_progress','completed','abandoned') DEFAULT 'in_progress',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT NULL,
  `has_passed` tinyint(1) DEFAULT 0,
  `has_ever_passed` tinyint(1) DEFAULT 0,
  `time_taken` int(11) DEFAULT NULL COMMENT 'Time taken in seconds',
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of student answers',
  `feedback` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of feedback per question'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_attempts`
--

INSERT INTO `assessment_attempts` (`id`, `student_id`, `assessment_id`, `status`, `started_at`, `completed_at`, `score`, `max_score`, `has_passed`, `has_ever_passed`, `time_taken`, `answers`, `feedback`) VALUES
(11, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 08:37:58', '2025-09-04 08:37:58', 0.00, 1.00, 0, 1, 13, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1}]', NULL),
(12, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:26:57', '2025-09-04 09:26:57', 100.00, 1.00, 1, 1, 2253, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"True\",\"is_correct\":true,\"points\":1}]', NULL),
(13, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:33:59', '2025-09-04 09:33:59', 100.00, 1.00, 1, 1, 3, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"True\",\"is_correct\":true,\"points\":1}]', NULL),
(14, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:34:41', '2025-09-04 09:34:41', 0.00, 1.00, 0, 1, 6, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"False\",\"is_correct\":false,\"points\":1}]', NULL),
(15, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:42:29', '2025-09-04 09:42:29', 0.00, 1.00, 0, 1, 6, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"False\",\"is_correct\":false,\"points\":1}]', NULL),
(16, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:43:43', '2025-09-04 09:43:43', 100.00, 1.00, 1, 1, 24, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"True\",\"is_correct\":true,\"points\":1}]', NULL),
(17, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:47:04', '2025-09-04 09:47:04', 0.00, 1.00, 0, 1, 5, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1}]', NULL),
(18, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:50:54', '2025-09-04 09:50:54', 0.00, 1.00, 0, 1, 6, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1}]', NULL),
(19, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:51:16', '2025-09-04 09:51:16', 0.00, 1.00, 0, 1, 6, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1}]', NULL),
(20, 4, 'assess_68b94fa759f75', 'completed', '2025-09-04 09:56:40', '2025-09-04 09:56:40', 100.00, 1.00, 1, 1, 5, '[{\"question_id\":55,\"question_text\":\"Information and Computer Technology\",\"question_type\":\"true_false\",\"student_answer\":\"True\",\"is_correct\":true,\"points\":1}]', NULL),
(21, 4, 'assess_68b962a4ec142', 'completed', '2025-09-04 09:59:47', '2025-09-04 09:59:47', 0.00, 1.00, 0, 1, 7, '[{\"question_id\":56,\"question_text\":\"Which of the following is considered the \\u201cbrain\\u201d of the computer?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"0\",\"is_correct\":false,\"points\":1}]', NULL),
(22, 4, 'assess_68b962a4ec142', 'completed', '2025-09-04 09:59:59', '2025-09-04 09:59:59', 0.00, 1.00, 0, 1, 5, '[{\"question_id\":56,\"question_text\":\"Which of the following is considered the \\u201cbrain\\u201d of the computer?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1}]', NULL),
(23, 4, 'assess_68b962a4ec142', 'completed', '2025-09-04 10:08:38', '2025-09-04 10:08:38', 0.00, 1.00, 0, 1, 5, '[{\"question_id\":56,\"question_text\":\"Which of the following is considered the \\u201cbrain\\u201d of the computer?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1}]', NULL),
(24, 4, 'assess_68b962a4ec142', 'completed', '2025-09-04 10:14:03', '2025-09-04 10:14:03', 0.00, 1.00, 0, 1, 5, '[{\"question_id\":56,\"question_text\":\"Which of the following is considered the \\u201cbrain\\u201d of the computer?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1}]', NULL),
(25, 4, 'assess_68b962a4ec142', 'completed', '2025-09-04 10:14:33', '2025-09-04 10:14:33', 100.00, 1.00, 1, 1, 4, '[{\"question_id\":56,\"question_text\":\"Which of the following is considered the \\u201cbrain\\u201d of the computer?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"1\",\"is_correct\":true,\"points\":1}]', NULL),
(26, 4, 'assess_68b966b8035b1', 'completed', '2025-09-04 10:17:00', '2025-09-04 10:17:00', 0.00, 1.00, 0, 1, 9, '[{\"question_id\":57,\"question_text\":\"What is considered the \\u201cbrain\\u201d of the computer?\",\"question_type\":\"identification\",\"student_answer\":\"s\",\"is_correct\":false,\"points\":1}]', NULL),
(27, 4, 'assess_68b966b8035b1', 'completed', '2025-09-04 10:17:15', '2025-09-04 10:17:15', 100.00, 1.00, 1, 1, 8, '[{\"question_id\":57,\"question_text\":\"What is considered the \\u201cbrain\\u201d of the computer?\",\"question_type\":\"identification\",\"student_answer\":\"Cpu\",\"is_correct\":true,\"points\":1}]', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `badge_name` varchar(50) NOT NULL,
  `badge_description` text DEFAULT NULL,
  `badge_icon` varchar(255) NOT NULL,
  `badge_type` enum('course_completion','high_score','participation','streak','special') DEFAULT 'participation',
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON criteria for earning the badge',
  `points_value` int(11) DEFAULT 0 COMMENT 'Points awarded for earning this badge',
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `awarded_to` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of users who earned this badge',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `status` enum('active','inactive','archived','draft') NOT NULL DEFAULT 'active',
  `academic_period_id` int(11) NOT NULL,
  `year_level` varchar(10) DEFAULT NULL,
  `credits` int(11) DEFAULT 3,
  `is_archived` tinyint(1) DEFAULT 0,
  `modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of modules with videos',
  `sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of section IDs',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `course_code`, `description`, `teacher_id`, `status`, `academic_period_id`, `year_level`, `credits`, `is_archived`, `modules`, `sections`, `created_at`, `updated_at`) VALUES
(1, 'Introduction to Computing', 'CC - 100', 'This provides a foundational overview of the computing industry and profession, including its history, key components of computer systems, and applications across various field', 2, 'active', 1, NULL, 3, 0, '[{\"id\":\"mod_68b572d5b916e\",\"module_title\":\"Moule  1\",\"module_description\":\"\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":70,\"videos\":[{\"id\":\"vid_68b632f1d9525\",\"video_title\":\"Video 4\",\"video_description\":\"Video link for CC 100\",\"video_order\":4,\"video_url\":\"https:\\/\\/www.youtube.com\\/watch?v=uls8Wdd1FTo&t=694s\",\"min_watch_time\":30,\"created_at\":\"2025-09-02 01:57:37\",\"updated_at\":\"2025-09-02 02:17:54\"},{\"id\":\"vid_68b6352f6e665\",\"video_title\":\"Video 2\",\"video_description\":\"Video 2 for CC100\",\"video_order\":2,\"video_file\":\"https:\\/\\/www.youtube.com\\/watch?v=wGW4HnY55Ew\",\"created_at\":\"2025-09-02 02:07:11\",\"min_watch_time\":30,\"updated_at\":\"2025-09-02 02:37:27\"},{\"id\":\"vid_68b63b6211057\",\"video_title\":\"Video 1\",\"video_description\":\"Videos for week 1\",\"video_order\":1,\"video_url\":\"https:\\/\\/drive.google.com\\/file\\/d\\/1DgcVmkkg5aBHUD5VFMPPwGq95OwKK5Px\\/view?usp=sharing\",\"min_watch_time\":30,\"created_at\":\"2025-09-02 02:33:38\"},{\"id\":\"vid_68b63c9c8f2f8\",\"video_title\":\"Video 3\",\"video_description\":\"week 3 video\",\"video_order\":3,\"video_url\":\"https:\\/\\/youtu.be\\/NFK5F6jPyjE?si=k_Fr6xSmcmIrq5dY\",\"min_watch_time\":30,\"created_at\":\"2025-09-02 02:38:52\",\"updated_at\":\"2025-09-02 02:48:27\"}],\"assessments\":[{\"id\":\"assess_68b94fa759f75\",\"assessment_title\":\"Assessment 1\",\"description\":\"week 1\",\"time_limit\":5,\"difficulty\":\"easy\",\"num_questions\":10,\"passing_rate\":70,\"attempt_limit\":3,\"assessment_order\":1,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-04 10:36:55\"},{\"id\":\"assess_68b962a4ec142\",\"assessment_title\":\"Assessment 2\",\"description\":\"week 2\",\"time_limit\":5,\"difficulty\":\"medium\",\"num_questions\":10,\"passing_rate\":80,\"attempt_limit\":10,\"assessment_order\":2,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-04 11:57:56\"},{\"id\":\"assess_68b966b8035b1\",\"assessment_title\":\"Assessment 3\",\"description\":\"\",\"time_limit\":5,\"difficulty\":\"medium\",\"num_questions\":10,\"passing_rate\":70,\"attempt_limit\":10,\"assessment_order\":3,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-04 12:15:20\"}],\"created_at\":\"2025-09-01 12:17:57\"},{\"id\":\"mod_68b5732114c66\",\"module_title\":\"Moule 2\",\"module_description\":\"Module 1\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[{\"id\":\"vid_68b57c5751174\",\"video_title\":\"Video 1\",\"video_description\":\"Description test 1\",\"video_order\":1,\"video_file\":\"68b57c57461b1_WIN_20241112_20_57_13_Pro.mp4\",\"created_at\":\"2025-09-01 12:58:31\"}],\"assessments\":[],\"created_at\":\"2025-09-01 12:19:13\",\"updated_at\":\"2025-09-02 02:28:05\"},{\"id\":\"mod_68b63a3bec469\",\"module_title\":\"Moule 3\",\"module_description\":\"module week 3\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-02 02:28:43\"}]', '[\"2\",\"3\"]', '2025-09-01 02:27:50', '2025-09-04 10:15:20'),
(2, 'Networking 1, Fundamentals', 'IT-NET01', 'This cover the basics of connecting devices, including components like nodes (clients and servers), channels (wired or wireless), and intermediary devices such as hubs, switches, and routers', 3, 'active', 1, NULL, 3, 0, NULL, '[\"2\",\"3\"]', '2025-09-01 02:30:18', '2025-09-01 03:16:41'),
(3, 'Programming Fundamentals', 'IT-PROG101', 'Introduction to programming concepts, algorithms, and problem-solving techniques using high-level programming languages.', 2, 'active', 1, '1st Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(4, 'Data Structures and Algorithms', 'IT-DSA201', 'Study of fundamental data structures and algorithms including arrays, linked lists, stacks, queues, trees, and graphs.', 3, 'active', 1, '2nd Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(5, 'Database Management Systems', 'IT-DBMS202', 'Introduction to database concepts, design, implementation, and management using SQL and database systems.', 4, 'active', 1, '2nd Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(6, 'Web Development', 'IT-WEB301', 'Front-end and back-end web development using HTML, CSS, JavaScript, and server-side technologies.', 5, 'active', 1, '3rd Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(7, 'Software Engineering', 'IT-SE302', 'Software development lifecycle, methodologies, requirements analysis, design patterns, and project management.', 6, 'active', 1, '3rd Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(8, 'Computer Networks', 'IT-NET303', 'Network architecture, protocols, TCP/IP, routing, switching, and network security fundamentals.', 7, 'active', 1, '3rd Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(9, 'Operating Systems', 'IT-OS304', 'Operating system concepts, process management, memory management, file systems, and system programming.', 8, 'active', 1, '3rd Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(10, 'Mobile Application Development', 'IT-MOBILE401', 'Development of mobile applications for iOS and Android platforms using modern frameworks and tools.', 9, 'active', 1, '4th Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(11, 'Information Security', 'IT-SEC402', 'Cybersecurity fundamentals, cryptography, network security, ethical hacking, and security policies.', 10, 'active', 1, '4th Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(12, 'Capstone Project', 'IT-CAP403', 'Final year project integrating knowledge from all previous courses to develop a comprehensive software solution.', 11, 'active', 1, '4th Year', 3, 0, '[]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:03:15');

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('active','completed','dropped','pending') NOT NULL DEFAULT 'active',
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completion_date` timestamp NULL DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `is_completed` tinyint(1) DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_accessed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `module_progress` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON tracking progress per module',
  `video_progress` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON tracking video completion'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_enrollments`
--

INSERT INTO `course_enrollments` (`id`, `student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(1, 4, 1, 'active', '2025-09-01 05:00:44', NULL, NULL, 33.33, 0, '2025-09-01 05:00:44', '2025-09-04 03:47:45', '{\"mod_68b572d5b916e\":{\"is_completed\":1,\"completed_at\":\"2025-09-04 05:47:45\"}}', NULL),
(2, 5, 1, 'active', '2025-09-01 05:00:52', NULL, NULL, 0.00, 0, '2025-09-01 05:00:52', '2025-09-01 05:00:52', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_requests`
--

CREATE TABLE `enrollment_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `auto_approved` tinyint(1) DEFAULT 0 COMMENT 'Whether request was auto-approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollment_requests`
--

INSERT INTO `enrollment_requests` (`id`, `student_id`, `course_id`, `status`, `requested_at`, `approved_at`, `approved_by`, `rejection_reason`, `auto_approved`) VALUES
(1, 4, 2, 'rejected', '2025-09-04 05:44:26', '2025-09-04 05:45:22', 3, '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `file_uploads`
--

CREATE TABLE `file_uploads` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL COMMENT 'File size in bytes',
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `related_type` enum('module','video','profile','badge','other') DEFAULT 'other',
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related entity',
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `user_agent` text DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `risk_score` int(11) DEFAULT 0 COMMENT 'Calculated risk score for this attempt'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `email`, `attempt_time`, `success`, `user_agent`, `country`, `city`, `risk_score`) VALUES
(1, '::1', 'salvador@gmail.com', '2025-09-01 00:23:11', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(2, '::1', 'puesca@gmail.com', '2025-09-01 02:37:21', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(3, '::1', 'salvador@gmail.com', '2025-09-01 04:07:14', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(4, '::1', 'puesca@gmail.com', '2025-09-01 07:34:43', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(6, '::1', 'salvador@gmail.com', '2025-09-01 07:37:30', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(7, '::1', 'puesca@gmail.com', '2025-09-01 09:30:20', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(8, '::1', 'puesca@gmail.com', '2025-09-01 23:54:59', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(9, '::1', 'puesca@gmail.com', '2025-09-02 10:59:06', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(10, '::1', 'puesca@gmail.com', '2025-09-03 02:01:14', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(11, '::1', 'espiritu@gmail.com', '2025-09-03 02:13:08', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(12, '::1', 'salvador@gmail.com', '2025-09-03 02:24:05', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(13, '::1', 'puesca@gmail.com', '2025-09-04 02:18:13', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(14, '::1', 'espiritu@gmail.com', '2025-09-04 02:22:45', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(15, '::1', 'salvador@gmail.com', '2025-09-04 03:57:17', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(16, '::1', 'eusebio@gmail.com', '2025-09-04 05:45:12', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(17, '::1', 'puesca@gmail.com', '2025-09-04 05:47:21', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(18, '::1', 'puesca@gmail.com', '2025-09-04 09:17:13', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(19, '::1', 'espiritu@gmail.com', '2025-09-04 09:17:18', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(20, '::1', 'puesca@gmail.com', '2025-09-05 00:45:51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(21, '::1', 'espiritu@gmail.com', '2025-09-05 00:45:58', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('enrollment_rejected','enrollment_approved','general','assessment','badge') NOT NULL DEFAULT 'general',
  `related_id` int(11) DEFAULT NULL COMMENT 'Related ID (e.g., enrollment_request_id)',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `priority` enum('low','normal','high') DEFAULT 'normal',
  `delivery_status` enum('pending','sent','delivered','failed') DEFAULT 'pending',
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_id`, `is_read`, `priority`, `delivery_status`, `delivered_at`, `created_at`) VALUES
(1, 4, 'Enrollment Request Rejected', 'Your enrollment request for course \'Networking 1, Fundamentals\' has been rejected. You can request enrollment again if needed.', 'enrollment_rejected', 1, 0, 'normal', 'pending', NULL, '2025-09-04 05:45:22');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `assessment_id` varchar(50) NOT NULL COMMENT 'String ID like assess_68b63fb3c750e',
  `question_text` text NOT NULL,
  `question_type` varchar(32) NOT NULL DEFAULT 'multiple_choice',
  `question_order` int(11) NOT NULL,
  `points` int(11) DEFAULT 1,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of question options',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `assessment_id`, `question_text`, `question_type`, `question_order`, `points`, `options`, `created_at`, `updated_at`) VALUES
(55, 'assess_68b94fa759f75', 'Information and Computer Technology', 'true_false', 1, 1, '[{\"text\":\"True\",\"is_correct\":true,\"order\":1},{\"text\":\"False\",\"is_correct\":false,\"order\":2}]', '2025-09-04 08:37:33', NULL),
(56, 'assess_68b962a4ec142', 'Which of the following is considered the “brain” of the computer?', 'multiple_choice', 1, 1, '[{\"text\":\"RAM\",\"is_correct\":false,\"order\":1},{\"text\":\"CPU\",\"is_correct\":true,\"order\":2},{\"text\":\"MONITOR\",\"is_correct\":false,\"order\":3},{\"text\":\"FAN\",\"is_correct\":false,\"order\":4}]', '2025-09-04 09:59:23', NULL),
(57, 'assess_68b966b8035b1', 'What is considered the “brain” of the computer?', 'identification', 1, 1, '[{\"text\":\"CPU\",\"is_correct\":true,\"order\":1}]', '2025-09-04 10:16:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `registration_tokens`
--

CREATE TABLE `registration_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_by` int(11) DEFAULT NULL COMMENT 'User ID who used this token',
  `used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `year_level` int(11) NOT NULL DEFAULT 1,
  `section_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `academic_period_id` int(11) NOT NULL,
  `students` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of student IDs',
  `teachers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of teacher IDs',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `year_level`, `section_name`, `description`, `is_active`, `academic_period_id`, `students`, `teachers`, `created_at`) VALUES
(2, 1, 'A', 'Section A for first year', 1, 1, '[\"5\",\"4\"]', NULL, '2025-09-01 03:03:59'),
(3, 1, 'B', 'Section B for 1st Year', 1, 3, NULL, NULL, '2025-09-01 03:16:41');

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
  `role` enum('admin','teacher','student') NOT NULL,
  `status` enum('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_irregular` tinyint(1) DEFAULT 0,
  `identifier` varchar(20) DEFAULT NULL COMMENT 'Student ID, Teacher ID, or Admin ID based on role',
  `department` varchar(100) DEFAULT NULL COMMENT 'For teachers only',
  `access_level` enum('super_admin','admin','moderator') DEFAULT NULL COMMENT 'For admins only',
  `academic_period_id` int(11) DEFAULT NULL COMMENT 'For students only',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `profile_picture`, `is_irregular`, `identifier`, `department`, `access_level`, `academic_period_id`, `created_at`, `updated_at`) VALUES
(1, 'mon', 'salvador@gmail.com', '$2y$10$9dBJLQrfknEAO922pc6sE.ol/dc9DVv.ZIQI7Zt/te3JCETbEO1cG', 'Raymond', 'Salvador', 'admin', 'active', NULL, 0, '1', NULL, 'super_admin', NULL, '2025-09-01 00:22:53', '2025-09-01 01:52:16'),
(2, 'aga', 'puesca@gmail.com', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Lawrence', 'Puesca', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00001', NULL, NULL, NULL, '2025-09-01 01:57:07', '2025-09-01 01:57:07'),
(3, 'jl', 'eusebio@gmail.com', '$2y$10$SRqOR/5Wig75yS38lFwaZuglCi4/GPnmFRNvRPHKMUF37de5WLsOq', 'John Lloyd', 'Eusebio', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00002', NULL, NULL, NULL, '2025-09-01 01:58:30', '2025-09-01 01:58:40'),
(4, 'jj', 'espiritu@gmail.com', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'John Joseph', 'Espiritu', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00001', NULL, NULL, NULL, '2025-09-01 02:02:37', '2025-09-01 02:02:37'),
(5, 'mj', 'delacruz@gmail.com', '$2y$10$2oWwIACG/K75nr6pwMHjK.B8sMKlGsTEMqSazqNpQNiws.6Draawy', 'Mark James', 'Dela Cruz', 'student', 'active', NULL, 1, 'NEUST-MGT(STD)-00002', NULL, NULL, NULL, '2025-09-01 02:03:27', '2025-09-01 02:03:27'),
(6, 'prof_smith', 'smith@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Sarah', 'Smith', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00003', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(7, 'prof_johnson', 'johnson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Michael', 'Johnson', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00004', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(8, 'prof_wilson', 'wilson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Emily', 'Wilson', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00005', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(9, 'prof_brown', 'brown@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. David', 'Brown', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00006', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(10, 'prof_davis', 'davis@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Lisa', 'Davis', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00007', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(11, 'prof_miller', 'miller@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Robert', 'Miller', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00008', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(12, 'prof_garcia', 'garcia@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Maria', 'Garcia', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00009', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(13, 'prof_martinez', 'martinez@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Carlos', 'Martinez', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00010', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(14, 'prof_rodriguez', 'rodriguez@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Ana', 'Rodriguez', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00011', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(15, 'prof_lee', 'lee@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. James', 'Lee', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00012', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(16, 'student001', 'student001@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Juan', 'Santos', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00003', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(17, 'student002', 'student002@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Maria', 'Cruz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00004', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(18, 'student003', 'student003@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Jose', 'Reyes', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00005', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(19, 'student004', 'student004@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Ana', 'Gonzales', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00006', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(20, 'student005', 'student005@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Pedro', 'Lopez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00007', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(21, 'student006', 'student006@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carmen', 'Torres', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00008', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(22, 'student007', 'student007@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Miguel', 'Flores', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00009', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(23, 'student008', 'student008@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Isabella', 'Mendoza', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00010', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(24, 'student009', 'student009@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Rafael', 'Herrera', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00011', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(25, 'student010', 'student010@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sofia', 'Vargas', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00012', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(26, 'student011', 'student011@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Diego', 'Castillo', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00013', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(27, 'student012', 'student012@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Valentina', 'Jimenez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00014', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(28, 'student013', 'student013@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sebastian', 'Morales', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00015', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(29, 'student014', 'student014@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Camila', 'Ortiz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00016', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(30, 'student015', 'student015@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Mateo', 'Ramirez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00017', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(31, 'student016', 'student016@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Natalia', 'Silva', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00018', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(32, 'student017', 'student017@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alejandro', 'Vega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00019', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(33, 'student018', 'student018@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Gabriela', 'Ramos', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00020', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(34, 'student019', 'student019@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Nicolas', 'Pena', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00021', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(35, 'student020', 'student020@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Lucia', 'Guerrero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00022', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(36, 'student021', 'student021@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carlos', 'Fernandez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00023', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(37, 'student022', 'student022@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Elena', 'Martinez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00024', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(38, 'student023', 'student023@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Fernando', 'Gutierrez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00025', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(39, 'student024', 'student024@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Patricia', 'Ruiz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00026', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(40, 'student025', 'student025@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Antonio', 'Diaz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00027', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(41, 'student026', 'student026@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Monica', 'Herrera', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00028', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(42, 'student027', 'student027@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Roberto', 'Moreno', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00029', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(43, 'student028', 'student028@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Cristina', 'Alvarez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00030', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(44, 'student029', 'student029@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Francisco', 'Romero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00031', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(45, 'student030', 'student030@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Adriana', 'Navarro', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00032', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(46, 'student031', 'student031@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Manuel', 'Torres', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00033', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(47, 'student032', 'student032@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Beatriz', 'Jimenez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00034', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(48, 'student033', 'student033@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alberto', 'Molina', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00035', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(49, 'student034', 'student034@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Dolores', 'Castro', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00036', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(50, 'student035', 'student035@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Eduardo', 'Ortega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00037', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(51, 'student036', 'student036@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Rosa', 'Delgado', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00038', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(52, 'student037', 'student037@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Javier', 'Vega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00039', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(53, 'student038', 'student038@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Teresa', 'Ramos', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00040', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(54, 'student039', 'student039@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Ruben', 'Pena', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00041', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(55, 'student040', 'student040@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Pilar', 'Guerrero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00042', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_periods`
--
ALTER TABLE `academic_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_academic_period` (`academic_year`,`semester_name`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_author_id` (`author_id`),
  ADD KEY `idx_is_global` (`is_global`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_difficulty` (`difficulty`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_lock_type` (`lock_type`),
  ADD KEY `idx_unlock_date` (`unlock_date`),
  ADD KEY `idx_assessment_order` (`assessment_order`);

--
-- Indexes for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_assessment_id` (`assessment_id`),
  ADD KEY `idx_assessment_pass_status` (`student_id`,`assessment_id`,`has_ever_passed`),
  ADD KEY `idx_attempts_student_assessment` (`student_id`,`assessment_id`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `badge_name` (`badge_name`),
  ADD KEY `idx_badge_type` (`badge_type`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `fk_badges_created_by` (`created_by`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `idx_teacher_id` (`teacher_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_academic_period_id` (`academic_period_id`),
  ADD KEY `idx_archived` (`is_archived`),
  ADD KEY `idx_courses_teacher_status` (`teacher_id`,`status`);

--
-- Indexes for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_completed` (`is_completed`),
  ADD KEY `idx_enrollments_student_status` (`student_id`,`status`);

--
-- Indexes for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`student_id`,`course_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_approved_by` (`approved_by`);

--
-- Indexes for table `file_uploads`
--
ALTER TABLE `file_uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_related_type` (`related_type`),
  ADD KEY `idx_related_id` (`related_id`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`);

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
  ADD KEY `idx_attempt_time` (`attempt_time`),
  ADD KEY `idx_risk_score` (`risk_score`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `type` (`type`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_delivery_status` (`delivery_status`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`),
  ADD KEY `question_order` (`question_order`),
  ADD KEY `question_type` (`question_type`);

--
-- Indexes for table `registration_tokens`
--
ALTER TABLE `registration_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_used_by` (`used_by`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_year_level` (`year_level`),
  ADD KEY `idx_academic_period_id` (`academic_period_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `identifier` (`identifier`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_academic_period_id` (`academic_period_id`),
  ADD KEY `idx_users_role_status` (`role`,`status`),
  ADD KEY `idx_users_identifier` (`identifier`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_periods`
--
ALTER TABLE `academic_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `file_uploads`
--
ALTER TABLE `file_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `registration_tokens`
--
ALTER TABLE `registration_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD CONSTRAINT `fk_assessment_attempts_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assessment_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `badges`
--
ALTER TABLE `badges`
  ADD CONSTRAINT `fk_badges_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_academic_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`),
  ADD CONSTRAINT `fk_courses_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD CONSTRAINT `fk_course_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_course_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  ADD CONSTRAINT `fk_enrollment_requests_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_enrollment_requests_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enrollment_requests_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `file_uploads`
--
ALTER TABLE `file_uploads`
  ADD CONSTRAINT `fk_file_uploads_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_tokens`
--
ALTER TABLE `registration_tokens`
  ADD CONSTRAINT `fk_registration_tokens_user` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_sections_academic_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_academic_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
