-- =====================================================
-- Additional Courses for Each Year Level
-- Creates courses for 2nd, 3rd, and 4th year students
-- =====================================================

-- 2nd Year Courses (IDs 13-18)
INSERT INTO `courses` (`course_name`, `course_code`, `description`, `teacher_id`, `status`, `academic_period_id`, `year_level`, `credits`, `is_archived`, `modules`, `sections`, `created_at`, `updated_at`) VALUES
('Object-Oriented Programming', 'IT-OOP201', 'Advanced programming concepts using object-oriented principles and design patterns.', 16, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
('Computer Organization', 'IT-CO202', 'Computer architecture, assembly language, and hardware-software interface.', 17, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
('Discrete Mathematics', 'IT-DM203', 'Mathematical foundations for computer science including logic, sets, and combinatorics.', 18, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
('Human-Computer Interaction', 'IT-HCI204', 'User interface design, usability principles, and user experience methodologies.', 19, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW()),
('System Analysis and Design', 'IT-SAD205', 'Systems development lifecycle, requirements gathering, and system modeling.', 20, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW()),
('Computer Graphics', 'IT-CG206', '2D and 3D graphics programming, rendering techniques, and graphics algorithms.', 21, 'active', 1, '2nd Year', 3, 0, '[]', '["6","7","8","9"]', NOW(), NOW());

-- 3rd Year Courses (IDs 19-24)
INSERT INTO `courses` (`course_name`, `course_code`, `description`, `teacher_id`, `status`, `academic_period_id`, `year_level`, `credits`, `is_archived`, `modules`, `sections`, `created_at`, `updated_at`) VALUES
('Advanced Web Technologies', 'IT-AWT301', 'Modern web frameworks, APIs, cloud computing, and web security.', 22, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Machine Learning Fundamentals', 'IT-ML302', 'Introduction to machine learning algorithms, data preprocessing, and model evaluation.', 23, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Advanced Database Systems', 'IT-ADS303', 'Distributed databases, NoSQL, data warehousing, and big data technologies.', 24, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Computer Security', 'IT-CS304', 'Cryptography, secure coding practices, and security assessment methodologies.', 25, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Software Testing and Quality Assurance', 'IT-STQA305', 'Testing methodologies, automated testing, and software quality metrics.', 26, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW()),
('Distributed Systems', 'IT-DS306', 'Distributed computing concepts, microservices, and cloud architecture patterns.', 27, 'active', 1, '3rd Year', 3, 0, '[]', '["10","11","12","13"]', NOW(), NOW());

-- 4th Year Courses (IDs 25-30)
INSERT INTO `courses` (`course_name`, `course_code`, `description`, `teacher_id`, `status`, `academic_period_id`, `year_level`, `credits`, `is_archived`, `modules`, `sections`, `created_at`, `updated_at`) VALUES
('Advanced Mobile Development', 'IT-AMD401', 'Cross-platform development, mobile security, and performance optimization.', 28, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Artificial Intelligence', 'IT-AI402', 'AI algorithms, neural networks, natural language processing, and computer vision.', 29, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Cloud Computing', 'IT-CC403', 'Cloud platforms, containerization, serverless computing, and DevOps practices.', 30, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Cybersecurity Management', 'IT-CM404', 'Security governance, risk management, and incident response strategies.', 31, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Enterprise Application Development', 'IT-EAD405', 'Large-scale application development, enterprise patterns, and integration strategies.', 32, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW()),
('Research Methods in IT', 'IT-RMIT406', 'Research methodologies, academic writing, and thesis preparation techniques.', 33, 'active', 1, '4th Year', 3, 0, '[]', '["14","15","16","17"]', NOW(), NOW());

-- Display course summary
SELECT 'Additional Courses Added!' as Status;
SELECT 
    year_level,
    COUNT(*) as course_count
FROM courses 
WHERE year_level IS NOT NULL
GROUP BY year_level
ORDER BY year_level;
