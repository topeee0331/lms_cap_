-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 07, 2025 at 04:03 PM
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
(2, 'test 2 announcement admin', 'admin testing the announcement general announcement type', 1, NULL, 1, 'normal', NULL, '[2,4,1]', '2025-09-03 02:24:50');

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
('assess_68ba5addafb29', 13, 'Assessment: Cloud Computing Fundamentals Quiz', 1, 'This quiz assesses your understanding of key concepts in the &quot;Cloud Computing Fundamentals: Architecture and Applications&quot; module. The quiz consists of multiple-choice, identification, and true-or-false questions.', 10, 'medium', 'active', 10, 80.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-04 21:37:01', '2025-09-05 03:37:14'),
('assess_68ba8f00de59f', 1, 'CC-100 Cloud Computing Security Essentials Quiz', 1, 'This quiz assesses your understanding of the core concepts covered in the &quot;CC-100: Cloud Computing Security Essentials&quot; module. The quiz includes multiple-choice, identification, and true-or-false questions. Select or provide the correct answer for each question.', 10, 'medium', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:19:28', '2025-09-05 07:19:28'),
('assess_68ba92062de48', 3, 'Programming Fundamentals Quiz', 2, 'This quiz evaluates your understanding of the core concepts covered in the &quot;Programming Fundamentals&quot; module. The quiz includes multiple-choice, identification, and true-or-false questions. Select or provide the correct answer for each question', 10, 'medium', 'active', 10, 70.00, 2, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:32:22', '2025-09-07 13:13:11'),
('assess_68ba967f9a7f1', 13, 'Object-Oriented Programming Essentials', 2, 'Object-Oriented Programming Essentials introduces students to the foundational principles of object-oriented programming (OOP), a key paradigm in software development. This module covers essential OOP concepts, including classes, objects, encapsulation, inheritance, polymorphism, and abstraction, using beginner-friendly languages like Python or Java. Through practical coding exercises and real-world examples, students will learn to design modular, reusable code to solve computational problems. The course emphasizes hands-on practice to build skills in creating and managing objects, ensuring a solid foundation for further programming studies.', 10, 'medium', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:51:27', '2025-09-07 13:13:11'),
('assess_68bd882e71c60', 28, 'Introduction Quiz', 3, 'Basic concepts and fundamentals', 30, 'easy', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-07 13:27:10', '2025-09-07 13:28:12'),
('assess_68bd882e7283b', 28, 'Intermediate Concepts Quiz', 4, 'Advanced topics and applications', 45, 'medium', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-07 13:27:10', '2025-09-07 13:28:12'),
('assess_68bd882e73679', 28, 'Advanced Topics Quiz', 5, 'Complex scenarios and problem solving', 60, 'hard', 'active', 20, 80.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-07 13:27:10', '2025-09-07 13:28:12'),
('assess_68bd89771825d', 28, 'hfhf hhg', 2, 'a', 5, 'medium', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-07 07:32:39', '2025-09-07 14:02:12'),
('assess_68bd8983e52d3', 28, 'qwetyyy', 1, 'hhkj kjhk hk', 5, 'medium', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-07 07:32:51', '2025-09-07 14:02:19'),
('assess_cap403_1', 12, 'Capstone Project Proposal', 1, 'Project planning and requirements', 60, 'hard', 'active', 20, 80.00, 2, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_dbms202_1', 5, 'Database Fundamentals Quiz', 1, 'Basic database concepts and SQL', 40, 'medium', 'active', 12, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_dsa201_1', 4, 'Data Structures Quiz', 1, 'Linear data structures assessment', 45, 'medium', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_mobile401_1', 10, 'Mobile Development Quiz', 1, 'Mobile platform fundamentals', 35, 'medium', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_net303_1', 8, 'Network Fundamentals Quiz', 1, 'OSI model and network protocols', 45, 'medium', 'active', 12, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_os304_1', 9, 'Operating Systems Quiz', 1, 'Process management and scheduling', 40, 'hard', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_prog101_1', 3, 'Programming Basics Quiz', 1, 'Basic programming concepts and syntax', 30, 'easy', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_se302_1', 7, 'Software Engineering Quiz', 1, 'SDLC and requirements engineering', 50, 'medium', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_sec402_1', 11, 'Information Security Quiz', 1, 'Security fundamentals and threats', 45, 'hard', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04'),
('assess_web301_1', 6, 'Web Development Quiz', 1, 'HTML, CSS, and JavaScript basics', 35, 'easy', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, '2025-09-05 01:15:04', '2025-09-05 01:15:04');

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
(28, 4, 'assess_68ba5addafb29', 'completed', '2025-09-05 03:56:11', '2025-09-05 03:56:11', 70.00, 10.00, 0, 1, 160, '[{\"question_id\":65,\"question_text\":\"Cloud computing always requires on-premises hardware to function effectively.\",\"question_type\":\"true_false\",\"student_answer\":\"False\",\"is_correct\":true,\"points\":3},{\"question_id\":66,\"question_text\":\"Hybrid cloud deployments allow businesses to store sensitive data on a private cloud while using public cloud resources for less sensitive tasks.\",\"question_type\":\"true_false\",\"student_answer\":\"True\",\"is_correct\":true,\"points\":1},{\"question_id\":63,\"question_text\":\"Identify the cloud deployment model that combines both public and private clouds to leverage benefits of both\",\"question_type\":\"identification\",\"student_answer\":\"Hybrid\",\"is_correct\":true,\"points\":5},{\"question_id\":59,\"question_text\":\"Which cloud deployment model offers the most control over security and infrastructure?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"1\",\"is_correct\":true,\"points\":4},{\"question_id\":60,\"question_text\":\"What is a key benefit of virtualization in cloud computing?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"1\",\"is_correct\":true,\"points\":4},{\"question_id\":64,\"question_text\":\"Name a common security risk in cloud computing that involves unauthorized access to sensitive data.\",\"question_type\":\"identification\",\"student_answer\":\"data breach\",\"is_correct\":true,\"points\":5},{\"question_id\":62,\"question_text\":\"Name the cloud service model that provides access to software applications over the internet without requiring users to manage the underlying infrastructure.\",\"question_type\":\"identification\",\"student_answer\":\"Software as a service\",\"is_correct\":false,\"points\":5},{\"question_id\":61,\"question_text\":\"Which cloud provider offers the &quot;Elastic Compute Cloud (EC2)&quot; service?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"2\",\"is_correct\":false,\"points\":4},{\"question_id\":58,\"question_text\":\"Which of the following is NOT a primary cloud service model?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"3\",\"is_correct\":true,\"points\":4},{\"question_id\":67,\"question_text\":\"Object storage is typically used for high-performance applications requiring low-latency access.\",\"question_type\":\"true_false\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1}]', NULL),
(29, 4, 'assess_68ba5addafb29', 'completed', '2025-09-05 04:01:03', '2025-09-05 04:01:03', 80.00, 10.00, 1, 1, 108, '[{\"question_id\":63,\"question_text\":\"Identify the cloud deployment model that combines both public and private clouds to leverage benefits of both\",\"question_type\":\"identification\",\"student_answer\":\"Hybrid\",\"is_correct\":true,\"points\":5},{\"question_id\":58,\"question_text\":\"Which of the following is NOT a primary cloud service model?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"3\",\"is_correct\":true,\"points\":4},{\"question_id\":59,\"question_text\":\"Which cloud deployment model offers the most control over security and infrastructure?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"1\",\"is_correct\":true,\"points\":4},{\"question_id\":64,\"question_text\":\"Name a common security risk in cloud computing that involves unauthorized access to sensitive data.\",\"question_type\":\"identification\",\"student_answer\":\"data breach\",\"is_correct\":true,\"points\":5},{\"question_id\":61,\"question_text\":\"Which cloud provider offers the &quot;Elastic Compute Cloud (EC2)&quot; service?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"2\",\"is_correct\":false,\"points\":4},{\"question_id\":66,\"question_text\":\"Hybrid cloud deployments allow businesses to store sensitive data on a private cloud while using public cloud resources for less sensitive tasks.\",\"question_type\":\"true_false\",\"student_answer\":\"True\",\"is_correct\":true,\"points\":1},{\"question_id\":65,\"question_text\":\"Cloud computing always requires on-premises hardware to function effectively.\",\"question_type\":\"true_false\",\"student_answer\":\"False\",\"is_correct\":true,\"points\":3},{\"question_id\":67,\"question_text\":\"Object storage is typically used for high-performance applications requiring low-latency access.\",\"question_type\":\"true_false\",\"student_answer\":\"\",\"is_correct\":false,\"points\":1},{\"question_id\":60,\"question_text\":\"What is a key benefit of virtualization in cloud computing?\",\"question_type\":\"multiple_choice\",\"student_answer\":\"1\",\"is_correct\":true,\"points\":4},{\"question_id\":62,\"question_text\":\"Name the cloud service model that provides access to software applications over the internet without requiring users to manage the underlying infrastructure.\",\"question_type\":\"identification\",\"student_answer\":\"Software as a Service (SaaS)\",\"is_correct\":true,\"points\":5}]', NULL);

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

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `badge_name`, `badge_description`, `badge_icon`, `badge_type`, `criteria`, `points_value`, `created_by`, `is_active`, `awarded_to`, `created_at`) VALUES
(1, 'First Course Complete', 'Completed your first course', 'default_badge.png', 'course_completion', '{\"type\":\"course_completion\",\"courses_required\":1}', 10, NULL, 1, NULL, '2025-09-05 01:11:19'),
(2, 'High Achiever', 'Scored 90% or higher on an assessment', 'default_badge.png', 'high_score', '{\"type\":\"high_score\",\"min_score\":90}', 15, NULL, 1, '[{\"student_id\":4,\"awarded_at\":\"2025-09-05 03:55:16\"}]', '2025-09-05 01:11:19'),
(3, 'Active Participant', 'Participated in 5 discussions', 'default_badge.png', 'participation', '{\"type\":\"participation\",\"discussions_required\":5}', 5, NULL, 1, NULL, '2025-09-05 01:11:19'),
(4, 'First Steps', 'Completed your first course in the LMS', 'first_steps.png', 'course_completion', '{\"type\":\"course_completion\",\"courses_required\":1}', 10, NULL, 1, NULL, '2025-09-05 01:54:14'),
(5, 'Course Master', 'Completed 5 courses successfully', 'course_master.png', 'course_completion', '{\"type\":\"course_completion\",\"courses_required\":5}', 50, NULL, 1, NULL, '2025-09-05 01:54:14'),
(6, 'Academic Excellence', 'Completed 10 courses with outstanding performance', 'academic_excellence.png', 'course_completion', '{\"type\":\"course_completion\",\"courses_required\":10,\"min_average_grade\":85}', 100, NULL, 1, NULL, '2025-09-05 01:54:14'),
(7, 'Perfect Score', 'Achieved 100% on any assessment', 'perfect_score.png', 'high_score', '{\"type\":\"high_score\",\"min_score\":100}', 25, NULL, 1, NULL, '2025-09-05 01:54:14'),
(8, 'Consistent Performer', 'Achieved 80% or higher on 5 consecutive assessments', 'consistent_performer.png', 'high_score', '{\"type\":\"consecutive_high_scores\",\"min_score\":80,\"consecutive_count\":5}', 40, NULL, 1, NULL, '2025-09-05 01:54:14'),
(9, 'Assessment Warrior', 'Completed 20 assessments successfully', 'assessment_warrior.png', 'participation', '{\"type\":\"assessment_completion\",\"assessments_required\":20}', 30, NULL, 1, NULL, '2025-09-05 01:54:14'),
(10, 'Video Learner', 'Watched 10 videos completely', 'video_learner.png', 'participation', '{\"type\":\"video_completion\",\"videos_required\":10}', 20, NULL, 1, NULL, '2025-09-05 01:54:14'),
(11, 'Knowledge Seeker', 'Watched 50 videos across all courses', 'knowledge_seeker.png', 'participation', '{\"type\":\"video_completion\",\"videos_required\":50}', 60, NULL, 1, NULL, '2025-09-05 01:54:14'),
(12, 'Focused Learner', 'Watched 5 videos in a single day', 'focused_learner.png', 'streak', '{\"type\":\"daily_video_watching\",\"videos_required\":5}', 15, NULL, 1, NULL, '2025-09-05 01:54:14'),
(13, 'Daily Learner', 'Logged in and studied for 7 consecutive days', 'daily_learner.png', 'streak', '{\"type\":\"login_streak\",\"days_required\":7}', 25, NULL, 1, NULL, '2025-09-05 01:54:14'),
(14, 'Dedicated Student', 'Maintained a 30-day learning streak', 'dedicated_student.png', 'streak', '{\"type\":\"login_streak\",\"days_required\":30}', 100, NULL, 1, NULL, '2025-09-05 01:54:14'),
(15, 'Weekend Warrior', 'Completed activities on 5 consecutive weekends', 'weekend_warrior.png', 'streak', '{\"type\":\"weekend_activity\",\"weekends_required\":5}', 35, NULL, 1, NULL, '2025-09-05 01:54:14'),
(16, 'Early Bird', 'Completed an assessment within the first hour of availability', 'early_bird.png', 'special', '{\"type\":\"early_completion\",\"time_threshold\":3600}', 20, NULL, 1, '[{\"student_id\":4,\"awarded_at\":\"2025-09-05 03:55:16\"}]', '2025-09-05 01:54:14'),
(17, 'Speed Demon', 'Completed an assessment in less than half the time limit', 'speed_demon.png', 'special', '{\"type\":\"fast_completion\",\"time_ratio\":0.5}', 30, NULL, 1, NULL, '2025-09-05 01:54:14'),
(18, 'Perfect Attendance', 'Never missed a single day of course activities for a month', 'perfect_attendance.png', 'special', '{\"type\":\"perfect_attendance\",\"days_required\":30}', 75, NULL, 1, NULL, '2025-09-05 01:54:14'),
(19, 'Module Master', 'Completed all modules in a single course', 'module_master.png', 'special', '{\"type\":\"module_completion\",\"all_modules\":true}', 40, NULL, 1, NULL, '2025-09-05 01:54:14'),
(20, 'Assessment Ace', 'Passed 10 assessments on the first attempt', 'assessment_ace.png', 'special', '{\"type\":\"first_attempt_success\",\"assessments_required\":10}', 50, NULL, 1, NULL, '2025-09-05 01:54:14'),
(21, 'Freshman Explorer', 'Completed 3 first-year courses', 'freshman_explorer.png', 'course_completion', '{\"type\":\"year_level_completion\",\"year_level\":1,\"courses_required\":3}', 30, NULL, 1, NULL, '2025-09-05 01:54:14'),
(22, 'Sophomore Scholar', 'Completed 3 second-year courses', 'sophomore_scholar.png', 'course_completion', '{\"type\":\"year_level_completion\",\"year_level\":2,\"courses_required\":3}', 35, NULL, 1, NULL, '2025-09-05 01:54:14'),
(23, 'Junior Expert', 'Completed 3 third-year courses', 'junior_expert.png', 'course_completion', '{\"type\":\"year_level_completion\",\"year_level\":3,\"courses_required\":3}', 40, NULL, 1, NULL, '2025-09-05 01:54:14'),
(24, 'Senior Specialist', 'Completed 3 fourth-year courses', 'senior_specialist.png', 'course_completion', '{\"type\":\"year_level_completion\",\"year_level\":4,\"courses_required\":3}', 45, NULL, 1, NULL, '2025-09-05 01:54:14'),
(25, 'Easy Rider', 'Completed 5 easy-level assessments', 'easy_rider.png', 'participation', '{\"type\":\"difficulty_completion\",\"difficulty\":\"easy\",\"assessments_required\":5}', 15, NULL, 1, NULL, '2025-09-05 01:54:14'),
(26, 'Medium Master', 'Completed 10 medium-level assessments', 'medium_master.png', 'participation', '{\"type\":\"difficulty_completion\",\"difficulty\":\"medium\",\"assessments_required\":10}', 35, NULL, 1, NULL, '2025-09-05 01:54:14'),
(27, 'Hard Core', 'Completed 5 hard-level assessments', 'hard_core.png', 'participation', '{\"type\":\"difficulty_completion\",\"difficulty\":\"hard\",\"assessments_required\":5}', 50, NULL, 1, NULL, '2025-09-05 01:54:14'),
(28, 'Night Owl', 'Completed 5 activities between 10 PM and 6 AM', 'night_owl.png', 'special', '{\"type\":\"time_based_activity\",\"time_range\":\"night\",\"activities_required\":5}', 25, NULL, 1, NULL, '2025-09-05 01:54:14'),
(29, 'Morning Person', 'Completed 5 activities between 6 AM and 10 AM', 'morning_person.png', 'special', '{\"type\":\"time_based_activity\",\"time_range\":\"morning\",\"activities_required\":5}', 25, NULL, 1, NULL, '2025-09-05 01:54:14'),
(30, 'Century Club', 'Earned 100 total points from all activities', 'century_club.png', 'special', '{\"type\":\"total_points\",\"points_required\":100}', 0, NULL, 1, NULL, '2025-09-05 01:54:14'),
(31, 'Point Collector', 'Earned 500 total points from all activities', 'point_collector.png', 'special', '{\"type\":\"total_points\",\"points_required\":500}', 0, NULL, 1, NULL, '2025-09-05 01:54:14'),
(32, 'Legend', 'Earned 1000 total points from all activities', 'legend.png', 'special', '{\"type\":\"total_points\",\"points_required\":1000}', 0, NULL, 1, NULL, '2025-09-05 01:54:14');

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
(1, 'Introduction to Computing', 'CC - 100', 'This provides a foundational overview of the computing industry and profession, including its history, key components of computer systems, and applications across various field', 2, 'active', 1, NULL, 3, 0, '[{\"id\":\"mod_68b572d5b916e\",\"module_title\":\"Moule  1\",\"module_description\":\"\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":70,\"videos\":[{\"id\":\"vid_68bd5bfcdc039\",\"video_title\":\"Video 1\",\"video_description\":\"k\",\"video_order\":1,\"video_url\":\"https:\\/\\/www.youtube.com\\/watch?v=wGW4HnY55Ew\",\"min_watch_time\":1,\"created_at\":\"2025-09-07 12:18:36\",\"updated_at\":\"2025-09-07 12:53:31\"}],\"assessments\":[{\"id\":\"assess_68ba8f00de59f\",\"assessment_title\":\"CC-100 Cloud Computing Security Essentials Quiz\",\"description\":\"This quiz assesses your understanding of the core concepts covered in the &quot;CC-100: Cloud Computing Security Essentials&quot; module. The quiz includes multiple-choice, identification, and true-or-false questions. Select or provide the correct answer for each question.\",\"time_limit\":10,\"difficulty\":\"medium\",\"num_questions\":10,\"passing_rate\":70,\"attempt_limit\":3,\"assessment_order\":1,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-05 09:19:28\"}],\"created_at\":\"2025-09-01 12:17:57\",\"file\":{\"filename\":\"mod_68b572d5b916e_1757047432.docx\",\"original_name\":\"Reviewer kaso onti laang.docx\",\"file_size\":17312,\"uploaded_at\":\"2025-09-05 06:43:52\"}},{\"id\":\"mod_68b5732114c66\",\"module_title\":\"Moule 2\",\"module_description\":\"Module 1\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-01 12:19:13\",\"updated_at\":\"2025-09-02 02:28:05\"},{\"id\":\"mod_68b63a3bec469\",\"module_title\":\"Moule 3\",\"module_description\":\"module week 3\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-02 02:28:43\"}]', '[\"2\",\"3\"]', '2025-09-01 02:27:50', '2025-09-07 10:53:31'),
(2, 'Networking 1, Fundamentals', 'IT-NET01', 'This cover the basics of connecting devices, including components like nodes (clients and servers), channels (wired or wireless), and intermediary devices such as hubs, switches, and routers', 3, 'active', 1, NULL, 3, 0, NULL, '[\"2\",\"3\"]', '2025-09-01 02:30:18', '2025-09-01 03:16:41'),
(3, 'Programming Fundamentals', 'IT-PROG101', 'Introduction to programming concepts, algorithms, and problem-solving techniques using high-level programming languages.', 2, 'active', 1, '1st Year', 3, 0, '[{\"id\":\"mod_prog101_1\",\"module_title\":\"Introduction to Programming\",\"module_description\":\"Basic programming concepts and syntax\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[{\"id\":\"assess_68ba92062de48\",\"assessment_title\":\"Programming Fundamentals Quiz\",\"description\":\"This quiz evaluates your understanding of the core concepts covered in the &quot;Programming Fundamentals&quot; module. The quiz includes multiple-choice, identification, and true-or-false questions. Select or provide the correct answer for each question\",\"time_limit\":10,\"difficulty\":\"medium\",\"num_questions\":10,\"passing_rate\":70,\"attempt_limit\":2,\"assessment_order\":1,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-05 09:32:22\"}],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_prog101_2\",\"module_title\":\"Control Structures\",\"module_description\":\"Conditionals, loops, and program flow\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_prog101_3\",\"module_title\":\"Functions and Arrays\",\"module_description\":\"Modular programming and data organization\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 07:32:22'),
(4, 'Data Structures and Algorithms', 'IT-DSA201', 'Study of fundamental data structures and algorithms including arrays, linked lists, stacks, queues, trees, and graphs.', 3, 'active', 1, '2nd Year', 3, 0, '[{\"id\":\"mod_dsa201_1\",\"module_title\":\"Linear Data Structures\",\"module_description\":\"Arrays, linked lists, stacks, and queues\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_dsa201_2\",\"module_title\":\"Tree Structures\",\"module_description\":\"Binary trees, BST, and tree traversals\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_dsa201_3\",\"module_title\":\"Graph Algorithms\",\"module_description\":\"Graph representation and traversal algorithms\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:15:03'),
(5, 'Database Management Systems', 'IT-DBMS202', 'Introduction to database concepts, design, implementation, and management using SQL and database systems.', 4, 'active', 1, '2nd Year', 3, 0, '[{\"id\":\"mod_dbms202_1\",\"module_title\":\"Database Fundamentals\",\"module_description\":\"Introduction to databases and data modeling\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_dbms202_2\",\"module_title\":\"SQL Programming\",\"module_description\":\"Structured Query Language and database operations\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_dbms202_3\",\"module_title\":\"Database Design\",\"module_description\":\"Normalization, relationships, and optimization\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:15:03'),
(6, 'Web Development', 'IT-WEB301', 'Front-end and back-end web development using HTML, CSS, JavaScript, and server-side technologies.', 5, 'active', 1, '3rd Year', 3, 0, '[{\"id\":\"mod_web301_1\",\"module_title\":\"Frontend Development\",\"module_description\":\"HTML, CSS, and JavaScript fundamentals\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_web301_2\",\"module_title\":\"Backend Development\",\"module_description\":\"Server-side programming and APIs\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_web301_3\",\"module_title\":\"Full-Stack Integration\",\"module_description\":\"Connecting frontend and backend systems\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:15:03'),
(7, 'Software Engineering', 'IT-SE302', 'Software development lifecycle, methodologies, requirements analysis, design patterns, and project management.', 6, 'active', 1, '3rd Year', 3, 0, '[{\"id\":\"mod_se302_1\",\"module_title\":\"Software Development Lifecycle\",\"module_description\":\"SDLC phases and methodologies\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_se302_2\",\"module_title\":\"Requirements Engineering\",\"module_description\":\"Gathering and analyzing software requirements\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_se302_3\",\"module_title\":\"Design Patterns\",\"module_description\":\"Common design patterns and best practices\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:15:03'),
(8, 'Computer Networks', 'IT-NET303', 'Network architecture, protocols, TCP/IP, routing, switching, and network security fundamentals.', 7, 'active', 1, '3rd Year', 3, 0, '[{\"id\":\"mod_net303_1\",\"module_title\":\"Network Fundamentals\",\"module_description\":\"OSI model, protocols, and network topologies\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_net303_2\",\"module_title\":\"TCP/IP Protocol Suite\",\"module_description\":\"Internet protocols and addressing\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_net303_3\",\"module_title\":\"Network Security\",\"module_description\":\"Network threats, firewalls, and security protocols\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\",5]', '2025-09-05 01:03:15', '2025-09-05 01:41:09'),
(9, 'Operating Systems', 'IT-OS304', 'Operating system concepts, process management, memory management, file systems, and system programming.', 8, 'active', 1, '3rd Year', 3, 0, '[{\"id\":\"mod_os304_1\",\"module_title\":\"OS Concepts\",\"module_description\":\"Process management and scheduling\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_os304_2\",\"module_title\":\"Memory Management\",\"module_description\":\"Virtual memory, paging, and segmentation\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_os304_3\",\"module_title\":\"File Systems\",\"module_description\":\"File organization, directories, and I/O operations\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:15:03'),
(10, 'Mobile Application Development', 'IT-MOBILE401', 'Development of mobile applications for iOS and Android platforms using modern frameworks and tools.', 9, 'active', 1, '4th Year', 3, 0, '[{\"id\":\"mod_mobile401_1\",\"module_title\":\"Mobile Development Basics\",\"module_description\":\"Introduction to mobile platforms and development tools\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_mobile401_2\",\"module_title\":\"Native Development\",\"module_description\":\"iOS and Android native app development\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_mobile401_3\",\"module_title\":\"Cross-Platform Development\",\"module_description\":\"React Native, Flutter, and hybrid frameworks\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:15:03'),
(11, 'Information Security', 'IT-SEC402', 'Cybersecurity fundamentals, cryptography, network security, ethical hacking, and security policies.', 10, 'active', 1, '4th Year', 3, 0, '[{\"id\":\"mod_sec402_1\",\"module_title\":\"Security Fundamentals\",\"module_description\":\"Basic security concepts and threats\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_sec402_2\",\"module_title\":\"Cryptography\",\"module_description\":\"Encryption, hashing, and digital signatures\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_sec402_3\",\"module_title\":\"Network Security\",\"module_description\":\"Firewalls, intrusion detection, and security policies\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\"]', '2025-09-05 01:03:15', '2025-09-05 01:15:03'),
(12, 'Capstone Project', 'IT-CAP403', 'Final year project integrating knowledge from all previous courses to develop a comprehensive software solution.', 11, 'active', 1, '4th Year', 3, 0, '[{\"id\":\"mod_cap403_1\",\"module_title\":\"Project Planning\",\"module_description\":\"Project proposal, requirements, and planning\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":0,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_cap403_2\",\"module_title\":\"Development Phase\",\"module_description\":\"Implementation and testing of the project\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"},{\"id\":\"mod_cap403_3\",\"module_title\":\"Presentation and Documentation\",\"module_description\":\"Final presentation and project documentation\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 01:03:15\"}]', '[\"2\",\"3\",5]', '2025-09-05 01:03:15', '2025-09-05 01:41:08'),
(13, 'Object-Oriented Programming', 'IT-OOP201', 'Advanced programming concepts using object-oriented principles and design patterns.', 2, 'active', 1, '2nd Year', 3, 0, '[{\"id\":\"mod_68ba58b18ab63\",\"module_title\":\"Module 1\",\"module_description\":\"\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":70,\"videos\":[{\"id\":\"vid_68bd56ce1e7b1\",\"video_title\":\"Video 3\",\"video_description\":\"A\",\"video_order\":1,\"video_url\":\"https:\\/\\/drive.google.com\\/file\\/d\\/1DgcVmkkg5aBHUD5VFMPPwGq95OwKK5Px\\/view?usp=sharing\",\"min_watch_time\":30,\"created_at\":\"2025-09-07 11:56:30\"}],\"assessments\":[{\"id\":\"assess_68ba967f9a7f1\",\"assessment_title\":\"Object-Oriented Programming Essentials\",\"description\":\"Object-Oriented Programming Essentials introduces students to the foundational principles of object-oriented programming (OOP), a key paradigm in software development. This module covers essential OOP concepts, including classes, objects, encapsulation, inheritance, polymorphism, and abstraction, using beginner-friendly languages like Python or Java. Through practical coding exercises and real-world examples, students will learn to design modular, reusable code to solve computational problems. The course emphasizes hands-on practice to build skills in creating and managing objects, ensuring a solid foundation for further programming studies.\",\"time_limit\":10,\"difficulty\":\"medium\",\"num_questions\":10,\"passing_rate\":70,\"attempt_limit\":3,\"assessment_order\":1,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-05 09:51:27\"}],\"created_at\":\"2025-09-05 05:27:45\",\"file\":{\"filename\":\"mod_68ba58b18ab63_1757052371.pdf\",\"original_name\":\"midterm-pratical.pdf\",\"file_size\":4283801,\"uploaded_at\":\"2025-09-05 08:06:11\"}},{\"id\":\"mod_68ba58fb01a79\",\"module_title\":\"Module 2\",\"module_description\":\"&quot;Cybersecurity Essentials: Protecting Digital Ecosystems&quot;\\r\\n\\r\\nThis module provides a comprehensive introduction to cybersecurity, focusing on safeguarding digital systems and data in an interconnected world. Students will explore fundamental concepts such as threat identification, risk assessment, encryption, network security, and secure software practices. The course covers real-world cyber threats, including malware, phishing, and social engineering, while introducing tools and strategies to mitigate them, such as firewalls, intrusion detection systems, and secure coding. Through practical exercises and case studies, learners will gain hands-on experience in protecting digital ecosystems and develop skills to address evolving cybersecurity challenges. Ideal for beginners and those seeking to understand the principles of securing IT environments.\",\"module_order\":2,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-05 05:28:59\"},{\"id\":\"mod_68ba594bcf0eb\",\"module_title\":\"Module 3\",\"module_description\":\"Cloud Computing Fundamentals: Architecture and Applications\\r\\n\\r\\nThis module introduces the essentials of cloud computing, exploring its architecture, services, and practical applications in modern IT environments. Students will learn about cloud models (IaaS, PaaS, SaaS), deployment strategies (public, private, hybrid), and key providers like AWS, Azure, and Google Cloud. The course covers virtualization, cloud storage, scalability, and security considerations, with hands-on exercises in deploying and managing cloud-based solutions. Through real-world case studies, learners will understand how cloud technologies drive business innovation and efficiency. Ideal for beginners and those aiming to build foundational skills in designing and leveraging cloud-based IT systems.\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[{\"id\":\"assess_68ba5addafb29\",\"assessment_title\":\"Assessment: Cloud Computing Fundamentals Quiz\",\"description\":\"This quiz assesses your understanding of key concepts in the &quot;Cloud Computing Fundamentals: Architecture and Applications&quot; module. The quiz consists of multiple-choice, identification, and true-or-false questions.\",\"time_limit\":10,\"difficulty\":\"medium\",\"num_questions\":10,\"passing_rate\":80,\"attempt_limit\":3,\"assessment_order\":1,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-05 05:37:01\"}],\"created_at\":\"2025-09-05 05:30:19\",\"updated_at\":\"2025-09-05 09:47:32\"}]', '[\"6\",\"7\",\"8\",\"9\"]', '2025-09-05 01:38:52', '2025-09-07 09:56:30'),
(14, 'Computer Organization', 'IT-CO202', 'Computer architecture, assembly language, and hardware-software interface.', 67, 'active', 1, '2nd Year', 3, 0, '[]', '[\"6\",\"7\",\"8\",\"9\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(15, 'Discrete Mathematics', 'IT-DM203', 'Mathematical foundations for computer science including logic, sets, and combinatorics.', 68, 'active', 1, '2nd Year', 3, 0, '[]', '[\"6\",\"7\",\"8\",\"9\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(16, 'Human-Computer Interaction', 'IT-HCI204', 'User interface design, usability principles, and user experience methodologies.', 69, 'active', 1, '2nd Year', 3, 0, '[]', '[\"6\",\"7\",\"8\",\"9\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(17, 'System Analysis and Design', 'IT-SAD205', 'Systems development lifecycle, requirements gathering, and system modeling.', 70, 'active', 1, '2nd Year', 3, 0, '[]', '[\"6\",\"7\",\"8\",\"9\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(18, 'Advanced Web Technologies', 'IT-AWT301', 'Modern web frameworks, APIs, cloud computing, and web security.', 71, 'active', 1, '3rd Year', 3, 0, '[]', '[\"10\",\"11\",\"12\",\"13\",4]', '2025-09-05 01:38:52', '2025-09-06 10:06:43'),
(19, 'Machine Learning Fundamentals', 'IT-ML302', 'Introduction to machine learning algorithms, data preprocessing, and model evaluation.', 72, 'active', 1, '3rd Year', 3, 0, '[]', '[\"10\",\"11\",\"12\",\"13\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(20, 'Advanced Database Systems', 'IT-ADS303', 'Distributed databases, NoSQL, data warehousing, and big data technologies.', 73, 'active', 1, '3rd Year', 3, 0, '[]', '[\"10\",\"11\",\"12\",\"13\",4]', '2025-09-05 01:38:52', '2025-09-06 10:06:43'),
(21, 'Computer Security', 'IT-CS304', 'Cryptography, secure coding practices, and security assessment methodologies.', 74, 'active', 1, '3rd Year', 3, 0, '[]', '[\"10\",\"11\",\"12\",\"13\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(22, 'Software Testing and Quality Assurance', 'IT-STQA305', 'Testing methodologies, automated testing, and software quality metrics.', 75, 'active', 1, '3rd Year', 3, 0, '[]', '[\"10\",\"11\",\"12\",\"13\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(23, 'Advanced Mobile Development', 'IT-AMD401', 'Cross-platform development, mobile security, and performance optimization.', 76, 'active', 1, '4th Year', 3, 0, '[]', '[\"14\",\"15\",\"16\",\"17\",4]', '2025-09-05 01:38:52', '2025-09-06 10:06:43'),
(24, 'Artificial Intelligence', 'IT-AI402', 'AI algorithms, neural networks, natural language processing, and computer vision.', 77, 'active', 1, '4th Year', 3, 0, '[]', '[\"14\",\"15\",\"16\",\"17\",4]', '2025-09-05 01:38:52', '2025-09-06 10:06:43'),
(25, 'Cloud Computing', 'IT-CC403', 'Cloud platforms, containerization, serverless computing, and DevOps practices.', 78, 'active', 1, '4th Year', 3, 0, '[]', '[\"14\",\"15\",\"16\",\"17\",5]', '2025-09-05 01:38:52', '2025-09-05 01:41:08'),
(26, 'Cybersecurity Management', 'IT-CM404', 'Security governance, risk management, and incident response strategies.', 79, 'active', 1, '4th Year', 3, 0, '[]', '[\"14\",\"15\",\"16\",\"17\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(27, 'Enterprise Application Development', 'IT-EAD405', 'Large-scale application development, enterprise patterns, and integration strategies.', 80, 'active', 1, '4th Year', 3, 0, '[]', '[\"14\",\"15\",\"16\",\"17\"]', '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(28, 'test', '4556', 'hggh hgh hf gf hgf hgf gf ghf ghfhjgf hjhfhgfhgfghf hfh ggj g', 2, 'active', 1, '2', 3, 0, '[{\"id\":\"mod_68bb977918691\",\"module_title\":\"ggggj gjh j\",\"module_description\":\"jhjhj jhhh kh hjkh hk hk\",\"module_order\":1,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[{\"id\":\"assess_68bd89771825d\",\"assessment_title\":\"hfhf hhg\",\"description\":\"a\",\"time_limit\":5,\"difficulty\":\"medium\",\"num_questions\":10,\"passing_rate\":70,\"attempt_limit\":3,\"assessment_order\":2,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-07 15:32:39\",\"updated_at\":\"2025-09-07 16:02:12\"},{\"id\":\"assess_68bd8983e52d3\",\"assessment_title\":\"qwetyyy\",\"description\":\"hhkj kjhk hk\",\"time_limit\":5,\"difficulty\":\"medium\",\"num_questions\":10,\"passing_rate\":70,\"attempt_limit\":3,\"assessment_order\":1,\"is_active\":true,\"status\":\"active\",\"created_at\":\"2025-09-07 15:32:51\",\"updated_at\":\"2025-09-07 16:02:19\"}],\"created_at\":\"2025-09-06 04:07:53\"},{\"id\":\"mod_68bb97a2a3340\",\"module_title\":\"ytyutu\",\"module_description\":\"hjgjhgjhg hjg gj j\",\"module_order\":3,\"is_locked\":0,\"unlock_score\":70,\"videos\":[],\"assessments\":[],\"created_at\":\"2025-09-06 04:08:34\"}]', '[4]', '2025-09-06 02:07:01', '2025-09-07 14:02:19');

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
(1, 4, 1, 'active', '2025-09-01 05:00:44', NULL, NULL, 33.33, 0, '2025-09-01 05:00:44', '2025-09-07 10:53:20', '{\"mod_68b572d5b916e\":{\"is_completed\":1,\"completed_at\":\"2025-09-04 05:47:45\"}}', '{\"vid_68bd4be93e383\":{\"is_watched\":1,\"watched_at\":\"2025-09-07 11:45:44\"},\"vid_68bd5bfcdc039\":{\"is_watched\":1,\"watched_at\":\"2025-09-07 12:53:20\",\"watch_duration\":61,\"completion_percentage\":100}}'),
(2, 5, 1, 'active', '2025-09-01 05:00:52', NULL, NULL, 0.00, 0, '2025-09-01 05:00:52', '2025-09-01 05:00:52', NULL, NULL),
(3, 16, 3, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(4, 17, 3, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(5, 36, 3, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(6, 37, 3, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(7, 18, 4, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(8, 19, 4, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(9, 38, 4, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(10, 39, 4, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(11, 20, 5, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(12, 21, 5, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(13, 40, 5, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(14, 41, 5, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(15, 22, 6, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(16, 23, 6, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(17, 42, 6, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(18, 43, 6, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(19, 24, 7, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(20, 25, 7, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(21, 44, 7, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(22, 45, 7, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(23, 26, 8, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(24, 27, 8, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(25, 46, 8, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(26, 47, 8, 'active', '2025-09-05 01:15:03', NULL, NULL, 0.00, 0, '2025-09-05 01:15:03', '2025-09-05 01:15:03', NULL, NULL),
(27, 28, 9, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(28, 29, 9, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(29, 48, 9, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(30, 49, 9, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(31, 30, 10, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(32, 31, 10, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(33, 50, 10, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(34, 51, 10, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(35, 32, 11, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(36, 33, 11, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(37, 52, 11, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(38, 53, 11, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(39, 34, 12, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(40, 35, 12, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(41, 54, 12, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(42, 55, 12, 'active', '2025-09-05 01:15:04', NULL, NULL, 0.00, 0, '2025-09-05 01:15:04', '2025-09-05 01:15:04', NULL, NULL),
(83, 4, 18, 'active', '2025-09-05 01:51:21', NULL, NULL, 0.00, 0, '2025-09-05 01:51:21', '2025-09-05 01:51:21', NULL, NULL),
(84, 4, 20, 'active', '2025-09-05 01:51:23', NULL, NULL, 0.00, 0, '2025-09-05 01:51:23', '2025-09-05 01:51:23', NULL, NULL),
(85, 4, 23, 'active', '2025-09-05 01:51:26', NULL, NULL, 0.00, 0, '2025-09-05 01:51:26', '2025-09-05 01:51:26', NULL, NULL),
(86, 4, 13, 'active', '2025-09-05 03:53:01', NULL, NULL, 0.00, 0, '2025-09-05 03:53:01', '2025-09-07 09:56:55', NULL, '{\"vid_68bd56ce1e7b1\":{\"is_watched\":1,\"watched_at\":\"2025-09-07 11:56:55\"}}'),
(87, 4, 3, 'active', '2025-09-05 07:46:21', NULL, NULL, 0.00, 0, '2025-09-05 07:46:21', '2025-09-05 07:46:21', NULL, NULL),
(88, 4, 28, 'active', '2025-09-07 12:13:36', NULL, NULL, 0.00, 0, '2025-09-07 12:13:36', '2025-09-07 12:13:36', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course_modules`
--

CREATE TABLE `course_modules` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `module_title` varchar(255) NOT NULL,
  `module_description` text DEFAULT NULL,
  `module_order` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_videos`
--

CREATE TABLE `course_videos` (
  `id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `video_title` varchar(255) NOT NULL,
  `video_description` text DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `video_file` varchar(255) DEFAULT NULL,
  `video_order` int(11) DEFAULT 1,
  `min_watch_time` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 4, 2, 'rejected', '2025-09-04 05:44:26', '2025-09-04 05:45:22', 3, '', 0),
(2, 66, 3, 'rejected', '2025-09-05 01:36:46', '2025-09-05 01:41:41', 2, '', 0),
(3, 4, 13, 'approved', '2025-09-05 03:49:15', '2025-09-05 03:53:01', 2, NULL, 0),
(4, 4, 3, 'approved', '2025-09-05 07:46:10', '2025-09-05 07:46:21', 2, NULL, 0),
(5, 4, 5, 'pending', '2025-09-05 08:20:31', NULL, NULL, NULL, 0),
(6, 4, 28, 'approved', '2025-09-07 12:13:14', '2025-09-07 12:13:36', 2, NULL, 0);

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
(21, '::1', 'espiritu@gmail.com', '2025-09-05 00:45:58', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(22, '::1', 'salvador@gmail.com', '2025-09-05 01:16:09', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(23, '::1', 'puesca@gmail.com', '2025-09-05 01:37:08', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(26, '::1', 'puesca@gmail.com', '2025-09-05 01:56:18', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(27, '::1', 'puesca@gmail.com', '2025-09-05 03:22:07', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(28, '::1', 'espiritu@gmail.com', '2025-09-05 03:48:15', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(29, '::1', 'puesca@gmail.com', '2025-09-05 05:27:39', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(30, '::1', 'espiritu@gmail.com', '2025-09-05 05:27:50', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(31, '::1', 'espiritu@gmail.com', '2025-09-05 07:08:51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(32, '::1', 'espiritu@gmail.com', '2025-09-05 07:14:08', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(34, '::1', 'puesca@gmail.com', '2025-09-05 12:17:10', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(35, '::1', 'puesca@gmail.com', '2025-09-06 01:52:55', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(36, '::1', 'puesca@gmail.com', '2025-09-06 02:20:42', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(37, '::1', 'espiritu@gmail.com', '2025-09-06 02:21:11', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(38, '::1', 'salvador@gmail.com', '2025-09-06 02:22:25', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(39, '::1', 'puesca@gmail.com', '2025-09-06 02:27:49', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(40, '::1', 'salvador@gmail.com', '2025-09-06 02:28:57', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, NULL, 0),
(41, '::1', 'espiritu@gmail.com', '2025-09-06 05:17:45', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(42, '::1', 'espiritu@gmail.com', '2025-09-06 06:45:00', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(43, '::1', 'salvador@gmail.com', '2025-09-06 09:28:10', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, NULL, 0),
(44, '::1', 'espiritu@gmail.com', '2025-09-06 09:39:04', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(47, '::1', 'espiritu@gmail.com', '2025-09-06 09:40:25', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(48, '::1', 'puesca@gmail.com', '2025-09-06 10:43:54', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(49, '::1', 'delacruz@gmail.com', '2025-09-06 10:44:13', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(50, '::1', 'eusebio@gmail.com', '2025-09-06 10:46:19', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(51, '::1', 'norte@gmail.com', '2025-09-06 11:22:31', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(52, '::1', 'norte@gmail.com', '2025-09-06 12:12:27', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(53, '::1', 'eusebio@gmail.com', '2025-09-06 12:12:40', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(54, '::1', 'puesca@gmail.com', '2025-09-06 12:13:15', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(55, '::1', 'salvador@gmail.com', '2025-09-07 00:07:06', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, NULL, 0),
(56, '::1', 'norte@gmail.com', '2025-09-07 00:07:30', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(58, '::1', 'espiritu@gmail.com', '2025-09-07 00:09:05', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(59, '::1', 'puesca@gmail.com', '2025-09-07 00:09:58', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(60, '::1', 'espiritu@gmail.com', '2025-09-07 08:39:51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(61, '::1', 'puesca@gmail.com', '2025-09-07 08:40:02', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', NULL, NULL, 0),
(62, '::1', 'salvador@gmail.com', '2025-09-07 08:40:59', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, NULL, 0),
(63, '::1', 'espiritu@gmail.com', '2025-09-07 12:09:56', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', NULL, NULL, 0),
(64, '::1', 'puesca@gmail.com', '2025-09-07 12:10:21', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', NULL, NULL, 0);

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
(1, 4, 'Enrollment Request Rejected', 'Your enrollment request for course \'Networking 1, Fundamentals\' has been rejected. You can request enrollment again if needed.', 'enrollment_rejected', 1, 0, 'normal', 'pending', NULL, '2025-09-04 05:45:22'),
(2, 66, 'Enrollment Request Rejected', 'Your enrollment request for course \'Programming Fundamentals\' has been rejected. You can request enrollment again if needed.', 'enrollment_rejected', 2, 0, 'normal', 'pending', NULL, '2025-09-05 01:41:41'),
(3, 4, '🎉 New Badge Earned!', 'Congratulations! You\'ve earned the \'High Achiever\' badge. Scored 90% or higher on an assessment', 'badge', NULL, 0, 'normal', 'pending', NULL, '2025-09-05 01:55:16'),
(4, 4, '🎉 New Badge Earned!', 'Congratulations! You\'ve earned the \'Early Bird\' badge. Completed an assessment within the first hour of availability', 'badge', NULL, 0, 'normal', 'pending', NULL, '2025-09-05 01:55:16'),
(5, 4, 'Enrollment Request Approved', 'Your enrollment request for course \'Object-Oriented Programming\' has been approved! You can now access the course.', 'enrollment_approved', 3, 0, 'normal', 'pending', NULL, '2025-09-05 03:53:01'),
(6, 4, 'Enrollment Request Approved', 'Your enrollment request for course \'Programming Fundamentals\' has been approved! You can now access the course.', 'enrollment_approved', 4, 0, 'normal', 'pending', NULL, '2025-09-05 07:46:21'),
(7, 4, 'Enrollment Request Approved', 'Your enrollment request for course \'test\' has been approved! You can now access the course.', 'enrollment_approved', 6, 0, 'normal', 'pending', NULL, '2025-09-07 12:13:36');

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
(58, 'assess_68ba5addafb29', 'Which of the following is NOT a primary cloud service model?', 'multiple_choice', 1, 4, '[{\"text\":\"Infrastructure as a Service (IaaS)\",\"is_correct\":false,\"order\":1},{\"text\":\"Software as a Service (SaaS)\",\"is_correct\":false,\"order\":2},{\"text\":\"Platform as a Service (PaaS)\",\"is_correct\":false,\"order\":3},{\"text\":\"Network as a Service (NaaS)\",\"is_correct\":true,\"order\":4}]', '2025-09-05 03:47:50', NULL),
(59, 'assess_68ba5addafb29', 'Which cloud deployment model offers the most control over security and infrastructure?', 'multiple_choice', 2, 4, '[{\"text\":\"public\",\"is_correct\":false,\"order\":1},{\"text\":\"private\",\"is_correct\":true,\"order\":2},{\"text\":\"hybrid\",\"is_correct\":false,\"order\":3},{\"text\":\"community\",\"is_correct\":false,\"order\":4}]', '2025-09-05 03:47:50', NULL),
(60, 'assess_68ba5addafb29', 'What is a key benefit of virtualization in cloud computing?', 'multiple_choice', 3, 4, '[{\"text\":\"Increased hardware costs\",\"is_correct\":false,\"order\":1},{\"text\":\"Improved resource utilization\",\"is_correct\":true,\"order\":2},{\"text\":\"Reduced scalability\",\"is_correct\":false,\"order\":3},{\"text\":\"Limited access to applications\",\"is_correct\":false,\"order\":4}]', '2025-09-05 03:47:50', NULL),
(61, 'assess_68ba5addafb29', 'Which cloud provider offers the &quot;Elastic Compute Cloud (EC2)&quot; service?', 'multiple_choice', 4, 4, '[{\"text\":\"Microsoft Azure\",\"is_correct\":false,\"order\":1},{\"text\":\"Google Cloud Platform\",\"is_correct\":true,\"order\":2},{\"text\":\"Amazon Web Services (AWS)\",\"is_correct\":false,\"order\":3},{\"text\":\"IBM Cloud\",\"is_correct\":false,\"order\":4}]', '2025-09-05 03:47:50', NULL),
(62, 'assess_68ba5addafb29', 'Name the cloud service model that provides access to software applications over the internet without requiring users to manage the underlying infrastructure.', 'identification', 5, 5, '[{\"text\":\"SOFTWARE AS A SERVICE (SAAS)\",\"is_correct\":true,\"order\":1}]', '2025-09-05 03:47:50', NULL),
(63, 'assess_68ba5addafb29', 'Identify the cloud deployment model that combines both public and private clouds to leverage benefits of both', 'identification', 6, 5, '[{\"text\":\"HYBRID\",\"is_correct\":true,\"order\":1}]', '2025-09-05 03:47:50', NULL),
(64, 'assess_68ba5addafb29', 'Name a common security risk in cloud computing that involves unauthorized access to sensitive data.', 'identification', 7, 5, '[{\"text\":\"DATA BREACH\",\"is_correct\":true,\"order\":1}]', '2025-09-05 03:47:50', NULL),
(65, 'assess_68ba5addafb29', 'Cloud computing always requires on-premises hardware to function effectively.', 'true_false', 8, 3, '[{\"text\":\"True\",\"is_correct\":false,\"order\":1},{\"text\":\"False\",\"is_correct\":true,\"order\":2}]', '2025-09-05 03:47:50', NULL),
(66, 'assess_68ba5addafb29', 'Hybrid cloud deployments allow businesses to store sensitive data on a private cloud while using public cloud resources for less sensitive tasks.', 'true_false', 9, 1, '[{\"text\":\"True\",\"is_correct\":true,\"order\":1},{\"text\":\"False\",\"is_correct\":false,\"order\":2}]', '2025-09-05 03:47:50', NULL),
(67, 'assess_68ba5addafb29', 'Object storage is typically used for high-performance applications requiring low-latency access.', 'true_false', 10, 1, '[{\"text\":\"True\",\"is_correct\":false,\"order\":1},{\"text\":\"False\",\"is_correct\":true,\"order\":2}]', '2025-09-05 03:47:50', NULL),
(68, 'assess_68ba8f00de59f', 'Which of the following is a common security threat in cloud computing?', 'multiple_choice', 1, 1, '[{\"text\":\"Physical server damage\",\"is_correct\":false,\"order\":1},{\"text\":\"Data breach\",\"is_correct\":true,\"order\":2},{\"text\":\"Hardware failure\",\"is_correct\":false,\"order\":3},{\"text\":\"Slow internet speed\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:26:48', NULL),
(69, 'assess_68ba8f00de59f', 'What security mechanism is used to encode data to prevent unauthorized access in the cloud?', 'multiple_choice', 2, 1, '[{\"text\":\"Load balancing\",\"is_correct\":false,\"order\":1},{\"text\":\"Encryption\",\"is_correct\":true,\"order\":2},{\"text\":\"Virtualization\",\"is_correct\":false,\"order\":3},{\"text\":\"Scalability\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:26:48', NULL),
(70, 'assess_68ba8f00de59f', 'Which cloud deployment model typically offers the highest level of security control?', 'multiple_choice', 3, 1, '[{\"text\":\"Public\",\"is_correct\":false,\"order\":1},{\"text\":\"Private\",\"is_correct\":true,\"order\":2},{\"text\":\"Hybrid\",\"is_correct\":false,\"order\":3},{\"text\":\"Community\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:26:48', NULL),
(71, 'assess_68ba8f00de59f', 'Which tool is commonly used to control access to cloud resources?', 'multiple_choice', 4, 1, '[{\"text\":\"Firewall\",\"is_correct\":false,\"order\":1},{\"text\":\"Virtual Machine Monitor\",\"is_correct\":false,\"order\":2},{\"text\":\"Identity and Access Management (IAM)\",\"is_correct\":true,\"order\":3},{\"text\":\"Content Delivery Network (CDN)\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:26:48', NULL),
(72, 'assess_68ba8f00de59f', 'Name the security practice that verifies the identity of users before granting access to cloud resources.', 'identification', 5, 1, '[{\"text\":\"AUTHENTICATION\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:26:48', NULL),
(73, 'assess_68ba8f00de59f', 'Identify a common cloud security threat caused by incorrect settings in cloud configurations.', 'identification', 6, 1, '[{\"text\":\"MISCONFIGURATION\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:26:48', NULL),
(74, 'assess_68ba8f00de59f', 'Name one regulatory standard that governs data privacy in cloud systems, often relevant for healthcare data.', 'identification', 7, 1, '[{\"text\":\"HIPAA\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:26:48', NULL),
(75, 'assess_68ba8f00de59f', 'Multi-factor authentication (MFA) requires only a password to access cloud systems.', 'true_false', 8, 1, '[{\"text\":\"True\",\"is_correct\":false,\"order\":1},{\"text\":\"False\",\"is_correct\":true,\"order\":2}]', '2025-09-05 07:26:48', NULL),
(76, 'assess_68ba8f00de59f', 'Public clouds are inherently less secure than private clouds due to shared infrastructure.', 'true_false', 9, 1, '[{\"text\":\"True\",\"is_correct\":false,\"order\":1},{\"text\":\"False\",\"is_correct\":true,\"order\":2}]', '2025-09-05 07:26:48', NULL),
(77, 'assess_68ba8f00de59f', 'Encryption protects data both at rest and in transit in cloud environments', 'true_false', 10, 1, '[{\"text\":\"True\",\"is_correct\":true,\"order\":1},{\"text\":\"False\",\"is_correct\":false,\"order\":2}]', '2025-09-05 07:26:48', NULL),
(78, 'assess_68ba92062de48', 'Which of the following is a valid data type in most programming languages?', 'multiple_choice', 1, 1, '[{\"text\":\"Loop\",\"is_correct\":false,\"order\":1},{\"text\":\"Integer\",\"is_correct\":true,\"order\":2},{\"text\":\"Function\",\"is_correct\":false,\"order\":3},{\"text\":\"Condition\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:43:03', NULL),
(79, 'assess_68ba92062de48', 'What is the purpose of a loop in programming?', 'multiple_choice', 2, 1, '[{\"text\":\"To store data permanently\",\"is_correct\":false,\"order\":1},{\"text\":\"To execute a block of code repeatedly\",\"is_correct\":true,\"order\":2},{\"text\":\"To define a new function\",\"is_correct\":false,\"order\":3},{\"text\":\"To debug syntax errors\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:43:03', NULL),
(80, 'assess_68ba92062de48', 'Which programming language is known for its simplicity and readability, often recommended for beginners?', 'multiple_choice', 3, 1, '[{\"text\":\"C++\",\"is_correct\":false,\"order\":1},{\"text\":\"Python\",\"is_correct\":true,\"order\":2},{\"text\":\"Assembly\",\"is_correct\":false,\"order\":3},{\"text\":\"Fortran\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:43:03', NULL),
(81, 'assess_68ba92062de48', 'What does a conditional statement (e.g., if-else) do in a program?', 'multiple_choice', 4, 1, '[{\"text\":\"Repeats a block of code\",\"is_correct\":false,\"order\":1},{\"text\":\"Stores multiple values in a list\",\"is_correct\":false,\"order\":2},{\"text\":\"Makes decisions based on conditions\",\"is_correct\":true,\"order\":3},{\"text\":\"Defines a new variable\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:43:03', NULL),
(82, 'assess_68ba92062de48', 'Name the programming construct used to execute a block of code only if a certain condition is true.', 'identification', 5, 1, '[{\"text\":\"CONDITIONAL STATEMENT\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:43:03', NULL),
(83, 'assess_68ba92062de48', 'Identify the term for a named block of code that performs a specific task and can be reused.', 'identification', 6, 1, '[{\"text\":\"FUNCTION\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:43:03', NULL),
(84, 'assess_68ba92062de48', 'Name a basic data type used to store whole numbers in programming', 'identification', 7, 1, '[{\"text\":\"INTEGER\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:43:03', NULL),
(85, 'assess_68ba92062de48', 'A variable must be declared with a specific data type in all programming languages.', 'true_false', 8, 1, '[{\"text\":\"True\",\"is_correct\":false,\"order\":1},{\"text\":\"False\",\"is_correct\":true,\"order\":2}]', '2025-09-05 07:43:03', NULL),
(86, 'assess_68ba92062de48', 'A function can accept input parameters to perform specific tasks.', 'true_false', 9, 1, '[{\"text\":\"True\",\"is_correct\":true,\"order\":1},{\"text\":\"False\",\"is_correct\":false,\"order\":2}]', '2025-09-05 07:43:03', NULL),
(87, 'assess_68ba92062de48', 'Syntax errors occur when a program runs but produces incorrect results.', 'true_false', 10, 1, '[{\"text\":\"True\",\"is_correct\":false,\"order\":1},{\"text\":\"False\",\"is_correct\":true,\"order\":2}]', '2025-09-05 07:43:03', NULL),
(88, 'assess_68ba967f9a7f1', 'What is the primary purpose of a class in object-oriented programming?', 'multiple_choice', 1, 1, '[{\"text\":\"To store temporary data\",\"is_correct\":false,\"order\":1},{\"text\":\"To serve as a blueprint for creating objects\",\"is_correct\":true,\"order\":2},{\"text\":\"To execute repetitive tasks\",\"is_correct\":false,\"order\":3},{\"text\":\"To compile code\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:58:56', NULL),
(89, 'assess_68ba967f9a7f1', 'Which OOP principle hides the internal details of an object and exposes only necessary functionality?', 'multiple_choice', 2, 1, '[{\"text\":\"Inheritance\",\"is_correct\":false,\"order\":1},{\"text\":\"Polymorphism\",\"is_correct\":false,\"order\":2},{\"text\":\"Abstraction\",\"is_correct\":true,\"order\":3},{\"text\":\"Compilation\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:58:56', NULL),
(90, 'assess_68ba967f9a7f1', 'In OOP, what term describes a class that inherits properties from another class?', 'multiple_choice', 3, 1, '[{\"text\":\"Parent class\",\"is_correct\":false,\"order\":1},{\"text\":\"Subclass\",\"is_correct\":true,\"order\":2},{\"text\":\"Object\",\"is_correct\":false,\"order\":3},{\"text\":\"Method\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:58:56', NULL),
(91, 'assess_68ba967f9a7f1', 'Which of the following is an example of polymorphism in OOP?', 'multiple_choice', 4, 1, '[{\"text\":\"Defining multiple variables in a class\",\"is_correct\":false,\"order\":1},{\"text\":\"A method performing different actions based on the object type\",\"is_correct\":true,\"order\":2},{\"text\":\"Storing data in a private variable\",\"is_correct\":false,\"order\":3},{\"text\":\"Creating a new class\",\"is_correct\":false,\"order\":4}]', '2025-09-05 07:58:56', NULL),
(92, 'assess_68ba967f9a7f1', 'Name the OOP principle that allows objects to be treated as instances of their parent class.', 'identification', 5, 1, '[{\"text\":\"POLYMORPHISM\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:58:56', NULL),
(93, 'assess_68ba967f9a7f1', 'Identify the term for a specific instance of a class that contains data and methods.', 'identification', 6, 1, '[{\"text\":\"OBJECT\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:58:56', NULL),
(94, 'assess_68ba967f9a7f1', 'Name the OOP concept that bundles data and methods together, restricting direct access to some components', 'identification', 7, 1, '[{\"text\":\"ENCAPSULATION\",\"is_correct\":true,\"order\":1}]', '2025-09-05 07:58:56', NULL),
(95, 'assess_68ba967f9a7f1', 'All classes in OOP must inherit from another class.', 'true_false', 8, 1, '[{\"text\":\"True\",\"is_correct\":true,\"order\":1},{\"text\":\"False\",\"is_correct\":false,\"order\":2}]', '2025-09-05 07:58:56', NULL),
(96, 'assess_68ba967f9a7f1', 'Encapsulation improves code security by restricting access to an object’s internal data.', 'true_false', 9, 1, '[{\"text\":\"True\",\"is_correct\":true,\"order\":1},{\"text\":\"False\",\"is_correct\":false,\"order\":2}]', '2025-09-05 07:58:56', NULL),
(97, 'assess_68ba967f9a7f1', 'In OOP, an object can only belong to one class at a time.', 'true_false', 10, 1, '[{\"text\":\"True\",\"is_correct\":true,\"order\":1},{\"text\":\"False\",\"is_correct\":false,\"order\":2}]', '2025-09-05 07:58:56', NULL);

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
(2, 1, 'A', 'Section A for first year', 1, 1, '[4,5,16,17,18,19,20,21,22,23,24,25,26,27,66]', NULL, '2025-09-01 03:03:59'),
(3, 1, 'B', 'Section B for 1st Year', 1, 3, '[\"28\", \"29\", \"30\", \"31\", \"32\", \"33\", \"34\", \"35\", \"36\", \"37\", \"38\", \"39\", \"41\"]', NULL, '2025-09-01 03:16:41'),
(4, 1, 'C', 'Section C for 1st Year', 1, 1, '[\"42\", \"43\", \"44\", \"45\", \"46\", \"47\", \"48\", \"49\", \"50\", \"51\", \"52\", \"53\", \"54\", \"55\", \"122\", \"101\", \"26\", \"108\", \"5\", \"119\"]', NULL, '2025-09-05 01:23:34'),
(5, 1, 'D', 'Section D for 1st Year', 1, 1, '[\"134\"]', NULL, '2025-09-05 01:23:34'),
(6, 2, 'A', 'Section A for 2nd Year', 1, 1, '[66,135]', NULL, '2025-09-05 01:23:34'),
(7, 2, 'B', 'Section B for 2nd Year', 1, 1, '[87,88,89,90,91,92,93,94,95]', NULL, '2025-09-05 01:23:34'),
(8, 2, 'C', 'Section C for 2nd Year', 1, 1, '[96,97,98,99,100,101,102,103,104,105,107,108,109,110]', NULL, '2025-09-05 01:23:34'),
(9, 2, 'D', 'Section D for 2nd Year', 1, 1, '[\"111\",\"112\",\"113\",\"114\",\"115\",\"116\",\"117\",\"118\",\"119\",\"120\",\"121\",\"122\",\"123\",\"124\",\"125\"]', NULL, '2025-09-05 01:23:34'),
(10, 3, 'A', 'Section A for 3rd Year', 1, 1, '[126,127,128,129,130,131]', NULL, '2025-09-05 01:23:34'),
(11, 3, 'B', 'Section B for 3rd Year', 1, 1, '[]', NULL, '2025-09-05 01:23:34'),
(12, 3, 'C', 'Section C for 3rd Year', 1, 1, '[]', NULL, '2025-09-05 01:23:34'),
(13, 3, 'D', 'Section D for 3rd Year', 1, 1, '[\"40\"]', NULL, '2025-09-05 01:23:34'),
(14, 4, 'A', 'Section A for 4th Year', 1, 1, '[]', NULL, '2025-09-05 01:23:34'),
(15, 4, 'B', 'Section B for 4th Year', 1, 1, '[]', NULL, '2025-09-05 01:23:34'),
(16, 4, 'C', 'Section C for 4th Year', 1, 1, '[]', NULL, '2025-09-05 01:23:34'),
(17, 4, 'D', 'Section D for 4th Year', 1, 1, '[]', NULL, '2025-09-05 01:23:34');

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
  `year_level` int(11) DEFAULT 1 COMMENT 'Student year level (1-4)',
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

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `profile_picture`, `is_irregular`, `year_level`, `identifier`, `department`, `access_level`, `academic_period_id`, `created_at`, `updated_at`) VALUES
(1, 'mon', 'salvador@gmail.com', '$2y$10$9dBJLQrfknEAO922pc6sE.ol/dc9DVv.ZIQI7Zt/te3JCETbEO1cG', 'Raymond', 'Salvador', 'admin', 'active', NULL, 0, 1, '1', NULL, 'super_admin', NULL, '2025-09-01 00:22:53', '2025-09-01 01:52:16'),
(2, 'aga', 'puesca@gmail.com', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Lawrence', 'Puesca', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00001', NULL, NULL, NULL, '2025-09-01 01:57:07', '2025-09-01 01:57:07'),
(3, 'jl', 'eusebio@gmail.com', '$2y$10$SRqOR/5Wig75yS38lFwaZuglCi4/GPnmFRNvRPHKMUF37de5WLsOq', 'John Lloyd', 'Eusebio', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00002', NULL, NULL, NULL, '2025-09-01 01:58:30', '2025-09-01 01:58:40'),
(4, 'jj', 'espiritu@gmail.com', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'John Joseph', 'Espiritu', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00001', NULL, NULL, NULL, '2025-09-01 02:02:37', '2025-09-01 02:02:37'),
(5, 'mj', 'delacruz@gmail.com', '$2y$10$2oWwIACG/K75nr6pwMHjK.B8sMKlGsTEMqSazqNpQNiws.6Draawy', 'Mark James', 'Dela Cruz', 'student', 'active', NULL, 1, 1, 'NEUST-MGT(STD)-00002', NULL, NULL, NULL, '2025-09-01 02:03:27', '2025-09-01 02:03:27'),
(6, 'prof_smith', 'smith@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Sarah', 'Smith', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00003', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(7, 'prof_johnson', 'johnson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Michael', 'Johnson', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00004', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(8, 'prof_wilson', 'wilson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Emily', 'Wilson', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00005', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(9, 'prof_brown', 'brown@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. David', 'Brown', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00006', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(10, 'prof_davis', 'davis@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Lisa', 'Davis', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00007', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(11, 'prof_miller', 'miller@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Robert', 'Miller', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00008', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(12, 'prof_garcia', 'garcia@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Maria', 'Garcia', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00009', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(13, 'prof_martinez', 'martinez@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Carlos', 'Martinez', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00010', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(14, 'prof_rodriguez', 'rodriguez@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Ana', 'Rodriguez', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00011', 'Computer Science', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(15, 'prof_lee', 'lee@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. James', 'Lee', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00012', 'Information Technology', NULL, NULL, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(16, 'student001', 'student001@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Juan', 'Santos', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00003', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(17, 'student002', 'student002@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Maria', 'Cruz', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00004', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(18, 'student003', 'student003@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Jose', 'Reyes', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00005', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(19, 'student004', 'student004@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Ana', 'Gonzales', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00006', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(20, 'student005', 'student005@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Pedro', 'Lopez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00007', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(21, 'student006', 'student006@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carmen', 'Torres', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00008', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(22, 'student007', 'student007@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Miguel', 'Flores', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00009', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(23, 'student008', 'student008@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Isabella', 'Mendoza', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00010', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(24, 'student009', 'student009@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Rafael', 'Herrera', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00011', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(25, 'student010', 'student010@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sofia', 'Vargas', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00012', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(26, 'student011', 'student011@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Diego', 'Castillo', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00013', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(27, 'student012', 'student012@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Valentina', 'Jimenez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00014', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(28, 'student013', 'student013@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sebastian', 'Morales', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00015', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(29, 'student014', 'student014@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Camila', 'Ortiz', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00016', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(30, 'student015', 'student015@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Mateo', 'Ramirez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00017', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(31, 'student016', 'student016@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Natalia', 'Silva', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00018', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(32, 'student017', 'student017@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alejandro', 'Vega', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00019', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(33, 'student018', 'student018@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Gabriela', 'Ramos', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00020', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(34, 'student019', 'student019@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Nicolas', 'Pena', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00021', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(35, 'student020', 'student020@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Lucia', 'Guerrero', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00022', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(36, 'student021', 'student021@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carlos', 'Fernandez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00023', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(37, 'student022', 'student022@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Elena', 'Martinez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00024', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(38, 'student023', 'student023@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Fernando', 'Gutierrez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00025', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(39, 'student024', 'student024@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Patricia', 'Ruiz', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00026', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(40, 'student025', 'student025@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Antonio', 'Diaz', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00027', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(41, 'student026', 'student026@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Monica', 'Herrera', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00028', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(42, 'student027', 'student027@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Roberto', 'Moreno', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00029', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(43, 'student028', 'student028@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Cristina', 'Alvarez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00030', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(44, 'student029', 'student029@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Francisco', 'Romero', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00031', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(45, 'student030', 'student030@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Adriana', 'Navarro', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00032', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(46, 'student031', 'student031@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Manuel', 'Torres', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00033', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(47, 'student032', 'student032@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Beatriz', 'Jimenez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00034', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(48, 'student033', 'student033@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alberto', 'Molina', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00035', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(49, 'student034', 'student034@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Dolores', 'Castro', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00036', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(50, 'student035', 'student035@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Eduardo', 'Ortega', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00037', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(51, 'student036', 'student036@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Rosa', 'Delgado', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00038', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(52, 'student037', 'student037@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Javier', 'Vega', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00039', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(53, 'student038', 'student038@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Teresa', 'Ramos', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00040', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(54, 'student039', 'student039@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Ruben', 'Pena', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00041', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(55, 'student040', 'student040@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Pilar', 'Guerrero', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00042', NULL, NULL, 1, '2025-09-05 01:03:15', '2025-09-05 01:03:15'),
(66, 'ken', 'ken@gmail.com', '$2y$10$zZ62B6cCQmBmY/w2UGWYgudeOmvDKZ10CUTVKDTA.ic2tnVtCXGWa', 'Ken', 'Kaneki', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00043', NULL, NULL, NULL, '2025-09-05 01:35:05', '2025-09-06 10:40:34'),
(67, 'prof_anderson', 'anderson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Jennifer', 'Anderson', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00013', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(68, 'prof_taylor', 'taylor@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Christopher', 'Taylor', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00014', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(69, 'prof_thomas', 'thomas@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Amanda', 'Thomas', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00015', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(70, 'prof_white', 'white@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Daniel', 'White', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00016', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(71, 'prof_harris', 'harris@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Sarah', 'Harris', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00017', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(72, 'prof_martin', 'martin@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Kevin', 'Martin', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00018', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(73, 'prof_thompson', 'thompson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Michelle', 'Thompson', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00019', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(74, 'prof_garcia2', 'garcia2@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Anthony', 'Garcia', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00020', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(75, 'prof_martinez2', 'martinez2@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Jessica', 'Martinez', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00021', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(76, 'prof_robinson', 'robinson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Matthew', 'Robinson', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00022', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(77, 'prof_clark', 'clark@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Nicole', 'Clark', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00023', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(78, 'prof_rodriguez2', 'rodriguez2@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Andrew', 'Rodriguez', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00024', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(79, 'prof_lewis', 'lewis@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Stephanie', 'Lewis', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00025', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(80, 'prof_lee2', 'lee2@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Ryan', 'Lee', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00026', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(81, 'prof_walker', 'walker@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Lauren', 'Walker', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00027', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(82, 'prof_hall', 'hall@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Brandon', 'Hall', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00028', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(83, 'prof_allen', 'allen@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Rachel', 'Allen', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00029', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(84, 'prof_young', 'young@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Justin', 'Young', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00030', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(85, 'prof_king', 'king@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Samantha', 'King', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00031', 'Computer Science', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(86, 'prof_wright', 'wright@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Tyler', 'Wright', 'teacher', 'active', NULL, 0, 1, 'NEUST-MGT(TCH)-00032', 'Information Technology', NULL, NULL, '2025-09-05 01:38:52', '2025-09-05 01:38:52'),
(87, 'student042', 'student042@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Valentina', 'Castro', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00044', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(88, 'student043', 'student043@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sebastian', 'Ortega', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00045', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(89, 'student044', 'student044@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Camila', 'Delgado', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00046', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(90, 'student045', 'student045@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Mateo', 'Vega', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00047', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(91, 'student046', 'student046@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Natalia', 'Ramos', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00048', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(92, 'student047', 'student047@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alejandro', 'Pena', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00049', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(93, 'student048', 'student048@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Gabriela', 'Guerrero', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00050', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(94, 'student049', 'student049@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Nicolas', 'Fernandez', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00051', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(95, 'student050', 'student050@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Lucia', 'Martinez', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00052', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(96, 'student051', 'student051@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carlos', 'Gutierrez', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00053', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(97, 'student052', 'student052@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Elena', 'Ruiz', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00054', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(98, 'student053', 'student053@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Fernando', 'Diaz', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00055', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(99, 'student054', 'student054@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Patricia', 'Herrera', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00056', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(100, 'student055', 'student055@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Antonio', 'Moreno', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00057', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(101, 'student056', 'student056@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Monica', 'Alvarez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00058', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:40:34'),
(102, 'student057', 'student057@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Roberto', 'Romero', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00059', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(103, 'student058', 'student058@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Cristina', 'Navarro', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00060', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(104, 'student059', 'student059@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Francisco', 'Torres', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00061', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(105, 'student060', 'student060@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Adriana', 'Jimenez', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00062', NULL, NULL, 1, '2025-09-05 01:38:52', '2025-09-06 10:36:40'),
(107, 'student061', 'student061@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Manuel', 'Molina', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00063', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(108, 'student062', 'student062@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Beatriz', 'Castro', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00064', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:40:34'),
(109, 'student063', 'student063@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alberto', 'Ortega', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00065', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(110, 'student064', 'student064@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Dolores', 'Delgado', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00066', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(111, 'student065', 'student065@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Eduardo', 'Vega', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00067', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(112, 'student066', 'student066@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Rosa', 'Ramos', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00068', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(113, 'student067', 'student067@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Javier', 'Pena', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00069', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(114, 'student068', 'student068@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Teresa', 'Guerrero', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00070', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(115, 'student069', 'student069@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Ruben', 'Fernandez', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00071', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(116, 'student070', 'student070@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Pilar', 'Martinez', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00072', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(117, 'student071', 'student071@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sergio', 'Gutierrez', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00073', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(118, 'student072', 'student072@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Isabel', 'Ruiz', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00074', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(119, 'student073', 'student073@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Hector', 'Diaz', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00075', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:40:34'),
(120, 'student074', 'student074@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carmen', 'Herrera', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00076', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(121, 'student075', 'student075@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Raul', 'Moreno', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00077', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(122, 'student076', 'student076@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Lourdes', 'Alvarez', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00078', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:40:34'),
(123, 'student077', 'student077@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Victor', 'Romero', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00079', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(124, 'student078', 'student078@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alicia', 'Navarro', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00080', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(125, 'student079', 'student079@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Oscar', 'Torres', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00081', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(126, 'student080', 'student080@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Silvia', 'Jimenez', 'student', 'active', NULL, 0, 3, 'NEUST-MGT(STD)-00082', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(127, 'student081', 'student081@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Mario', 'Molina', 'student', 'active', NULL, 0, 3, 'NEUST-MGT(STD)-00083', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(128, 'student082', 'student082@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Norma', 'Castro', 'student', 'active', NULL, 0, 3, 'NEUST-MGT(STD)-00084', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(129, 'student083', 'student083@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Rafael', 'Ortega', 'student', 'active', NULL, 0, 3, 'NEUST-MGT(STD)-00085', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(130, 'student084', 'student084@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Graciela', 'Delgado', 'student', 'active', NULL, 0, 3, 'NEUST-MGT(STD)-00086', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(131, 'student085', 'student085@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Eduardo', 'Vega', 'student', 'active', NULL, 0, 3, 'NEUST-MGT(STD)-00087', NULL, NULL, 1, '2025-09-05 01:39:11', '2025-09-06 10:36:40'),
(134, 'antet', 'orlanda@gmail.com', '$2y$10$usytB.Dwm72gOPSWwZbcJ..xvwzPuom9FEX3oxkMlvMM.88.G/HiK', 'Francis', 'Orlanda', 'student', 'active', NULL, 0, 1, 'NEUST-MGT(STD)-00088', NULL, NULL, NULL, '2025-09-06 10:00:37', '2025-09-06 10:00:37'),
(135, 'igi', 'norte@gmail.com', '$2y$10$B/RQZvHo1pbyf/Lz7t/4auiyrhg1XEFpmFRI21JUiXfEsnI/y.68y', 'Louigi', 'Norte', 'student', 'active', NULL, 0, 2, 'NEUST-MGT(STD)-00089', NULL, NULL, NULL, '2025-09-06 10:14:17', '2025-09-06 10:36:40');

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
(1, 4, 0, 61, 100.00, '2025-09-07 10:53:20');

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
-- Indexes for table `course_modules`
--
ALTER TABLE `course_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_module_order` (`module_order`);

--
-- Indexes for table `course_videos`
--
ALTER TABLE `course_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_module_id` (`module_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_video_order` (`video_order`);

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
-- Indexes for table `video_views`
--
ALTER TABLE `video_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_video` (`student_id`,`video_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_video_id` (`video_id`),
  ADD KEY `idx_watched_at` (`watched_at`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `course_modules`
--
ALTER TABLE `course_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_videos`
--
ALTER TABLE `course_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment_requests`
--
ALTER TABLE `enrollment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `file_uploads`
--
ALTER TABLE `file_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `registration_tokens`
--
ALTER TABLE `registration_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `video_views`
--
ALTER TABLE `video_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
