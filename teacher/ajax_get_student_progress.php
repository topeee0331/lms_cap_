<?php
require_once '../includes/header.php';
requireRole('teacher');

header('Content-Type: application/json');

try {
    // Get parameters
    $academic_period_id = (int)($_GET['academic_period_id'] ?? 0);
    $course_filter = (int)($_GET['course'] ?? 0);
    $status_filter = sanitizeInput($_GET['status'] ?? '');
    $sort_by = sanitizeInput($_GET['sort'] ?? 'name');
    
    // Debug logging
    error_log("AJAX Progress Request - Academic Period: $academic_period_id, Course: $course_filter, Status: $status_filter, Sort: $sort_by");
    
    if (!$academic_period_id) {
        throw new Exception('Academic period ID is required');
    }
    
    // Build where conditions
    $where_conditions = ["c.teacher_id = ?", "c.academic_period_id = ?"];
    $params = [$_SESSION['user_id'], $academic_period_id];
    
    if ($course_filter > 0) {
        $where_conditions[] = "c.id = ?";
        $params[] = $course_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get student progress data
    $stmt = $db->prepare("
        SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at as user_created, u.identifier as neust_student_id,
               c.course_name, c.course_code, c.id as course_id, 
               COALESCE(s.section_name, 'Not Assigned') as section_name, 
               COALESCE(s.year_level, 'N/A') as section_year,
               e.enrolled_at, e.status as enrollment_status,
               e.progress_percentage as course_progress,
               e.last_accessed as last_activity,
               
               -- Assessment Statistics
               COALESCE(assessment_stats.total_assessments, 0) as total_assessments,
               COALESCE(assessment_stats.completed_assessments, 0) as completed_assessments,
               COALESCE(assessment_stats.avg_score, 0) as avg_score,
               COALESCE(assessment_stats.best_score, 0) as best_score,
               COALESCE(assessment_stats.total_attempts, 0) as total_attempts
               
        FROM course_enrollments e
        JOIN users u ON e.student_id = u.id
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        
        -- Assessment Statistics Subquery
        LEFT JOIN (
            SELECT 
                aa.student_id,
                c.id as course_id,
                COUNT(DISTINCT aa.assessment_id) as total_assessments,
                COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) as completed_assessments,
                ROUND(AVG(aa.score), 2) as avg_score,
                MAX(aa.score) as best_score,
                COUNT(*) as total_attempts
            FROM assessment_attempts aa
            JOIN courses c ON JSON_SEARCH(c.modules, 'one', aa.assessment_id) IS NOT NULL
            WHERE c.teacher_id = ? AND c.academic_period_id = ?
            GROUP BY aa.student_id, c.id
        ) assessment_stats ON assessment_stats.student_id = e.student_id AND assessment_stats.course_id = e.course_id
        
        $where_clause
        ORDER BY " . getSortClause($sort_by) . "
    ");
    $stmt->execute(array_merge($params, [$_SESSION['user_id'], $academic_period_id]));
    $students = $stmt->fetchAll();
    
    // Debug logging
    error_log("Found " . count($students) . " students");
    if (count($students) > 0) {
        error_log("First student data: " . json_encode($students[0]));
    }
    
    // Calculate summary statistics
    $summary = [
        'total_students' => count($students),
        'active_students' => count(array_filter($students, function($s) { 
            return ($s['enrollment_status'] ?? 'active') === 'active'; 
        })),
        'avg_progress' => count($students) > 0 ? 
            array_sum(array_column($students, 'course_progress')) / count($students) : 0,
        'avg_score' => count($students) > 0 ? 
            array_sum(array_column($students, 'avg_score')) / count($students) : 0
    ];
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'summary' => $summary,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => time()
    ]);
}

function getSortClause($sort_by) {
    switch ($sort_by) {
        case 'name':
            return 'u.last_name ASC, u.first_name ASC';
        case 'course':
            return 'c.course_name ASC';
        case 'enrolled':
            return 'e.enrolled_at DESC';
        case 'progress':
            return 'e.progress_percentage DESC';
        case 'score':
            return 'assessment_stats.avg_score DESC';
        case 'activity':
            return 'e.last_accessed DESC';
        default:
            return 'u.last_name ASC, u.first_name ASC';
    }
}
?>
