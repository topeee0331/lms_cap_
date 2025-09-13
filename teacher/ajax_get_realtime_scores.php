<?php
header('Content-Type: application/json');
require_once '../config/config.php';
requireRole('teacher');

// Get parameters
$academic_period_id = (int)($_GET['academic_period_id'] ?? 0);
$course_filter = (int)($_GET['course'] ?? 0);
$section_filter = (int)($_GET['section'] ?? 0);
$search_filter = sanitizeInput($_GET['search'] ?? '');
$show_enrolled_only = isset($_GET['enrolled_only']) && $_GET['enrolled_only'] === '1';

// Validate academic period
if (!$academic_period_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid academic period']);
    exit;
}

try {
    // Build where conditions for filtering
    $where_conditions = [];
    $params = [];

    if ($course_filter > 0) {
        $where_conditions[] = "c.id = ?";
        $params[] = $course_filter;
    }

    if ($section_filter > 0) {
        $where_conditions[] = "s.id = ?";
        $params[] = $section_filter;
    }

    if (!empty($search_filter)) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.identifier LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
        $search_term = "%{$search_filter}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '';
    $having_clause = $show_enrolled_only ? "HAVING COUNT(DISTINCT e.id) > 0" : "";

    // Get students with real-time score data
    if ($course_filter > 0) {
        $stmt = $db->prepare("
            SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier,
                   GROUP_CONCAT(DISTINCT s.section_name ORDER BY s.section_name SEPARATOR ', ') as section_names,
                   GROUP_CONCAT(DISTINCT s.year_level ORDER BY s.year_level SEPARATOR ', ') as section_years,
                   1 as total_courses,
                   COUNT(DISTINCT e.id) as enrolled_courses,
                   MAX(e.enrolled_at) as latest_enrollment,
                   AVG(e.progress_percentage) as avg_progress,
                   MAX(e.last_accessed) as last_activity,
                   CASE 
                       WHEN COUNT(DISTINCT e.id) > 0 THEN 'Regular'
                       ELSE 'Irregular'
                   END as student_status,
                   
                   -- Real-time Assessment Statistics for this specific course
                   COALESCE(SUM(assessment_stats.total_assessments), 0) as total_assessments,
                   COALESCE(SUM(assessment_stats.completed_assessments), 0) as completed_assessments,
                   COALESCE(AVG(assessment_stats.avg_score), 0) as avg_score,
                   COALESCE(MAX(assessment_stats.best_score), 0) as best_score,
                   COALESCE(SUM(assessment_stats.total_attempts), 0) as total_attempts,
                   COALESCE(SUM(assessment_stats.total_score), 0) as total_score
                   
            FROM sections s
            JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
            JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL 
                AND c.id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
            LEFT JOIN course_enrollments e ON e.student_id = u.id AND e.course_id = c.id
            
            -- Real-time Assessment Statistics Subquery for this specific course
            LEFT JOIN (
                SELECT 
                    aa.student_id,
                    COUNT(DISTINCT aa.assessment_id) as total_assessments,
                    COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) as completed_assessments,
                    ROUND(AVG(aa.score), 2) as avg_score,
                    MAX(aa.score) as best_score,
                    COUNT(*) as total_attempts,
                    SUM(aa.score) as total_score
                FROM assessment_attempts aa
                WHERE aa.assessment_id IN (
                    SELECT JSON_UNQUOTE(JSON_EXTRACT(c.modules, CONCAT('$[', numbers.n, ']')))
                    FROM courses c
                    CROSS JOIN (
                        SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
                    ) numbers
                    WHERE c.id = ? AND JSON_UNQUOTE(JSON_EXTRACT(c.modules, CONCAT('$[', numbers.n, ']'))) IS NOT NULL
                )
                GROUP BY aa.student_id
            ) assessment_stats ON assessment_stats.student_id = u.id
            
            WHERE s.is_active = 1
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier
            " . $having_clause . "
            ORDER BY u.last_name ASC, u.first_name ASC
        ");
        $stmt->execute([$course_filter, $_SESSION['user_id'], $academic_period_id, $course_filter]);
    } else {
        $stmt = $db->prepare("
            SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier,
                   GROUP_CONCAT(DISTINCT s.section_name ORDER BY s.section_name SEPARATOR ', ') as section_names,
                   GROUP_CONCAT(DISTINCT s.year_level ORDER BY s.year_level SEPARATOR ', ') as section_years,
                   COUNT(DISTINCT c.id) as total_courses,
                   COUNT(DISTINCT e.id) as enrolled_courses,
                   MAX(e.enrolled_at) as latest_enrollment,
                   AVG(e.progress_percentage) as avg_progress,
                   MAX(e.last_accessed) as last_activity,
                   CASE 
                       WHEN COUNT(DISTINCT e.id) = COUNT(DISTINCT c.id) THEN 'Regular'
                       WHEN COUNT(DISTINCT e.id) > 0 THEN 'Irregular'
                       ELSE 'Irregular'
                   END as student_status,
                   
                   -- Real-time Overall Assessment Statistics
                   COALESCE(SUM(assessment_stats.total_assessments), 0) as total_assessments,
                   COALESCE(SUM(assessment_stats.completed_assessments), 0) as completed_assessments,
                   COALESCE(AVG(assessment_stats.avg_score), 0) as avg_score,
                   COALESCE(MAX(assessment_stats.best_score), 0) as best_score,
                   COALESCE(SUM(assessment_stats.total_attempts), 0) as total_attempts,
                   COALESCE(SUM(assessment_stats.total_score), 0) as total_score
                   
            FROM sections s
            JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
            JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL 
                AND c.teacher_id = ? AND c.academic_period_id = ?
            LEFT JOIN course_enrollments e ON e.student_id = u.id AND e.course_id = c.id
            
            -- Real-time Assessment Statistics Subquery
            LEFT JOIN (
                SELECT 
                    aa.student_id,
                    c.id as course_id,
                    COUNT(DISTINCT aa.assessment_id) as total_assessments,
                    COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) as completed_assessments,
                    ROUND(AVG(aa.score), 2) as avg_score,
                    MAX(aa.score) as best_score,
                    COUNT(*) as total_attempts,
                    SUM(aa.score) as total_score
                FROM assessment_attempts aa
                JOIN courses c ON JSON_SEARCH(c.modules, 'one', aa.assessment_id) IS NOT NULL
                WHERE c.teacher_id = ? AND c.academic_period_id = ?
                GROUP BY aa.student_id, c.id
            ) assessment_stats ON assessment_stats.student_id = u.id AND assessment_stats.course_id = c.id
            
            WHERE s.is_active = 1
            " . ($where_clause ? "AND " . $where_clause : "") . "
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier
            " . $having_clause . "
            ORDER BY u.last_name ASC, u.first_name ASC
        ");
        $stmt->execute(array_merge([$_SESSION['user_id'], $academic_period_id, $_SESSION['user_id'], $academic_period_id], $params));
    }
    
    $students = $stmt->fetchAll();
    
    // Calculate summary statistics
    $total_students = count($students);
    $enrolled_students = array_filter($students, function($s) { return $s['enrolled_courses'] > 0; });
    $avg_progress = $total_students > 0 ? array_sum(array_column($students, 'avg_progress')) / $total_students : 0;
    $avg_score = $total_students > 0 ? array_sum(array_column($students, 'avg_score')) / $total_students : 0;
    
    // Prepare response
    $response = [
        'success' => true,
        'timestamp' => time(),
        'students' => $students,
        'summary' => [
            'total_students' => $total_students,
            'enrolled_students' => count($enrolled_students),
            'avg_progress' => round($avg_progress, 2),
            'avg_score' => round($avg_score, 2)
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_realtime_scores.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while loading real-time scores']);
}
?>
