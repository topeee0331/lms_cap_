<?php
/**
 * Semester Security Helper Functions
 * 
 * This file contains helper functions to check semester and academic year status
 * and enforce security rules for inactive semesters.
 */

/**
 * Check if a course is in an active academic period
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID to check
 * @return array Array with 'is_active' boolean and status details
 */
function checkCourseAcademicStatus($pdo, $course_id) {
    $stmt = $pdo->prepare("
        SELECT c.academic_period_id,
               ap.is_active as academic_period_active,
               ap.academic_year,
               ap.semester_name
        FROM courses c
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return [
            'is_active' => false,
            'academic_period_active' => false,
            'academic_year' => 'Unknown',
            'semester_name' => 'Unknown',
            'reason' => 'Course not found'
        ];
    }
    
    $is_active = $result['academic_period_active'];
    
    return [
        'is_active' => $is_active,
        'academic_period_active' => (bool)$result['academic_period_active'],
        'academic_year' => $result['academic_year'],
        'semester_name' => $result['semester_name'],
        'reason' => $is_active ? 'Active' : 'Inactive academic period'
    ];
}

/**
 * Check if an assessment is in an active academic period
 * 
 * @param PDO $pdo Database connection
 * @param int $assessment_id Assessment ID to check
 * @return array Array with 'is_active' boolean and status details
 */
function checkAssessmentAcademicStatus($pdo, $assessment_id) {
    $stmt = $pdo->prepare("
        SELECT a.id, a.course_id, c.academic_period_id,
               ap.is_active as academic_period_active,
               ap.academic_year,
               ap.semester_name
        FROM assessments a
        JOIN courses c ON a.course_id = c.id
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        WHERE a.id = ?
    ");
    $stmt->execute([$assessment_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return [
            'is_active' => false,
            'academic_period_active' => false,
            'academic_year' => 'Unknown',
            'semester_name' => 'Unknown',
            'reason' => 'Assessment not found'
        ];
    }
    
    $is_active = $result['academic_period_active'];
    
    return [
        'is_active' => $is_active,
        'academic_period_active' => (bool)$result['academic_period_active'],
        'academic_year' => $result['academic_year'],
        'semester_name' => $result['semester_name'],
        'reason' => $is_active ? 'Active' : 'Inactive academic period'
    ];
}

/**
 * Check if a module is in an active academic period
 * 
 * @param PDO $pdo Database connection
 * @param int $module_id Module ID to check
 * @return array Array with 'is_active' boolean and status details
 */
function checkModuleAcademicStatus($pdo, $module_id) {
    // Since modules are now stored as JSON in courses.modules, we need to find the course first
    $stmt = $pdo->prepare("
        SELECT c.id, c.academic_period_id,
               ap.is_active as academic_period_active,
               ap.academic_year,
               ap.semester_name
        FROM courses c
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        WHERE JSON_SEARCH(c.modules, 'one', ?, '$[*].id') IS NOT NULL
    ");
    $stmt->execute([$module_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return [
            'is_active' => false,
            'academic_period_active' => false,
            'academic_year' => 'Unknown',
            'semester_name' => 'Unknown',
            'reason' => 'Module not found'
        ];
    }
    
    $is_active = $result['academic_period_active'];
    
    return [
        'is_active' => $is_active,
        'academic_period_active' => (bool)$result['academic_period_active'],
        'academic_year' => $result['academic_year'],
        'semester_name' => $result['semester_name'],
        'reason' => $is_active ? 'Active' : 'Inactive academic period'
    ];
}

/**
 * Get a user-friendly message explaining why content is inactive
 * 
 * @param array $status Status array from check functions
 * @return string User-friendly message
 */
function getInactiveStatusMessage($status) {
    $reasons = [];
    
    if (!$status['academic_period_active']) {
        $reasons[] = "Academic Period {$status['academic_year']} - {$status['semester_name']} is inactive";
    }
    
    if (empty($reasons)) {
        return "Content is currently inactive";
    }
    
    return implode(" and ", $reasons) . ". This content is view-only for review purposes.";
}

/**
 * Check if a user can submit assessments for a given course
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID to check
 * @return bool True if user can submit, false if view-only
 */
function canSubmitAssessments($pdo, $course_id) {
    $status = checkCourseAcademicStatus($pdo, $course_id);
    return $status['is_active'];
}

/**
 * Check if a user can submit a specific assessment
 * 
 * @param PDO $pdo Database connection
 * @param int $assessment_id Assessment ID to check
 * @return bool True if user can submit, false if view-only
 */
function canSubmitAssessment($pdo, $assessment_id) {
    $status = checkAssessmentAcademicStatus($pdo, $assessment_id);
    return $status['is_active'];
}
?>
