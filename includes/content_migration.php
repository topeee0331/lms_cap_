<?php
/**
 * Content Migration System
 * 
 * This file contains functions to migrate content (courses, modules, assessments) 
 * from inactive academic periods to active ones for reuse.
 */

require_once 'semester_security.php';

/**
 * Migrate a course from one academic period to another
 * 
 * IMPORTANT: This function ONLY migrates course content (modules, assessments, questions).
 * It does NOT migrate student enrollments, student progress, or any student data.
 * Students must enroll separately in the new academic period.
 * 
 * @param PDO $pdo Database connection
 * @param int $source_course_id Source course ID
 * @param int $target_academic_period_id Target academic period ID
 * @param int $teacher_id Teacher ID (must own the course)
 * @return array Result array with success status and details
 */
function migrateCourse($pdo, $source_course_id, $target_academic_period_id, $teacher_id) {
    try {
        $pdo->beginTransaction();
        
        // Verify teacher owns the source course
        $stmt = $pdo->prepare("
            SELECT c.*, ap.academic_year, ap.semester_name 
            FROM courses c 
            JOIN academic_periods ap ON c.academic_period_id = ap.id 
            WHERE c.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$source_course_id, $teacher_id]);
        $source_course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$source_course) {
            throw new Exception('Course not found or you do not have permission to migrate it.');
        }
        
        // Check if target academic period is active
        $stmt = $pdo->prepare("SELECT is_active FROM academic_periods WHERE id = ?");
        $stmt->execute([$target_academic_period_id]);
        $target_period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$target_period || !$target_period['is_active']) {
            throw new Exception('Target academic period is not active.');
        }
        
        // Check if course with same name or code already exists in target period
        $stmt = $pdo->prepare("
            SELECT id FROM courses 
            WHERE (course_name = ? OR course_code = ?) AND academic_period_id = ? AND teacher_id = ?
        ");
        $stmt->execute([$source_course['course_name'], $source_course['course_code'], $target_academic_period_id, $teacher_id]);
        if ($stmt->fetch()) {
            throw new Exception('A course with the same name or code already exists in the target academic period.');
        }
        
        // Create new course in target academic period
        // Get the next available integer ID
        $stmt = $pdo->prepare("SELECT MAX(id) as max_id FROM courses");
        $stmt->execute();
        $max_id = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'];
        $new_course_id = ($max_id + 1);
        
        // Make course code unique by appending a timestamp suffix
        $timestamp = date('YmdHis');
        $new_course_code = $source_course['course_code'] . '_' . $timestamp;
        $new_course_name = $source_course['course_name'] . ' (Migrated)';
        
        $stmt = $pdo->prepare("
            INSERT INTO courses (
                id, teacher_id, course_name, course_code, description, 
                status, academic_period_id, year_level, credits, is_archived,
                modules, sections, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $new_course_id,
            $teacher_id,
            $new_course_name,
            $new_course_code,
            $source_course['description'],
            'active', // Reset status to active
            $target_academic_period_id,
            $source_course['year_level'], // Copy year level
            $source_course['credits'] ?? 3, // Copy credits or default to 3
            0, // Reset archived status
            $source_course['modules'], // Copy modules JSON
            '[]' // Reset sections to empty array - sections should not be migrated
        ]);
        
        // Migrate assessments
        $migrated_assessments = migrateAssessments($pdo, $source_course_id, $new_course_id);
        
        // IMPORTANT: Ensure no student enrollments are created
        // The migration system is designed to ONLY migrate course content,
        // NOT student enrollments or student data
        $stmt = $pdo->prepare("SELECT COUNT(*) as enrollment_count FROM course_enrollments WHERE course_id = ?");
        $stmt->execute([$new_course_id]);
        $enrollment_count = $stmt->fetch(PDO::FETCH_ASSOC)['enrollment_count'];
        
        if ($enrollment_count > 0) {
            // This should never happen, but if it does, clean it up
            error_log("WARNING: Found {$enrollment_count} student enrollments in migrated course {$new_course_id}. Removing them.");
            $stmt = $pdo->prepare("DELETE FROM course_enrollments WHERE course_id = ?");
            $stmt->execute([$new_course_id]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Course migrated successfully.',
            'new_course_id' => $new_course_id,
            'migrated_assessments' => $migrated_assessments,
            'source_period' => $source_course['academic_year'] . ' - ' . $source_course['semester_name']
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log('Migration error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Migrate assessments from source course to target course
 * 
 * @param PDO $pdo Database connection
 * @param int $source_course_id Source course ID
 * @param int $target_course_id Target course ID
 * @return array Array of migrated assessment IDs
 */
function migrateAssessments($pdo, $source_course_id, $target_course_id) {
    $migrated_assessments = [];
    
    // Get all assessments from source course
    $stmt = $pdo->prepare("
        SELECT * FROM assessments 
        WHERE course_id = ? 
        ORDER BY assessment_order ASC
    ");
    $stmt->execute([$source_course_id]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($assessments as $assessment) {
        // Create new assessment ID (assessments use string IDs like 'assess_68b63fb3c750e')
        $new_assessment_id = 'assess_' . uniqid();
        
        // Insert new assessment
        $stmt = $pdo->prepare("
            INSERT INTO assessments (
                id, course_id, assessment_title, assessment_order, description, 
                time_limit, difficulty, status, num_questions, passing_rate, 
                attempt_limit, is_locked, lock_type, questions, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $new_assessment_id,
            $target_course_id,
            $assessment['assessment_title'],
            $assessment['assessment_order'],
            $assessment['description'],
            $assessment['time_limit'],
            $assessment['difficulty'],
            'active', // Reset to active in new period
            $assessment['num_questions'] ?? 10, // Default to 10 if not set
            $assessment['passing_rate'],
            $assessment['attempt_limit'],
            0, // Reset lock status
            'manual', // Reset lock type
            $assessment['questions'] // Copy questions JSON
        ]);
        
        // Migrate questions
        migrateQuestions($pdo, $assessment['id'], $new_assessment_id);
        
        $migrated_assessments[] = [
            'old_id' => $assessment['id'],
            'new_id' => $new_assessment_id,
            'title' => $assessment['assessment_title']
        ];
    }
    
    return $migrated_assessments;
}

/**
 * Migrate questions from source assessment to target assessment
 * 
 * @param PDO $pdo Database connection
 * @param int $source_assessment_id Source assessment ID
 * @param int $target_assessment_id Target assessment ID
 * @return int Number of questions migrated
 */
function migrateQuestions($pdo, $source_assessment_id, $target_assessment_id) {
    // Get all questions from source assessment
    $stmt = $pdo->prepare("
        SELECT * FROM questions 
        WHERE assessment_id = ? 
        ORDER BY question_order ASC
    ");
    $stmt->execute([$source_assessment_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $migrated_count = 0;
    foreach ($questions as $question) {
        $stmt = $pdo->prepare("
            INSERT INTO questions (
                assessment_id, question_text, question_type, question_order, 
                points, options, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $target_assessment_id,
            $question['question_text'],
            $question['question_type'],
            $question['question_order'],
            $question['points'],
            $question['options']
        ]);
        $migrated_count++;
    }
    
    return $migrated_count;
}

/**
 * Get courses available for migration (from inactive academic periods)
 * 
 * @param PDO $pdo Database connection
 * @param int $teacher_id Teacher ID
 * @return array Array of courses available for migration
 */
function getCoursesForMigration($pdo, $teacher_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, ap.academic_year, ap.semester_name, ap.is_active,
               COUNT(a.id) as assessment_count
        FROM courses c
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        LEFT JOIN assessments a ON c.id = a.course_id
        WHERE c.teacher_id = ? AND ap.is_active = 0
        GROUP BY c.id
        ORDER BY ap.academic_year DESC, ap.semester_name DESC, c.created_at DESC
    ");
    $stmt->execute([$teacher_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get active academic periods available for migration
 * 
 * @param PDO $pdo Database connection
 * @return array Array of active academic periods
 */
function getActiveAcademicPeriods($pdo) {
    $stmt = $pdo->prepare("
        SELECT id, academic_year, semester_name 
        FROM academic_periods 
        WHERE is_active = 1 
        ORDER BY academic_year DESC, semester_name DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if content can be modified based on academic period status
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return array Array with permission details
 */
function checkContentModificationPermission($pdo, $course_id) {
    $status = checkCourseAcademicStatus($pdo, $course_id);
    
    return [
        'can_modify' => $status['is_active'],
        'is_view_only' => !$status['is_active'],
        'academic_period' => $status['academic_year'] . ' - ' . $status['semester_name'],
        'reason' => $status['reason'],
        'message' => $status['is_active'] ? 
            'Content can be modified.' : 
            'Content is view-only. Academic period is inactive.'
    ];
}

/**
 * Get migration statistics for a teacher
 * 
 * @param PDO $pdo Database connection
 * @param int $teacher_id Teacher ID
 * @return array Migration statistics
 */
function getMigrationStats($pdo, $teacher_id) {
    // Count courses in inactive periods
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as inactive_courses
        FROM courses c
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        WHERE c.teacher_id = ? AND ap.is_active = 0
    ");
    $stmt->execute([$teacher_id]);
    $inactive_courses = $stmt->fetchColumn();
    
    // Count courses in active periods
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_courses
        FROM courses c
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        WHERE c.teacher_id = ? AND ap.is_active = 1
    ");
    $stmt->execute([$teacher_id]);
    $active_courses = $stmt->fetchColumn();
    
    return [
        'inactive_courses' => $inactive_courses,
        'active_courses' => $active_courses,
        'total_courses' => $inactive_courses + $active_courses
    ];
}
?>
