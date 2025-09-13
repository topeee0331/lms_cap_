<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get parameters
$student_id = (int)($_GET['student_id'] ?? 0);
$academic_period_id = (int)($_GET['academic_period_id'] ?? 0);

if (!$student_id || !$academic_period_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Student ID and Academic Period ID are required']);
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get student basic info
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.identifier
        FROM users u
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
    
    // Get student's courses with the teacher
    $stmt = $pdo->prepare("
        SELECT c.id as course_id, c.course_name, c.course_code,
               e.enrolled_at, e.status, e.progress_percentage, e.last_accessed,
               
               -- Assessment Statistics
               COALESCE(assessment_stats.total_assessments, 0) as total_assessments,
               COALESCE(assessment_stats.completed_assessments, 0) as completed_assessments,
               COALESCE(assessment_stats.avg_score, 0) as avg_score,
               COALESCE(assessment_stats.best_score, 0) as best_score,
               COALESCE(assessment_stats.total_attempts, 0) as total_attempts
               
        FROM courses c
        LEFT JOIN course_enrollments e ON e.course_id = c.id AND e.student_id = ?
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', ?) IS NOT NULL 
            AND JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        
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
            WHERE aa.student_id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
            GROUP BY aa.student_id, c.id
        ) assessment_stats ON assessment_stats.student_id = ? AND assessment_stats.course_id = c.id
        
        WHERE c.teacher_id = ? AND c.academic_period_id = ?
        ORDER BY c.course_name
    ");
    $stmt->execute([
        $student_id, $student_id, $student_id, $_SESSION['user_id'], $academic_period_id, 
        $student_id, $_SESSION['user_id'], $academic_period_id
    ]);
    $courses = $stmt->fetchAll();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'student' => $student,
        'courses' => $courses,
        'message' => 'Student course details retrieved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_student_courses.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while retrieving student course details'
    ]);
}
?>
