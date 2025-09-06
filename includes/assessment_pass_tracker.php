<?php
/**
 * Assessment Pass Status Tracker
 * 
 * This file contains helper functions to track and manage assessment pass status
 * for students across multiple attempts.
 */

/**
 * Update assessment pass status for a student
 * 
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @param int $assessment_id Assessment ID
 * @param float $score Student's score (percentage)
 * @param float $passing_rate Assessment passing rate
 * @return bool Whether the student passed this attempt
 */
function updateAssessmentPassStatus($pdo, $student_id, $assessment_id, $score, $passing_rate) {
    $is_passed = $score >= $passing_rate;
    
    // Update the current attempt's pass status
    $stmt = $pdo->prepare("
        UPDATE assessment_attempts 
        SET has_passed = ? 
        WHERE student_id = ? AND assessment_id = ? 
        AND status = 'completed' 
        ORDER BY completed_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$is_passed ? 1 : 0, $student_id, $assessment_id]);
    
    // If this attempt passed, update has_ever_passed for all attempts of this student for this assessment
    if ($is_passed) {
        $stmt = $pdo->prepare("
            UPDATE assessment_attempts 
            SET has_ever_passed = 1 
            WHERE student_id = ? AND assessment_id = ? 
            AND status = 'completed'
        ");
        $stmt->execute([$student_id, $assessment_id]);
    }
    
    return $is_passed;
}

/**
 * Check if a student has ever passed a specific assessment
 * 
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @param int $assessment_id Assessment ID
 * @return bool Whether the student has ever passed this assessment
 */
function hasStudentPassedAssessment($pdo, $student_id, $assessment_id) {
    $stmt = $pdo->prepare("
        SELECT has_ever_passed 
        FROM assessment_attempts 
        WHERE student_id = ? AND assessment_id = ? 
        AND status = 'completed' 
        LIMIT 1
    ");
    $stmt->execute([$student_id, $assessment_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['has_ever_passed'] == 1;
}

/**
 * Get assessment pass statistics for a student
 * 
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @param int $assessment_id Assessment ID
 * @return array Array with pass statistics
 */
function getAssessmentPassStats($pdo, $student_id, $assessment_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_attempts,
            SUM(CASE WHEN has_passed = 1 THEN 1 ELSE 0 END) as passed_attempts,
            MAX(has_ever_passed) as has_ever_passed,
            MAX(score) as best_score,
            AVG(score) as average_score
        FROM assessment_attempts 
        WHERE student_id = ? AND assessment_id = ? 
        AND status = 'completed'
    ");
    $stmt->execute([$student_id, $assessment_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total_attempts' => (int)$result['total_attempts'],
        'passed_attempts' => (int)$result['passed_attempts'],
        'has_ever_passed' => (bool)$result['has_ever_passed'],
        'best_score' => (float)$result['best_score'],
        'average_score' => (float)$result['average_score'],
        'pass_rate' => $result['total_attempts'] > 0 ? round(($result['passed_attempts'] / $result['total_attempts']) * 100, 1) : 0
    ];
}

/**
 * Get all assessments that a student has passed
 * 
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @return array Array of passed assessment IDs
 */
function getPassedAssessments($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT assessment_id 
        FROM assessment_attempts 
        WHERE student_id = ? AND has_ever_passed = 1 
        AND status = 'completed'
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get all assessments that a student has not passed yet
 * 
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @return array Array of not passed assessment IDs
 */
function getNotPassedAssessments($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.id 
        FROM assessments a
        JOIN course_enrollments e ON a.course_id = e.course_id
        WHERE e.student_id = ? AND e.status = 'active'
        AND a.id NOT IN (
            SELECT DISTINCT assessment_id 
            FROM assessment_attempts 
            WHERE student_id = ? AND has_ever_passed = 1 
            AND status = 'completed'
        )
    ");
    $stmt->execute([$student_id, $student_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Update pass status for all existing assessment attempts
 * This function can be run after database schema changes
 * 
 * @param PDO $pdo Database connection
 * @return int Number of records updated
 */
function updateAllAssessmentPassStatus($pdo) {
    // Update has_passed for all completed attempts
    $stmt = $pdo->prepare("
        UPDATE assessment_attempts aa
        JOIN assessments a ON aa.assessment_id = a.id
        SET aa.has_passed = CASE 
            WHEN aa.score >= COALESCE(a.passing_rate, 70) THEN 1 
            ELSE 0 
        END
        WHERE aa.status = 'completed' AND aa.score IS NOT NULL
    ");
    $stmt->execute();
    $has_passed_updated = $stmt->rowCount();
    
    // Update has_ever_passed for all students and assessments
    $stmt = $pdo->prepare("
        UPDATE assessment_attempts aa1
        JOIN (
            SELECT student_id, assessment_id, MAX(has_passed) as ever_passed
            FROM assessment_attempts 
            WHERE status = 'completed'
            GROUP BY student_id, assessment_id
        ) aa2 ON aa1.student_id = aa2.student_id AND aa1.assessment_id = aa2.assessment_id
        SET aa1.has_ever_passed = aa2.ever_passed
        WHERE aa1.status = 'completed'
    ");
    $stmt->execute();
    $has_ever_passed_updated = $stmt->rowCount();
    
    return [
        'has_passed_updated' => $has_passed_updated,
        'has_ever_passed_updated' => $has_ever_passed_updated
    ];
}
?>
