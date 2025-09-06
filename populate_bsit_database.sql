-- =====================================================
-- BSIT Database Population Script
-- This script populates the LMS database with:
-- - 10 BSIT-related courses
-- - 3 modules per course
-- - 20 students
-- - Teacher assignments for each course
-- =====================================================

-- First, let's add more teachers (we currently have 2, need more for 10 courses)
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `profile_picture`, `is_irregular`, `identifier`, `department`, `access_level`, `academic_period_id`, `created_at`, `updated_at`) VALUES
('prof_smith', 'smith@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Sarah', 'Smith', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00003', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_johnson', 'johnson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Michael', 'Johnson', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00004', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_wilson', 'wilson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Emily', 'Wilson', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00005', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_brown', 'brown@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. David', 'Brown', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00006', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_davis', 'davis@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Lisa', 'Davis', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00007', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_miller', 'miller@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Robert', 'Miller', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00008', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_garcia', 'garcia@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Maria', 'Garcia', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00009', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_martinez', 'martinez@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Carlos', 'Martinez', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00010', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_rodriguez', 'rodriguez@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Ana', 'Rodriguez', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00011', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_lee', 'lee@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. James', 'Lee', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00012', 'Information Technology', NULL, NULL, NOW(), NOW());

-- Add 40 students (20 for each section)
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `profile_picture`, `is_irregular`, `identifier`, `department`, `access_level`, `academic_period_id`, `created_at`, `updated_at`) VALUES
-- Section A Students (20 students)
('student001', 'student001@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Juan', 'Santos', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00003', NULL, NULL, 1, NOW(), NOW()),
('student002', 'student002@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Maria', 'Cruz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00004', NULL, NULL, 1, NOW(), NOW()),
('student003', 'student003@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Jose', 'Reyes', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00005', NULL, NULL, 1, NOW(), NOW()),
('student004', 'student004@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Ana', 'Gonzales', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00006', NULL, NULL, 1, NOW(), NOW()),
('student005', 'student005@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Pedro', 'Lopez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00007', NULL, NULL, 1, NOW(), NOW()),
('student006', 'student006@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carmen', 'Torres', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00008', NULL, NULL, 1, NOW(), NOW()),
('student007', 'student007@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Miguel', 'Flores', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00009', NULL, NULL, 1, NOW(), NOW()),
('student008', 'student008@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Isabella', 'Mendoza', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00010', NULL, NULL, 1, NOW(), NOW()),
('student009', 'student009@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Rafael', 'Herrera', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00011', NULL, NULL, 1, NOW(), NOW()),
('student010', 'student010@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sofia', 'Vargas', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00012', NULL, NULL, 1, NOW(), NOW()),
('student011', 'student011@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Diego', 'Castillo', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00013', NULL, NULL, 1, NOW(), NOW()),
('student012', 'student012@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Valentina', 'Jimenez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00014', NULL, NULL, 1, NOW(), NOW()),
('student013', 'student013@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sebastian', 'Morales', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00015', NULL, NULL, 1, NOW(), NOW()),
('student014', 'student014@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Camila', 'Ortiz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00016', NULL, NULL, 1, NOW(), NOW()),
('student015', 'student015@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Mateo', 'Ramirez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00017', NULL, NULL, 1, NOW(), NOW()),
('student016', 'student016@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Natalia', 'Silva', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00018', NULL, NULL, 1, NOW(), NOW()),
('student017', 'student017@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alejandro', 'Vega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00019', NULL, NULL, 1, NOW(), NOW()),
('student018', 'student018@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Gabriela', 'Ramos', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00020', NULL, NULL, 1, NOW(), NOW()),
('student019', 'student019@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Nicolas', 'Pena', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00021', NULL, NULL, 1, NOW(), NOW()),
('student020', 'student020@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Lucia', 'Guerrero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00022', NULL, NULL, 1, NOW(), NOW()),

-- Section B Students (20 students)
('student021', 'student021@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carlos', 'Fernandez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00023', NULL, NULL, 1, NOW(), NOW()),
('student022', 'student022@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Elena', 'Martinez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00024', NULL, NULL, 1, NOW(), NOW()),
('student023', 'student023@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Fernando', 'Gutierrez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00025', NULL, NULL, 1, NOW(), NOW()),
('student024', 'student024@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Patricia', 'Ruiz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00026', NULL, NULL, 1, NOW(), NOW()),
('student025', 'student025@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Antonio', 'Diaz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00027', NULL, NULL, 1, NOW(), NOW()),
('student026', 'student026@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Monica', 'Herrera', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00028', NULL, NULL, 1, NOW(), NOW()),
('student027', 'student027@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Roberto', 'Moreno', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00029', NULL, NULL, 1, NOW(), NOW()),
('student028', 'student028@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Cristina', 'Alvarez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00030', NULL, NULL, 1, NOW(), NOW()),
('student029', 'student029@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Francisco', 'Romero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00031', NULL, NULL, 1, NOW(), NOW()),
('student030', 'student030@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Adriana', 'Navarro', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00032', NULL, NULL, 1, NOW(), NOW()),
('student031', 'student031@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Manuel', 'Torres', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00033', NULL, NULL, 1, NOW(), NOW()),
('student032', 'student032@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Beatriz', 'Jimenez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00034', NULL, NULL, 1, NOW(), NOW()),
('student033', 'student033@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alberto', 'Molina', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00035', NULL, NULL, 1, NOW(), NOW()),
('student034', 'student034@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Dolores', 'Castro', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00036', NULL, NULL, 1, NOW(), NOW()),
('student035', 'student035@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Eduardo', 'Ortega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00037', NULL, NULL, 1, NOW(), NOW()),
('student036', 'student036@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Rosa', 'Delgado', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00038', NULL, NULL, 1, NOW(), NOW()),
('student037', 'student037@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Javier', 'Vega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00039', NULL, NULL, 1, NOW(), NOW()),
('student038', 'student038@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Teresa', 'Ramos', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00040', NULL, NULL, 1, NOW(), NOW()),
('student039', 'student039@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Ruben', 'Pena', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00041', NULL, NULL, 1, NOW(), NOW()),
('student040', 'student040@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Pilar', 'Guerrero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00042', NULL, NULL, 1, NOW(), NOW());

-- Create 10 BSIT-related courses
INSERT INTO `courses` (`course_name`, `course_code`, `description`, `teacher_id`, `status`, `academic_period_id`, `year_level`, `credits`, `is_archived`, `modules`, `sections`, `created_at`, `updated_at`) VALUES
('Programming Fundamentals', 'IT-PROG101', 'Introduction to programming concepts, algorithms, and problem-solving techniques using high-level programming languages.', 2, 'active', 1, '1st Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Data Structures and Algorithms', 'IT-DSA201', 'Study of fundamental data structures and algorithms including arrays, linked lists, stacks, queues, trees, and graphs.', 3, 'active', 1, '2nd Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Database Management Systems', 'IT-DBMS202', 'Introduction to database concepts, design, implementation, and management using SQL and database systems.', 4, 'active', 1, '2nd Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Web Development', 'IT-WEB301', 'Front-end and back-end web development using HTML, CSS, JavaScript, and server-side technologies.', 5, 'active', 1, '3rd Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Software Engineering', 'IT-SE302', 'Software development lifecycle, methodologies, requirements analysis, design patterns, and project management.', 6, 'active', 1, '3rd Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Computer Networks', 'IT-NET303', 'Network architecture, protocols, TCP/IP, routing, switching, and network security fundamentals.', 7, 'active', 1, '3rd Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Operating Systems', 'IT-OS304', 'Operating system concepts, process management, memory management, file systems, and system programming.', 8, 'active', 1, '3rd Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Mobile Application Development', 'IT-MOBILE401', 'Development of mobile applications for iOS and Android platforms using modern frameworks and tools.', 9, 'active', 1, '4th Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Information Security', 'IT-SEC402', 'Cybersecurity fundamentals, cryptography, network security, ethical hacking, and security policies.', 10, 'active', 1, '4th Year', 3, 0, '[]', '["2","3"]', NOW(), NOW()),
('Capstone Project', 'IT-CAP403', 'Final year project integrating knowledge from all previous courses to develop a comprehensive software solution.', 11, 'active', 1, '4th Year', 3, 0, '[]', '["2","3"]', NOW(), NOW());

-- Now let's add modules for each course (3 modules per course)
-- Course 1: Programming Fundamentals
UPDATE `courses` SET `modules` = '[{"id":"mod_prog101_1","module_title":"Introduction to Programming","module_description":"Basic programming concepts and syntax","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_prog101_2","module_title":"Control Structures","module_description":"Conditionals, loops, and program flow","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_prog101_3","module_title":"Functions and Arrays","module_description":"Modular programming and data organization","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 3;

-- Course 2: Data Structures and Algorithms
UPDATE `courses` SET `modules` = '[{"id":"mod_dsa201_1","module_title":"Linear Data Structures","module_description":"Arrays, linked lists, stacks, and queues","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_dsa201_2","module_title":"Tree Structures","module_description":"Binary trees, BST, and tree traversals","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_dsa201_3","module_title":"Graph Algorithms","module_description":"Graph representation and traversal algorithms","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 4;

-- Course 3: Database Management Systems
UPDATE `courses` SET `modules` = '[{"id":"mod_dbms202_1","module_title":"Database Fundamentals","module_description":"Introduction to databases and data modeling","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_dbms202_2","module_title":"SQL Programming","module_description":"Structured Query Language and database operations","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_dbms202_3","module_title":"Database Design","module_description":"Normalization, relationships, and optimization","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 5;

-- Course 4: Web Development
UPDATE `courses` SET `modules` = '[{"id":"mod_web301_1","module_title":"Frontend Development","module_description":"HTML, CSS, and JavaScript fundamentals","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_web301_2","module_title":"Backend Development","module_description":"Server-side programming and APIs","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_web301_3","module_title":"Full-Stack Integration","module_description":"Connecting frontend and backend systems","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 6;

-- Course 5: Software Engineering
UPDATE `courses` SET `modules` = '[{"id":"mod_se302_1","module_title":"Software Development Lifecycle","module_description":"SDLC phases and methodologies","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_se302_2","module_title":"Requirements Engineering","module_description":"Gathering and analyzing software requirements","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_se302_3","module_title":"Design Patterns","module_description":"Common design patterns and best practices","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 7;

-- Course 6: Computer Networks
UPDATE `courses` SET `modules` = '[{"id":"mod_net303_1","module_title":"Network Fundamentals","module_description":"OSI model, protocols, and network topologies","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_net303_2","module_title":"TCP/IP Protocol Suite","module_description":"Internet protocols and addressing","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_net303_3","module_title":"Network Security","module_description":"Network threats, firewalls, and security protocols","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 8;

-- Course 7: Operating Systems
UPDATE `courses` SET `modules` = '[{"id":"mod_os304_1","module_title":"OS Concepts","module_description":"Process management and scheduling","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_os304_2","module_title":"Memory Management","module_description":"Virtual memory, paging, and segmentation","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_os304_3","module_title":"File Systems","module_description":"File organization, directories, and I/O operations","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 9;

-- Course 8: Mobile Application Development
UPDATE `courses` SET `modules` = '[{"id":"mod_mobile401_1","module_title":"Mobile Development Basics","module_description":"Introduction to mobile platforms and development tools","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_mobile401_2","module_title":"Native Development","module_description":"iOS and Android native app development","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_mobile401_3","module_title":"Cross-Platform Development","module_description":"React Native, Flutter, and hybrid frameworks","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 10;

-- Course 9: Information Security
UPDATE `courses` SET `modules` = '[{"id":"mod_sec402_1","module_title":"Security Fundamentals","module_description":"Basic security concepts and threats","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_sec402_2","module_title":"Cryptography","module_description":"Encryption, hashing, and digital signatures","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_sec402_3","module_title":"Network Security","module_description":"Firewalls, intrusion detection, and security policies","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 11;

-- Course 10: Capstone Project
UPDATE `courses` SET `modules` = '[{"id":"mod_cap403_1","module_title":"Project Planning","module_description":"Project proposal, requirements, and planning","module_order":1,"is_locked":0,"unlock_score":0,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_cap403_2","module_title":"Development Phase","module_description":"Implementation and testing of the project","module_order":2,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"},{"id":"mod_cap403_3","module_title":"Presentation and Documentation","module_description":"Final presentation and project documentation","module_order":3,"is_locked":0,"unlock_score":70,"videos":[],"assessments":[],"created_at":"2025-09-05 01:03:15"}]' WHERE `id` = 12;

-- Enroll students in courses (distribute them across different courses)
-- Each course will have 4 students (2 from Section A, 2 from Section B)
-- Note: Student IDs start from 6 (after existing students 1-5)
INSERT INTO `course_enrollments` (`student_id`, `course_id`, `status`, `enrolled_at`, `completion_date`, `final_grade`, `progress_percentage`, `is_completed`, `started_at`, `last_accessed`, `module_progress`, `video_progress`) VALUES
-- Course 1: Programming Fundamentals (Students 6-7, 26-27)
(6, 3, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(7, 3, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(26, 3, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(27, 3, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 2: Data Structures and Algorithms (Students 8-9, 28-29)
(8, 4, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(9, 4, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(28, 4, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(29, 4, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 3: Database Management Systems (Students 10-11, 30-31)
(10, 5, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(11, 5, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(30, 5, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(31, 5, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 4: Web Development (Students 12-13, 32-33)
(12, 6, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(13, 6, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(32, 6, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(33, 6, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 5: Software Engineering (Students 14-15, 34-35)
(14, 7, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(15, 7, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(34, 7, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(35, 7, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 6: Computer Networks (Students 16-17, 36-37)
(16, 8, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(17, 8, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(36, 8, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(37, 8, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 7: Operating Systems (Students 18-19, 38-39)
(18, 9, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(19, 9, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(38, 9, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(39, 9, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 8: Mobile Application Development (Students 20-21, 40-41)
(20, 10, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(21, 10, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(40, 10, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(41, 10, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 9: Information Security (Students 22-23, 42-43)
(22, 11, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(23, 11, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(42, 11, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(43, 11, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),

-- Course 10: Capstone Project (Students 24-25, 44-45)
(24, 12, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(25, 12, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(44, 12, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL),
(45, 12, 'active', NOW(), NULL, NULL, 0.00, 0, NOW(), NOW(), NULL, NULL);

-- Update sections to include the new students
-- Section A (ID: 2) - First 20 students (IDs 4-25)
UPDATE `sections` SET `students` = '["4","5","6","7","8","9","10","11","12","13","14","15","16","17","18","19","20","21","22","23","24","25"]' WHERE `id` = 2;

-- Section B (ID: 3) - Next 20 students (IDs 26-45)  
UPDATE `sections` SET `students` = '["26","27","28","29","30","31","32","33","34","35","36","37","38","39","40","41","42","43","44","45"]' WHERE `id` = 3;

-- Add some sample assessments for each course
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

-- Display summary
SELECT 'Database Population Complete!' as Status;
SELECT COUNT(*) as 'Total Courses' FROM courses WHERE status = 'active';
SELECT COUNT(*) as 'Total Students' FROM users WHERE role = 'student';
SELECT COUNT(*) as 'Total Teachers' FROM users WHERE role = 'teacher';
SELECT COUNT(*) as 'Total Enrollments' FROM course_enrollments WHERE status = 'active';
SELECT 'Section A Students' as Section, COUNT(*) as Count FROM users WHERE role = 'student' AND id BETWEEN 4 AND 25;
SELECT 'Section B Students' as Section, COUNT(*) as Count FROM users WHERE role = 'student' AND id BETWEEN 26 AND 45;
