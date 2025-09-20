<?php
require_once 'config/config.php';
require_once 'config/database.php';
requireRole('admin');

header('Content-Type: application/json');

try {
    // Get performance summary data
    $performance_data = [];
    
    // Get total students across all sections
    $total_students_query = "
        SELECT COUNT(DISTINCT u.id) as total_students
        FROM users u
        JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        WHERE u.role = 'student' AND u.status = 'active'
    ";
    $stmt = $db->prepare($total_students_query);
    $stmt->execute();
    $total_students = $stmt->fetchColumn();
    
    // Get assessment statistics
    $assessment_stats_query = "
        SELECT 
            COUNT(DISTINCT aa.id) as total_attempts,
            COUNT(DISTINCT aa.student_id) as students_with_attempts,
            AVG(aa.score) as average_score,
            COUNT(CASE WHEN aa.score >= 70 THEN 1 END) as passing_attempts,
            COUNT(CASE WHEN aa.status = 'completed' THEN 1 END) as completed_attempts
        FROM assessment_attempts aa
        WHERE aa.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    $stmt = $db->prepare($assessment_stats_query);
    $stmt->execute();
    $assessment_stats = $stmt->fetch();
    
    // Get recent activity (last 24 hours)
    $recent_activity_query = "
        SELECT 
            COUNT(DISTINCT aa.student_id) as active_students_today,
            COUNT(aa.id) as attempts_today
        FROM assessment_attempts aa
        WHERE aa.started_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ";
    $stmt = $db->prepare($recent_activity_query);
    $stmt->execute();
    $recent_activity = $stmt->fetch();
    
    // Get section-wise performance
    $section_performance_query = "
        SELECT 
            s.id,
            CONCAT('BSIT-', s.year_level, s.section_name) as section_name,
            JSON_LENGTH(COALESCE(s.students, '[]')) as student_count,
            COALESCE(perf.avg_score, 0) as avg_score,
            COALESCE(perf.total_attempts, 0) as total_attempts,
            COALESCE(perf.passing_rate, 0) as passing_rate
        FROM sections s
        LEFT JOIN (
            SELECT 
                s2.id as section_id,
                AVG(aa.score) as avg_score,
                COUNT(aa.id) as total_attempts,
                ROUND((COUNT(CASE WHEN aa.score >= 70 THEN 1 END) / COUNT(aa.id)) * 100, 1) as passing_rate
            FROM sections s2
            JOIN users u ON JSON_SEARCH(s2.students, 'one', u.id) IS NOT NULL
            JOIN assessment_attempts aa ON u.id = aa.student_id
            WHERE aa.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY s2.id
        ) perf ON s.id = perf.section_id
        WHERE s.is_active = 1
        ORDER BY perf.avg_score DESC
    ";
    $stmt = $db->prepare($section_performance_query);
    $stmt->execute();
    $section_performance = $stmt->fetchAll();
    
    // Get question type performance (simplified - no detailed question tracking available)
    // Since assessment_question_answers table doesn't exist, we'll provide empty data
    $question_type_performance = [];
    
    // Calculate passing rate
    $passing_rate = $assessment_stats['total_attempts'] > 0 ? 
        round(($assessment_stats['passing_attempts'] / $assessment_stats['total_attempts']) * 100, 1) : 0;
    
    $performance_data = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'overview' => [
                'total_students' => (int)$total_students,
                'total_attempts' => (int)$assessment_stats['total_attempts'],
                'students_with_attempts' => (int)$assessment_stats['students_with_attempts'],
                'average_score' => round($assessment_stats['average_score'], 1),
                'passing_rate' => $passing_rate,
                'completed_attempts' => (int)$assessment_stats['completed_attempts']
            ],
            'recent_activity' => [
                'active_students_today' => (int)$recent_activity['active_students_today'],
                'attempts_today' => (int)$recent_activity['attempts_today']
            ],
            'section_performance' => $section_performance,
            'question_type_performance' => $question_type_performance
        ]
    ];
    
    echo json_encode($performance_data);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching performance data: ' . $e->getMessage()
    ]);
}
?>
