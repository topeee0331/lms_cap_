<?php
/**
 * AJAX endpoint for fetching real-time teacher student statistics
 */

// Start output buffering to prevent any output before headers
ob_start();

// Include necessary files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Clear any output that might have been sent
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isLoggedIn() || $_SESSION['role'] !== 'teacher') {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Teacher access required'
    ]);
    ob_end_flush();
    exit();
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $teacher_id = $_SESSION['user_id'];
    $academic_period_id = $_GET['academic_period_id'] ?? 1; // Default to period 1
    
    // Get the same statistics as in teacher/students.php but optimized for real-time updates
    $stats = getTeacherStudentStats($pdo, $teacher_id, $academic_period_id);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'academic_period_id' => $academic_period_id
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_teacher_student_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'stats' => []
    ]);
}

ob_end_flush();

/**
 * Get teacher student statistics
 */
function getTeacherStudentStats($pdo, $teacher_id, $academic_period_id) {
    // Use the exact same calculation logic as the main page
    
    // 1. Total Students (all students in sections associated with teacher's courses)
    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT u.id)
        FROM sections s
        JOIN users u ON JSON_SEARCH(s.students, "one", u.id) IS NOT NULL
        JOIN courses c ON JSON_SEARCH(c.sections, "one", s.id) IS NOT NULL
        WHERE c.teacher_id = ? AND c.academic_period_id = ? AND s.is_active = 1
    ');
    $stmt->execute([$teacher_id, $academic_period_id]);
    $total_students = $stmt->fetchColumn();

    // 2. Active Students (students with progress > 0%)
    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT e.student_id)
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE c.teacher_id = ? AND c.academic_period_id = ? AND e.status = "active" AND e.progress_percentage > 0
    ');
    $stmt->execute([$teacher_id, $academic_period_id]);
    $active_students = $stmt->fetchColumn();

    // 3. Average Progress (only for students with progress > 0%)
    $stmt = $pdo->prepare('
        SELECT AVG(e.progress_percentage)
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE c.teacher_id = ? AND c.academic_period_id = ? AND e.status = "active" AND e.progress_percentage > 0
    ');
    $stmt->execute([$teacher_id, $academic_period_id]);
    $avg_progress = $stmt->fetchColumn() ?: 0;

    // 4. Average Score (from completed assessment attempts)
    $stmt = $pdo->prepare('
        SELECT AVG(aa.score)
        FROM assessment_attempts aa
        JOIN assessments a ON aa.assessment_id = a.id
        JOIN courses c ON a.course_id = c.id
        WHERE c.teacher_id = ? AND c.academic_period_id = ? AND aa.status = "completed"
    ');
    $stmt->execute([$teacher_id, $academic_period_id]);
    $avg_score = $stmt->fetchColumn() ?: 0;
    
    return [
        'total_students' => (int)$total_students,
        'active_students' => (int)$active_students,
        'avg_progress' => round($avg_progress, 1),
        'avg_score' => round($avg_score, 1)
    ];
}
?>
