-- =====================================================
-- BSIT Database Completion Script
-- This script completes the missing data from populate_bsit_database.sql
-- Run this after the main population script
-- =====================================================

-- Update courses with modules (3 modules per course)
-- Course 3: Programming Fundamentals
UPDATE `courses` SET `modules` = '[{"id":"mod_prog101_1","module_title":"Introduction to Programming","module_description":"Basic programming concepts and syntax","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_prog101_2","module_title":"Control Structures","module_description":"Conditionals, loops, and program flow","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_prog101_3","module_title":"Functions and Arrays","module_description":"Modular programming and data organization","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 3;

-- Course 4: Data Structures and Algorithms
UPDATE `courses` SET `modules` = '[{"id":"mod_dsa201_1","module_title":"Linear Data Structures","module_description":"Arrays, linked lists, stacks, and queues","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_dsa201_2","module_title":"Tree Structures","module_description":"Binary trees, BST, and tree traversals","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_dsa201_3","module_title":"Graph Algorithms","module_description":"Graph representation and traversal algorithms","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 4;

-- Course 5: Database Management Systems
UPDATE `courses` SET `modules` = '[{"id":"mod_dbms202_1","module_title":"Database Fundamentals","module_description":"Introduction to databases and data modeling","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_dbms202_2","module_title":"SQL Programming","module_description":"Structured Query Language and database operations","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_dbms202_3","module_title":"Database Design","module_description":"Normalization, relationships, and optimization","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 5;

-- Course 6: Web Development
UPDATE `courses` SET `modules` = '[{"id":"mod_web301_1","module_title":"Frontend Development","module_description":"HTML, CSS, and JavaScript fundamentals","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_web301_2","module_title":"Backend Development","module_description":"Server-side programming and APIs","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_web301_3","module_title":"Full-Stack Integration","module_description":"Connecting frontend and backend systems","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 6;

-- Course 7: Software Engineering
UPDATE `courses` SET `modules` = '[{"id":"mod_se302_1","module_title":"Software Development Lifecycle","module_description":"SDLC phases and methodologies","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_se302_2","module_title":"Requirements Engineering","module_description":"Gathering and analyzing software requirements","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_se302_3","module_title":"Design Patterns","module_description":"Common design patterns and best practices","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 7;

-- Course 8: Computer Networks
UPDATE `courses` SET `modules` = '[{"id":"mod_net303_1","module_title":"Network Fundamentals","module_description":"OSI model, protocols, and network topologies","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_net303_2","module_title":"TCP/IP Protocol Suite","module_description":"Internet protocols and addressing","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_net303_3","module_title":"Network Security","module_description":"Network threats, firewalls, and security protocols","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 8;

-- Course 9: Operating Systems
UPDATE `courses` SET `modules` = '[{"id":"mod_os304_1","module_title":"OS Concepts","module_description":"Process management and scheduling","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_os304_2","module_title":"Memory Management","module_description":"Virtual memory, paging, and segmentation","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_os304_3","module_title":"File Systems","module_description":"File organization, directories, and I/O operations","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 9;

-- Course 10: Mobile Application Development
UPDATE `courses` SET `modules` = '[{"id":"mod_mobile401_1","module_title":"Mobile Development Basics","module_description":"Introduction to mobile platforms and development tools","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_mobile401_2","module_title":"Native Development","module_description":"iOS and Android native app development","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_mobile401_3","module_title":"Cross-Platform Development","module_description":"React Native, Flutter, and hybrid frameworks","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 10;

-- Course 11: Information Security
UPDATE `courses` SET `modules` = '[{"id":"mod_sec402_1","module_title":"Security Fundamentals","module_description":"Basic security concepts and threats","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_sec402_2","module_title":"Cryptography","module_description":"Encryption, hashing, and digital signatures","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_sec402_3","module_title":"Network Security","module_description":"Firewalls, intrusion detection, and security policies","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 11;

-- Course 12: Capstone Project
UPDATE `courses` SET `modules` = '[{"id":"mod_cap403_1","module_title":"Project Planning","module_description":"Project proposal, requirements, and planning","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_cap403_2","module_title":"Development Phase","module_description":"Implementation and testing of the project","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_cap403_3","module_title":"Presentation and Documentation","module_description":"Final presentation and project documentation","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 12;

-- Add course enrollments for all students (4 students per course)
-- Course 3: Programming Fundamentals (Students 16-17, 36-37)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(16, 3, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(17, 3, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(36, 3, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(37, 3, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 4: Data Structures and Algorithms (Students 18-19, 38-39)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(18, 4, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(19, 4, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(38, 4, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(39, 4, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 5: Database Management Systems (Students 20-21, 40-41)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(20, 5, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(21, 5, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(40, 5, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(41, 5, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 6: Web Development (Students 22-23, 42-43)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(22, 6, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(23, 6, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(42, 6, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(43, 6, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 7: Software Engineering (Students 24-25, 44-45)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(24, 7, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(25, 7, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(44, 7, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(45, 7, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 8: Computer Networks (Students 26-27, 46-47)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(26, 8, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(27, 8, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(46, 8, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(47, 8, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 9: Operating Systems (Students 28-29, 48-49)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(28, 9, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(29, 9, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(48, 9, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(49, 9, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 10: Mobile Application Development (Students 30-31, 50-51)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(30, 10, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(31, 10, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(50, 10, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(51, 10, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 11: Information Security (Students 32-33, 52-53)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(32, 11, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(33, 11, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(52, 11, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(53, 11, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Course 12: Capstone Project (Students 34-35, 54-55)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
(34, 12, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(35, 12, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(54, 12, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(55, 12, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Create additional sections for each year level (4 sections per year)
-- First, let's create the missing sections
INSERT INTO `sections` (`year_level`, `section_name`, `description`, `is_active`, `academic_period_id`, `students`, `teachers`, `created_at`) VALUES
-- 1st Year Sections
(1, 'C', 'Section C for 1st Year', 1, 1, NULL, NULL, NOW()),
(1, 'D', 'Section D for 1st Year', 1, 1, NULL, NULL, NOW()),

-- 2nd Year Sections
(2, 'A', 'Section A for 2nd Year', 1, 1, NULL, NULL, NOW()),
(2, 'B', 'Section B for 2nd Year', 1, 1, NULL, NULL, NOW()),
(2, 'C', 'Section C for 2nd Year', 1, 1, NULL, NULL, NOW()),
(2, 'D', 'Section D for 2nd Year', 1, 1, NULL, NULL, NOW()),

-- 3rd Year Sections
(3, 'A', 'Section A for 3rd Year', 1, 1, NULL, NULL, NOW()),
(3, 'B', 'Section B for 3rd Year', 1, 1, NULL, NULL, NOW()),
(3, 'C', 'Section C for 3rd Year', 1, 1, NULL, NULL, NOW()),
(3, 'D', 'Section D for 3rd Year', 1, 1, NULL, NULL, NOW()),

-- 4th Year Sections
(4, 'A', 'Section A for 4th Year', 1, 1, NULL, NULL, NOW()),
(4, 'B', 'Section B for 4th Year', 1, 1, NULL, NULL, NOW()),
(4, 'C', 'Section C for 4th Year', 1, 1, NULL, NULL, NOW()),
(4, 'D', 'Section D for 4th Year', 1, 1, NULL, NULL, NOW());

-- Update existing sections and distribute students across all sections
-- Note: We have 55 students total (IDs 1-55), so we'll distribute them realistically
-- 1st Year - Section A (ID: 2) - 14 students (existing + new students 16-27)
UPDATE `sections` SET `students` = '["4","5","16","17","18","19","20","21","22","23","24","25","26","27"]' WHERE `id` = 2;

-- 1st Year - Section B (ID: 3) - 14 students (students 28-41)
UPDATE `sections` SET `students` = '["28","29","30","31","32","33","34","35","36","37","38","39","40","41"]' WHERE `id` = 3;

-- 1st Year - Section C (ID: 4) - 14 students (students 42-55)
UPDATE `sections` SET `students` = '["42","43","44","45","46","47","48","49","50","51","52","53","54","55"]' WHERE `id` = 4;

-- 1st Year - Section D (ID: 5) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 5;

-- 2nd Year - Section A (ID: 6) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 6;

-- 2nd Year - Section B (ID: 7) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 7;

-- 2nd Year - Section C (ID: 8) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 8;

-- 2nd Year - Section D (ID: 9) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 9;

-- 3rd Year - Section A (ID: 10) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 10;

-- 3rd Year - Section B (ID: 11) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 11;

-- 3rd Year - Section C (ID: 12) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 12;

-- 3rd Year - Section D (ID: 13) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 13;

-- 4th Year - Section A (ID: 14) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 14;

-- 4th Year - Section B (ID: 15) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 15;

-- 4th Year - Section C (ID: 16) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 16;

-- 4th Year - Section D (ID: 17) - Empty for now (can add more students later)
UPDATE `sections` SET `students` = '[]' WHERE `id` = 17;

-- Add assessments for each BSIT course
INSERT INTO `assessments` (`id`, `course_id`, `assessment_title`, `assessment_order`, `description`, `time_limit`, `difficulty`, `status`, `num_questions`, `passing_rate`, `attempt_limit`, `is_locked`, `lock_type`, `prerequisite_assessment_id`, `prerequisite_score`, `prerequisite_video_count`, `unlock_date`, `lock_message`, `questions`, `lock_updated_at`, `created_at`, `updated_at`) VALUES
('assess_prog101_1', 3, 'Programming Basics Quiz', 1, 'Basic programming concepts and syntax', 30, 'easy', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_dsa201_1', 4, 'Data Structures Quiz', 1, 'Linear data structures assessment', 45, 'medium', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_dbms202_1', 5, 'Database Fundamentals Quiz', 1, 'Basic database concepts and SQL', 40, 'medium', 'active', 12, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_web301_1', 6, 'Web Development Quiz', 1, 'HTML, CSS, and JavaScript basics', 35, 'easy', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_se302_1', 7, 'Software Engineering Quiz', 1, 'SDLC and requirements engineering', 50, 'medium', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_net303_1', 8, 'Network Fundamentals Quiz', 1, 'OSI model and network protocols', 45, 'medium', 'active', 12, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_os304_1', 9, 'Operating Systems Quiz', 1, 'Process management and scheduling', 40, 'hard', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_mobile401_1', 10, 'Mobile Development Quiz', 1, 'Mobile platform fundamentals', 35, 'medium', 'active', 10, 70.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_sec402_1', 11, 'Information Security Quiz', 1, 'Security fundamentals and threats', 45, 'hard', 'active', 15, 75.00, 3, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW()),
('assess_cap403_1', 12, 'Capstone Project Proposal', 1, 'Project planning and requirements', 60, 'hard', 'active', 20, 80.00, 2, 0, 'manual', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NOW(), NOW());

-- Display completion summary
SELECT 'Database Completion Complete!' as Status;
SELECT COUNT(*) as 'Total Courses with Modules' FROM courses WHERE modules IS NOT NULL AND modules != '[]';
SELECT COUNT(*) as 'Total Students' FROM users WHERE role = 'student';
SELECT COUNT(*) as 'Total Teachers' FROM users WHERE role = 'teacher';
SELECT COUNT(*) as 'Total Enrollments' FROM course_enrollments WHERE status = 'active';
SELECT COUNT(*) as 'Total Assessments' FROM assessments;
SELECT COUNT(*) as 'Total Sections' FROM sections;
SELECT '1st Year Section A' as Section, 14 as Count;
SELECT '1st Year Section B' as Section, 14 as Count;
SELECT '1st Year Section C' as Section, 14 as Count;
SELECT '1st Year Section D' as Section, 0 as Count;
SELECT '2nd Year Sections' as Section, 0 as Count;
SELECT '3rd Year Sections' as Section, 0 as Count;
SELECT '4th Year Sections' as Section, 0 as Count;
