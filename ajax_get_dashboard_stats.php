<?php
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

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Login required'
    ]);
    ob_end_flush();
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    $academic_period_id = $_GET['academic_period_id'] ?? null;
    
    // Get stats based on user role
    switch ($user_role) {
        case 'teacher':
            $stats = getTeacherStats($pdo, $user_id, $academic_period_id);
            break;
            
        case 'student':
            $stats = getStudentStats($pdo, $user_id, $academic_period_id);
            break;
            
        case 'admin':
            $stats = getAdminStats($pdo, $academic_period_id);
            break;
            
        default:
            throw new Exception('Invalid user role');
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'role' => $user_role,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_dashboard_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'stats' => []
    ]);
}

ob_end_flush();

// Helper functions
function getTeacherStats($pdo, $teacher_id, $academic_period_id) {
    $year_condition = $academic_period_id ? "AND c.academic_period_id = ?" : "";
    $params = $academic_period_id ? [$teacher_id, $academic_period_id, $teacher_id, $academic_period_id] : [$teacher_id, $teacher_id];
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_courses,
            (
                SELECT COUNT(DISTINCT e.student_id)
                FROM course_enrollments e
                JOIN courses c2 ON e.course_id = c2.id
                WHERE c2.teacher_id = ? AND e.status = 'active' $year_condition
            ) as total_students,
            COUNT(DISTINCT a.id) as total_assessments,
            AVG(aa.score) as average_score,
            COUNT(DISTINCT aa.assessment_id) as total_assessments_taken,
            COUNT(DISTINCT e.student_id) as total_enrollments,
            (
                SELECT COUNT(*)
                FROM enrollment_requests er
                JOIN courses c3 ON er.course_id = c3.id
                WHERE c3.teacher_id = ? AND er.status = 'pending' $year_condition
            ) as pending_enrollment_requests
        FROM courses c
        LEFT JOIN assessments a ON a.course_id = c.id
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.status = 'completed'
        LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
        WHERE c.teacher_id = ? $year_condition
    ");
    $stmt->execute($params);
    return $stmt->fetch();
}

function getStudentStats($pdo, $student_id, $academic_period_id) {
    $year_condition = $academic_period_id ? "AND c.academic_period_id = ?" : "";
    $params = $academic_period_id ? [$student_id, $academic_period_id, $student_id, $academic_period_id] : [$student_id, $student_id];
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as enrolled_courses,
            SUM(JSON_LENGTH(c.modules)) as total_modules,
            (
                SELECT COUNT(*)
                FROM module_progress mp
                JOIN courses c2 ON mp.course_id = c2.id
                WHERE mp.student_id = ? AND mp.is_completed = 1 $year_condition
            ) as completed_modules,
            COUNT(DISTINCT a.id) as total_assessments,
            (
                SELECT COUNT(*)
                FROM assessment_attempts aa
                JOIN assessments a2 ON aa.assessment_id = a2.id
                JOIN courses c2 ON a2.course_id = c2.id
                WHERE aa.student_id = ? AND aa.status = 'completed' $year_condition
            ) as completed_assessments,
            AVG(aa.score) as average_score,
            (
                SELECT COUNT(*)
                FROM student_badges sb
                WHERE sb.student_id = ?
            ) as total_badges,
            (
                SELECT COUNT(*)
                FROM enrollment_requests er
                WHERE er.student_id = ? AND er.status IN ('pending', 'rejected')
            ) as pending_enrollment_requests
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN assessments a ON a.course_id = c.id
        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id = ? AND aa.status = 'completed'
        WHERE e.student_id = ? AND e.status = 'active' $year_condition
    ");
    $stmt->execute(array_merge($params, [$student_id, $student_id, $student_id]));
    return $stmt->fetch();
}

function getAdminStats($pdo, $academic_period_id) {
    $year_condition = $academic_period_id ? "WHERE c.academic_period_id = ?" : "";
    $params = $academic_period_id ? [$academic_period_id] : [];
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT CASE WHEN u.role = 'student' THEN u.id END) as total_students,
            COUNT(DISTINCT CASE WHEN u.role = 'teacher' THEN u.id END) as total_teachers,
            COUNT(DISTINCT c.id) as total_courses,
            COUNT(DISTINCT e.student_id) as total_enrollments,
            COUNT(DISTINCT aa.assessment_id) as total_assessments_taken,
            AVG(aa.score) as average_score,
            (
                SELECT COUNT(*)
                FROM enrollment_requests er
                JOIN courses c2 ON er.course_id = c2.id
                WHERE er.status = 'pending' $year_condition
            ) as pending_enrollment_requests,
            (
                SELECT COUNT(*)
                FROM announcements a
                WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ) as recent_announcements
        FROM users u
        LEFT JOIN courses c ON c.academic_period_id = ? $year_condition
        LEFT JOIN course_enrollments e ON c.id = e.course_id AND e.status = 'active'
        LEFT JOIN assessment_attempts aa ON aa.status = 'completed'
    ");
    $stmt->execute($params);
    return $stmt->fetch();
}
?>
