-- =====================================================
-- SAFE BSIT DATABASE EXPANSION SCRIPT
-- This script safely expands the database without conflicts
-- Uses INSERT IGNORE and proper ID management
-- =====================================================

-- Check current state first
SELECT 'Starting Safe Database Expansion...' as Status;
SELECT MAX(id) as 'Max User ID' FROM users;
SELECT MAX(id) as 'Max Course ID' FROM courses;
SELECT MAX(id) as 'Max Section ID' FROM sections;

-- Step 1: Add 20 more teachers (starting from ID 66)
-- Using INSERT IGNORE to prevent conflicts
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `profile_picture`, `is_irregular`, `identifier`, `department`, `access_level`, `academic_period_id`, `created_at`, `updated_at`) VALUES
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

-- Step 2: Add students for 2nd, 3rd, and 4th year (starting from ID 66)
-- Using INSERT IGNORE to prevent conflicts
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `profile_picture`, `is_irregular`, `identifier`, `department`, `access_level`, `academic_period_id`, `created_at`, `updated_at`) VALUES
-- 2nd Year Students (Sample - 20 students)
('student041', 'student041@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alejandro', 'Mendoza', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00043', NULL, NULL, 1, NOW(), NOW()),
('student042', 'student042@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Valentina', 'Castro', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00044', NULL, NULL, 1, NOW(), NOW()),
('student043', 'student043@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Sebastian', 'Ortega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00045', NULL, NULL, 1, NOW(), NOW()),
('student044', 'student044@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Camila', 'Delgado', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00046', NULL, NULL, 1, NOW(), NOW()),
('student045', 'student045@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Mateo', 'Vega', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00047', NULL, NULL, 1, NOW(), NOW()),
('student046', 'student046@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Natalia', 'Ramos', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00048', NULL, NULL, 1, NOW(), NOW()),
('student047', 'student047@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Alejandro', 'Pena', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00049', NULL, NULL, 1, NOW(), NOW()),
('student048', 'student048@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Gabriela', 'Guerrero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00050', NULL, NULL, 1, NOW(), NOW()),
('student049', 'student049@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Nicolas', 'Fernandez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00051', NULL, NULL, 1, NOW(), NOW()),
('student050', 'student050@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Lucia', 'Martinez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00052', NULL, NULL, 1, NOW(), NOW()),
('student051', 'student051@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Carlos', 'Gutierrez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00053', NULL, NULL, 1, NOW(), NOW()),
('student052', 'student052@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Elena', 'Ruiz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00054', NULL, NULL, 1, NOW(), NOW()),
('student053', 'student053@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Fernando', 'Diaz', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00055', NULL, NULL, 1, NOW(), NOW()),
('student054', 'student054@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Patricia', 'Herrera', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00056', NULL, NULL, 1, NOW(), NOW()),
('student055', 'student055@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Antonio', 'Moreno', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00057', NULL, NULL, 1, NOW(), NOW()),
('student056', 'student056@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Monica', 'Alvarez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00058', NULL, NULL, 1, NOW(), NOW()),
('student057', 'student057@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Roberto', 'Romero', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00059', NULL, NULL, 1, NOW(), NOW()),
('student058', 'student058@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Cristina', 'Navarro', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00060', NULL, NULL, 1, NOW(), NOW()),
('student059', 'student059@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Francisco', 'Torres', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00061', NULL, NULL, 1, NOW(), NOW()),
('student060', 'student060@neust.edu.ph', '$2y$10$zr7zX3ujGxHJ6BCGGn4Zee02IUOdyKnbFv89lrNUtzdwX3mLexsc6', 'Adriana', 'Jimenez', 'student', 'active', NULL, 0, 'NEUST-MGT(STD)-00062', NULL, NULL, 1, NOW(), NOW());

-- Step 3: Add additional courses (starting from ID 13)
-- Using INSERT IGNORE to prevent conflicts
INSERT IGNORE INTO `courses` (`course_name`, `course_code`, `description`, `teacher_id`, `status`, `academic_period_id`, `year_level`, `credits`, `is_archived`, `modules`, `sections`, `created_at`, `updated_at`) VALUES
-- 2nd Year Courses
('Object-Oriented Programming', 'IT-OOP201', 'Advanced programming concepts using object-oriented principles and design patterns.', 66, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
('Computer Organization', 'IT-CO202', 'Computer architecture, assembly language, and hardware-software interface.', 67, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
('Discrete Mathematics', 'IT-DM203', 'Mathematical foundations for computer science including logic, sets, and combinatorics.', 68, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
('Human-Computer Interaction', 'IT-HCI204', 'User interface design, usability principles, and user experience methodologies.', 69, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
('System Analysis and Design', 'IT-SAD205', 'Systems development lifecycle, requirements gathering, and system modeling.', 70, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
-- 3rd Year Courses
('Advanced Web Technologies', 'IT-AWT301', 'Modern web frameworks, APIs, cloud computing, and web security.', 71, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Machine Learning Fundamentals', 'IT-ML302', 'Introduction to machine learning algorithms, data preprocessing, and model evaluation.', 72, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Advanced Database Systems', 'IT-ADS303', 'Distributed databases, NoSQL, data warehousing, and big data technologies.', 73, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Computer Security', 'IT-CS304', 'Cryptography, secure coding practices, and security assessment methodologies.', 74, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Software Testing and Quality Assurance', 'IT-STQA305', 'Testing methodologies, automated testing, and software quality metrics.', 75, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
-- 4th Year Courses
('Advanced Mobile Development', 'IT-AMD401', 'Cross-platform development, mobile security, and performance optimization.', 76, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Artificial Intelligence', 'IT-AI402', 'AI algorithms, neural networks, natural language processing, and computer vision.', 77, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Cloud Computing', 'IT-CC403', 'Cloud platforms, containerization, serverless computing, and DevOps practices.', 78, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Cybersecurity Management', 'IT-CM404', 'Security governance, risk management, and incident response strategies.', 79, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Enterprise Application Development', 'IT-EAD405', 'Large-scale application development, enterprise patterns, and integration strategies.', 80, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW());

-- Step 4: Update sections with new students (only update empty sections)
-- 2nd Year - Section A (ID: 6) - Add students 66-80
UPDATE `sections` SET `students` = '["66","67","68","69","70","71","72","73","74","75","76","77","78","79","80"]' WHERE `id` = 6 AND (`students` IS NULL OR `students` = '[]');

-- 2nd Year - Section B (ID: 7) - Add students 81-95
UPDATE `sections` SET `students` = '["81","82","83","84","85","86","87","88","89","90","91","92","93","94","95"]' WHERE `id` = 7 AND (`students` IS NULL OR `students` = '[]');

-- 2nd Year - Section C (ID: 8) - Add students 96-110
UPDATE `sections` SET `students` = '["96","97","98","99","100","101","102","103","104","105","106","107","108","109","110"]' WHERE `id` = 8 AND (`students` IS NULL OR `students` = '[]');

-- 2nd Year - Section D (ID: 9) - Add students 111-125
UPDATE `sections` SET `students` = '["111","112","113","114","115","116","117","118","119","120","121","122","123","124","125"]' WHERE `id` = 9 AND (`students` IS NULL OR `students` = '[]');

-- 3rd Year - Section A (ID: 10) - Add students 126-140
UPDATE `sections` SET `students` = '["126","127","128","129","130","131","132","133","134","135","136","137","138","139","140"]' WHERE `id` = 10 AND (`students` IS NULL OR `students` = '[]');

-- 3rd Year - Section B (ID: 11) - Add students 141-155
UPDATE `sections` SET `students` = '["141","142","143","144","145","146","147","148","149","150","151","152","153","154","155"]' WHERE `id` = 11 AND (`students` IS NULL OR `students` = '[]');

-- 3rd Year - Section C (ID: 12) - Add students 156-170
UPDATE `sections` SET `students` = '["156","157","158","159","160","161","162","163","164","165","166","167","168","169","170"]' WHERE `id` = 12 AND (`students` IS NULL OR `students` = '[]');

-- 3rd Year - Section D (ID: 13) - Add students 171-185
UPDATE `sections` SET `students` = '["171","172","173","174","175","176","177","178","179","180","181","182","183","184","185"]' WHERE `id` = 13 AND (`students` IS NULL OR `students` = '[]');

-- 4th Year - Section A (ID: 14) - Add students 186-200
UPDATE `sections` SET `students` = '["186","187","188","189","190","191","192","193","194","195","196","197","198","199","200"]' WHERE `id` = 14 AND (`students` IS NULL OR `students` = '[]');

-- 4th Year - Section B (ID: 15) - Add students 201-215
UPDATE `sections` SET `students` = '["201","202","203","204","205","206","207","208","209","210","211","212","213","214","215"]' WHERE `id` = 15 AND (`students` IS NULL OR `students` = '[]');

-- 4th Year - Section C (ID: 16) - Add students 216-230
UPDATE `sections` SET `students` = '["216","217","218","219","220","221","222","223","224","225","226","227","228","229","230"]' WHERE `id` = 16 AND (`students` IS NULL OR `students` = '[]');

-- 4th Year - Section D (ID: 17) - Add students 231-245
UPDATE `sections` SET `students` = '["231","232","233","234","235","236","237","238","239","240","241","242","243","244","245"]' WHERE `id` = 17 AND (`students` IS NULL OR `students` = '[]');

-- Display final summary
SELECT 'Safe Database Expansion Complete!' as Status;
SELECT COUNT(*) as 'Total Teachers' FROM users WHERE role = 'teacher';
SELECT COUNT(*) as 'Total Students' FROM users WHERE role = 'student';
SELECT COUNT(*) as 'Total Courses' FROM courses;
SELECT 
    s.year_level,
    s.section_name,
    JSON_LENGTH(s.students) as student_count
FROM sections s 
WHERE s.students IS NOT NULL AND s.students != '[]'
ORDER BY s.year_level, s.section_name;
