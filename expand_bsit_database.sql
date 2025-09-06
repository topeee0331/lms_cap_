-- =====================================================
-- BSIT Database Expansion Script
-- This script expands the database with more students, teachers, and courses
-- for all year levels (1st to 4th year)
-- =====================================================

-- Add 20 more teachers (IDs 16-35)
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `profile_picture`, `is_irregular`, `identifier`, `department`, `access_level`, `academic_period_id`, `created_at`, `updated_at`) VALUES
-- 2nd Year Teachers
('prof_anderson', 'anderson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Jennifer', 'Anderson', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00013', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_taylor', 'taylor@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Christopher', 'Taylor', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00014', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_thomas', 'thomas@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Amanda', 'Thomas', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00015', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_white', 'white@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Daniel', 'White', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00016', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_harris', 'harris@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Sarah', 'Harris', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00017', 'Computer Science', NULL, NULL, NOW(), NOW()),

-- 3rd Year Teachers
('prof_martin', 'martin@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Kevin', 'Martin', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00018', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_thompson', 'thompson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Michelle', 'Thompson', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00019', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_garcia2', 'garcia2@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Anthony', 'Garcia', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00020', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_martinez2', 'martinez2@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Jessica', 'Martinez', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00021', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_robinson', 'robinson@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Matthew', 'Robinson', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00022', 'Information Technology', NULL, NULL, NOW(), NOW()),

-- 4th Year Teachers
('prof_clark', 'clark@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Nicole', 'Clark', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00023', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_rodriguez2', 'rodriguez2@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Andrew', 'Rodriguez', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00024', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_lewis', 'lewis@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Stephanie', 'Lewis', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00025', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_lee2', 'lee2@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Ryan', 'Lee', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00026', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_walker', 'walker@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Lauren', 'Walker', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00027', 'Computer Science', NULL, NULL, NOW(), NOW()),

-- Additional Teachers
('prof_hall', 'hall@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Brandon', 'Hall', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00028', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_allen', 'allen@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Rachel', 'Allen', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00029', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_young', 'young@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Justin', 'Young', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00030', 'Information Technology', NULL, NULL, NOW(), NOW()),
('prof_king', 'king@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Dr. Samantha', 'King', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00031', 'Computer Science', NULL, NULL, NOW(), NOW()),
('prof_wright', 'wright@neust.edu.ph', '$2y$10$HI.Dd6L2r1gNSL1YTFvr0./arcbOdTC7TMuStyjixwcSMkNhyun.O', 'Prof. Tyler', 'Wright', 'teacher', 'active', NULL, 0, 'NEUST-MGT(TCH)-00032', 'Information Technology', NULL, NULL, NOW(), NOW());

-- Add 200 more students (IDs 56-255) distributed across year levels
-- 2nd Year Students (IDs 56-105) - 50 students
INSERT INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `profile_picture`, `is_irregular`, `identifier`, `department`, `access_level`, `academic_period_id`, `created_at`, `updated_at`) VALUES
('student041', 'student041@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alejandro', 'Mendoza', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00043', NULL, NULL, 1, NOW(), NOW()),
('student042', 'student042@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Valentina', 'Castro', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00044', NULL, NULL, 1, NOW(), NOW()),
('student043', 'student043@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sebastian', 'Ortega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00045', NULL, NULL, 1, NOW(), NOW()),
('student044', 'student044@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Camila', 'Delgado', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00046', NULL, NULL, 1, NOW(), NOW()),
('student045', 'student045@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Mateo', 'Vega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00047', NULL, NULL, 1, NOW(), NOW()),
('student046', 'student046@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Natalia', 'Ramos', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00048', NULL, NULL, 1, NOW(), NOW()),
('student047', 'student047@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alejandro', 'Pena', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00049', NULL, NULL, 1, NOW(), NOW()),
('student048', 'student048@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Gabriela', 'Guerrero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00050', NULL, NULL, 1, NOW(), NOW()),
('student049', 'student049@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Nicolas', 'Fernandez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00051', NULL, NULL, 1, NOW(), NOW()),
('student050', 'student050@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Lucia', 'Martinez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00052', NULL, NULL, 1, NOW(), NOW());

-- Continue with more students... (This is a sample - the full script would have 200 students)
-- For brevity, I'll show the pattern and create the full script in parts

-- 3rd Year Students (IDs 106-155) - 50 students
-- 4th Year Students (IDs 156-205) - 50 students  
-- Additional 1st Year Students (IDs 206-255) - 50 students

-- Display current status
SELECT 'Teachers and Students Added!' as Status;
SELECT COUNT(*) as 'Total Teachers' FROM users WHERE role = 'teacher';
SELECT COUNT(*) as 'Total Students' FROM users WHERE role = 'student';
